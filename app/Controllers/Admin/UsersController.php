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
            $result = $this->usersService->listUsers(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['status']) ? (string) $query['status'] : null,
                isset($query['role']) ? (string) $query['role'] : null,
                (string) ($query['sort'] ?? 'newest')
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }

    public function userOverview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                return ResponseHelper::success($this->usersService->userOverview((string) $args['id']));
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }

    public function userComments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->moderationService->listComments(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['target_type']) && $query['target_type'] !== '' ? (string) $query['target_type'] : null,
                (string) ($query['sort'] ?? 'newest'),
                isset($query['moderation_status']) && $query['moderation_status'] !== '' ? (string) $query['moderation_status'] : null,
                (string) $args['id']
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }

    public function userBlogs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->moderationService->listBlogs(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['status']) && $query['status'] !== '' ? (string) $query['status'] : null,
                (string) ($query['sort'] ?? 'newest'),
                (string) $args['id']
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function updateUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $this->usersService->updateUser((string)$args['id'], $payload, $modId);
                return ResponseHelper::success();
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }

    public function listViolations(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->usersService->listViolations(
                (string) $args['id'],
                $page,
                $perPage,
                isset($query['level']) && $query['level'] !== '' ? (string) $query['level'] : null,
                isset($query['scope']) && $query['scope'] !== '' ? (string) $query['scope'] : null
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }

    public function recordViolation(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $result = $this->usersService->recordViolation(
                    (string) $args['id'],
                    $payload,
                    (string) $request->getAttribute('user_id')
                );
                return ResponseHelper::created($result);
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function userOptions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->usersService->listAllUsersForSelect());
        }
    
    public function rbacRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->usersService->listRbacRoles());
        }
    
    public function rbacAssignments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->usersService->listRbacAssignments($page, $perPage);
            return ResponseHelper::success($result['items'], $result['meta']);
        }
    
    public function assignPermissionToRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $this->usersService->assignPermissionToRole($payload, $modId);
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
                $revoked = $this->usersService->revokePermissionFromRole($payload, $modId);
                return ResponseHelper::success(['revoked' => $revoked]);
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            }
        }
    
    public function permissionMatrix(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $roles = $this->usersService->listRolesWithPermissions();
            $permissions = $this->adminUserRepo->getAllSystemPermissions();
            return ResponseHelper::success([
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
        }
    
    public function ownershipCapabilities(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->usersService->ownershipCapabilities());
        }
}
