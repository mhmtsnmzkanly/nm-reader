<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminDashboardRepository extends AdminRepositoryBase
{
    public function summaryKpis(): array
    {
        $todayViews = $this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'total_views' ORDER BY stat_date DESC LIMIT 1");

        // Keep moderation counters together with the core KPIs so the panel
        // can render one compact summary without issuing separate requests.
        $blogNotDeleted = $this->blogsNotDeletedCondition('b');
        $blogDeleted = $this->blogsDeletedCondition('b');
        $blogSummary = $this->queryOne(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN b.status IN ("draft", "pending") AND ' . $blogNotDeleted . ' THEN 1 ELSE 0 END) AS pending_total,
                SUM(CASE WHEN b.status IN ("rejected", "hidden") OR ' . $blogDeleted . ' THEN 1 ELSE 0 END) AS closed_total
             FROM blogs b'
        );
        $reportSummary = $this->queryOne(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ("pending", "reviewing") THEN 1 ELSE 0 END) AS pending_total,
                SUM(CASE WHEN status IN ("resolved", "rejected") THEN 1 ELSE 0 END) AS closed_total
             FROM reports'
        );
        
        // Fetch Performance & Health. The dashboard should still render while
        // the first analytics rollup (or a fresh schema) is not available.
        try {
            $health = $this->pdo->query("SELECT * FROM analytics_snapshots_health ORDER BY stat_date DESC LIMIT 1")->fetch() ?: null;
        } catch (\Throwable) {
            $health = null;
        }
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
        $d1EligibleUsers = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'd1_eligible_users_total' ORDER BY stat_date DESC LIMIT 1");
        $d7Retained = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'd7_retained_total' ORDER BY stat_date DESC LIMIT 1");
        $d7EligibleUsers = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'd7_eligible_users_total' ORDER BY stat_date DESC LIMIT 1");
        $newUsers = (int)$this->queryValue("SELECT metric_value FROM analytics_snapshots_daily WHERE metric_name = 'new_users_7d_total' ORDER BY stat_date DESC LIMIT 1");
    
        // Fetch Top Contents 7d (most recent date available in snapshots)
        $latestDate = $this->queryValue("SELECT MAX(stat_date) FROM analytics_snapshots_series_top");
        $topContents = [];
        if ($latestDate) {
            $stmt = $this->pdo->prepare(
                "SELECT s.id, s.title, s.type, s.slug, SUM(t.view_count) as view_count_7d,
                        COALESCE((
                            SELECT COUNT(*)
                            FROM comments cm
                            LEFT JOIN chapters ccm ON cm.target_type = 'chapter' AND ccm.id = cm.target_id
                            WHERE cm.created_at >= DATE_SUB(:latest_comments, INTERVAL 6 DAY)
                              AND cm.deleted_at IS NULL
                              AND cm.moderation_status <> 'deleted'
                              AND (
                                  (cm.target_type = 'series' AND cm.target_id = t.content_id)
                                  OR (cm.target_type = 'chapter' AND ccm.content_id = t.content_id)
                              )
                        ), 0) AS comment_count_7d
                 FROM analytics_snapshots_series_top t
                 JOIN series s ON s.id = t.content_id
                 WHERE t.stat_date >= DATE_SUB(:latest, INTERVAL 6 DAY)
                 GROUP BY t.content_id
                 ORDER BY view_count_7d DESC
                 LIMIT 5"
            );
            $stmt->execute(['latest' => $latestDate, 'latest_comments' => $latestDate]);
            $topContents = $stmt->fetchAll();
        }
    
        return [
            'users_total' => $this->count('SELECT COUNT(*) FROM users'),
            'contents_total' => $this->count('SELECT COUNT(*) FROM series'),
            'chapters_total' => $this->count('SELECT COUNT(*) FROM chapters'),
            'comments_total' => $this->count('SELECT COUNT(*) FROM comments'),
            'today_content_views_total' => (int)($todayViews ?? 0),
            'blogs_total' => (int) ($blogSummary['total'] ?? 0),
            'blogs_pending_total' => (int) ($blogSummary['pending_total'] ?? 0),
            'blogs_closed_total' => (int) ($blogSummary['closed_total'] ?? 0),
            'queue_pending_total' => $this->count("SELECT COUNT(*) FROM system_jobs WHERE status = 'pending'"),
            'queue_failed_total' => $this->count("SELECT COUNT(*) FROM system_jobs WHERE status = 'failed'"),
            'reports_total' => (int) ($reportSummary['total'] ?? 0),
            'reports_pending_total' => (int) ($reportSummary['pending_total'] ?? 0),
            'reports_closed_total' => (int) ($reportSummary['closed_total'] ?? 0),
            'audit_24h_total' => $this->count('SELECT COUNT(*) FROM system_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'funnel' => [
                'home_to_content_pct' => $homeToContent,
                'content_to_chapter_pct' => $contentToChapter,
            ],
            'retention_search' => [
                'search_total_7d' => $searchTotal,
                'zero_result_pct_7d' => $searchTotal > 0 ? round(($zeroResults / $searchTotal) * 100, 1) : 0,
                'd1_retention_pct' => $d1EligibleUsers > 0 ? round(($d1Retained / $d1EligibleUsers) * 100, 1) : 0,
                'd1_eligible_users_7d' => $d1EligibleUsers,
                'd7_retention_pct' => $d7EligibleUsers > 0 ? round(($d7Retained / $d7EligibleUsers) * 100, 1) : 0,
                'd7_eligible_users_30d' => $d7EligibleUsers,
                'new_users_7d' => $newUsers
            ],
            'performance_slo' => [
                'server_error_rate_pct_24h' => $errorRate,
                'p95_duration_ms_24h' => (int)($health['p95_duration_ms_24h'] ?? 0),
            ],
            'top_contents_7d' => $topContents
        ];
    }

    /**
     * Counts failed jobs that have not been seen by the current moderator.
     * A view marker is written to admin_actions, so no schema change is
     * required and a new failure (failed_at after the marker) is visible.
     */
    public function unseenFailedQueueCount(?string $moderatorUserId = null): int
    {
        $fallback = $this->count("SELECT COUNT(*) FROM system_jobs WHERE status = 'failed'");
        $moderatorUserId = trim((string) $moderatorUserId);
        if ($moderatorUserId === '') {
            return $fallback;
        }

        try {
            $seen = $this->pdo->prepare(
                'SELECT MAX(created_at)
                 FROM admin_actions
                 WHERE moderator_user_id = :moderator_user_id
                   AND target_type = "system"
                   AND target_id = "queue"
                   AND action = "view_failures"'
            );
            $seen->execute(['moderator_user_id' => $moderatorUserId]);
            $seenAt = $seen->fetchColumn();
            if ($seenAt === false || $seenAt === null || (string) $seenAt === '') {
                return $fallback;
            }

            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM system_jobs
                 WHERE status = "failed"
                   AND COALESCE(failed_at, updated_at, created_at) > :seen_at'
            );
            $stmt->execute(['seen_at' => (string) $seenAt]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function topViewedStats(int $days, int $limit): array
    {
        $days = max(1, min(90, $days));
        $limit = max(1, min(30, $limit));
        // Snapshot rows are calendar-day buckets. Convert the requested
        // number of days to an inclusive date window (today counts as day 1).
        $windowDays = max(0, $days - 1);
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
            ['limit' => $limit, 'days' => $windowDays]
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
            ['limit' => $limit, 'days' => $windowDays]
        );
    
        $types = $this->queryList(
            'SELECT c.type, COALESCE(SUM(s.view_count), 0) AS view_total
             FROM analytics_snapshots_series_top s
             INNER JOIN series c ON c.id = s.content_id
             WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)
             GROUP BY c.type
             ORDER BY view_total DESC, c.type ASC
             LIMIT :limit',
            ['limit' => $limit, 'days' => $windowDays]
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
            ['limit' => $limit, 'days' => $windowDays]
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
            ['limit' => $limit, 'days' => $windowDays]
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
               AND b.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
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
            ['days' => max(0, $days - 1), 'limit' => 400]
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
            ['days' => max(0, $days - 1), 'limit' => 400]
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

    public function siteVisits(): array
    {
        $daily = $this->visitCount(1);
        $weekly = $this->visitCount(7);
        $monthly = $this->visitCount(30);
        $uniqueDaily = $this->uniqueVisitorCount(1);
        $uniqueWeekly = $this->uniqueVisitorCount(7);
        $uniqueMonthly = $this->uniqueVisitorCount(30);
        $trend = $this->siteVisitTrend(30);
    
        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
            'unique_daily' => $uniqueDaily,
            'unique_weekly' => $uniqueWeekly,
            'unique_monthly' => $uniqueMonthly,
            'trend' => $trend,
        ];
    }

    private function uniqueVisitorCount(int $days): int
    {
        $days = max(1, min(365, $days));
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT COALESCE(NULLIF(session_hash, ''), ip_hash))
                 FROM analytics_events
                 WHERE event_type IN ('content_view', 'chapter_view')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"
            );
            // This query is intentionally rolling (NOW), unlike the daily
            // snapshot counters which use inclusive calendar-day windows.
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            return (int) ($stmt->fetchColumn() ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function siteVisitTrend(int $days): array
    {
        $days = max(1, min(90, $days));
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DATE(created_at) AS day,
                        COUNT(*) AS views,
                        COUNT(DISTINCT COALESCE(NULLIF(session_hash, ''), ip_hash)) AS unique_visitors
                 FROM analytics_events
                 WHERE event_type IN ('content_view', 'chapter_view')
                   AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC"
            );
            $stmt->bindValue(':days', max(0, $days - 1), PDO::PARAM_INT);
            $stmt->execute();
            return array_map(static fn(array $row): array => [
                'day' => (string) ($row['day'] ?? ''),
                'views' => (int) ($row['views'] ?? 0),
                'unique_visitors' => (int) ($row['unique_visitors'] ?? 0),
            ], $stmt->fetchAll());
        } catch (\Throwable) {
            return [];
        }
    }

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
}
