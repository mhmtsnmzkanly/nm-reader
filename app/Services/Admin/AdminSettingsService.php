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
final class AdminSettingsService extends AdminConsoleServiceBase
{
    public function readEnv(string $moderatorId): array
    {
        $this->ensureRootUser($moderatorId);
        $data = array_intersect_key($this->readEnvFile(), array_flip(AdminConsoleServiceBase::ENV_EDITABLE_KEYS));
        foreach ($data as $key => $value) {
            if ($this->isSensitiveEnvKey($key) && $value !== '') $data[$key] = AdminConsoleServiceBase::ENV_MASK;
        }
        return $data;
    }

    public function updateEnv(array $payload, string $moderatorId): void
    {
        $this->ensureRootUser($moderatorId);
        $path = dirname(__DIR__, 2) . '/.env';
        $backupPath = $path . '.bak';
    
        if (!file_exists($path)) {
            throw new \RuntimeException('.env file not found');
        }
    
        $current = $this->readEnvFile();
        $safePayload = [];
        foreach ($payload as $key => $value) {
            $key = strtoupper(trim((string)$key));
            if (!in_array($key, AdminConsoleServiceBase::ENV_EDITABLE_KEYS, true) || !is_scalar($value)) continue;
            $value = (string)$value;
            if ($value === AdminConsoleServiceBase::ENV_MASK && $this->isSensitiveEnvKey($key)) continue;
            if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
                throw new \InvalidArgumentException("Invalid environment value for $key");
            }
            $safePayload[$key] = $value;
        }
        if ($safePayload === []) throw new \InvalidArgumentException('No editable environment keys supplied');
        $merged = array_merge($current, $safePayload);
        $diff = [];
        foreach ($safePayload as $k => $v) {
            $old = $current[$k] ?? '';
            if ($old !== (string)$v) {
                $diff[$k] = $this->isSensitiveEnvKey($k) ? ['changed' => true] : ['before' => $old, 'after' => $v];
            }
        }
    
        if (empty($diff)) return;
    
        // Atomic write strategy
        copy($path, $backupPath);
        try {
            $content = "# Updated via Admin Console at " . date('Y-m-d H:i:s') . "\n";
            foreach ($merged as $key => $value) {
                $key = strtoupper(trim((string)$key));
                if ($key === '') continue;
                // Quote values with spaces or special chars
                if (preg_match('/\s|[#$!]/', (string)$value)) {
                    $value = '"' . str_replace('"', '\"', (string)$value) . '"';
                }
                $content .= "{$key}={$value}\n";
            }
    
            if (file_put_contents($path, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Failed to write .env file');
            }
            
            $this->repo->createModerationAction($moderatorId, 'system', 'config', 'env_update', json_encode(['diff' => $diff]));
            if (isset($safePayload['APP_URL'])) {
                $this->cache->delete('robots_txt');
                $this->cache->delete('sitemap_xml');
            }
            @unlink($backupPath);
        } catch (\Throwable $e) {
            copy($backupPath, $path);
            throw $e;
        }
    }
}
