<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthorizationService;
use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for User Authentication and Session Verification.
 *
 * This middleware checks the session for a valid user ID. It:
 * - Rejects unauthorized requests with a 401 status by default.
 * - Supports an 'optional' mode where guest requests are allowed.
 * - Normalizes user roles and permissions via AuthorizationService.
 * - Injects user identity (ID, roles, permissions) into the request attributes.
 *
 * @package App\Middleware
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private AuthorizationService $authorization;
    private bool $optional = false;

    /**
     * @param mixed $arg Boolean for 'optional' mode or an AuthorizationService instance.
     * @param AuthorizationService|null $authorization
     */
    public function __construct(mixed $arg = null, ?AuthorizationService $authorization = null)
    {
        if (is_bool($arg)) {
            $this->optional = $arg;
        } elseif ($arg instanceof AuthorizationService) {
            $authorization = $arg;
        }

        $this->authorization = $authorization ?? new AuthorizationService();
    }

    /**
     * Processes the request identity.
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getAttribute('user_id') ?? ($_SESSION['user_id'] ?? null);

        if ($userId === null) {
            if ($this->optional) {
                // Allow guest with minimal guest-level attributes.
                $request = $request
                    ->withAttribute('user_id', null)
                    ->withAttribute('roles', ['user'])
                    ->withAttribute('permissions', [])
                    ->withAttribute('is_admin', false);

                return $handler->handle($request);
            }

            return ResponseHelper::error(401, 'Unauthorized');
        }

        $roles = $request->getAttribute('roles') ?? (is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : []);
        $normalizedRoles = $this->authorization->normalizeRoles($roles);
        $effectivePermissions = $this->authorization->resolveEffectivePermissions($normalizedRoles, [], (string) $userId);
        $isAdmin = in_array('admin.panel.access', $effectivePermissions, true) || $this->authorization->highestRole($normalizedRoles) === 'admin';

        // Synchronize session if session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['roles'] = $normalizedRoles;
            $_SESSION['permissions'] = $effectivePermissions;
            $_SESSION['is_admin'] = $isAdmin;
        }

        $request = $request
            ->withAttribute('user_id', (string) $userId)
            ->withAttribute('roles', $normalizedRoles)
            ->withAttribute('permissions', $effectivePermissions)
            ->withAttribute('is_admin', $isAdmin);

        return $handler->handle($request);
    }
}
