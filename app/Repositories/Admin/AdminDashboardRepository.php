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
                 WHERE t.stat_date >= DATE_SUB(:latest, INTERVAL 6 DAY)
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
