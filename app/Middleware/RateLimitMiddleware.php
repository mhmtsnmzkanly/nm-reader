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
 * Middleware for IP-based Rate Limiting.
 *
 * Protects endpoints from abuse by limiting the number of requests per IP 
 * within a fixed time window. It:
 * - Uses CacheService to persist attempt counts.
 * - Tracks 'blocked' and 'allowed' metrics for system monitoring.
 * - Rejects exceeding requests with a 429 status code.
 *
 * @package App\Middleware
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param CacheService $cache
     * @param string $bucket Unique identifier for the rate limit rule (e.g., 'login').
     * @param int $limit Maximum number of requests allowed.
     * @param int $windowSeconds The time window duration in seconds.
     */
    public function __construct(
        private readonly CacheService $cache,
        private readonly string $bucket,
        private readonly int $limit,
        private readonly int $windowSeconds
    ) {
    }

    /**
     * Checks if the client IP has exceeded its request quota.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $ipHash = hash('sha256', $ip);
        $key = sprintf('rate_%s_%s', $this->bucket, $ipHash);

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
