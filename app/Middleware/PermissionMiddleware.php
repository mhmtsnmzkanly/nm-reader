<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for Granular Permission-Based Access Control.
 *
 * Ensures that the authenticated user possesses all specified permissions
 * before allowing access to the route. Rejects with 403 if any are missing.
 *
 * @package App\Middleware
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $required List of required permission codes.
     */
    public function __construct(private readonly array $required)
    {
    }

    /**
     * Verifies user permissions against the required list.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permissions = (array) $request->getAttribute('permissions', []);

        $hasAll = array_all($this->required, static fn(string $perm): bool => in_array($perm, $permissions, true));
        if (!$hasAll) {
            return ResponseHelper::error(403, 'Insufficient permissions');
        }

        return $handler->handle($request);
    }
}
