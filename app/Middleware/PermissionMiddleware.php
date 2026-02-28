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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permissions = $request->getAttribute('permissions', []);

        foreach ($this->required as $perm) {
            if (!in_array($perm, $permissions, true)) {
                // Audit 403 attempt
                try {
                    $pdo = \App\App::getContainer()->get(\PDO::class);
                    $stmt = $pdo->prepare(
                        'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                         VALUES (NULL, "security", "forbidden", "permission_denied", :reason, NOW())'
                    );
                    $stmt->execute([
                        'reason' => json_encode([
                            'user_id' => $request->getAttribute('user_id'),
                            'path' => (string) $request->getUri()->getPath(),
                            'required' => $this->required,
                            'missing' => $perm
                        ])
                    ]);
                } catch (\Throwable) {}

                return ResponseHelper::error(403, 'Insufficient permissions');
            }
        }

        return $handler->handle($request);
    }
}
