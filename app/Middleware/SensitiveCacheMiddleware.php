<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Prevents authenticated API responses from being stored by shared caches.
 * Guest/public responses retain their existing cache behaviour.
 */
final class SensitiveCacheMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $path = (string) $request->getUri()->getPath();
        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);

        if (!str_starts_with($path, '/api/v1/') || !is_string($userId) || $userId === '') {
            return $response;
        }

        return $response
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Pragma', 'no-cache')
            ->withAddedHeader('Vary', 'Authorization')
            ->withAddedHeader('Vary', 'Cookie');
    }
}
