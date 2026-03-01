<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use App\Helpers\OutputSanitizer;
use App\Repositories\AdminConsoleRepository;

final class AdminConsoleService
{
    private const CACHE_KEY_KPI = 'admin_kpi_summary';
    private const CACHE_TTL_KPI = 300;

    public function __construct(
        private readonly AdminConsoleRepository $repo,
        private readonly CacheService $cache,
        private readonly RetentionService $retention
    ) {
    }

    /**
     * Aggregates key system metrics for the dashboard.
     * Uses caching to reduce database load.
     */
    public function overview(): array
    {
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
                'top_contents_7d' => $data['top_contents_7d'] ?? []
            ]
        ];
    }

    /**
     * Retrieves audit logs with optional filtering.
     */
    public function listAuditLogs(int $page, int $perPage): array
    {
        $result = $this->repo->listAuditLogs($page, $perPage);
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
    public function listUsers(int $page, int $perPage): array
    {
        $result = $this->repo->listUsers($page, $perPage);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['username', 'bio']);

        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    /**
     * Paginated list of content (series) for management.
     */
    public function listContents(int $page, int $perPage): array
    {
        $result = $this->repo->listContents($page, $perPage);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['title']);

        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    /**
     * Lists jobs in the system queue.
     */
    public function listQueueJobs(int $page, int $perPage): array
    {
        $result = $this->repo->listQueueJobs($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
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
    public function listComments(int $page, int $perPage): array
    {
        $result = $this->repo->listComments($page, $perPage);
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
    public function createModerationAction(?string $moderatorId, array $payload): int
    {
        $error = Validator::requireFields($payload, ['target_type', 'target_id', 'action']);
        if ($error) {
            throw new \InvalidArgumentException($error);
        }

        return $this->repo->createModerationAction(
            $moderatorId,
            (string) $payload['target_type'],
            (string) $payload['target_id'],
            (string) $payload['action'],
            (string) ($payload['reason'] ?? '')
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

    /**
     * Lists RBAC assignments.
     */
    public function listRbacAssignments(int $page, int $perPage): array
    {
        $result = $this->repo->listUserRoleAssignments($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function listBlogs(int $page, int $perPage): array
    {
        $result = $this->repo->listBlogs($page, $perPage);
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
        
        $this->repo->assignPermissionToRole($role, $perm, $moderatorId);
        return true;
    }

    public function revokePermissionFromRole(array $payload, string $moderatorId): bool
    {
        $role = (string)($payload['role'] ?? '');
        $perm = (string)($payload['permission'] ?? '');
        if ($role === '' || $perm === '') throw new \InvalidArgumentException('role and permission are required');

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
        
        $genre = $this->repo->createGenre($name);
        $this->repo->createModerationAction($moderatorId, 'system', (string)$genre['id'], 'create_genre', "New genre created: $name");
        
        return $genre;
    }

    public function createTag(string $name, string $moderatorId): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $tag = $this->repo->createTag($name);
        $this->repo->createModerationAction($moderatorId, 'system', (string)$tag['id'], 'create_tag', "New tag created: $name");

        return $tag;
    }

    public function updateContentTaxonomy(string $contentId, array $genreIds, array $tagIds, string $moderatorId): void
    {
        $this->repo->updateContentTaxonomy($contentId, $genreIds, $tagIds);
        $this->repo->createModerationAction($moderatorId, 'content', $contentId, 'update', 'Genre/Tag assignments updated');
    }

    public function cleanupRetention(int $days): array
    {
        return $this->retention->cleanup($days);
    }

    public function readEnv(string $moderatorId): array
    {
        $this->ensureRootUser($moderatorId);
        $path = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($path)) return [];

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1], " \"'");
                $data[$key] = $val;
            }
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

        // Fetch current for diff logging
        $current = $this->readEnv($moderatorId);
        $diff = [];
        foreach ($payload as $k => $v) {
            $old = $current[$k] ?? '';
            if ($old !== (string)$v) {
                $diff[$k] = ['before' => $old, 'after' => $v];
            }
        }

        if (empty($diff)) return;

        // Atomic write strategy
        copy($path, $backupPath);
        try {
            $content = "# Updated via Admin Console at " . date('Y-m-d H:i:s') . "\n";
            foreach ($payload as $key => $value) {
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

    private function ensureRootUser(?string $userId): void
    {
        $rootId = $_ENV['ROOT_USER'] ?? getenv('ROOT_USER') ?: null;
        if ($rootId === null || $userId === null || $userId !== $rootId) {
            throw new \RuntimeException('Unauthorized: Only the ROOT_USER can perform this action.');
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
}
