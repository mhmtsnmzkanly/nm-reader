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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $_SESSION['user_id'] ?? null;
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

            // Audit suspicious 401 attempt
            try {
                $pdo = \App\App::getContainer()->get(\PDO::class);
                $stmt = $pdo->prepare(
                    'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES (NULL, "security", "unauthorized", "auth_fail", :reason, NOW())'
                );
                $stmt->execute([
                    'reason' => json_encode([
                        'path' => (string) $request->getUri()->getPath(),
                        'ip_hash' => hash('sha256', (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')),
                        'ua' => substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255)
                    ])
                ]);
            } catch (\Throwable) {}

            return ResponseHelper::error(401, 'Unauthorized');
        }

        $roles = is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : [];
        $permissions = is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];
        $normalizedRoles = $this->authorization->normalizeRoles($roles);
        $effectivePermissions = $this->authorization->resolveEffectivePermissions($normalizedRoles, $permissions, (string) $userId);
        $isAdmin = $this->authorization->highestRole($normalizedRoles) === 'admin';

        // Synchronize and persist normalized authorization state.
        $_SESSION['roles'] = $normalizedRoles;
        $_SESSION['permissions'] = $effectivePermissions;
        $_SESSION['is_admin'] = $isAdmin;

        $request = $request
            ->withAttribute('user_id', (string) $userId)
            ->withAttribute('roles', $normalizedRoles)
            ->withAttribute('permissions', $effectivePermissions)
            ->withAttribute('is_admin', $isAdmin);

        return $handler->handle($request);
    }
}
