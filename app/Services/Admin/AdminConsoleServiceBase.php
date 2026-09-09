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

/** Shared infrastructure for extracted admin console services. */
abstract class AdminConsoleServiceBase
{
protected const BAN_TYPES = ['general', 'comment', 'blog', 'voting', 'reporting'];
    protected const CACHE_KEY_KPI = 'admin_kpi_summary';
    protected const CACHE_TTL_KPI = 10;
    protected const ANALYTICS_AUTO_INTERVAL = 60;
    protected const ANALYTICS_LOCK_TTL = 300;
    protected const ANALYTICS_LAST_RUN_TTL = 86400 * 7;
    protected const ENV_MASK = '********';
    protected const ENV_EDITABLE_KEYS = [
        'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'SITE_ADDRESS', 'APP_TIMEZONE', 'CORS_ALLOWED_ORIGINS',
        'SESSION_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL', 'SESSION_COOKIE_SECURE',
        'SESSION_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE', 'REMEMBER_COOKIE_SAME_SITE', 'ENFORCE_HTTPS',
        'TRUSTED_PROXIES',
        'RESEND_API_KEY', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY',
        'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SITE_KEY', 'CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ];

    public function __construct(
        protected readonly AdminConsoleRepository $repo,
        protected readonly CacheService $cache,
        protected readonly RetentionService $retention,
        protected readonly AnalyticsAggregationService $aggregation,
        protected readonly QueueService $queueService,
        protected readonly BackupService $backupService,
        protected readonly SlugService $slugger,
        protected readonly SitemapService $sitemapService,
        protected readonly SeriesService $seriesService,
    ) {
    }

    public function createModerationAction(
        ?string $moderatorId,
        array|string $payload,
        ?string $targetId = null,
        ?string $action = null,
        ?string $reason = null
    ): int
    {
        if (is_array($payload)) {
            $data = $payload;
        } else {
            $data = [
                'target_type' => $payload,
                'target_id' => $targetId,
                'action' => $action,
                'reason' => $reason,
            ];
        }
    
        $error = Validator::requireFields($data, ['target_type', 'target_id', 'action']);
        if ($error) {
            throw new \InvalidArgumentException($error);
        }
    
        return $this->repo->createModerationAction(
            $moderatorId,
            (string) $data['target_type'],
            (string) $data['target_id'],
            (string) $data['action'],
            (string) ($data['reason'] ?? ''),
            is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
            (string) ($data['outcome'] ?? 'success')
        );
    }

    protected function taxonomySlug(string $name): string
    {
        $slug = $this->slugger->normalize($name);
        if ($slug === '') throw new \InvalidArgumentException('Name must contain slug-compatible characters');
        return substr($slug, 0, 50);
    }

    protected function readEnvFile(): array
    {
        $path = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($path)) return [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $data = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;
            $key = strtoupper(trim($parts[0]));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;
            $data[$key] = trim($parts[1], " \"'");
        }
        return $data;
    }

    protected function isSensitiveEnvKey(string $key): bool
    {
        // Integration credentials commonly use suffixes such as
        // SECRET_KEY, SITE_KEY and API_KEY. Never write their values to the
        // moderation/audit history.
        return preg_match('/(?:PASSWORD|SECRET|TOKEN|KEY)$/', strtoupper($key)) === 1;
    }

    protected function runCliScript(
        string $scriptPath,
        string $args = '',
        ?string $moderatorId = null,
        ?string $actionTarget = null,
        ?string $actionReason = null
    ): array {
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('CLI script not found: ' . basename($scriptPath));
        }
    
        if (!\function_exists('exec')) {
            return [
                'success' => false,
                'output' => [
                    'ERROR: PHP exec() fonksiyonu sunucuda devre disi birakilmistir (disable_functions).',
                    'Bu islem sadece sunucu terminalinde (CLI) calistirilabilir: php ' . basename($scriptPath) . ($args !== '' ? ' ' . $args : ''),
                ],
            ];
        }
    
        $command = 'php ' . escapeshellarg($scriptPath) . ($args !== '' ? ' ' . $args : '');
        $output = [];
        $returnVar = 0;
        @exec($command, $output, $returnVar);
    
        if ($moderatorId !== null && $actionTarget !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', $actionTarget, 'trigger', $actionReason ?? 'CLI script triggered');
        }
    
        return ['success' => $returnVar === 0, 'output' => $output];
    }

    protected function ensureRootUser(?string $userId): void
    {
        $rootId = $_ENV['ROOT_USER'] ?? getenv('ROOT_USER') ?: null;
        if ($rootId === null || $userId === null || $userId !== $rootId) {
            throw new \DomainException('Unauthorized: Only the ROOT_USER can perform this action.');
        }
    }

    protected function withMeta(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    protected function checkAndAutoTriggerAnalytics(): void
    {
        $lastRunKey = 'system_last_analytics_run';
        $lastRun = (int) $this->cache->get($lastRunKey);
        if ((time() - $lastRun) < self::ANALYTICS_AUTO_INTERVAL) {
            return;
        }
    
        // increment() is backed by CacheService's atomic key lock. This
        // prevents concurrent dashboard requests from starting duplicate runs.
        $lockKey = 'system_analytics_aggregation_lock';
        if ($this->cache->increment($lockKey, 1, self::ANALYTICS_LOCK_TTL) !== 1) {
            return;
        }
    
        try {
            // Re-check after acquiring the lock because another request may
            // have completed the aggregation between the first read and lock.
            $lastRun = (int) $this->cache->get($lastRunKey);
            $now = time();
            if (($now - $lastRun) < self::ANALYTICS_AUTO_INTERVAL) {
                return;
            }
    
            $this->aggregation->aggregateAll(30);
            $this->cache->set($lastRunKey, (string) time(), self::ANALYTICS_LAST_RUN_TTL);
            $this->cache->delete(self::CACHE_KEY_KPI);
        } catch (\Throwable $e) {
            error_log("Auto-Analytics Failed: " . $e->getMessage());
        } finally {
            $this->cache->delete($lockKey);
        }
    }
}
