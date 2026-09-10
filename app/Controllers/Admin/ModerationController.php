<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class ModerationController extends AdminController
{
    public function blogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->moderationService->listBlogs(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['status']) ? (string) $query['status'] : null,
                (string) ($query['sort'] ?? 'newest')
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }

    public function blogPreview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                return ResponseHelper::success($this->moderationService->getBlogForPreview((string) $args['id']));
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }

    public function likers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            try {
                $result = $this->moderationService->listLikers(
                    strtolower(trim((string) ($query['target_type'] ?? ''))),
                    trim((string) ($query['target_id'] ?? '')),
                    $page,
                    $perPage,
                    trim((string) ($query['q'] ?? ''))
                );
                return ResponseHelper::paginate(
                    $result['items'],
                    $page,
                    $perPage,
                    $result['total'],
                    ['target' => $result['target']]
                );
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function hideBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $modId = (string) $request->getAttribute('user_id');
            $this->moderationService->hideBlog((string)$args['id'], $modId);
            return ResponseHelper::success();
        }
    
    public function deleteBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $modId = (string) $request->getAttribute('user_id');
            $this->moderationService->deleteBlog((string)$args['id'], $modId);
            return ResponseHelper::success();
        }
    
    public function comments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->moderationService->listComments(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['target_type']) ? (string) $query['target_type'] : null,
                (string) ($query['sort'] ?? 'newest'),
                isset($query['status']) ? (string) $query['status'] : null
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function deleteComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $modId = (string) $request->getAttribute('user_id');
            $this->moderationService->deleteComment((int)$args['id'], $modId);
            return ResponseHelper::success();
        }
    
    public function moderateComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = $request->getParsedBody();
            $payload = is_array($payload) ? $payload : [];
            $status = trim((string) ($payload['status'] ?? ''));
            $reason = trim((string) ($payload['reason'] ?? ''));
            if ($status === '') {
                return ResponseHelper::error(400, 'status is required');
            }
            $modId = (string) $request->getAttribute('user_id');
            try {
                $updated = $this->moderationService->moderateComment((int) $args['id'], $status, $modId, $reason !== '' ? $reason : null);
                return $updated ? ResponseHelper::success(['updated' => true]) : ResponseHelper::error(404, 'Comment not found');
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(400, $e->getMessage());
            }
        }
    
    public function auditLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $q = $request->getQueryParams();
            $result = $this->moderationService->listAuditLogs($page, $perPage, (string)($q['q'] ?? ''), isset($q['method']) ? (string)$q['method'] : null, isset($q['status']) ? (string)$q['status'] : null, isset($q['user_id']) ? (string)$q['user_id'] : null, isset($q['date_from']) ? (string)$q['date_from'] : null, isset($q['date_to']) ? (string)$q['date_to'] : null, (string)($q['sort'] ?? 'newest'));
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function loginEvents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->moderationService->listLoginEvents($page, $perPage);
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function moderationActions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->moderationService->listModerationActions($page, $perPage);
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function createModerationAction(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $id = $this->moderationService->createModerationAction($modId, $payload);
            return ResponseHelper::created(['id' => $id]);
        }
}
