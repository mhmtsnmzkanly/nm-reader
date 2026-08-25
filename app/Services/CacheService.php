<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service for file-based caching with atomic operations.
 *
 * This service provides a robust caching layer using the local filesystem.
 * Features include:
 * - Atomic write/read via flock.
 * - TTL-based expiration.
 * - Index-based key tracking for prefix deletion.
 * - Internal system performance tracking (hits/misses/writes).
 *
 * @package App\Services
 */
final class CacheService
{
    /** @var string Filename used to track all active cache keys. */
    private const string INDEX_FILE = '.key_index.json';

    /**
     * @param string $cachePath Path to the storage directory.
     * @param int $defaultTtl Default expiration time in seconds (default 300).
     */
    public function __construct(
        private readonly string $cachePath,
        private readonly int $defaultTtl = 300
    ) {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0775, true);
        }
    }

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key Unique cache key.
     * @param mixed $default Value to return if key is missing or expired.
     * @return mixed Cached data or default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->readEntry($key);
        if (!$entry['found']) {
            $this->track('sys_cache_get_miss', 1, 86400 * 365);
            return $default;
        }

        $this->track('sys_cache_get_hit', 1, 86400 * 365);
        return $entry['value'];
    }

    /**
     * Stores a value in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Expiration time in seconds.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->rawSet($key, $value, $ttl ?? $this->defaultTtl);
        $this->registerKey($key);
        $this->track('sys_cache_write', 1, 86400 * 365);
    }

    /**
     * Deletes a specific cache entry.
     *
     * @param string $key
     */
    public function delete(string $key): void
    {
        $file = $this->fileName($key);
        if (is_file($file)) {
            @unlink($file);
            $this->track('sys_cache_delete', 1, 86400 * 365);
        }
        $this->unregisterKey($key);
    }

    /**
     * Deletes all cache entries starting with a specific string.
     *
     * @param string $prefix
     * @return int Number of keys deleted.
     */
    public function deleteByPrefix(string $prefix): int
    {
        $keys = $this->listKeys();
        $deleted = 0;

        foreach ($keys as $key) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            $this->delete($key);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Executes a callback and caches the result if not already cached.
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed Result of the callback or cached value.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $entry = $this->readEntry($key);
        if ($entry['found']) {
            $this->track('sys_cache_get_hit', 1, 86400 * 365);
            return $entry['value'];
        }

        $this->track('sys_cache_get_miss', 1, 86400 * 365);
        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Atomically increments a numeric cache value.
     *
     * @param string $key
     * @param int $by Increment amount.
     * @param int|null $ttl
     * @return int The new value.
     */
    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        return $this->withKeyLock($key, function () use ($key, $by, $ttl): int {
            $current = (int) $this->rawGet($key, 0);
            $next = $current + $by;
            $this->rawSet($key, $next, $ttl ?? $this->defaultTtl);
            $this->registerKey($key);
            return $next;
        });
    }

    /**
     * Retrieves cache engine statistics.
     *
     * @return array [cache_file_count, cache_expired_count]
     */
    public function stats(): array
    {
        $pattern = rtrim($this->cachePath, '/') . '/*.cache';
        $files = glob($pattern) ?: [];
        $now = time();
        $expired = 0;

        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload['expires_at']) && (int) $payload['expires_at'] < $now) {
                $expired++;
            }
        }

        return [
            'cache_file_count' => count($files),
            'cache_expired_count' => $expired,
        ];
    }

    /**
     * Garbage collects expired cache files and stale lock files.
     *
     * @return array{scanned: int, expired_deleted: int, stale_locks_deleted: int}
     */
    public function prune(): array
    {
        $dir = rtrim($this->cachePath, '/');
        $files = glob($dir . '/*.cache') ?: [];
        $locks = glob($dir . '/*.lock') ?: [];
        $now = time();
        $deleted = 0;
        $deletedLocks = 0;

        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload['expires_at']) && (int) $payload['expires_at'] < $now) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        // Cleanup stale locks older than 60 seconds
        foreach ($locks as $lockFile) {
            $mtime = @filemtime($lockFile);
            if ($mtime !== false && ($now - $mtime) > 60) {
                if (@unlink($lockFile)) {
                    $deletedLocks++;
                }
            }
        }

        return [
            'scanned' => count($files),
            'expired_deleted' => $deleted,
            'stale_locks_deleted' => $deletedLocks,
        ];
    }

    /**
     * Flushes the entire cache directory.
     *
     * @return int Total files deleted.
     */
    public function flush(): int
    {
        $dir = rtrim($this->cachePath, '/');
        $files = glob($dir . '/*.cache') ?: [];
        $deleted = 0;

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        $indexFile = $this->indexFileName();
        if (is_file($indexFile)) {
            @unlink($indexFile);
        }

        $fastrouteCache = $dir . '/fastroute_cache.php';
        if (is_file($fastrouteCache)) {
            @unlink($fastrouteCache);
        }

        return $deleted;
    }

    /**
     * Generates the hashed file path for a cache key.
     */
    private function fileName(string $key): string
    {
        return rtrim($this->cachePath, '/') . '/' . hash('sha256', $key) . '.cache';
    }

    /**
     * Internal tracking for cache performance metrics.
     */
    private function track(string $key, int $by, int $ttl): void
    {
        if (str_starts_with($key, 'sys_')) {
            $this->increment($key, $by, $ttl);
        }
    }

    /**
     * Low-level get without index tracking.
     */
    private function rawGet(string $key, mixed $default = null): mixed
    {
        $entry = $this->readEntry($key);
        if (!$entry['found']) {
            return $default;
        }

        return $entry['value'];
    }

    /**
     * Low-level set with mandatory locking.
     */
    private function rawSet(string $key, mixed $value, int $ttl): void
    {
        $payload = json_encode([
            'expires_at' => time() + $ttl,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        file_put_contents($this->fileName($key), $payload, LOCK_EX);
    }

    /**
     * Reads a cache entry and validates expiration.
     */
    private function readEntry(string $key): array
    {
        $file = $this->fileName($key);
        if (!is_file($file)) {
            return ['found' => false, 'value' => null];
        }

        $raw = file_get_contents($file);
        if ($raw === false || !json_validate($raw)) {
            if ($raw !== false) {
                @unlink($file);
                $this->unregisterKey($key);
            }
            return ['found' => false, 'value' => null];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['expires_at']) || !array_key_exists('value', $payload)) {
            return ['found' => false, 'value' => null];
        }

        if ((int) $payload['expires_at'] < time()) {
            @unlink($file);
            $this->unregisterKey($key);
            return ['found' => false, 'value' => null];
        }

        return ['found' => true, 'value' => $payload['value']];
    }

    /**
     * Executes logic within a file lock context for a specific key.
     */
    private function withKeyLock(string $key, callable $callback): mixed
    {
        $lockFile = rtrim($this->cachePath, '/') . '/' . hash('sha256', $key) . '.lock';
        $fp = fopen($lockFile, 'c');
        if ($fp === false) {
            return $callback();
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return $callback();
            }

            return $callback();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function indexFileName(): string
    {
        return rtrim($this->cachePath, '/') . '/' . self::INDEX_FILE;
    }

    /**
     * Lists all keys currently in the index.
     */
    private function listKeys(): array
    {
        $file = $this->indexFileName();
        if (!is_file($file)) {
            return [];
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return [];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', array_keys($payload))));
    }

    private function registerKey(string $key): void
    {
        $this->updateIndex(function (array $index) use ($key): array {
            $index[$key] = true;
            return $index;
        });
    }

    private function unregisterKey(string $key): void
    {
        $this->updateIndex(function (array $index) use ($key): array {
            unset($index[$key]);
            return $index;
        });
    }

    /**
     * Atomically updates the global key index.
     */
    private function updateIndex(callable $updater): void
    {
        $file = $this->indexFileName();
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }

            $raw = stream_get_contents($fp);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $index = is_array($decoded) ? $decoded : [];
            $next = $updater($index);
            if (!is_array($next)) {
                $next = $index;
            }

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, (string) json_encode($next, JSON_UNESCAPED_UNICODE));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
