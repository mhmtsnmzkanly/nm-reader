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
 * Final Unified Admin Controller.
 * Handles all backend interactions for the Management Console.
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

    // --- DASHBOARD ---
    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->overview());
    }

    // --- CONTENT & CHAPTERS ---
    public function listSeries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listContents($page, $perPage);
        return ResponseHelper::success($result['items'], ['page' => $page, 'per_page' => $perPage, 'total' => $result['total'] ?? 0]);
    }

    public function createContent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->adminService->createContent($payload, $modId));
    }

    public function updateContent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->adminService->updateContent((string)$args['id'], $payload, $modId);
        return ResponseHelper::success();
    }

    public function listChapters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->adminService->listChapters((string)$args['id'], $page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function getChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ResponseHelper::success($this->adminService->getChapter((string)$args['id']));
    }

    public function createChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->adminService->createChapter((string)$args['type'], (string)$args['slug'], $payload, $modId));
    }

    public function updateChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->adminService->updateChapter((string)$args['id'], $payload, $modId);
        return ResponseHelper::success();
    }

    public function deleteChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        $this->adminService->deleteChapter((string)$args['id'], $modId);
        return ResponseHelper::success(['deleted' => true]);
    }

    // --- USERS & RBAC ---
    public function listUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listUsers($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function updateUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->console->updateUser((string)$args['id'], $payload, $modId);
        return ResponseHelper::success();
    }

    public function rbacRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->listRbacRoles());
    }

    // --- BLOGS & COMMENTS ---
    public function blogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listBlogs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function hideBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        $this->console->hideBlog((string)$args['id'], $modId);
        return ResponseHelper::success();
    }

    public function deleteBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        $this->console->deleteBlog((string)$args['id'], $modId);
        return ResponseHelper::success();
    }

    public function comments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listComments($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function deleteComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        $this->console->deleteComment((int)$args['id'], $modId);
        return ResponseHelper::success();
    }

    // --- LOGS & OPS ---
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
        $q = $request->getQueryParams();
        return ResponseHelper::success($this->console->viewStats((int)($q['days'] ?? 30), (int)($q['limit'] ?? 10)));
    }

    public function blogStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();
        return ResponseHelper::success($this->console->blogStats((int)($q['days'] ?? 30), (int)($q['limit'] ?? 10)));
    }

    public function userReputation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();
        return ResponseHelper::success($this->console->userReputation((int)($q['limit'] ?? 10)));
    }

    // --- TAXONOMY & UPLOADS ---
    public function listGenres(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->listAllGenres());
    }

    public function listTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->listAllTags());
    }

    public function updateTaxonomy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->console->updateContentTaxonomy((string)$args['id'], (array)($payload['genres'] ?? []), (array)($payload['tags'] ?? []), $modId);
        return ResponseHelper::success();
    }

    public function uploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listUploads($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function deleteUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        $this->console->deleteUpload((int)$args['id'], $modId);
        return ResponseHelper::success();
    }

    public function uploadImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $toProcess = [];
        $collector = function($item) use (&$collector, &$toProcess) {
            if ($item instanceof \Psr\Http\Message\UploadedFileInterface) $toProcess[] = $item;
            elseif (is_array($item)) foreach ($item as $sub) $collector($sub);
        };
        $collector($files);
        if (empty($toProcess)) return ResponseHelper::error(400, "No files.");
        usort($toProcess, fn($a, $b) => strnatcasecmp($a->getClientFilename() ?? '', $b->getClientFilename() ?? ''));
        $userId = (string) $request->getAttribute('user_id');
        $type = (string) ($request->getQueryParams()['type'] ?? 'chapters');
        return ResponseHelper::success(['paths' => $this->uploadService->handleBulkImageUpload($userId, $toProcess, $type)]);
    }

    // --- UTILS ---
    private function pagination(ServerRequestInterface $request): array
    {
        $q = $request->getQueryParams();
        return [max(1, (int)($q['page'] ?? 1)), max(1, min(100, (int)($q['per_page'] ?? 20)))];
    }
}
