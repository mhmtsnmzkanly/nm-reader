<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for unique Request Tracing.
 *
 * Generates a unique 16-character ID for each incoming request.
 * Useful for debugging and correlating logs. It:
 * - Attaches 'request_id' to the request attributes.
 * - Sets the 'X-Request-Id' header in the response.
 *
 * @package App\Middleware
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    /**
     * Assigns a unique ID to the request lifecycle.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = bin2hex(random_bytes(8));
        $request = $request->withAttribute('request_id', $requestId);

        $response = $handler->handle($request);

        return $response->withHeader('X-Request-Id', $requestId);
    }
}
