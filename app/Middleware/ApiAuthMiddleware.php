<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PDO;

/**
 * Middleware for Token-based (Bearer) Authentication.
 * 
 * Checks the 'Authorization' header for a valid Bearer token.
 * If valid, hydrates the request with user identity and permissions.
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly \App\Services\AuthorizationService $authorization
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return $handler->handle($request);
        }

        $token = substr($authHeader, 7);
        if ($token === '') {
            return $handler->handle($request);
        }

        try {
            $stmt = $this->pdo->prepare('
                SELECT id, username, roles 
                FROM users 
                WHERE api_token = :token 
                  AND (api_token_expires_at IS NULL OR api_token_expires_at > NOW())
                LIMIT 1
            ');
            $stmt->execute(['token' => $token]);
            $user = $stmt->fetch();

            if ($user) {
                $userId = (string) $user['id'];
                $rolesRaw = (string) ($user['roles'] ?? '');
                
                // Resolve roles & permissions (DRY from AuthService)
                $roles = ['user'];
                $idMap = array_flip(\App\Config::getSettings()['rbac']['id_map'] ?? []);
                foreach (explode(',', $rolesRaw) as $rid) {
                    if (isset($idMap[$rid])) $roles[] = $idMap[$rid];
                }
                
                $roles = array_values(array_unique($roles));
                $permissions = $this->authorization->resolveEffectivePermissions($roles, [], $userId);

                $request = $request
                    ->withAttribute('user_id', $userId)
                    ->withAttribute('username', (string) $user['username'])
                    ->withAttribute('roles', $roles)
                    ->withAttribute('permissions', $permissions)
                    ->withAttribute('is_admin', in_array('admin.panel.access', $permissions, true));
            }
        } catch (\Throwable) {
            // Token validation failed, proceed as guest
        }

        return $handler->handle($request);
    }
}
