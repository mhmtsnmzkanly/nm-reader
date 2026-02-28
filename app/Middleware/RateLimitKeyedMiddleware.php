<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\CacheService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for Advanced Key-Based Rate Limiting.
 *
 * Unlike standard IP-based limiting, this middleware allows for dynamic keys
 * derived from the request (e.g., limiting attempts per email, per username, 
 * or per specific content slug).
 *
 * It uses a callable 'keyResolver' to extract the unique identifier for the request.
 *
 * @package App\Middleware
 */
final class RateLimitKeyedMiddleware implements MiddlewareInterface
{
    /**
     * @param CacheService $cache
     * @param string $bucket Unique identifier for the rate limit rule.
     * @param int $limit Max requests.
     * @param int $windowSeconds Time window.
     * @param callable(ServerRequestInterface): string $keyResolver Logic to extract the rate limit key.
     */
    public function __construct(
        private readonly CacheService $cache,
        private readonly string $bucket,
        private readonly int $limit,
        private readonly int $windowSeconds,
        private /* callable */ $keyResolver
    ) {
    }

    /**
     * Resolves the key and enforces the rate limit.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $suffix = (string) ($this->keyResolver)($request);
        if ($suffix === '') {
            // Fallback: block if we cannot derive a key to avoid limit bypass.
            return ResponseHelper::error(429, 'Too many requests');
        }

        $hash = hash('sha256', $suffix);
        $key = sprintf('rate_%s_%s', $this->bucket, $hash);

        $current = (int) $this->cache->get($key, 0);
        if ($current >= $this->limit) {
            $this->cache->increment(sprintf('sys_rate_limit_blocked_%s', $this->bucket), 1, 86400 * 365);
            return ResponseHelper::error(429, 'Too many requests');
        }

        $this->cache->set($key, $current + 1, $this->windowSeconds);
        $this->cache->increment(sprintf('sys_rate_limit_allowed_%s', $this->bucket), 1, 86400 * 365);

        return $handler->handle($request);
    }
}
