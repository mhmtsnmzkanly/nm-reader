<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class ContentController extends AdminController
{
    public function listSeries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->contentService->listContents(
                $page,
                $perPage,
                trim((string) ($query['q'] ?? '')),
                isset($query['status']) ? (string) $query['status'] : null,
                isset($query['type']) ? (string) $query['type'] : null,
                isset($query['lifecycle']) ? (string) $query['lifecycle'] : null,
                (string) ($query['sort'] ?? 'newest')
            );
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['total'] ?? null);
        }
    
    public function createContent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::created($this->adminContentService->createContent($payload, $modId));
        }
    
    public function updateContent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $this->adminContentService->updateContent((string)$args['id'], $payload, $modId);
            return ResponseHelper::success();
        }
    
    public function changeContentLifecycle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                return ResponseHelper::success($this->adminContentService->changeContentLifecycle(
                    (string) $args['id'],
                    (string) ($payload['action'] ?? ''),
                    isset($payload['scheduled_at']) ? (string) $payload['scheduled_at'] : null,
                    (string) $request->getAttribute('user_id')
                ));
            } catch (\InvalidArgumentException $exception) {
                return ResponseHelper::error(400, $exception->getMessage());
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function contentPreview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                return ResponseHelper::success($this->adminContentService->contentPreview((string) $args['id']));
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function contentRevisions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $limit = (int) ($request->getQueryParams()['limit'] ?? 50);
                return ResponseHelper::success($this->adminContentService->contentRevisions((string) $args['id'], $limit));
            } catch (\DomainException $exception) {
                return ResponseHelper::error(404, $exception->getMessage());
            }
        }
    
    public function listChapters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->adminChapterService->listChapters((string)$args['id'], $page, $perPage);
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        }
    
    public function getChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            return ResponseHelper::success($this->adminChapterService->getChapter((string)$args['id']));
        }
    
    public function createChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::created($this->adminChapterService->createChapter((string)$args['type'], (string)$args['slug'], $payload, $modId));
        }
    
    public function createChapterByContentId(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::created($this->adminChapterService->createChapterByContentId((string)$args['id'], $payload, $modId));
        }
    
    public function updateChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $this->adminChapterService->updateChapter((string)$args['id'], $payload, $modId);
            return ResponseHelper::success();
        }
    
    public function deleteChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $modId = (string) $request->getAttribute('user_id');
            $this->adminChapterService->deleteChapter((string)$args['id'], $modId);
            return ResponseHelper::success(['deleted' => true]);
        }
    
    public function bulkChapters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $payload = (array) $request->getParsedBody();
                $modId = (string) $request->getAttribute('user_id');
                $chapterIds = (array) ($payload['ids'] ?? []);
                $action = (string) ($payload['action'] ?? '');
                $params = (array) ($payload['params'] ?? []);
    
                $result = $this->adminChapterService->bulkChapterAction($chapterIds, $action, $params, $modId);
                return ResponseHelper::success($result);
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(400, $e->getMessage());
            }
        }
    
    public function listGenres(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->contentService->listAllGenres());
        }
    
    public function listTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->contentService->listAllTags());
        }
    
    public function updateTaxonomy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $this->contentService->updateContentTaxonomy((string)$args['id'], (array)($payload['genres'] ?? []), (array)($payload['tags'] ?? []), $modId);
            return ResponseHelper::success();
        }
    
    public function createGenre(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::created($this->contentService->createGenre((string) ($payload['name'] ?? ''), $modId));
        }
    
    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::created($this->contentService->createTag((string) ($payload['name'] ?? ''), $modId));
        }
    
    public function editTaxonomy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                return ResponseHelper::success($this->contentService->updateTaxonomy((int)$args['id'], (array)$request->getParsedBody(), (string)$request->getAttribute('user_id')));
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
    
    public function deleteTaxonomyItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try {
                $this->contentService->deleteTaxonomy((int)$args['id'], (string)$request->getAttribute('user_id'));
                return ResponseHelper::success();
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
    
    public function mergeTaxonomies(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                return ResponseHelper::success($this->contentService->mergeTaxonomies((array)$request->getParsedBody(), (string)$request->getAttribute('user_id')));
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
    
    public function reorderTaxonomies(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            try {
                $this->contentService->reorderTaxonomies((array)$request->getParsedBody(), (string)$request->getAttribute('user_id'));
                return ResponseHelper::success();
            } catch (\InvalidArgumentException $e) {
                return ResponseHelper::error(422, $e->getMessage());
            }
        }
    
    public function listSeriesTeam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            return ResponseHelper::success($this->adminContentRepo->listSeriesTeam((string) $args['id']));
        }
    
    public function assignSeriesTeam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $userId = trim((string) ($payload['user_id'] ?? ''));
            $role = trim((string) ($payload['role'] ?? 'translator'));
    
            if ($userId === '') {
                return ResponseHelper::error(400, 'user_id is required');
            }
    
            $result = $this->adminContentRepo->assignTeamMember((string) $args['id'], $userId, $role);
            return ResponseHelper::created($result);
        }
    
    public function removeSeriesTeam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $assignmentId = (int) ($args['assignmentId'] ?? 0);
            $deleted = $this->adminContentRepo->removeTeamMember($assignmentId);
            return ResponseHelper::success(['deleted' => $deleted]);
        }
}
