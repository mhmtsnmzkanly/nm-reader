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
use App\Services\WebhookService;
use App\Services\SystemLogService;
use App\Services\AnalyticsAggregationService;
use App\Services\WalletService;
use App\Repositories\UserRepository;
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
        private readonly AnalyticsAggregationService $aggregation,
        private readonly WalletService $wallets,
        private readonly WebhookService $webhooks,
        private readonly \App\Repositories\AdminConsoleRepository $adminConsoleRepo,
        private readonly UserRepository $users
    ) {
    }

    // --- DASHBOARD ---
    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->overview());
    }

    public function reauthenticate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array)$request->getParsedBody();
        $userId = (string)$request->getAttribute('user_id');
        $hash = $this->users->passwordHashForId($userId);
        if ($hash === null || !password_verify((string)($payload['password'] ?? ''), $hash)) {
            $this->console->createModerationAction($userId, 'security', $userId, 'auth_fail', 'Critical action reauthentication failed');
            return ResponseHelper::error(401, 'Parola doğrulanamadı.');
        }
        $_SESSION['admin_reauthenticated_at'] = time();
        $_SESSION['admin_reauthenticated_user_id'] = $userId;
        $this->console->createModerationAction($userId, 'security', $userId, 'update', 'Critical action reauthentication succeeded');
        return ResponseHelper::success(['valid_for_seconds' => 300]);
    }

    // --- CONTENT & CHAPTERS ---
    public function listSeries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = $request->getQueryParams();
        $result = $this->console->listContents(
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
        return ResponseHelper::created($this->adminService->createContent($payload, $modId));
    }

    public function updateContent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->adminService->updateContent((string)$args['id'], $payload, $modId);
        return ResponseHelper::success();
    }

    public function changeContentLifecycle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            return ResponseHelper::success($this->adminService->changeContentLifecycle(
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
            return ResponseHelper::success($this->adminService->contentPreview((string) $args['id']));
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function contentRevisions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $limit = (int) ($request->getQueryParams()['limit'] ?? 50);
            return ResponseHelper::success($this->adminService->contentRevisions((string) $args['id'], $limit));
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function listChapters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->adminService->listChapters((string)$args['id'], $page, $perPage);
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
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

    public function createChapterByContentId(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->adminService->createChapterByContentId((string)$args['id'], $payload, $modId));
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

    public function createModerationAction(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $id = $this->console->createModerationAction($modId, $payload);
        return ResponseHelper::created(['id' => $id]);
    }

    // --- BLOGS & COMMENTS ---
    public function blogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = $request->getQueryParams();
        $result = $this->console->listBlogs(
            $page,
            $perPage,
            trim((string) ($query['q'] ?? '')),
            isset($query['status']) ? (string) $query['status'] : null,
            (string) ($query['sort'] ?? 'newest')
        );
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
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
        $query = $request->getQueryParams();
        $result = $this->console->listComments(
            $page,
            $perPage,
            trim((string) ($query['q'] ?? '')),
            isset($query['target_type']) ? (string) $query['target_type'] : null,
            (string) ($query['sort'] ?? 'newest')
        );
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
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
        $q = $request->getQueryParams();
        $result = $this->console->listAuditLogs($page, $perPage, (string)($q['q'] ?? ''), isset($q['method']) ? (string)$q['method'] : null, isset($q['status']) ? (string)$q['status'] : null, isset($q['user_id']) ? (string)$q['user_id'] : null, isset($q['date_from']) ? (string)$q['date_from'] : null, isset($q['date_to']) ? (string)$q['date_to'] : null, (string)($q['sort'] ?? 'newest'));
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
    }

    public function loginEvents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listLoginEvents($page, $perPage);
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
    }

    public function moderationActions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listModerationActions($page, $perPage);
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
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

    public function createGenre(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->console->createGenre((string) ($payload['name'] ?? ''), $modId));
    }

    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->console->createTag((string) ($payload['name'] ?? ''), $modId));
    }

    public function editTaxonomy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            return ResponseHelper::success($this->console->updateTaxonomy((int)$args['id'], (array)$request->getParsedBody(), (string)$request->getAttribute('user_id')));
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function deleteTaxonomyItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->console->deleteTaxonomy((int)$args['id'], (string)$request->getAttribute('user_id'));
            return ResponseHelper::success();
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function mergeTaxonomies(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return ResponseHelper::success($this->console->mergeTaxonomies((array)$request->getParsedBody(), (string)$request->getAttribute('user_id')));
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function reorderTaxonomies(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->console->reorderTaxonomies((array)$request->getParsedBody(), (string)$request->getAttribute('user_id'));
            return ResponseHelper::success();
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function uploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = $request->getQueryParams();
        $result = $this->console->listUploads($page, $perPage, (string)($query['q'] ?? ''), isset($query['mime']) ? (string)$query['mime'] : null, filter_var($query['orphans'] ?? false, FILTER_VALIDATE_BOOL));
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null, ['stats' => $result['stats'] ?? []]);
    }

    public function deleteUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        $this->console->deleteUpload((int)$args['id'], $modId);
        return ResponseHelper::success();
    }

    public function deleteUploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array)$request->getParsedBody();
        return ResponseHelper::success($this->console->deleteUploads((array)($payload['ids'] ?? []), (string)$request->getAttribute('user_id')));
    }

    public function optimizeUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try { return ResponseHelper::success($this->console->optimizeUpload((int)$args['id'], (string)$request->getAttribute('user_id'))); }
        catch (\InvalidArgumentException|\DomainException $e) { return ResponseHelper::error(422, $e->getMessage()); }
    }

    public function uploadImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $toProcess = [];
        $zipFile = null;
        $collector = function($item) use (&$collector, &$toProcess) {
            if ($item instanceof \Psr\Http\Message\UploadedFileInterface) $toProcess[] = $item;
            elseif (is_array($item)) foreach ($item as $sub) $collector($sub);
        };
        $collector($files);
        if (empty($toProcess)) return ResponseHelper::error(400, "No files.");
        usort($toProcess, fn($a, $b) => strnatcasecmp($a->getClientFilename() ?? '', $b->getClientFilename() ?? ''));
        $userId = (string) $request->getAttribute('user_id');
        $type = (string) ($request->getQueryParams()['type'] ?? 'chapters');

        foreach ($toProcess as $candidate) {
            $name = strtolower((string) ($candidate->getClientFilename() ?? ''));
            $mime = strtolower((string) ($candidate->getClientMediaType() ?? ''));
            if (str_ends_with($name, '.zip') || $mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
                $zipFile = $candidate;
                break;
            }
        }

        if ($zipFile !== null) {
            return ResponseHelper::success(['paths' => $this->uploadService->handleZipImageUpload($userId, $zipFile, $type)]);
        }

        return ResponseHelper::success(['paths' => $this->uploadService->handleBulkImageUpload($userId, $toProcess, $type)]);
    }

    public function queueJobs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = $request->getQueryParams();
        $result = $this->console->listQueueJobs($page, $perPage, isset($query['status']) ? (string)$query['status'] : null, (string)($query['q'] ?? ''));
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
    }

    public function systemHealth(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->systemHealth());
    }

    public function retryQueueJob(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->console->retryQueueJob((int)$args['id'], (string)$request->getAttribute('user_id'));
            return ResponseHelper::success();
        } catch (\DomainException $e) { return ResponseHelper::error(409, $e->getMessage()); }
    }

    public function cancelQueueJob(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->console->cancelQueueJob((int)$args['id'], (string)$request->getAttribute('user_id'));
            return ResponseHelper::success();
        } catch (\DomainException $e) { return ResponseHelper::error(409, $e->getMessage()); }
    }

    public function runQueueOnce(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $limit = max(1, min(100, (int) ($payload['limit'] ?? 10)));
        $jobType = isset($payload['job_type']) ? (string) $payload['job_type'] : null;

        return ResponseHelper::success($this->queueService->runOnce($limit), ['job_type' => $jobType, 'requested_limit' => $limit, 'moderator_id' => $modId]);
    }

    public function cleanupRetention(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $days = max(1, min(3650, (int) ($payload['days'] ?? 30)));
        return ResponseHelper::success($this->console->cleanupRetention($days));
    }

    public function triggerBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerBackup($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function triggerSitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerSitemap($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function triggerCacheWarmup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerCacheWarmup($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function triggerAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerAnalytics($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function triggerApiTests(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerApiTests($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function triggerOpenApi(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerOpenApi($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function triggerSeedData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->triggerSeedData($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function getEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->readEnv($modId));
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        }
    }

    public function saveEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $this->console->updateEnv($payload, $modId);
            return ResponseHelper::success(['saved' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(403, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function systemAccessLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listSystemAccessLogs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function systemErrorLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listSystemErrorLogs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function metricsSnapshot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->overview());
    }

    public function metricsInsights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();
        $days = (int) ($q['days'] ?? 30);
        $limit = (int) ($q['limit'] ?? 10);

        return ResponseHelper::success([
            'views' => $this->console->viewStats($days, $limit),
            'blogs' => $this->console->blogStats($days, $limit),
            'visits' => $this->console->siteVisits(),
            'reputation' => $this->console->userReputation($limit),
        ]);
    }

    public function shopPackages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->wallets->packages($page, $perPage, false);
        return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
    }

    public function createShopPackage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::created($this->wallets->createPackage($payload, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    public function updateShopPackage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->updatePackage((int) $args['id'], $payload, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    public function grantShopPackage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $packageId = (int) ($payload['package_id'] ?? 0);
            $cashAmount = isset($payload['cash_amount']) ? (string) $payload['cash_amount'] : null;
            $reason = (string) ($payload['reason'] ?? '');
            return ResponseHelper::success($this->wallets->grantPackageToUser((string) $args['userId'], $packageId, $cashAmount, $reason, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            $code = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
            return ResponseHelper::error($code, $exception->getMessage());
        }
    }

    public function creditWallet(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $amount = (int) ($payload['amount'] ?? 0);
            $reason = (string) ($payload['reason'] ?? '');
            return ResponseHelper::success($this->wallets->creditCoins((string) $args['userId'], $amount, $reason, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function debitWallet(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $amount = (int) ($payload['amount'] ?? 0);
            $reason = (string) ($payload['reason'] ?? '');
            return ResponseHelper::success($this->wallets->debitCoins((string) $args['userId'], $amount, $reason, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            $code = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 402;
            return ResponseHelper::error($code, $exception->getMessage());
        }
    }

    public function walletTransactions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $result = $this->wallets->transactions((string) $args['userId'], $page, $perPage);
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function walletSummary(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            return ResponseHelper::success($this->wallets->wallet((string) $args['userId']));
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function updateSeriesPricing(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->updateSeriesPricing((string) $args['id'], $payload, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function updateChapterPricing(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->updateChapterPricing((string) $args['id'], $payload, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function featureProducts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->wallets->featureProducts(false));
    }

    public function configureAdFree(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->configureAdFree($payload, $modId));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    // --- BULK CHAPTER OPERATIONS ---
    public function bulkChapters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $chapterIds = (array) ($payload['ids'] ?? []);
            $action = (string) ($payload['action'] ?? '');
            $params = (array) ($payload['params'] ?? []);

            $result = $this->adminService->bulkChapterAction($chapterIds, $action, $params, $modId);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    // --- SERIES TEAM ASSIGNMENTS ---
    public function listSeriesTeam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ResponseHelper::success($this->adminConsoleRepo->listSeriesTeam((string) $args['id']));
    }

    public function assignSeriesTeam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $userId = trim((string) ($payload['user_id'] ?? ''));
        $role = trim((string) ($payload['role'] ?? 'translator'));

        if ($userId === '') {
            return ResponseHelper::error(400, 'user_id is required');
        }

        $result = $this->adminConsoleRepo->assignTeamMember((string) $args['id'], $userId, $role);
        return ResponseHelper::created($result);
    }

    public function removeSeriesTeam(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assignmentId = (int) ($args['assignmentId'] ?? 0);
        $deleted = $this->adminConsoleRepo->removeTeamMember($assignmentId);
        return ResponseHelper::success(['deleted' => $deleted]);
    }

    // --- PERMISSION MATRIX ---
    public function permissionMatrix(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $roles = $this->console->listRolesWithPermissions();
        $permissions = $this->adminConsoleRepo->getAllSystemPermissions();
        return ResponseHelper::success([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    // --- SITE SETTINGS ---
    public function getSiteConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->siteConfig->all());
    }

    public function updateSiteConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $modId = (string) $request->getAttribute('user_id');
            $updated = $this->siteConfig->update($payload, $modId);
            return ResponseHelper::success($updated);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    // --- WEBHOOKS ---
    public function listWebhooks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->webhooks->listWebhooks());
    }

    public function createWebhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            return ResponseHelper::created($this->webhooks->createWebhook($payload));
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    public function updateWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $this->webhooks->updateWebhook((int) $args['id'], $payload);
            return ResponseHelper::success();
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    public function deleteWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->webhooks->deleteWebhook((int) $args['id']);
            return ResponseHelper::success(['deleted' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    public function testWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $result = $this->webhooks->testWebhook((int) $args['id']);
            return ResponseHelper::success($result);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    // --- ADVANCED ANALYTICS ---
    public function monetizationAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();
        $days = (int) ($q['days'] ?? 30);
        return ResponseHelper::success($this->metricsService->monetizationAnalytics($days));
    }

    public function financeTransactions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $q = $request->getQueryParams();
        return ResponseHelper::success($this->wallets->adminFinance($page, $perPage, (string)($q['q'] ?? ''), isset($q['type']) ? (string)$q['type'] : null, (string)($q['sort'] ?? 'newest')));
    }

    public function refundFinanceTransaction(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array)$request->getParsedBody();
            return ResponseHelper::success($this->wallets->refundTransaction((int)$args['id'], (string)($payload['reason'] ?? ''), (string)$request->getAttribute('user_id')));
        } catch (\InvalidArgumentException|\DomainException $e) {
            return ResponseHelper::error(422, $e->getMessage());
        }
    }

    public function searchInsights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();
        $days = (int) ($q['days'] ?? 30);
        $limit = (int) ($q['limit'] ?? 20);
        return ResponseHelper::success($this->metricsService->searchInsights($days, $limit));
    }

    public function seriesReadingFunnel(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ResponseHelper::success($this->metricsService->seriesReadingFunnel((string) $args['id']));
    }

    // --- UTILS ---
    private function pagination(ServerRequestInterface $request): array
    {
        $q = $request->getQueryParams();
        return [max(1, (int)($q['page'] ?? 1)), max(1, min(100, (int)($q['per_page'] ?? 20)))];
    }
}
