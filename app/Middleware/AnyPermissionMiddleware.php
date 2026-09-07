<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Allows a route when the authenticated user has at least one permission.
 *
 * This is useful for shared resources such as image uploads, where creating
 * content and editing existing content are separate capabilities.
 */
final class AnyPermissionMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    /**
     * @param array<int, string> $required
     */
    public function __construct(private readonly array $required)
    {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permissions = (array) $request->getAttribute('permissions', []);
        foreach ($this->required as $permission) {
            if (in_array($permission, $permissions, true)) {
                return $handler->handle($request);
            }
        }

        return ResponseHelper::error(403, 'Insufficient permissions');
    }
}
