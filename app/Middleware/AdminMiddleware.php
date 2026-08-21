<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for strict Administrative Access Control.
 *
 * Ensures that the current user has explicitly granted administrative privileges.
 * Usually placed after AuthMiddleware in the pipeline.
 *
 * @package App\Middleware
 */
final class AdminMiddleware implements MiddlewareInterface
{
    /**
     * Rejects requests from non-admin users with a 403 Forbidden status.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $roles = $request->getAttribute('roles') ?? (is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : []);
        $isAdmin = (bool) ($request->getAttribute('is_admin') ?? ($_SESSION['is_admin'] ?? false)) || in_array('admin', $roles, true);
        if (!$isAdmin) {
            return ResponseHelper::error(403, 'Forbidden');
        }

        return $handler->handle($request);
    }
}
