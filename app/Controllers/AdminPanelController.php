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
use App\Services\WalletService;
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
        private readonly WalletService $wallets
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
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->console->assignPermissionToRole($payload, $modId);
        return ResponseHelper::success(['assigned' => true]);
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

    public function queueJobs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listQueueJobs($page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
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
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerBackup($modId));
    }

    public function triggerSitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerSitemap($modId));
    }

    public function triggerCacheWarmup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerCacheWarmup($modId));
    }

    public function triggerAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerAnalytics($modId));
    }

    public function getEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $modId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->readEnv($modId));
    }

    public function saveEnvConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $modId = (string) $request->getAttribute('user_id');
        $this->console->updateEnv($payload, $modId);
        return ResponseHelper::success(['saved' => true]);
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
        return ResponseHelper::success($result['items'], $result['meta']);
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
            return ResponseHelper::success($result['items'], $result['meta']);
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

    // --- UTILS ---
    private function pagination(ServerRequestInterface $request): array
    {
        $q = $request->getQueryParams();
        return [max(1, (int)($q['page'] ?? 1)), max(1, min(100, (int)($q['per_page'] ?? 20)))];
    }
}
