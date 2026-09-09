<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Repositories\AdminConsoleRepository;
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
    public function listQueueJobs(int $page, int $perPage, ?string $status = null, string $query = ''): array
    {
        $result = $this->repo->listQueueJobs($page, $perPage, $status, trim($query));
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function retryQueueJob(int $id, string $moderatorId): void
    {
        if (!$this->repo->retryQueueJob($id)) throw new \DomainException('Only failed or cancelled jobs can be retried');
        $this->repo->createModerationAction($moderatorId, 'system', (string)$id, 'trigger', 'Queue job retried');
    }

    public function cancelQueueJob(int $id, string $moderatorId): void
    {
        if (!$this->repo->cancelQueueJob($id)) throw new \DomainException('Only pending jobs can be cancelled');
        $this->repo->createModerationAction($moderatorId, 'system', (string)$id, 'trigger', 'Queue job cancelled');
    }

    public function systemHealth(): array
    {
        $snapshot = $this->repo->systemHealthSnapshot();
        $base = dirname(__DIR__, 2);
        $paths = [$base . '/storage', $base . '/storage/logs', $base . '/storage/cache'];
        $snapshot['runtime'] = ['php_version' => PHP_VERSION, 'memory_limit' => ini_get('memory_limit'), 'memory_usage_bytes' => memory_get_usage(true)];
        $snapshot['storage'] = [
            'ok' => array_reduce($paths, static fn(bool $ok, string $path): bool => $ok && is_dir($path) && is_writable($path), true),
            'free_bytes' => (int)(disk_free_space($base) ?: 0),
            'total_bytes' => (int)(disk_total_space($base) ?: 0),
        ];
        $backupDir = $base . '/storage/backups';
        $backups = is_dir($backupDir) ? array_values(array_filter(glob($backupDir . '/*') ?: [], 'is_file')) : [];
        usort($backups, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $snapshot['backup'] = $backups === [] ? null : ['file' => basename($backups[0]), 'created_at' => gmdate('Y-m-d H:i:s', filemtime($backups[0]) ?: time()), 'size_bytes' => (int)(filesize($backups[0]) ?: 0)];
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
        // Custom error log retrieval could be added here
        return $this->withMeta([], 0, $page, $perPage);
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
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/ApiTestSuite.php';
        return $this->runCliScript($scriptPath, '', $moderatorId, 'api_tests', 'Manual API test suite execution triggered');
    }

    public function triggerOpenApi(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/generate_openapi.php';
        return $this->runCliScript($scriptPath, '', $moderatorId, 'openapi', 'Manual OpenAPI spec generation triggered');
    }

    public function triggerSeedData(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/seed_default_data.php';
        return $this->runCliScript($scriptPath, '', $moderatorId, 'seed_data', 'Default data seeding triggered');
    }
}
