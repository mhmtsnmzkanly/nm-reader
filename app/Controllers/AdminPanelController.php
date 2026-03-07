<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AdminService;
use App\Services\AdminConsoleService;
use App\Services\SiteConfigService;
use App\Services\QueueService;
use App\Services\MetricsService;
use App\Services\RetentionService;
use App\Services\UploadService;
use App\Services\SystemLogService;
use App\Services\AnalyticsAggregationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unified Controller for all Administrative API operations.
 *
 * Consolidates Content CRUD, System Operations, Metrics, and Console management.
 *
 * @package App\Controllers
 */
final class AdminPanelController
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly AdminConsoleService $console,
        private readonly SiteConfigService $siteConfig,
        private readonly QueueService $queueService,
        private readonly MetricsService $metricsService,
        private readonly RetentionService $retentionService,
        private readonly UploadService $uploadService,
        private readonly SystemLogService $logs,
        private readonly AnalyticsAggregationService $aggregation
    ) {
    }

    // --- DASHBOARD & METRICS ---

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success([
            'message' => 'Admin dashboard is active',
            'metrics' => $this->metricsService->snapshot(),
        ]);
    }

    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->overview());
    }

    public function metricsSnapshot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->metricsService->snapshot());
    }

    public function metricsInsights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        return ResponseHelper::success($this->metricsService->insights($days));
    }

    public function genreInterest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        $slug = (string) ($args['slug'] ?? '');
        try {
            return ResponseHelper::success($this->metricsService->genreInterest($slug, $days));
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    // --- CONTENT MANAGEMENT (SERIES) ---

    public function listSeries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listContents($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function createContent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $created = $this->adminService->createContent($payload, $moderatorId);
            return ResponseHelper::created($created);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    public function updateContent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->adminService->updateContent((string) $args['id'], $payload, $moderatorId);
            return ResponseHelper::success();
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    // --- CHAPTER MANAGEMENT ---

    public function listChapters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $contentId = (string) $args['id'];
        $result = $this->adminService->listChapters($contentId, $page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function getChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            return ResponseHelper::success($this->adminService->getChapter((string) $args['id']));
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    public function createChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $created = $this->adminService->createChapter((string)$args['type'], (string)$args['slug'], $payload, $moderatorId);
            return ResponseHelper::created($created);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    public function updateChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->adminService->updateChapter((string) $args['id'], $payload, $moderatorId);
            return ResponseHelper::success(['updated' => true]);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    public function deleteChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->adminService->deleteChapter((string) $args['id'], $moderatorId);
            return ResponseHelper::success(['deleted' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    // --- USER & RBAC MANAGEMENT ---

    public function listUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listUsers($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function updateUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->updateUser((string) $args['id'], $payload, $moderatorId);
            return ResponseHelper::success();
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    public function revokeUserSession(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $userId = (string) ($payload['user_id'] ?? '');
            $sessionKey = (string) ($payload['session_key'] ?? '');
            $moderatorId = (string) $request->getAttribute('user_id');
            if ($userId === '' || $sessionKey === '') return ResponseHelper::error(400, 'ID and Key required');
            $this->console->revokeUserSession($userId, $sessionKey, $moderatorId);
            return ResponseHelper::success(['revoked' => true]);
        } catch (\Throwable $e) {
            return ResponseHelper::error(500, $e->getMessage());
        }
    }

    public function rbacRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->console->listRbacRoles();
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function rbacAssignments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listRbacAssignments($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function assignPermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->assignPermissionToRole($payload, $moderatorId));
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function revokePermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->revokePermissionFromRole($payload, $moderatorId));
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- SYSTEM LOGS & OPS ---

    public function systemAccessLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $limit = max(1, min(200, (int) ($query['limit'] ?? 50)));
        return ResponseHelper::success($this->logs->getAccessLogs($limit));
    }

    public function systemErrorLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $limit = max(1, min(200, (int) ($query['limit'] ?? 50)));
        return ResponseHelper::success($this->logs->getErrorLogs($limit));
    }

    public function auditLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listAuditLogs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function loginEvents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listLoginEvents($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function moderationActions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listModerationActions($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function siteVisits(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->siteVisits());
    }

    public function viewStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 30);
        $limit = (int) ($query['limit'] ?? 10);
        return ResponseHelper::success($this->console->viewStats($days, $limit));
    }

    public function blogStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 30);
        $limit = (int) ($query['limit'] ?? 10);
        return ResponseHelper::success($this->console->blogStats($days, $limit));
    }

    public function userReputation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $limit = (int) ($query['limit'] ?? 10);
        return ResponseHelper::success($this->console->userReputation($limit));
    }

    public function queueJobs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listQueueJobs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function runQueueOnce(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $jobType = isset($payload['type']) ? (string)$payload['type'] : null;
        $limit = (int) ($payload['limit'] ?? 10);
        $moderatorId = (string) $request->getAttribute('user_id');
        $results = $this->console->runQueueOnce($jobType, $limit, $moderatorId);
        try { $this->aggregation->aggregateAll(); } catch (\Throwable) {}
        return ResponseHelper::success($results);
    }

    public function cleanupRetention(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $days = (int) ($payload['days'] ?? 30);
        return ResponseHelper::success($this->console->cleanupRetention($days));
    }

    // --- TAXONOMY & ASSETS ---

    public function listGenres(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->listAllGenres());
    }

    public function listTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->listAllTags());
    }

    public function createGenre(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->console->createGenre((string)($payload['name'] ?? ''), $moderatorId));
    }

    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->console->createTag((string)($payload['name'] ?? ''), $moderatorId));
    }

    public function updateTaxonomy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $moderatorId = (string) $request->getAttribute('user_id');
        $genres = (array)($payload['series_genres'] ?? $payload['genres'] ?? []);
        $tags = (array)($payload['series_tags'] ?? $payload['tags'] ?? []);
        $this->console->updateContentTaxonomy((string)$args['id'], $genres, $tags, $moderatorId);
        return ResponseHelper::success();
    }

    public function uploadImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $postSize = (int) ($request->getHeaderLine('Content-Length') ?: 0);
            $files = $request->getUploadedFiles();
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($files) && $postSize > 0) {
                return ResponseHelper::error(400, "Files too large.");
            }
            $toProcess = [];
            $collector = function($item) use (&$collector, &$toProcess) {
                if ($item instanceof \Psr\Http\Message\UploadedFileInterface) $toProcess[] = $item;
                elseif (is_array($item)) foreach ($item as $sub) $collector($sub);
            };
            $collector($files);
            if (empty($toProcess)) return ResponseHelper::error(400, "No files.");
            usort($toProcess, fn($a, $b) => strnatcasecmp($a->getClientFilename() ?? '', $b->getClientFilename() ?? ''));
            $userId = (string) $request->getAttribute('user_id');
            $type = (string) ($request->getQueryParams()['type'] ?? $request->getParsedBody()['type'] ?? 'chapters');
            $paths = $this->uploadService->handleBulkImageUpload($userId, $toProcess, $type);
            return ResponseHelper::success(['paths' => $paths]);
        } catch (\Throwable $e) { return ResponseHelper::error(500, $e->getMessage()); }
    }

    public function uploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listUploads($page, $perPage);
        return ResponseHelper::success($result['items'] ?? $result, $result['meta'] ?? null);
    }

    public function deleteUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        $this->console->deleteUpload((int) ($args['id'] ?? 0), $moderatorId);
        return ResponseHelper::success(['deleted' => true]);
    }

    // --- OTHER ADMIN ---

    public function blogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listBlogs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function hideBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->hideBlog((string)$args['id'], $moderatorId);
            return ResponseHelper::success(['hidden' => true]);
        } catch (\Exception $e) { return ResponseHelper::error(404, $e->getMessage()); }
    }

    public function deleteBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->deleteBlog((string)$args['id'], $moderatorId);
            return ResponseHelper::success(['deleted' => true]);
        } catch (\Exception $e) { return ResponseHelper::error(404, $e->getMessage()); }
    }

    public function comments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listComments($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function deleteComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        $this->console->deleteComment((int) ($args['id'] ?? 0), $moderatorId);
        return ResponseHelper::success(['deleted' => true]);
    }

    public function triggerBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerBackup($moderatorId));
    }

    public function triggerAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerAnalytics($moderatorId));
    }

    public function triggerSitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerSitemap($moderatorId));
    }

    public function triggerCacheWarmup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerCacheWarmup($moderatorId));
    }

    public function getEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->readEnv($moderatorId));
        } catch (\Throwable $e) { return ResponseHelper::error(403, $e->getMessage()); }
    }

    public function saveEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->updateEnv($payload, $moderatorId);
            return ResponseHelper::success(['updated' => true]);
        } catch (\Throwable $e) { return ResponseHelper::error(403, $e->getMessage()); }
    }

    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        return [max(1, (int) ($query['page'] ?? 1)), max(1, min(100, (int) ($query['per_page'] ?? 20)))];
    }
}
