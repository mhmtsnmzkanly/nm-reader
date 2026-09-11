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
final class AdminSettingsService extends AdminConsoleServiceBase
{
    public function readEnv(string $moderatorId): array
    {
        $this->ensureRootUser($moderatorId);
        $data = array_intersect_key($this->readEnvFile(), array_flip(AdminConsoleServiceBase::ENV_EDITABLE_KEYS));
        // Process/container variables override .env at runtime. Reflect the
        // effective value in the admin form so an operator is not misled by a
        // file value that the hosting environment will ignore.
        foreach (AdminConsoleServiceBase::ENV_EDITABLE_KEYS as $key) {
            if (Config::environmentSource($key) === 'process') {
                $data[$key] = (string) Config::getEnv($key, '');
            }
        }
        foreach ($data as $key => $value) {
            if ($this->isSensitiveEnvKey($key) && $value !== '') $data[$key] = AdminConsoleServiceBase::ENV_MASK;
        }
        return $data;
    }

    public function updateEnv(array $payload, string $moderatorId): void
    {
        $this->ensureRootUser($moderatorId);
        $base = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\');
        $path = $base . '/.env';
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
            // Secret inputs are intentionally blank in the admin form. An
            // empty value therefore means "keep the existing secret"; this
            // prevents an untouched password/API-key field from erasing the
            // live credential. Clearing a secret remains an explicit server-
            // side operation rather than an accidental form submission.
            if (trim($value) === '' && $this->isSensitiveEnvKey($key)) continue;
            if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
                throw new \InvalidArgumentException("Invalid environment value for $key");
            }
            $safePayload[$key] = $value;
        }
        if ($safePayload === []) throw new \InvalidArgumentException('No editable environment keys supplied');
        // Newly introduced numeric fields may not exist in older .env files;
        // an untouched empty form field must not turn into an invalid value.
        foreach (['SESSION_LIFETIME', 'SESSION_COOKIE_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL'] as $numericKey) {
            if (($safePayload[$numericKey] ?? null) === '' && !array_key_exists($numericKey, $current)) {
                unset($safePayload[$numericKey]);
            }
        }
        if ($safePayload === []) throw new \InvalidArgumentException('No editable environment keys supplied');
        $merged = array_merge($current, $safePayload);
        $validationInput = array_intersect_key($merged, array_flip(AdminConsoleServiceBase::ENV_EDITABLE_KEYS));
        $normalizedEnvironment = Config::normalizeEnvironment($validationInput);
        foreach ($safePayload as $key => $value) {
            if (array_key_exists($key, $normalizedEnvironment)) {
                $safePayload[$key] = $normalizedEnvironment[$key];
            }
        }
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
        if (!copy($path, $backupPath)) {
            throw new \RuntimeException('Failed to create .env backup');
        }
        try {
            $content = "# Updated via Admin Console at " . date('Y-m-d H:i:s') . "\n";
            foreach ($merged as $key => $value) {
                $key = strtoupper(trim((string)$key));
                if ($key === '') continue;
                // Quote values that dotenv could otherwise parse ambiguously.
                if (strpbrk((string) $value, " \t\r\n#$!'\"\\") !== false) {
                    $value = '"' . addcslashes((string) $value, "\\\"") . '"';
                }
                $content .= "{$key}={$value}\n";
            }
    
            if (file_put_contents($path, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Failed to write .env file');
            }
            
            Config::clearCache();
            $this->repo->createModerationAction($moderatorId, 'system', 'config', 'env_update', json_encode(['diff' => $diff]));
            if (isset($safePayload['APP_URL']) || isset($safePayload['SITE_ADDRESS'])) {
                $this->cache->delete('robots_txt');
                $this->cache->delete('sitemap_xml');
            }
            @unlink($backupPath);
        } catch (\Throwable $e) {
            if (is_file($backupPath)) {
                if (!copy($backupPath, $path)) {
                    throw new \RuntimeException('Failed to restore .env backup after update failure.', 0, $e);
                }
                // The file may have been written successfully before a later
                // audit/cache operation failed. Re-read the restored file so
                // the in-process snapshot cannot keep the rejected values.
                Config::clearCache();
            }
            throw $e;
        }
    }
}
