<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class UsersController extends AdminController
{
    public function listUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->console->listUsers(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['status']) ? (string) $query['status'] : null,
                isset($query['role']) ? (string) $query['role'] : null,
                (string) ($query['sort'] ?? 'newest')
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function updateUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $this->console->updateUser((string)$args['id'], $payload, $modId);
            return ResponseHelper::success();
        }
    
    public function userOptions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->listAllUsersForSelect());
        }
    
    public function rbacRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->listRbacRoles());
        }
    
    public function rbacAssignments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->console->listRbacAssignments($page, $perPage);
            return ResponseHelper::success($result['items'], $result['meta']);
        }
    
    public function assignPermissionToRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $this->console->assignPermissionToRole($payload, $modId);
                return ResponseHelper::success(['assigned' => true]);
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            }
        }
    
    public function revokePermissionFromRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $revoked = $this->console->revokePermissionFromRole($payload, $modId);
                return ResponseHelper::success(['revoked' => $revoked]);
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            }
        }
    
    public function permissionMatrix(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $roles = $this->console->listRolesWithPermissions();
            $permissions = $this->adminConsoleRepo->getAllSystemPermissions();
            return ResponseHelper::success([
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
        }
    
    public function ownershipCapabilities(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->ownershipCapabilities());
        }
}
