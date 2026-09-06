<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Admin Console database operations.
 *
 * Provides specialized raw SQL interactions for the administrative dashboard.
 * Responsibilities include:
 * - Aggregating system-wide KPIs (Users, Chapters, Queue status).
 * - Administrative listings for Users and Contents with management metadata.
 * - Role and Permission assignment/revocation.
 * - Fetching analytics snapshots for top-viewed content and blog performance.
 *
 * @package App\Repositories
 */
final class AdminConsoleRepository
{
    /** @var bool|null Cache for checking if 'blogs' table has 'deleted_at'. */
    private ?bool $blogsHasDeletedAt = null;

    /** @var bool|null Cache for checking if  'comments' table has 'blog_id'. */
    private ?bool $commentsHasBlogId = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Aggregates high-level Key Performance Indicators for the dashboard.
     *
     * @return array [users_total, contents_total, chapters_total, today_views, etc.]
     */
    public function summaryKpis(): array
    {
        $todayViews = $this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'total_views' ORDER BY stat_date DESC LIMIT 1");
        
        // Fetch Performance & Health
        $health = $this->pdo->query("SELECT * FROM analytics_snapshots_health ORDER BY stat_date DESC LIMIT 1")->fetch();
        $errorRate = 0;
        if ($health && ($health['request_total_24h'] ?? 0) > 0) {
            $errorRate = round(($health['server_error_total_24h'] / $health['request_total_24h']) * 100, 2);
        }

        // Fetch Funnel Metrics
        $homeViews = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'home_view_total' ORDER BY stat_date DESC LIMIT 1");
        $contentViews = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'content_view_total' ORDER BY stat_date DESC LIMIT 1");
        $chapterViews = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'chapter_view_total' ORDER BY stat_date DESC LIMIT 1");

        $homeToContent = $homeViews > 0 ? round(($contentViews / $homeViews) * 100, 1) : 0;
        $contentToChapter = $contentViews > 0 ? round(($chapterViews / $contentViews) * 100, 1) : 0;

        // Fetch Retention & Search (Last available snapshot)
        $searchTotal = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'search_total_7d' ORDER BY stat_date DESC LIMIT 1");
        $zeroResults = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'zero_result_total_7d' ORDER BY stat_date DESC LIMIT 1");
        $d1Retained = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'd1_retained_total' ORDER BY stat_date DESC LIMIT 1");
        $newUsers = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'new_users_7d_total' ORDER BY stat_date DESC LIMIT 1");

        $retentionPct = $newUsers > 0 ? round(($d1Retained / $newUsers) * 100, 1) : 0;

        // Fetch Top Contents 7d (most recent date available in snapshots)
        $latestDate = $this->queryValue("SELECT MAX(stat_date) FROM analytics_snapshots_series_top");
        $topContents = [];
        if ($latestDate) {
            $stmt = $this->pdo->prepare(
                "SELECT s.title, s.type, s.slug, SUM(t.view_count) as view_count_7d, 0 as comment_count_7d
                 FROM analytics_snapshots_series_top t
                 JOIN series s ON s.id = t.content_id
                 WHERE t.stat_date >= DATE_SUB(:latest, INTERVAL 7 DAY)
                 GROUP BY t.content_id
                 ORDER BY view_count_7d DESC
                 LIMIT 5"
            );
            $stmt->execute(['latest' => $latestDate]);
            $topContents = $stmt->fetchAll();
        }

        return [
            'users_total' => $this->count('SELECT COUNT(*) FROM users'),
            'contents_total' => $this->count('SELECT COUNT(*) FROM series'),
            'chapters_total' => $this->count('SELECT COUNT(*) FROM chapters'),
            'comments_total' => $this->count('SELECT COUNT(*) FROM comments'),
            'today_content_views_total' => (int)($todayViews ?? 0),
            'blogs_pending_total' => $this->count('SELECT COUNT(*) FROM blogs WHERE approved = 0'),
            'queue_pending_total' => $this->count("SELECT COUNT(*) FROM system_jobs WHERE status = 'pending'"),
            'queue_failed_total' => $this->count("SELECT COUNT(*) FROM system_jobs WHERE status = 'failed'"),
            'audit_24h_total' => $this->count('SELECT COUNT(*) FROM system_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'funnel' => [
                'home_to_content_pct' => $homeToContent,
                'content_to_chapter_pct' => $contentToChapter,
            ],
            'retention_search' => [
                'search_total_7d' => $searchTotal,
                'zero_result_pct_7d' => $searchTotal > 0 ? round(($zeroResults / $searchTotal) * 100, 1) : 0,
                'd1_retention_pct' => $retentionPct,
                'new_users_7d' => $newUsers
            ],
            'performance_slo' => [
                'server_error_rate_pct_24h' => $errorRate,
                'p95_duration_ms_24h' => (int)($health['p95_duration_ms_24h'] ?? 0),
            ],
            'top_contents_7d' => $topContents
        ];
    }

    private function queryValue(string $sql, array $params = []): mixed
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Detailed listing of series for admin management.
     *
     * Includes metadata like author and comma-separated taxonomy IDs.
     *
     * @return array ['items' => [...], 'total' => int]
     */
    public function listContents(int $page, int $perPage, string $query = '', ?string $status = null, ?string $type = null, ?string $lifecycle = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($query !== '') {
            $where[] = '(c.title LIKE :query OR c.slug LIKE :query OR c.alternative_titles LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if ($status !== null && in_array($status, ['ongoing', 'completed', 'hiatus', 'dropped'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }
        if ($type !== null && in_array($type, ['manga', 'manhua', 'manhwa', 'webtoon', 'novel', 'light-novel', 'web-novel'], true)) {
            $where[] = 'REPLACE(c.type, "_", "-") = :type';
            $params['type'] = $type;
        }
        if ($lifecycle !== null && in_array($lifecycle, ['draft', 'scheduled', 'published', 'archived'], true)) {
            $where[] = 'c.lifecycle_status = :lifecycle';
            $params['lifecycle'] = $lifecycle;
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = match ($sort) {
            'oldest' => 'c.created_at ASC',
            'title' => 'c.title ASC',
            'updated' => 'c.updated_at DESC',
            default => 'c.created_at DESC',
        };
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM series c WHERE ' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT
                c.id,
                c.title,
                c.slug,
                c.alternative_titles,
                c.type,
                c.status,
                c.lifecycle_status,
                c.scheduled_at,
                c.published_at,
                c.archived_at,
                c.is_adult,
                c.is_members_only,
                c.cover_image,
                c.description,
                c.chapter_count,
                c.comment_count,
                c.rating_avg,
                c.rating_count,
                c.created_at,
                c.author,
                c.artist,
                c.country,
                c.release_year,
                (SELECT GROUP_CONCAT(stm.taxonomy_id) FROM series_taxonomy_map stm INNER JOIN taxonomies t ON t.id = stm.taxonomy_id WHERE stm.content_id = c.id AND t.type = "genre") as genre_ids,
                (SELECT GROUP_CONCAT(stm.taxonomy_id) FROM series_taxonomy_map stm INNER JOIN taxonomies t ON t.id = stm.taxonomy_id WHERE stm.content_id = c.id AND t.type = "tag") as tag_ids
             FROM series c
             WHERE ' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Comprehensive user listing with roles, ban status, and activity counts.
     *
     * @return array ['items' => [...], 'total' => int]
     */
    public function listUsers(int $page, int $perPage, string $query = '', ?string $accountStatus = null, ?string $role = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(u.username LIKE :query OR u.email LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if (in_array($accountStatus, ['active', 'banned'], true)) {
            $exists = 'EXISTS (SELECT 1 FROM admin_actions ban_filter WHERE ban_filter.target_type = "user" AND ban_filter.action = "ban" AND (ban_filter.target_id = u.id OR ban_filter.target_id = u.username))';
            $where[] = $accountStatus === 'banned' ? $exists : 'NOT ' . $exists;
        }
        $roleId = (string) ((\App\Config::getSettings()['rbac']['id_map'][$role] ?? ''));
        if ($roleId !== '') {
            $where[] = 'FIND_IN_SET(:role_id, IFNULL(u.roles, "")) > 0';
            $params['role_id'] = $roleId;
        }
        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $orderBy = match ($sort) {
            'oldest' => 'u.created_at ASC',
            'username' => 'u.username ASC',
            default => 'u.created_at DESC',
        };
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM users u' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.bio,
                u.roles,
                u.created_at,
                EXISTS(
                    SELECT 1
                    FROM admin_actions ma
                    WHERE ma.target_type = "user"
                      AND ma.action = "ban"
                      AND (ma.target_id = u.id OR ma.target_id = u.username)
                ) AS is_banned,
                (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comment_count,
                (SELECT COUNT(*) FROM blogs b WHERE b.user_id = u.id) AS blog_count,
                (SELECT COUNT(*) FROM user_series_follows f WHERE f.user_id = u.id) AS follow_count,
                (SELECT COALESCE(SUM(duration_seconds), 0) FROM user_activity ua WHERE ua.user_id = u.id) AS total_seconds
             FROM users u' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        // Map role IDs to slugs using static config
        $config = \App\Config::getSettings()['rbac'] ?? [];
        $idToSlug = array_flip((array) ($config['id_map'] ?? []));

        foreach ($items as &$item) {
            $ids = explode(',', (string)($item['roles'] ?? ''));
            $slugs = [];
            foreach ($ids as $id) {
                if (isset($idToSlug[$id])) {
                    $slugs[] = $idToSlug[$id];
                }
            }
            $item['role_names'] = implode(', ', $slugs);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function listAllUsersForSelect(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, username, email
             FROM users
             ORDER BY username ASC, created_at DESC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Atomically updates user details, roles, and moderation (ban) status.
     */
    public function updateUser(string $id, string $role, bool $isBanned, string $moderatorId, ?string $email = null, ?string $bio = null): void
    {
        $this->pdo->beginTransaction();
        try {
            if ($email !== null || $bio !== null) {
                $parts = [];
                $params = ['id' => $id];
                if ($email !== null) {
                    $parts[] = 'email = :email';
                    $params['email'] = $email;
                }
                if ($bio !== null) {
                    $parts[] = 'bio = :bio';
                    $params['bio'] = $bio === '' ? null : $bio;
                }

                $sql = 'UPDATE users SET ' . implode(', ', $parts) . ' WHERE id = :id';
                $this->pdo->prepare($sql)->execute($params);
            }

            // Role update
            if ($role !== '') {
                $stmt = $this->pdo->prepare('SELECT id, roles FROM users WHERE id = :user_id LIMIT 1');
                $stmt->execute(['user_id' => $id]);
                $currentUser = $stmt->fetch();
                $oldRoles = (string)($currentUser['roles'] ?? '');

                $config = \App\Config::getSettings()['rbac'] ?? [];
                $idMap = (array) ($config['id_map'] ?? []);
                $roleId = (string) ($idMap[$role] ?? '');

                if ($roleId !== '' && $roleId !== $oldRoles) {
                    $this->pdo->prepare('UPDATE users SET roles = :role_id WHERE id = :user_id')
                         ->execute(['role_id' => $roleId, 'user_id' => $id]);

                    $audit = $this->pdo->prepare(
                        'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                         VALUES (:mod, "user", :uid, "role_change", :reason, NOW())'
                    );
                    $audit->execute([
                        'mod' => $moderatorId,
                        'uid' => $id,
                        'reason' => json_encode(['diff' => ['roles' => ['before' => $oldRoles, 'after' => $roleId]]])
                    ]);
                }
            }

            // Ban status
            $stmt = $this->pdo->prepare('SELECT 1 FROM admin_actions WHERE target_type = "user" AND action = "ban" AND target_id = :target_id');
            $stmt->execute(['target_id' => $id]);
            $currentlyBanned = $stmt->fetchColumn() !== false;

            if ($isBanned && !$currentlyBanned) {
                $this->pdo->prepare(
                    'INSERT INTO admin_actions
                        (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES
                        (:mod, "user", :target_id, "ban", "Banned by admin", NOW())'
                )->execute(['mod' => $moderatorId, 'target_id' => $id]);
            } elseif (!$isBanned && $currentlyBanned) {
                $this->pdo->prepare(
                    'DELETE FROM admin_actions
                     WHERE target_type = "user" AND action = "ban" AND target_id = :target_id'
                )->execute(['target_id' => $id]);

                $this->pdo->prepare(
                    'INSERT INTO admin_actions
                        (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES
                        (:mod, "user", :target_id, "unban", "Unbanned by admin", NOW())'
                )->execute(['mod' => $moderatorId, 'target_id' => $id]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Checks if an email is not taken by another user.
     */
    public function isEmailAvailableForUser(string $userId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM users
             WHERE email = :email
               AND id <> :id
             LIMIT 1'
        );
        $stmt->execute([
            'email' => $email,
            'id' => $userId,
        ]);

        return $stmt->fetchColumn() === false;
    }

    /**
     * Lists jobs currently in the Job Queue.
     */
    public function listQueueJobs(int $page, int $perPage, ?string $status = null, string $query = ''): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'processing', 'done', 'failed', 'cancelled'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($query !== '') {
            $where[] = '(job_type LIKE :query OR last_error LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM system_jobs' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                job_type,
                status,
                attempts,
                last_error,
                available_at,
                created_at,
                updated_at
             FROM system_jobs' . $whereSql . '
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function retryQueueJob(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE system_jobs SET status = "pending", attempts = 0, last_error = NULL, available_at = NOW(), updated_at = NOW() WHERE id = :id AND status IN ("failed", "cancelled")');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function cancelQueueJob(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE system_jobs SET status = "cancelled", updated_at = NOW() WHERE id = :id AND status = "pending"');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function systemHealthSnapshot(): array
    {
        $queue = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'cancelled' => 0];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS total FROM system_jobs GROUP BY status')->fetchAll() as $row) {
            $queue[(string)$row['status']] = (int)$row['total'];
        }
        $latestMigration = $this->pdo->query('SELECT version, applied_at FROM schema_migrations ORDER BY applied_at DESC, version DESC LIMIT 1')->fetch() ?: null;
        return [
            'database' => ['ok' => $this->pdo->query('SELECT 1')->fetchColumn() !== false, 'version' => (string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION)],
            'queue' => $queue,
            'latest_migration' => $latestMigration,
        ];
    }

    /**
     * Retrieves HTTP audit logs for monitoring system activity.
     */
    public function listAuditLogs(int $page, int $perPage, string $query = '', ?string $method = null, ?string $statusGroup = null, ?string $userId = null, ?string $dateFrom = null, ?string $dateTo = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($query !== '') { $where[] = '(al.path LIKE :query OR al.user_agent LIKE :query OR u.username LIKE :query)'; $params['query'] = '%' . $query . '%'; }
        if ($method !== null && in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) { $where[] = 'al.method = :method'; $params['method'] = $method; }
        if ($statusGroup === '2xx') $where[] = 'al.status_code BETWEEN 200 AND 299';
        elseif ($statusGroup === '4xx') $where[] = 'al.status_code BETWEEN 400 AND 499';
        elseif ($statusGroup === '5xx') $where[] = 'al.status_code >= 500';
        if ($userId !== null && $userId !== '') { $where[] = 'al.user_id = :user_id'; $params['user_id'] = $userId; }
        if ($dateFrom !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'al.created_at >= :date_from'; $params['date_from'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'al.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to'] = $dateTo . ' 00:00:00'; }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM system_audit_logs al LEFT JOIN users u ON u.id = al.user_id' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $orderSql = $sort === 'oldest' ? 'al.id ASC' : ($sort === 'slowest' ? 'al.duration_ms DESC, al.id DESC' : 'al.id DESC');

        $stmt = $this->pdo->prepare(
            'SELECT
                al.id,
                al.user_id,
                u.username,
                al.method,
                al.path,
                al.status_code,
                al.ip_hash,
                al.user_agent,
                al.duration_ms,
                al.created_at
             FROM system_audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ' . $whereSql . '
             ORDER BY ' . $orderSql . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Lists individual login attempts (success/fail).
     */
    public function listLoginEvents(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->count('SELECT COUNT(*) FROM user_login_logs');

        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                user_id,
                email,
                ip_hash,
                user_agent,
                success,
                failure_reason,
                attempted_at
             FROM user_login_logs
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Lists all comments with their context (Blog title or Series title).
     */
    public function listComments(int $page, int $perPage, string $query = '', ?string $targetType = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($query !== '') {
            $where[] = '(c.body LIKE :query OR u.username LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if ($targetType !== null && in_array($targetType, ['series', 'chapter', 'blog'], true)) {
            $where[] = 'c.target_type = :target_type';
            $params['target_type'] = $targetType;
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = $sort === 'oldest' ? 'c.created_at ASC' : 'c.created_at DESC';
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM comments c INNER JOIN users u ON u.id = c.user_id WHERE ' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT
                c.id,
                c.user_id,
                u.username,
                c.body,
                c.created_at,
                c.upvote_count,
                c.downvote_count,
                c.target_type,
                c.target_id,
                (CASE 
                    WHEN c.target_type = "series" THEN s.title
                    WHEN c.target_type = "chapter" THEN s2.title
                    ELSE NULL 
                 END) AS content_title,
                (CASE WHEN c.target_type = "blog" THEN b.title ELSE NULL END) AS blog_title,
                ch.chapter_number
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN chapters ch ON (c.target_type = "chapter" AND ch.id = c.target_id)
             LEFT JOIN series s ON (c.target_type = "series" AND s.id = c.target_id)
             LEFT JOIN series s2 ON (c.target_type = "chapter" AND s2.id = ch.content_id)
             LEFT JOIN blogs b ON (c.target_type = "blog" AND b.id = c.target_id)
             WHERE ' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Permanently deletes a comment and logs the action with full context.
     */
    public function deleteComment(int $id, string $moderatorId): bool
    {
        // 1. Fetch data before deletion for auditing
        $stmt = $this->pdo->prepare(
            'SELECT c.user_id, c.body, c.target_type, c.target_id, u.username as author_name
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $comment = $stmt->fetch();
        if (!$comment) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM comments WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $success = $stmt->rowCount() > 0;

            if ($success) {
                // Clean up any votes on this comment
                $delVotes = $this->pdo->prepare('DELETE FROM votes WHERE target_type = "comment" AND target_id = :id');
                $delVotes->execute(['id' => (string) $id]);

                $context = [
                    'author_id' => $comment['user_id'],
                    'author_name' => $comment['author_name'],
                    'body' => $comment['body'],
                    'location' => [
                        'target_type' => $comment['target_type'],
                        'target_id' => $comment['target_id'],
                    ]
                ];

                $audit = $this->pdo->prepare(
                    'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES (:mod, "comment", :cid, "delete", :reason, NOW())'
                );
                $audit->execute([
                    'mod' => $moderatorId,
                    'cid' => (string)$id,
                    'reason' => json_encode($context, JSON_UNESCAPED_UNICODE)
                ]);
            }

            $this->pdo->commit();
            return $success;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lists moderator actions (bans, warnings, etc.).
     */
    public function listModerationActions(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->count('SELECT COUNT(*) FROM admin_actions');

        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                moderator_user_id,
                target_type,
                target_id,
                action,
                reason,
                created_at
             FROM admin_actions
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Records a new moderation action.
     */
    public function createModerationAction(
        ?string $moderatorUserId,
        string $targetType,
        string $targetId,
        string $action,
        ?string $reason
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_actions
                (moderator_user_id, target_type, target_id, action, reason, created_at)
             VALUES
                (:moderator_user_id, :target_type, :target_id, :action, :reason, NOW())'
        );
        $stmt->execute([
            'moderator_user_id' => $moderatorUserId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lists all system uploads with pagination.
     */
    public function listUploads(int $page, int $perPage, string $query = '', ?string $mime = null, bool $orphansOnly = false): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $referenceSql = '(EXISTS (SELECT 1 FROM series s WHERE s.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM blogs b WHERE b.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM users profile WHERE profile.profile_image = u.file_path OR profile.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM chapters ch WHERE CAST(ch.data AS CHAR) LIKE CONCAT("%", u.file_path, "%")))';
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(u.original_name LIKE :query OR u.image_id LIKE :query OR u.file_path LIKE :query OR us.username LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if ($mime !== null && $mime !== '') {
            $where[] = 'u.mime_type = :mime';
            $params['mime'] = $mime;
        }
        if ($orphansOnly) $where[] = 'NOT ' . $referenceSql;
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            'SELECT u.*, us.username, ' . $referenceSql . ' AS is_referenced
             FROM system_uploads u
             LEFT JOIN users us ON u.user_id = us.id
             ' . $whereSql . '
             ORDER BY u.created_at DESC 
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM system_uploads u LEFT JOIN users us ON u.user_id = us.id' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    public function uploadStats(): array
    {
        $row = $this->pdo->query('SELECT COUNT(*) AS total_files, COALESCE(SUM(file_size), 0) AS total_bytes, COALESCE(AVG(file_size), 0) AS average_bytes, SUM(mime_type = "image/jpeg") AS jpeg_files, SUM(mime_type = "image/png") AS png_files, SUM(mime_type = "image/webp") AS webp_files, SUM(mime_type = "image/gif") AS gif_files FROM system_uploads')->fetch();
        return $row ?: [];
    }

    public function uploadById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM system_uploads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateUploadFileSize(int $id, int $size): void
    {
        $stmt = $this->pdo->prepare('UPDATE system_uploads SET file_size = :size WHERE id = :id');
        $stmt->execute(['id' => $id, 'size' => $size]);
    }

    /**
     * Deletes a specific upload record and returns the image_id.
     */
    public function deleteUpload(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT image_id, file_path FROM system_uploads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $stmt = $this->pdo->prepare('DELETE FROM system_uploads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return [
            'image_id' => (string) $row['image_id'],
            'file_path' => (string) ($row['file_path'] ?? '')
        ];
    }

    /**
     * Creates a new genre.
     */
    public function createGenre(string $name, string $slug): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO taxonomies (type, name, slug, sort_order) SELECT "genre", :name, :slug, COALESCE(MAX(sort_order), -1) + 1 FROM taxonomies WHERE type = "genre"');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        $id = (int)$this->pdo->lastInsertId();

        return ['id' => $id, 'type' => 'genre', 'name' => $name, 'slug' => $slug, 'sort_order' => $this->taxonomySortOrder($id)];
    }

    /**
     * Creates a new tag.
     */
    public function createTag(string $name, string $slug): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO taxonomies (type, name, slug, sort_order) SELECT "tag", :name, :slug, COALESCE(MAX(sort_order), -1) + 1 FROM taxonomies WHERE type = "tag"');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        $id = (int)$this->pdo->lastInsertId();

        return ['id' => $id, 'type' => 'tag', 'name' => $name, 'slug' => $slug, 'sort_order' => $this->taxonomySortOrder($id)];
    }

    public function taxonomyById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT t.id, t.type, t.name, t.slug, t.ui_config, t.sort_order, COUNT(stm.content_id) AS usage_count FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.id = :id GROUP BY t.id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateTaxonomy(int $id, string $name, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE taxonomies SET name = :name, slug = :slug WHERE id = :id');
        $stmt->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
        return $this->taxonomyById($id);
    }

    public function deleteTaxonomy(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE t FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.id = :id AND stm.taxonomy_id IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function mergeTaxonomies(int $sourceId, int $targetId): array
    {
        $this->pdo->beginTransaction();
        try {
            $source = $this->taxonomyById($sourceId);
            $target = $this->taxonomyById($targetId);
            if (!$source || !$target || $source['type'] !== $target['type']) {
                throw new \InvalidArgumentException('Taxonomies must exist and have the same type');
            }
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO series_taxonomy_map (content_id, taxonomy_id) SELECT content_id, :target_id FROM series_taxonomy_map WHERE taxonomy_id = :source_id');
            $stmt->execute(['target_id' => $targetId, 'source_id' => $sourceId]);
            $this->pdo->prepare('DELETE FROM series_taxonomy_map WHERE taxonomy_id = :id')->execute(['id' => $sourceId]);
            $this->pdo->prepare('DELETE FROM taxonomies WHERE id = :id')->execute(['id' => $sourceId]);
            $this->pdo->commit();
            return $this->taxonomyById($targetId) ?? $target;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reorderTaxonomies(array $items): void
    {
        $stmt = $this->pdo->prepare('UPDATE taxonomies SET sort_order = :sort_order WHERE id = :id');
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $stmt->execute(['id' => (int)$item['id'], 'sort_order' => (int)$item['sort_order']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function taxonomySortOrder(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT sort_order FROM taxonomies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Updates genres and tags for a content item.
     */
    public function updateContentTaxonomy(string $contentId, array $genreIds, array $tagIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM series_taxonomy_map WHERE content_id = :id AND taxonomy_id IN (SELECT id FROM taxonomies WHERE type IN ("genre", "tag"))')->execute(['id' => $contentId]);
            $allTaxIds = array_filter(array_merge($genreIds, $tagIds));
            if (!empty($allTaxIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO series_taxonomy_map (content_id, taxonomy_id) VALUES (:id, :tid) ON DUPLICATE KEY UPDATE content_id = VALUES(content_id)');
                foreach ($allTaxIds as $tid) {
                    if ($tid) $stmt->execute(['id' => $contentId, 'tid' => (int) $tid]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lists all genres without pagination for administrative use.
     */
    public function listAllGenres(): array
    {
        $stmt = $this->pdo->query('SELECT t.id, t.type, t.name, t.slug, t.ui_config, t.sort_order, COUNT(stm.content_id) AS usage_count FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.type = "genre" GROUP BY t.id ORDER BY t.sort_order ASC, t.name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Lists all tags without pagination for administrative use.
     */
    public function listAllTags(): array
    {
        $stmt = $this->pdo->query('SELECT t.id, t.type, t.name, t.slug, t.ui_config, t.sort_order, COUNT(stm.content_id) AS usage_count FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.type = "tag" GROUP BY t.id ORDER BY t.sort_order ASC, t.name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Fetches all roles and their mapped permission codes.
     */
    public function listRolesWithPermissions(): array
    {
        $config = \App\Config::getSettings()['rbac'] ?? [];
        $idMap = (array) ($config['id_map'] ?? []);
        $overrides = $this->rolePermissionOverrides();
        $result = [];

        foreach ($config['roles'] ?? [] as $slug => $role) {
            $perms = (array) ($role['permissions'] ?? []);
            foreach ($overrides[$slug] ?? [] as $permission => $effect) {
                if ($effect === 'grant' && !in_array($permission, $perms, true)) {
                    $perms[] = $permission;
                } elseif ($effect === 'revoke') {
                    $perms = array_values(array_filter($perms, static fn(string $value): bool => $value !== $permission));
                }
            }
            sort($perms);
            $result[] = [
                'id' => (int) ($idMap[$slug] ?? 0),
                'slug' => $slug,
                'name' => (string) ($role['name'] ?? ucfirst($slug)),
                'description' => (string) ($role['description'] ?? ''),
                'permission_count' => count($perms),
                'permissions' => implode(',', $perms)
            ];
        }

        return $result;
    }

    /**
     * Lists users and their current role slugs.
     */
    public function listUserRoleAssignments(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $rolesData = $this->listRolesWithPermissions();
        $idToSlug = [];
        foreach ($rolesData as $rd) {
            $idToSlug[(string)$rd['id']] = $rd['slug'];
        }

        try {
            $total = $this->count('SELECT COUNT(*) FROM users');
            $stmt = $this->pdo->prepare(
                'SELECT
                    u.id AS user_id,
                    u.username,
                    u.roles,
                    u.created_at AS assigned_at
                 FROM users u
                 WHERE u.roles IS NOT NULL AND u.roles != ""
                 ORDER BY u.created_at DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();

            foreach ($items as &$item) {
                $ids = explode(',', (string)$item['roles']);
                $slugs = [];
                foreach ($ids as $id) {
                    if (isset($idToSlug[$id])) {
                        $slugs[] = $idToSlug[$id];
                    }
                }
                $item['roles'] = implode(',', $slugs);
            }

            return [
                'items' => $items,
                'total' => $total,
            ];
        } catch (\Throwable) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }
    }

    public function roleExistsBySlug(string $roleSlug): bool
    {
        $config = \App\Config::getSettings()['rbac'] ?? [];
        return isset($config['roles'][$roleSlug]);
    }

    public function permissionExistsByCode(string $permissionCode): bool
    {
        foreach ($this->getAllSystemPermissions() as $permissions) {
            if (array_key_exists($permissionCode, $permissions)) {
                return true;
            }
        }
        return false;
    }

    public function roleHasPermission(string $roleSlug, string $permissionCode): bool
    {
        foreach ($this->listRolesWithPermissions() as $role) {
            if (($role['slug'] ?? '') !== $roleSlug) continue;
            return in_array($permissionCode, explode(',', (string) ($role['permissions'] ?? '')), true);
        }
        return false;
    }

    public function assignPermissionToRole(string $roleSlug, string $permissionCode, string $moderatorId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rbac_role_permission_overrides (role_slug, permission_code, effect, updated_by)
             VALUES (:role, :permission, "grant", :updated_by)
             ON DUPLICATE KEY UPDATE effect = "grant", updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute(['role' => $roleSlug, 'permission' => $permissionCode, 'updated_by' => $moderatorId]);
        $this->createModerationAction($moderatorId, 'role', $roleSlug, 'grant_permission', $permissionCode);
    }

    public function revokePermissionFromRole(string $roleSlug, string $permissionCode, string $moderatorId): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rbac_role_permission_overrides (role_slug, permission_code, effect, updated_by)
             VALUES (:role, :permission, "revoke", :updated_by)
             ON DUPLICATE KEY UPDATE effect = "revoke", updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $result = $stmt->execute(['role' => $roleSlug, 'permission' => $permissionCode, 'updated_by' => $moderatorId]);
        if ($result) {
            $this->createModerationAction($moderatorId, 'role', $roleSlug, 'revoke_permission', $permissionCode);
        }
        return $result;
    }

    /** @return array<string, array<string, string>> */
    private function rolePermissionOverrides(): array
    {
        try {
            $rows = $this->pdo->query(
                'SELECT role_slug, permission_code, effect FROM rbac_role_permission_overrides'
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['role_slug']][(string) $row['permission_code']] = (string) $row['effect'];
        }
        return $result;
    }

    public function assignRoleToUser(string $userId, string $roleSlug): bool
    {
        $config = \App\Config::getSettings()['rbac'] ?? [];
        $idMap = (array) ($config['id_map'] ?? []);
        $roleId = (string) ($idMap[$roleSlug] ?? '');

        if ($roleId !== '') {
            $stmt = $this->pdo->prepare('
                UPDATE users 
                SET roles = IF(roles IS NULL OR roles = "", :role_id, CONCAT(roles, ",", :role_id2)) 
                WHERE id = :user_id AND NOT FIND_IN_SET(:role_id3, IFNULL(roles, "")) > 0
            ');
            $stmt->execute([
                'role_id' => $roleId,
                'role_id2' => $roleId,
                'role_id3' => $roleId,
                'user_id' => $userId,
            ]);
            return $stmt->rowCount() > 0;
        }

        return false;
    }

    public function listBlogs(int $page, int $perPage, string $query = '', ?string $status = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($status === 'deleted') {
            $where[] = 'b.deleted_at IS NOT NULL';
        } else {
            $where[] = 'b.deleted_at IS NULL';
            if ($status !== null && in_array($status, ['draft', 'pending', 'published', 'rejected', 'hidden'], true)) {
                $where[] = 'b.status = :status';
                $params['status'] = $status;
            }
        }
        if ($query !== '') {
            $where[] = '(b.title LIKE :query OR b.slug LIKE :query OR u.username LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = $sort === 'oldest' ? 'b.created_at ASC' : 'b.created_at DESC';
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM blogs b INNER JOIN users u ON u.id = b.user_id WHERE ' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT
                b.id,
                b.user_id,
                u.username,
                b.title,
                b.slug,
                b.status,
                b.approved,
                b.created_at,
                b.approved_at
             FROM blogs b
             INNER JOIN users u ON u.id = b.user_id
             WHERE ' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function revokeUserSession(string $userId, string $sessionKey, string $moderatorId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id = :user_id AND session_key = :session_key');
        $stmt->execute(['user_id' => $userId, 'session_key' => $sessionKey]);

        $this->createModerationAction($moderatorId, 'user', $userId, 'revoke_session', 'Session forcefully revoked by admin');
    }

    public function userExists(string $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    public function hideBlog(string $id, string $moderatorId): void
    {
        $stmt = $this->pdo->prepare('UPDATE blogs SET approved = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $this->createModerationAction($moderatorId, 'blog', $id, 'hide', 'Blog hidden by moderator');
    }

    public function deleteBlog(string $id, string $moderatorId): void
    {
        // Check if soft delete column exists
        if ($this->blogsHasDeletedAt()) {
            $stmt = $this->pdo->prepare('UPDATE blogs SET deleted_at = NOW(), approved = 0 WHERE id = :id');
        } else {
            $stmt = $this->pdo->prepare('DELETE FROM blogs WHERE id = :id');
        }
        $stmt->execute(['id' => $id]);

        $this->createModerationAction($moderatorId, 'blog', $id, 'delete', 'Blog permanently deleted or soft-deleted by moderator');
    }

    /**
     * Fetches top-performing content, chapters, series_genres, and series_tags from pre-aggregated snapshots.
     *
     * @param int $days Period to look back (used for context, results usually from CURRENT_DATE snapshot).
     * @param int $limit Max items per list.
     * @return array Categorized lists of top performers.
     */
    public function topViewedStats(int $days, int $limit): array
    {
        $days = max(1, min(90, $days));
        $limit = max(1, min(30, $limit));
        $series_tags = $this->queryList(
            'SELECT
                t.slug,
                t.name,
                COALESCE(SUM(s.view_count), 0) AS view_total
             FROM analytics_snapshots_series_top s
             INNER JOIN series_taxonomy_map ct ON ct.content_id = s.content_id
             INNER JOIN taxonomies t ON t.id = ct.taxonomy_id AND t.type = "tag"
             WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)
             GROUP BY t.id, t.slug, t.name
             ORDER BY view_total DESC, t.name ASC
             LIMIT :limit',
            ['limit' => $limit, 'days' => $days]
        );

        $series_genres = $this->queryList(
            'SELECT
                g.slug,
                g.name,
                COALESCE(SUM(s.view_count), 0) AS view_total
             FROM analytics_snapshots_series_top s
             INNER JOIN series_taxonomy_map cg ON cg.content_id = s.content_id
             INNER JOIN taxonomies g ON g.id = cg.taxonomy_id AND g.type = "genre"
             WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)
             GROUP BY g.id, g.slug, g.name
             ORDER BY view_total DESC, g.name ASC
             LIMIT :limit',
            ['limit' => $limit, 'days' => $days]
        );

        $types = $this->queryList(
            'SELECT c.type, COALESCE(SUM(s.view_count), 0) AS view_total
             FROM analytics_snapshots_series_top s
             INNER JOIN series c ON c.id = s.content_id
             WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)
             GROUP BY c.type
             ORDER BY view_total DESC, c.type ASC
             LIMIT :limit',
            ['limit' => $limit, 'days' => $days]
        );

        $series = $this->queryList(
            'SELECT
                c.id,
                c.title,
                c.slug,
                c.type,
                SUM(s.view_count) AS view_total
             FROM analytics_snapshots_series_top s
             INNER JOIN series c ON c.id = s.content_id
             WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)
             GROUP BY c.id, c.title, c.slug, c.type
             ORDER BY view_total DESC, c.title ASC
             LIMIT :limit',
            ['limit' => $limit, 'days' => $days]
        );

        $chapters = $this->queryList(
            'SELECT
                ch.id,
                ch.chapter_number,
                ch.title,
                c.slug AS content_slug,
                c.title AS content_title,
                c.type AS content_type,
                SUM(s.view_count) AS view_total
             FROM analytics_snapshots_chapters_top s
             INNER JOIN chapters ch ON ch.id = s.chapter_id
             INNER JOIN series c ON c.id = ch.content_id
             WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)
             GROUP BY ch.id, ch.chapter_number, ch.title, c.slug, c.title, c.type
             ORDER BY view_total DESC, ch.chapter_number ASC
             LIMIT :limit',
            ['limit' => $limit, 'days' => $days]
        );

        return [
            'period_days' => $days,
            'series_tags' => $series_tags,
            'series_genres' => $series_genres,
            'types' => $types,
            'series' => $series,
            'chapters' => $chapters,
        ];
    }

    /**
     * Aggregates blog platform statistics including creation/approval trends and top authors.
     */
    public function blogStats(int $days, int $limit): array
    {
        $days = max(1, min(90, $days));
        $limit = max(1, min(30, $limit));
        $notDeleted = $this->blogsNotDeletedCondition('b');
        $deleted = $this->blogsDeletedCondition('b');

        $summary = $this->queryOne(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN b.approved = 1 AND ' . $notDeleted . ' THEN 1 ELSE 0 END) AS visible_total,
                SUM(CASE WHEN b.approved = 0 AND ' . $notDeleted . ' THEN 1 ELSE 0 END) AS hidden_total,
                SUM(CASE WHEN ' . $deleted . ' THEN 1 ELSE 0 END) AS deleted_total,
                SUM(CASE WHEN b.created_at >= DATE_SUB(NOW(), INTERVAL :days_created DAY) THEN 1 ELSE 0 END) AS created_last_days,
                SUM(CASE WHEN b.approved = 1 AND b.approved_at IS NOT NULL AND b.approved_at >= DATE_SUB(NOW(), INTERVAL :days_approved DAY) THEN 1 ELSE 0 END) AS approved_last_days
             FROM blogs b',
            [
                'days_created' => $days,
                'days_approved' => $days,
            ]
        );

        $topAuthors = $this->queryByDaysAndLimit(
            'SELECT
                u.id AS user_id,
                u.username,
                COUNT(*) AS blog_total,
                SUM(CASE WHEN b.approved = 1 THEN 1 ELSE 0 END) AS approved_total
             FROM blogs b
             INNER JOIN users u ON u.id = b.user_id
             WHERE ' . $notDeleted . '
             GROUP BY u.id, u.username
             ORDER BY blog_total DESC, u.username ASC
             LIMIT :limit',
            ['days' => $days, 'limit' => $limit]
        );

        $dailyCreated = $this->queryByDaysAndLimit(
            'SELECT
                DATE(b.created_at) AS day,
                COUNT(*) AS total
             FROM blogs b
             WHERE b.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(b.created_at)
             ORDER BY day ASC
             LIMIT :limit',
            ['days' => $days, 'limit' => 400]
        );

        $dailyApproved = $this->queryByDaysAndLimit(
            'SELECT
                DATE(b.approved_at) AS day,
                COUNT(*) AS total
             FROM blogs b
             WHERE b.approved = 1
               AND b.approved_at IS NOT NULL
               AND b.approved_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(b.approved_at)
             ORDER BY day ASC
             LIMIT :limit',
            ['days' => $days, 'limit' => 400]
        );

        return [
            'period_days' => $days,
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'visible_total' => (int) ($summary['visible_total'] ?? 0),
                'hidden_total' => (int) ($summary['hidden_total'] ?? 0),
                'deleted_total' => (int) ($summary['deleted_total'] ?? 0),
                'created_last_days' => (int) ($summary['created_last_days'] ?? 0),
                'approved_last_days' => (int) ($summary['approved_last_days'] ?? 0),
            ],
            'top_authors' => $topAuthors,
            'daily_created' => $dailyCreated,
            'daily_approved' => $dailyApproved,
        ];
    }

    /**
     * Fetches raw visit/view counts for different time periods.
     */
    public function siteVisits(): array
    {
        $daily = $this->visitCount(1);
        $weekly = $this->visitCount(7);
        $monthly = $this->visitCount(30);

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }

    private function visitCount(int $days): int
    {
        $days = max(1, min(365, $days));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(metric_value), 0) AS total
                 FROM analytics_snapshots_daily
                 WHERE metric_name = :metric_name
                   AND stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)'
            );
            $stmt->bindValue(':metric_name', 'total_views', PDO::PARAM_STR);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            return (int) ($stmt->fetchColumn() ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Calculates user reputation leaderboard based on comments and voting activity.
     */
    public function userReputation(int $limit): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    u.id,
                    u.username,
                    COALESCE(c.comment_count, 0) AS comment_count,
                    COALESCE(vg.votes_given, 0) AS votes_given,
                    COALESCE(vr.up_votes, 0) AS up_votes,
                    COALESCE(vr.down_votes, 0) AS down_votes,
                    COALESCE(ua.total_seconds, 0) AS total_seconds,
                    (COALESCE(c.comment_count, 0) * 2)
                      + COALESCE(vr.up_votes, 0)
                      - COALESCE(vr.down_votes, 0)
                      + (COALESCE(vg.votes_given, 0) * 0.5)
                      + (COALESCE(ua.total_seconds, 0) / 3600 * 10) AS score
                 FROM users u
                 LEFT JOIN (
                    SELECT user_id, SUM(duration_seconds) AS total_seconds
                    FROM user_activity
                    GROUP BY user_id
                 ) ua ON ua.user_id = u.id
                 LEFT JOIN (
                    SELECT user_id, COUNT(*) AS comment_count
                    FROM comments
                    GROUP BY user_id
                 ) c ON c.user_id = u.id
                 LEFT JOIN (
                    SELECT user_id, COUNT(*) AS votes_given
                    FROM votes
                    WHERE target_type = "comment"
                    GROUP BY user_id
                 ) vg ON vg.user_id = u.id
                 LEFT JOIN (
                    SELECT user_id,
                           COALESCE(SUM(upvote_count), 0) AS up_votes,
                           COALESCE(SUM(downvote_count), 0) AS down_votes
                    FROM comments
                    GROUP BY user_id
                 ) vr ON vr.user_id = u.id
                 ORDER BY score DESC, u.created_at DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function count(string $sql): int
    {
        try {
            $value = $this->pdo->query($sql)->fetchColumn();
            return (int) ($value !== false ? $value : 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function queryOne(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
            }
            $stmt->execute();
            $row = $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function queryByDaysAndLimit(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (str_contains($sql, ':days')) {
                $stmt->bindValue(':days', (int) ($params['days'] ?? 7), PDO::PARAM_INT);
            }
            if (str_contains($sql, ':limit')) {
                $stmt->bindValue(':limit', (int) ($params['limit'] ?? 10), PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function blogsNotDeletedCondition(string $alias): string
    {
        return $this->blogsHasDeletedAt() ? $alias . '.deleted_at IS NULL' : '1=1';
    }

    private function blogsDeletedCondition(string $alias): string
    {
        return $this->blogsHasDeletedAt() ? $alias . '.deleted_at IS NOT NULL' : '0=1';
    }

    private function blogsHasDeletedAt(): bool
    {
        if ($this->blogsHasDeletedAt !== null) {
            return $this->blogsHasDeletedAt;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM blogs LIKE 'deleted_at'");
            $this->blogsHasDeletedAt = $stmt !== false && (bool) $stmt->fetch();
        } catch (\Throwable) {
            $this->blogsHasDeletedAt = false;
        }

        return $this->blogsHasDeletedAt;
    }

    private function commentsHasBlogId(): bool
    {
        if ($this->commentsHasBlogId !== null) {
            return $this->commentsHasBlogId;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM comments LIKE 'blog_id'");
            $this->commentsHasBlogId = $stmt !== false && (bool) $stmt->fetch();
        } catch (\Throwable) {
            $this->commentsHasBlogId = false;
        }

        return $this->commentsHasBlogId;
    }

    public function listSeriesTeam(string $seriesId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.series_id, t.user_id, t.role, t.created_at, u.username, u.email, u.profile_image
             FROM series_team_assignments t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.series_id = :series_id
             ORDER BY t.created_at ASC'
        );
        $stmt->execute(['series_id' => $seriesId]);
        return $stmt->fetchAll();
    }

    public function assignTeamMember(string $seriesId, string $userId, string $role): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO series_team_assignments (series_id, user_id, role, created_at)
             VALUES (:sid, :uid, :role, NOW())
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        );
        $stmt->execute([
            'sid' => $seriesId,
            'uid' => $userId,
            'role' => $role,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        return [
            'id' => $id,
            'series_id' => $seriesId,
            'user_id' => $userId,
            'role' => $role,
        ];
    }

    public function removeTeamMember(int $assignmentId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM series_team_assignments WHERE id = :id');
        $stmt->execute(['id' => $assignmentId]);
        return $stmt->rowCount() > 0;
    }

    public function canUserManageSeries(string $userId, string $seriesId): bool
    {
        // Check if user is assigned to this series
        $stmt = $this->pdo->prepare('SELECT 1 FROM series_team_assignments WHERE series_id = :sid AND user_id = :uid LIMIT 1');
        $stmt->execute(['sid' => $seriesId, 'uid' => $userId]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        // Check if user is super admin / admin
        $uStmt = $this->pdo->prepare('SELECT roles FROM users WHERE id = :uid LIMIT 1');
        $uStmt->execute(['uid' => $userId]);
        $roles = (string) $uStmt->fetchColumn();
        return str_contains($roles, '1') || str_contains($roles, '2') || str_contains($roles, 'admin');
    }

    public function getAllSystemPermissions(): array
    {
        return [
            'Panel & Metrics' => [
                'admin.panel.access' => 'Yönetim paneline ve listeleme uçlarına erişme',
                'admin.metrics.view' => 'Dashboard ve analitik metriklerini görüntüleme',
            ],
            'Content & Chapters' => [
                'admin.content.create' => 'Yeni Seri/İçerik Ekleme',
                'admin.content.update' => 'İçerik Bilgilerini Düzenleme',
                'admin.chapter.create' => 'Bölüm Yükleme (Resim/Metin)',
            ],
            'Moderation' => [
                'admin.blog.hide' => 'Blog Onaylama, Gizleme ve Silme',
                'admin.comment.delete' => 'Yorum Silme / Moderasyon',
                'admin.logs.view' => 'Sistem, Güvenlik ve Moderasyon Loglarını İnceleme',
                'admin.reports.view' => 'Rapor ve şikâyetleri görüntüleme',
                'admin.reports.manage' => 'Rapor ve şikâyet durumlarını yönetme',
            ],
            'Users & RBAC' => [
                'admin.users.manage' => 'Kullanıcı Bilgisi, Rolü ve Yasak Durumunu Güncelleme',
                'admin.permissions.grant' => 'Role izin atama',
                'admin.permissions.revoke' => 'Rolden izin kaldırma',
                'admin.roles.assign' => 'Kullanıcıya rol atama',
            ],
            'Monetization' => [
                'admin.wallet.view' => 'Cüzdan ve işlemleri görüntüleme',
                'admin.wallet.manage' => 'Bakiye ve paket müdahalesi',
                'admin.shop.manage' => 'Mağaza paketleri ve fiyatlandırmayı yönetme',
                'admin.finance.refund' => 'Finansal iade işlemi yapma',
                'admin.finance.view' => 'Finans işlem defterini ve özetini görüntüleme',
            ],
            'Operations & Settings' => [
                'admin.jobs.run' => 'Önbellek, yedek, sitemap, kuyruk ve bakım işleri çalıştırma',
                'admin.settings.modify' => 'Site ayarları, webhook ve ortam yapılandırmasını değiştirme',
                'admin.health.view' => 'Sistem sağlık durumunu görüntüleme',
                'admin.uploads.view' => 'Medya kütüphanesini görüntüleme',
                'admin.uploads.delete' => 'Medya kayıtlarını ve dosyalarını silme',
                'admin.uploads.optimize' => 'Medya dosyalarını optimize etme',
            ],
        ];
    }

    private function queryList(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (str_contains($sql, ':days')) {
                $stmt->bindValue(':days', (int) ($params['days'] ?? 7), PDO::PARAM_INT);
            }
            if (str_contains($sql, ':limit')) {
                $stmt->bindValue(':limit', (int) ($params['limit'] ?? 10), PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }
}
