<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for Cross-Site Request Forgery (CSRF) Protection.
 *
 * This middleware secures state-changing requests (POST, PUT, DELETE, PATCH).
 * It:
 * - Skips validation for safe methods (GET, HEAD, OPTIONS).
 * - Requires an 'X-CSRF-Token' header from the client.
 * - Uses constant-time string comparison (hash_equals) to prevent timing attacks.
 * - Rejects invalid requests with a 419 status code.
 *
 * @package App\Middleware
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Processes token validation for the current request.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        // Skip CSRF for logout to prevent edge cases when session is expiring
        if ($request->getUri()->getPath() === '/api/v1/auth/logout') {
            return $handler->handle($request);
        }

        // Stateless Bearer token requests are immune to CSRF
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ') && $request->getAttribute('user_id') !== null) {
            return $handler->handle($request);
        }

        $headerToken = $request->getHeaderLine('X-CSRF-Token');
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        if ($headerToken === '' || $sessionToken === null || !hash_equals($sessionToken, $headerToken)) {
            return ResponseHelper::error(419, 'Invalid CSRF token');
        }

        return $handler->handle($request);
    }
}
