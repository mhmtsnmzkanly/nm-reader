<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Config;
use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Services\AnalyticsAggregationService;
use App\Services\BackupService;
use App\Services\CacheService;
use App\Services\QueueService;
use App\Services\RetentionService;
use App\Services\SeriesService;
use App\Services\SlugService;
use App\Services\SitemapService;

use App\Services\Admin\AdminConsoleServiceBase;

/** Domain service extracted from the legacy admin console service. */
final class AdminOperationsService extends AdminConsoleServiceBase
{
    public function listQueueJobs(int $page, int $perPage, ?string $status = null, string $query = '', ?string $jobType = null): array
    {
        $result = $this->repo->listQueueJobs($page, $perPage, $status, trim($query), $jobType);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * A queue page visit acknowledges the failures visible to that moderator.
     * The marker is audited instead of adding another column to system_jobs.
     */
    public function markQueueFailuresSeen(string $moderatorId): void
    {
        $moderatorId = trim($moderatorId);
        if ($moderatorId === '') {
            return;
        }

        $this->repo->createModerationAction(
            $moderatorId,
            'system',
            'queue',
            'view_failures',
            'Queue failures viewed'
        );
    }

    public function retryQueueJob(int $id, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            if (!$this->repo->retryQueueJob($id)) throw new \DomainException('Only failed or cancelled jobs can be retried');
            $this->repo->createModerationAction($moderatorId, 'system', (string) $id, 'trigger', 'Queue job retried');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function cancelQueueJob(int $id, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            if (!$this->repo->cancelQueueJob($id)) throw new \DomainException('Only pending jobs can be cancelled');
            $this->repo->createModerationAction($moderatorId, 'system', (string) $id, 'trigger', 'Queue job cancelled');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function systemHealth(?string $userId = null): array
    {
        $snapshot = $this->repo->systemHealthSnapshot();
        // Services live under app/Services/Admin, while storage belongs to the
        // project root. Use the configured base path so health checks describe
        // the same directories used by BackupService and the log services.
        $base = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\');
        $paths = [$base . '/storage', $base . '/storage/logs', $base . '/storage/cache'];
        $snapshot['runtime'] = ['php_version' => PHP_VERSION, 'memory_limit' => ini_get('memory_limit'), 'memory_usage_bytes' => memory_get_usage(true)];
        $snapshot['storage'] = [
            'ok' => array_reduce($paths, static fn(bool $ok, string $path): bool => $ok && is_dir($path) && is_writable($path), true),
            'free_bytes' => (int)(disk_free_space($base) ?: 0),
            'total_bytes' => (int)(disk_total_space($base) ?: 0),
        ];
        $totalBytes = (int) ($snapshot['storage']['total_bytes'] ?? 0);
        $freeBytes = (int) ($snapshot['storage']['free_bytes'] ?? 0);
        $snapshot['storage']['used_bytes'] = max(0, $totalBytes - $freeBytes);
        $snapshot['storage']['usage_pct'] = $totalBytes > 0
            ? round(($snapshot['storage']['used_bytes'] / $totalBytes) * 100, 1)
            : 0;
        $snapshot['queue']['oldest_pending_at'] = null;
        $snapshot['queue']['stale_processing'] = 0;
        $snapshot['queue']['delayed'] = 0;
        try {
            $snapshot['queue'] = array_merge($snapshot['queue'], $this->repo->queueOperationalMetrics());
        } catch (\Throwable) {
            // Queue counts remain useful even when the optional age query fails.
        }
        $backupDir = $base . '/storage/backups';
        $backupFiles = is_dir($backupDir) ? array_values(array_filter(glob($backupDir . '/*') ?: [], 'is_file')) : [];
        $runs = [];
        foreach ($backupFiles as $file) {
            $name = basename($file);
            if (!preg_match('/^(db|media)_(.+)\\.(sql\\.gz|tar\\.gz)$/', $name, $match)) {
                continue;
            }
            $timestamp = $match[2];
            $runs[$timestamp][$match[1]] = $file;
        }
        if ($runs === []) {
            $snapshot['backup'] = null;
        } else {
            uksort($runs, static fn(string $a, string $b): int => strcmp($b, $a));
            $timestamp = (string) array_key_first($runs);
            $run = $runs[$timestamp];
            $dbFile = $run['db'] ?? null;
            $mediaFile = $run['media'] ?? null;
            $mtimes = array_map(static fn(?string $file): int => $file ? (int) (filemtime($file) ?: 0) : 0, [$dbFile, $mediaFile]);
            $snapshot['backup'] = [
                'timestamp' => $timestamp,
                'file' => basename($dbFile ?: $mediaFile),
                'database_file' => $dbFile ? basename($dbFile) : null,
                'media_file' => $mediaFile ? basename($mediaFile) : null,
                'complete' => $dbFile !== null && $mediaFile !== null,
                'created_at' => gmdate('Y-m-d H:i:s', max($mtimes)),
                'size_bytes' => array_sum(array_map(static fn(?string $file): int => $file ? (int) (filesize($file) ?: 0) : 0, [$dbFile, $mediaFile])),
            ];
        }
        $rootId = trim((string) (Config::getSettings()['app']['root_user'] ?? ''));
        $snapshot['capabilities'] = [
            'root_maintenance' => $rootId !== '' && $userId !== null && hash_equals($rootId, $userId),
            'developer_tools' => (string) (Config::getSettings()['app']['env'] ?? 'production') !== 'production',
        ];
        return $snapshot;
    }

    public function runQueueOnce(?string $jobType = null, int $limit = 10, ?string $moderatorId = null): array
    {
        $result = $this->queueService->runOnce($limit, $jobType);
        if ($moderatorId !== null) {
            $this->repo->createModerationAction(
                $moderatorId,
                'system',
                'queue',
                'trigger',
                sprintf('Queue worker run completed: %d processed, %d failed', $result['processed'], $result['failed'])
            );
        }
    
        return $result;
    }

    public function listSystemAccessLogs(int $page, int $perPage): array
    {
        $result = $this->repo->listAuditLogs($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function listSystemErrorLogs(int $page, int $perPage): array
    {
        $result = $this->logs->getErrorLogsPage($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function cleanupRetention(int $days): array
    {
        return $this->retention->cleanup($days);
    }

    public function triggerBackup(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $result = $this->backupService->create();
        if ($moderatorId !== null) {
            $this->repo->createModerationAction(
                $moderatorId,
                'system',
                'backup',
                'trigger',
                $result['success'] ? 'Manual backup completed' : 'Manual backup failed'
            );
        }
        return $result;
    }

    public function triggerSitemap(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $result = $this->sitemapService->generateAndSave();
    
        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'sitemap', 'trigger', 'Manual sitemap generation triggered');
        }
    
        return $result;
    }

    public function triggerCacheWarmup(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $output = ['--- Cache Warmer ---'];
    
        try {
            $output[] = '[1/2] Warming Homepage data...';
            $this->seriesService->home(1, 20);
            $output[] = 'SUCCESS: Homepage warmed.';
    
            $output[] = '[2/2] Warming Type listings...';
            $types = ['manga', 'novel', 'webtoon', 'manhwa', 'manhua', 'light-novel', 'web-novel'];
            foreach ($types as $type) {
                $this->seriesService->byType($type, 1, 20);
                $output[] = " - $type: OK";
            }
            $output[] = '--- Cache Warmup Completed ---';
            $success = true;
        } catch (\Throwable $e) {
            $output[] = 'ERROR: ' . $e->getMessage();
            $success = false;
        }
    
        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'cache_warmup', 'trigger', 'Manual cache warmup triggered');
        }
    
        return ['success' => $success, 'output' => $output];
    }

    public function triggerAnalytics(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $output = ['--- Analytics Aggregation ---'];
    
        try {
            $this->aggregation->aggregateAll(30);
            $this->cache->set('system_last_analytics_run', (string)time(), AdminConsoleServiceBase::ANALYTICS_LAST_RUN_TTL);
            $this->cache->delete(AdminConsoleServiceBase::CACHE_KEY_KPI);
            $output[] = 'SUCCESS: Analytics snapshots refreshed (30-day window; *_7d metrics use 7 days).';
            $success = true;
        } catch (\Throwable $e) {
            $output[] = 'ERROR: ' . $e->getMessage();
            $success = false;
        }
    
        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'analytics', 'trigger', 'Manual analytics aggregation triggered');
        }
    
        return ['success' => $success, 'output' => $output];
    }

    public function triggerApiTests(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\') . '/app/Console/ApiTestSuite.php';
        return $this->runCliScript($scriptPath, '', $moderatorId, 'api_tests', 'Manual API test suite execution triggered');
    }

    public function triggerOpenApi(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\') . '/app/Console/generate_openapi.php';
        return $this->runCliScript($scriptPath, '', $moderatorId, 'openapi', 'Manual OpenAPI spec generation triggered');
    }

    public function triggerSeedData(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\') . '/app/Console/seed_default_data.php';
        return $this->runCliScript($scriptPath, '', $moderatorId, 'seed_data', 'Default data seeding triggered');
    }
}
