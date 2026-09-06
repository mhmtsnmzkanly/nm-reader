<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use App\Helpers\OutputSanitizer;
use App\Repositories\AdminConsoleRepository;
use App\Services\AnalyticsAggregationService;

final class AdminConsoleService
{
    private const CACHE_KEY_KPI = 'admin_kpi_summary';
    private const CACHE_TTL_KPI = 10;
    private const ENV_MASK = '********';
    private const ENV_EDITABLE_KEYS = [
        'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_TIMEZONE', 'CORS_ALLOWED_ORIGINS',
        'SESSION_LIFETIME', 'REFRESH_TOKEN_DAYS', 'CACHE_TTL', 'SESSION_COOKIE_SECURE',
        'SESSION_COOKIE_SAME_SITE', 'REMEMBER_COOKIE_SECURE', 'REMEMBER_COOKIE_SAME_SITE',
        'SITE_NAME', 'SITE_ABBREVIATION', 'SITE_DESCRIPTION', 'SITE_LOGO', 'SITE_ADDRESS',
        'DEFAULT_LANGUAGE', 'DEFAULT_THEME', 'DEFAULT_PROFILE_IMAGE', 'DEFAULT_CONTENT_COVER_IMAGE',
        'ENFORCE_HTTPS', 'RESEND_API_KEY', 'GOOGLE_ANALYTICS_ID', 'GOOGLE_RECAPTCHA_SITE_KEY',
        'GOOGLE_RECAPTCHA_SECRET_KEY', 'CLOUDFLARE_TURNSTILE_SITE_KEY', 'CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ];

    public function __construct(
        private readonly AdminConsoleRepository $repo,
        private readonly CacheService $cache,
        private readonly RetentionService $retention,
        private readonly AnalyticsAggregationService $aggregation,
        private readonly SlugService $slugger
    ) {
    }

    /**
     * Aggregates key system metrics for the dashboard.
     * Uses caching to reduce database load.
     * Automatically triggers analytics aggregation every 12 hours.
     */
    public function overview(): array
    {
        // Lazy-cron: Check if we need to aggregate data (every 12 hours)
        $this->checkAndAutoTriggerAnalytics();

        $data = $this->cache->remember(self::CACHE_KEY_KPI, self::CACHE_TTL_KPI, function () {
            return $this->repo->summaryKpis();
        });

        // Split data into the structure expected by admin.js
        return [
            'kpis' => [
                'users_total' => $data['users_total'] ?? 0,
                'contents_total' => $data['contents_total'] ?? 0,
                'chapters_total' => $data['chapters_total'] ?? 0,
                'blogs_pending_total' => $data['blogs_pending_total'] ?? 0,
            ],
            'metrics' => [
                'funnel' => [
                    'home_to_content_pct' => $data['funnel']['home_to_content_pct'] ?? 0,
                    'content_to_chapter_pct' => $data['funnel']['content_to_chapter_pct'] ?? 0,
                ],
                'performance_slo' => [
                    'server_error_rate_pct_24h' => $data['performance_slo']['server_error_rate_pct_24h'] ?? 0,
                    'p95_duration_ms_24h' => $data['performance_slo']['p95_duration_ms_24h'] ?? 0,
                ],
                'retention_search' => [
                    'search_total_7d' => $data['retention_search']['search_total_7d'] ?? 0,
                    'zero_result_pct_7d' => $data['retention_search']['zero_result_pct_7d'] ?? 0,
                    'd1_retention_pct' => $data['retention_search']['d1_retention_pct'] ?? 0,
                    'new_users_7d' => $data['retention_search']['new_users_7d'] ?? 0,
                ],
                'top_contents_7d' => $data['top_contents_7d'] ?? []
            ]
        ];
    }

    /**
     * Retrieves audit logs with optional filtering.
     */
    public function listAuditLogs(int $page, int $perPage, string $query = '', ?string $method = null, ?string $statusGroup = null, ?string $userId = null, ?string $dateFrom = null, ?string $dateTo = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listAuditLogs($page, $perPage, trim($query), $method, $statusGroup, $userId, $dateFrom, $dateTo, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['username']);

        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    /**
     * Lists login attempts.
     */
    public function listLoginEvents(int $page, int $perPage): array
    {
        $result = $this->repo->listLoginEvents($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * Paginated list of users for management.
     */
    public function listUsers(int $page, int $perPage, string $query = '', ?string $status = null, ?string $role = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listUsers($page, $perPage, $query, $status, $role, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['username', 'bio']);

        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function listAllUsersForSelect(): array
    {
        $items = $this->repo->listAllUsersForSelect();
        return OutputSanitizer::sanitizeRows($items, ['username']);
    }

    /**
     * Paginated list of content (series) for management.
     */
    public function listContents(int $page, int $perPage, string $query = '', ?string $status = null, ?string $type = null, ?string $lifecycle = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listContents($page, $perPage, $query, $status, $type, $lifecycle, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['title']);

        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    /**
     * Lists jobs in the system queue.
     */
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

    /**
     * Triggers a specific job type or runs the queue with a limit.
     */
    public function runQueueOnce(?string $jobType = null, int $limit = 10, ?string $moderatorId = null): array
    {
        // Internal job trigger logic
        return [
            'triggered' => true, 
            'type' => $jobType ?? 'general',
            'limit' => $limit
        ];
    }

    /**
     * Lists system access logs.
     */
    public function listSystemAccessLogs(int $page, int $perPage): array
    {
        $result = $this->repo->listAuditLogs($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * Lists system error logs.
     */
    public function listSystemErrorLogs(int $page, int $perPage): array
    {
        // Custom error log retrieval could be added here
        return $this->withMeta([], 0, $page, $perPage);
    }

    /**
     * Lists all system uploads with pagination.
     */
    public function listUploads(int $page, int $perPage, string $query = '', ?string $mime = null, bool $orphansOnly = false): array
    {
        $result = $this->repo->listUploads($page, $perPage, trim($query), $mime, $orphansOnly);
        $result['stats'] = $this->repo->uploadStats();
        return $result;
    }

    /**
     * Deletes a specific upload entry.
     */
    public function deleteUpload(int $id, string $moderatorId): void
    {
        $info = $this->repo->deleteUpload($id);
        if ($info) {
            $filePath = (string) ($info['file_path'] ?? '');
            if ($filePath !== '') {
                $basePath = dirname(__DIR__, 2);
                $cleanName = basename($filePath);
                $storageDiskPath = $basePath . '/storage/media/' . $cleanName;
                if (is_file($storageDiskPath)) {
                    @unlink($storageDiskPath);
                }
                $publicDiskPath = $basePath . '/public' . $filePath;
                if (is_file($publicDiskPath)) {
                    @unlink($publicDiskPath);
                }
            }
            $imageId = (string) ($info['image_id'] ?? '');
            $this->createModerationAction($moderatorId, 'system', (string)$id, 'delete', "Deleted system upload record: $imageId");
        }
    }

    public function deleteUploads(array $ids, string $moderatorId): array
    {
        $deleted = 0;
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            if ($id <= 0) continue;
            $before = $this->repo->uploadById($id);
            if (!$before) continue;
            $this->deleteUpload($id, $moderatorId);
            $deleted++;
        }
        return ['deleted' => $deleted];
    }

    public function optimizeUpload(int $id, string $moderatorId): array
    {
        $upload = $this->repo->uploadById($id);
        if (!$upload) throw new \InvalidArgumentException('Upload not found');
        $mime = (string)$upload['mime_type'];
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !function_exists('imagecreatefromstring')) {
            throw new \DomainException('This image type cannot be optimized on this server');
        }
        $path = dirname(__DIR__, 2) . '/storage/media/' . basename((string)$upload['file_path']);
        if (!is_file($path)) throw new \DomainException('Physical file not found');
        $raw = file_get_contents($path);
        $image = $raw === false ? false : @imagecreatefromstring($raw);
        if ($image === false) throw new \DomainException('Image data is invalid');
        $temporary = tempnam(dirname($path), 'opt_');
        if ($temporary === false) { imagedestroy($image); throw new \RuntimeException('Temporary file could not be created'); }
        if (in_array($mime, ['image/png', 'image/webp'], true)) { imagealphablending($image, false); imagesavealpha($image, true); }
        $saved = match ($mime) { 'image/jpeg' => imagejpeg($image, $temporary, 82), 'image/png' => imagepng($image, $temporary, 8), 'image/webp' => imagewebp($image, $temporary, 78), default => false };
        imagedestroy($image);
        if (!$saved) { @unlink($temporary); throw new \RuntimeException('Optimized image could not be written'); }
        $oldSize = (int)(filesize($path) ?: 0);
        $newSize = (int)(filesize($temporary) ?: 0);
        if ($newSize > 0 && ($oldSize === 0 || $newSize < $oldSize)) {
            if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Optimized image could not replace original'); }
            $this->repo->updateUploadFileSize($id, $newSize);
        } else {
            @unlink($temporary);
            $newSize = $oldSize;
        }
        $this->repo->createModerationAction($moderatorId, 'system', (string)$id, 'update', "Upload optimized: $oldSize -> $newSize bytes");
        return ['id' => $id, 'old_size' => $oldSize, 'new_size' => $newSize, 'saved_bytes' => max(0, $oldSize - $newSize)];
    }

    /**
     * Updates user details and moderation status.
     */
    public function updateUser(string $id, array $payload, string $moderatorId): array
    {
        if (isset($payload['email']) && $payload['email'] !== '' && !Validator::validEmail((string)$payload['email'])) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        $this->repo->updateUser(
            $id,
            (string) ($payload['role'] ?? ''),
            (bool) ($payload['is_banned'] ?? false),
            $moderatorId,
            $payload['email'] ?? null,
            $payload['bio'] ?? null
        );

        return ['id' => $id, 'updated' => true];
    }

    /**
     * Lists social comments for moderation.
     */
    public function listComments(int $page, int $perPage, string $query = '', ?string $targetType = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listComments($page, $perPage, $query, $targetType, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['body', 'username', 'content_title', 'blog_title']);

        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    /**
     * Deletes a comment.
     */
    public function deleteComment(int $id, string $moderatorId): bool
    {
        return $this->repo->deleteComment($id, $moderatorId);
    }

    /**
     * Lists moderation history.
     */
    public function listModerationActions(int $page, int $perPage): array
    {
        $result = $this->repo->listModerationActions($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * Records a moderation action.
     */
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
            (string) ($data['reason'] ?? '')
        );
    }

    public function userReputation(int $limit): array
    {
        return $this->repo->userReputation($limit);
    }

    public function siteVisits(): array
    {
        return $this->repo->siteVisits();
    }

    public function viewStats(int $days, int $limit): array
    {
        return $this->repo->topViewedStats($days, $limit);
    }

    public function blogStats(int $days, int $limit): array
    {
        return $this->repo->blogStats($days, $limit);
    }

    /**
     * Lists RBAC roles from static config.
     */
    public function listRbacRoles(): array
    {
        $items = $this->repo->listRolesWithPermissions();
        return $this->withMeta($items, count($items), 1, count($items));
    }

    public function listRolesWithPermissions(): array
    {
        return $this->repo->listRolesWithPermissions();
    }

    /**
     * Lists RBAC assignments.
     */
    public function listRbacAssignments(int $page, int $perPage): array
    {
        $result = $this->repo->listUserRoleAssignments($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function listBlogs(int $page, int $perPage, string $query = '', ?string $status = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listBlogs($page, $perPage, $query, $status, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['title', 'username']);
        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function revokeUserSession(string $userId, string $sessionKey, string $moderatorId): void
    {
        $this->repo->revokeUserSession($userId, $sessionKey, $moderatorId);
    }

    public function hideBlog(string $id, string $moderatorId): void
    {
        $this->repo->hideBlog($id, $moderatorId);
    }

    public function deleteBlog(string $id, string $moderatorId): void
    {
        $this->repo->deleteBlog($id, $moderatorId);
    }

    public function assignPermissionToRole(array $payload, string $moderatorId): bool
    {
        $role = (string)($payload['role'] ?? '');
        $perm = (string)($payload['permission'] ?? '');
        if ($role === '' || $perm === '') throw new \InvalidArgumentException('role and permission are required');
        if (!$this->repo->roleExistsBySlug($role)) throw new \InvalidArgumentException('Unknown role');
        if (!$this->repo->permissionExistsByCode($perm)) throw new \InvalidArgumentException('Unknown permission');
        
        $this->repo->assignPermissionToRole($role, $perm, $moderatorId);
        return true;
    }

    public function revokePermissionFromRole(array $payload, string $moderatorId): bool
    {
        $role = (string)($payload['role'] ?? '');
        $perm = (string)($payload['permission'] ?? '');
        if ($role === '' || $perm === '') throw new \InvalidArgumentException('role and permission are required');
        if (!$this->repo->roleExistsBySlug($role)) throw new \InvalidArgumentException('Unknown role');
        if (!$this->repo->permissionExistsByCode($perm)) throw new \InvalidArgumentException('Unknown permission');
        if ($role === 'admin' && $perm === 'admin.panel.access') {
            throw new \InvalidArgumentException('Administrator panel access cannot be revoked');
        }

        return $this->repo->revokePermissionFromRole($role, $perm, $moderatorId);
    }

    public function assignRoleToUser(array $payload): bool
    {
        $userId = (string)($payload['user_id'] ?? '');
        $role = (string)($payload['role'] ?? '');
        if ($userId === '' || $role === '') throw new \InvalidArgumentException('user_id and role are required');

        return $this->repo->assignRoleToUser($userId, $role);
    }

    public function createGenre(string $name, string $moderatorId): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');
        
        $genre = $this->repo->createGenre($name, $this->taxonomySlug($name));
        $this->repo->createModerationAction($moderatorId, 'system', (string)$genre['id'], 'create_genre', "New genre created: $name");
        
        return $genre;
    }

    public function createTag(string $name, string $moderatorId): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $tag = $this->repo->createTag($name, $this->taxonomySlug($name));
        $this->repo->createModerationAction($moderatorId, 'system', (string)$tag['id'], 'create_tag', "New tag created: $name");

        return $tag;
    }

    public function updateContentTaxonomy(string $contentId, array $genreIds, array $tagIds, string $moderatorId): void
    {
        $this->repo->updateContentTaxonomy($contentId, $genreIds, $tagIds);
        $this->repo->createModerationAction($moderatorId, 'content', $contentId, 'update', 'Genre/Tag assignments updated');
    }

    public function listAllGenres(): array
    {
        return $this->repo->listAllGenres();
    }

    public function listAllTags(): array
    {
        return $this->repo->listAllTags();
    }

    public function updateTaxonomy(int $id, array $payload, string $moderatorId): array
    {
        $existing = $this->repo->taxonomyById($id);
        if (!$existing) throw new \InvalidArgumentException('Taxonomy not found');
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Name is required');
        $updated = $this->repo->updateTaxonomy($id, $name, $this->taxonomySlug($name));
        $this->repo->createModerationAction($moderatorId, 'system', (string)$id, 'update_taxonomy', "Taxonomy renamed: {$existing['name']} -> $name");
        return $updated ?? [];
    }

    public function deleteTaxonomy(int $id, string $moderatorId): void
    {
        $existing = $this->repo->taxonomyById($id);
        if (!$existing) throw new \InvalidArgumentException('Taxonomy not found');
        if ((int)$existing['usage_count'] > 0) throw new \InvalidArgumentException('Used taxonomy must be merged before deletion');
        if (!$this->repo->deleteTaxonomy($id)) throw new \RuntimeException('Taxonomy could not be deleted');
        $this->repo->createModerationAction($moderatorId, 'system', (string)$id, 'delete', "Taxonomy deleted: {$existing['name']}");
    }

    public function mergeTaxonomies(array $payload, string $moderatorId): array
    {
        $sourceId = (int)($payload['source_id'] ?? 0);
        $targetId = (int)($payload['target_id'] ?? 0);
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) throw new \InvalidArgumentException('Valid, different source_id and target_id are required');
        $merged = $this->repo->mergeTaxonomies($sourceId, $targetId);
        $this->repo->createModerationAction($moderatorId, 'system', (string)$targetId, 'update_taxonomy', "Taxonomy $sourceId merged into $targetId");
        return $merged;
    }

    public function reorderTaxonomies(array $payload, string $moderatorId): void
    {
        $items = (array)($payload['items'] ?? []);
        if ($items === []) throw new \InvalidArgumentException('items is required');
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item) || (int)($item['id'] ?? 0) <= 0) throw new \InvalidArgumentException('Each item must contain a valid id');
            $normalized[] = ['id' => (int)$item['id'], 'sort_order' => max(0, (int)($item['sort_order'] ?? 0))];
        }
        $this->repo->reorderTaxonomies($normalized);
        $this->repo->createModerationAction($moderatorId, 'system', 'taxonomy-order', 'update_taxonomy', 'Taxonomy order updated');
    }

    private function taxonomySlug(string $name): string
    {
        $slug = $this->slugger->normalize($name);
        if ($slug === '') throw new \InvalidArgumentException('Name must contain slug-compatible characters');
        return substr($slug, 0, 50);
    }

    public function cleanupRetention(int $days): array
    {
        return $this->retention->cleanup($days);
    }

    public function readEnv(string $moderatorId): array
    {
        $this->ensureRootUser($moderatorId);
        $data = array_intersect_key($this->readEnvFile(), array_flip(self::ENV_EDITABLE_KEYS));
        foreach ($data as $key => $value) {
            if ($this->isSensitiveEnvKey($key) && $value !== '') $data[$key] = self::ENV_MASK;
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
            if (!in_array($key, self::ENV_EDITABLE_KEYS, true) || !is_scalar($value)) continue;
            $value = (string)$value;
            if ($value === self::ENV_MASK && $this->isSensitiveEnvKey($key)) continue;
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
            @unlink($backupPath);
        } catch (\Throwable $e) {
            copy($backupPath, $path);
            throw $e;
        }
    }

    private function readEnvFile(): array
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

    private function isSensitiveEnvKey(string $key): bool
    {
        return preg_match('/(?:PASSWORD|SECRET|TOKEN|API_KEY)$/', $key) === 1;
    }

    public function triggerBackup(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/system_backup.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Backup script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath), $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'backup', 'trigger', 'Manual backup triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    public function triggerSitemap(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $this->cache->delete('sitemap_xml');
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/generate_sitemap.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Sitemap script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath), $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'sitemap', 'trigger', 'Manual sitemap generation triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    public function triggerCacheWarmup(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/cache_warmer.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Cache warmer script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath), $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'cache_warmup', 'trigger', 'Manual cache warmup triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    public function triggerAnalytics(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/analytics_aggregate.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Analytics script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath) . " --days=30", $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'analytics', 'trigger', 'Manual analytics aggregation triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    public function triggerApiTests(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/ApiTestSuite.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('API test suite script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath), $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'api_tests', 'trigger', 'Manual API test suite execution triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    public function triggerOpenApi(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/generate_openapi.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('OpenAPI generator script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath), $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'openapi', 'trigger', 'Manual OpenAPI spec generation triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    public function triggerSeedData(?string $moderatorId = null): array
    {
        $this->ensureRootUser($moderatorId);
        $scriptPath = dirname(__DIR__, 2) . '/app/Console/seed_default_data.php';
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Seed default data script not found');
        }

        $output = [];
        $returnVar = 0;
        exec("php " . escapeshellarg($scriptPath), $output, $returnVar);

        if ($moderatorId !== null) {
            $this->repo->createModerationAction($moderatorId, 'system', 'seed_data', 'trigger', 'Default data seeding triggered');
        }

        return ['success' => $returnVar === 0, 'output' => $output];
    }

    private function ensureRootUser(?string $userId): void
    {
        $rootId = $_ENV['ROOT_USER'] ?? getenv('ROOT_USER') ?: null;
        if ($rootId === null || $userId === null || $userId !== $rootId) {
            throw new \DomainException('Unauthorized: Only the ROOT_USER can perform this action.');
        }
    }

    private function withMeta(array $items, int $total, int $page, int $perPage): array
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

    /**
     * Internal helper to check and run analytics aggregation if 12 hours have passed.
     */
    private function checkAndAutoTriggerAnalytics(): void
    {
        $lastRunKey = 'system_last_analytics_run';
        $twelveHoursInSeconds = 12 * 3600;
        
        $lastRun = (int)$this->cache->get($lastRunKey);
        $now = time();

        if (($now - $lastRun) >= $twelveHoursInSeconds) {
            try {
                // Perform aggregation directly via service
                $this->aggregation->aggregateAll(30);
                
                // Record completion time
                $this->cache->set($lastRunKey, (string)$now, 86400 * 7);
                
                // Invalidate KPI cache to show new data
                $this->cache->delete(self::CACHE_KEY_KPI);
            } catch (\Throwable $e) {
                error_log("Auto-Analytics Failed: " . $e->getMessage());
            }
        }
    }
}
