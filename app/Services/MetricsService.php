<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class MetricsService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?CacheService $cache = null
    )
    {
    }

    public function snapshot(): array
    {
        $topContents = $this->topContentsSnapshot(5);

        return [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'users_total' => $this->countSafe('SELECT COUNT(*) FROM users'),
            'contents_total' => $this->countSafe('SELECT COUNT(*) FROM series'),
            'chapters_total' => $this->countSafe('SELECT COUNT(*) FROM chapters'),
            'comments_total' => $this->countSafe('SELECT COUNT(*) FROM social_comments'),
            'ratings_total' => $this->countSafe('SELECT COUNT(*) FROM ratings'),
            'content_follows_total' => $this->countSafe('SELECT COUNT(*) FROM user_series_follows'),
            'user_follows_total' => $this->countSafe('SELECT COUNT(*) FROM user_follows'),
            'notifications_unread_total' => $this->countSafe('SELECT COUNT(*) FROM user_notifications WHERE is_read = 0'),
            'queue_pending_total' => $this->countSafe("SELECT COUNT(*) FROM system_jobs WHERE status = 'pending'"),
            'queue_failed_total' => $this->countSafe("SELECT COUNT(*) FROM system_jobs WHERE status = 'failed'"),
            'today_login_success_total' => $this->dailyMetric('auth_login_success_total'),
            'today_login_failed_total' => $this->dailyMetric('auth_login_failed_total'),
            'today_content_views_total' => $this->dailyMetric('content_view_total'),
            'today_chapter_views_total' => $this->dailyMetric('chapter_view_total'),
            'today_chapter_reads_total' => $this->dailyMetric('chapter_read_total'),
            'today_comments_total' => $this->dailyMetric('comment_create_total'),
            'today_ratings_total' => $this->dailyMetric('content_rate_total'),
            'today_content_follows_total' => $this->dailyMetric('content_follow_total'),
            'today_user_follows_total' => $this->todayCountByRange('user_follows', 'created_at'),
            'today_notifications_total' => $this->todayCountByRange('user_notifications', 'created_at'),
            'top_contents_7d' => $topContents,
            'funnel' => $this->funnelMetrics(),
            'chapter_completion' => $this->chapterCompletionMetrics(),
            'retention' => $this->retentionMetrics(),
            'search_analytics' => $this->searchAnalytics(),
            'notification_performance' => $this->notificationPerformance(),
            'content_quality' => $this->contentQualityMetrics(),
            'performance_slo' => $this->performanceSlo(),
            'moderation' => $this->moderationMetrics(),
            'anti_abuse' => $this->antiAbuseMetrics(),
            'monetization_signals' => $this->monetizationSignals(),
            'operability' => $this->operabilityMetrics(),
        ];
    }

    public function insights(int $days = 7): array
    {
        $days = $this->normalizeDays($days);
        $current = $this->overallEngagementTotals($days, 0);
        $previous = $this->overallEngagementTotals($days, $days);

        $topCurrent = $this->topGenresByEngagement($days, 0, 5);
        $topPrevious = $this->topGenresByEngagement($days, $days, 50);
        $previousBySlug = [];
        foreach ($topPrevious as $row) {
            $previousBySlug[(string) ($row['slug'] ?? '')] = (int) ($row['engagement_score'] ?? 0);
        }

        $topCurrent = array_map(function (array $row) use ($previousBySlug): array {
            $slug = (string) ($row['slug'] ?? '');
            $prev = (int) ($previousBySlug[$slug] ?? 0);
            $curr = (int) ($row['engagement_score'] ?? 0);
            $delta = $curr - $prev;
            $row['previous_engagement_score'] = $prev;
            $row['delta_score'] = $delta;
            $row['delta_pct'] = $this->pct($delta, max(1, $prev));
            return $row;
        }, $topCurrent);

        $deltaScore = $current['engagement_score'] - $previous['engagement_score'];
        $trend = $deltaScore > 0 ? 'up' : ($deltaScore < 0 ? 'down' : 'flat');
        $topGenre = $topCurrent[0] ?? null;

        $insights = [
            [
                'code' => 'overall_engagement_trend',
                'level' => $trend === 'up' ? 'positive' : ($trend === 'down' ? 'warning' : 'neutral'),
                'text' => sprintf(
                    'Son %d gunde toplam etkilesim skoru %s (%d -> %d).',
                    $days,
                    $trend === 'up' ? 'yukseldi' : ($trend === 'down' ? 'dusdu' : 'sabit kaldi'),
                    (int) $previous['engagement_score'],
                    (int) $current['engagement_score']
                ),
            ],
        ];

        if (is_array($topGenre)) {
            $insights[] = [
                'code' => 'top_genre_interest',
                'level' => 'info',
                'text' => sprintf(
                    'Son %d gunde en cok ilgi goren genre: %s (skor: %d).',
                    $days,
                    (string) ($topGenre['name'] ?? $topGenre['slug'] ?? 'unknown'),
                    (int) ($topGenre['engagement_score'] ?? 0)
                ),
            ];
        }

        return [
            'period_days' => $days,
            'current' => $current,
            'previous' => $previous,
            'delta_score' => $deltaScore,
            'delta_pct' => $this->pct($deltaScore, max(1, (int) $previous['engagement_score'])),
            'top_genres' => $topCurrent,
            'insights' => $insights,
        ];
    }

    public function genreInterest(string $genreSlug, int $days = 7): array
    {
        $slug = strtolower(trim($genreSlug));
        if ($slug === '') {
            throw new \DomainException('Genre slug is required');
        }
        if (!$this->genreExists($slug)) {
            throw new \DomainException('Genre not found');
        }

        $days = $this->normalizeDays($days);
        $current = $this->genreEngagementTotals($slug, $days, 0);
        $previous = $this->genreEngagementTotals($slug, $days, $days);
        $deltaScore = $current['engagement_score'] - $previous['engagement_score'];
        $deltaPct = $this->pct($deltaScore, max(1, (int) $previous['engagement_score']));

        $summary = sprintf(
            'Kullanicilar son %d gunde \"%s\" genre iceriklerine %s ilgi gosterdi (skor: %d, onceki: %d).',
            $days,
            (string) $current['genre_name'],
            $deltaScore > 0 ? 'daha fazla' : ($deltaScore < 0 ? 'daha az' : 'benzer seviyede'),
            (int) $current['engagement_score'],
            (int) $previous['engagement_score']
        );

        return [
            'genre_slug' => $slug,
            'genre_name' => $current['genre_name'],
            'period_days' => $days,
            'current' => $current,
            'previous' => $previous,
            'delta_score' => $deltaScore,
            'delta_pct' => $deltaPct,
            'summary_text' => $summary,
        ];
    }

    private function normalizeDays(int $days): int
    {
        return max(1, min(90, $days));
    }

    private function genreExists(string $slug): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM series_genres WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function overallEngagementTotals(int $days, int $offsetDays): array
    {
        $startExpr = sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $days + $offsetDays);
        $endExpr = $offsetDays === 0
            ? 'NOW()'
            : sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $offsetDays);

        $views = $this->countSafe(
            "SELECT COUNT(*) FROM analytics_series_views
             WHERE viewed_at >= {$startExpr} AND viewed_at < {$endExpr}"
        );
        $follows = $this->countSafe(
            "SELECT COUNT(*) FROM user_series_follows
             WHERE created_at >= {$startExpr} AND created_at < {$endExpr}"
        );
        $ratings = $this->countSafe(
            "SELECT COUNT(*) FROM ratings
             WHERE created_at >= {$startExpr} AND created_at < {$endExpr}"
        );
        $comments = $this->countSafe(
            "SELECT COUNT(*) FROM social_comments
             WHERE created_at >= {$startExpr} AND created_at < {$endExpr}"
        );

        return $this->buildEngagementPayload('all', 'All Genres', $views, $follows, $ratings, $comments);
    }

    private function genreEngagementTotals(string $slug, int $days, int $offsetDays): array
    {
        $startExpr = sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $days + $offsetDays);
        $endExpr = $offsetDays === 0
            ? 'NOW()'
            : sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $offsetDays);

        $nameStmt = $this->pdo->prepare('SELECT name FROM series_genres WHERE slug = :slug LIMIT 1');
        $nameStmt->execute(['slug' => $slug]);
        $genreName = (string) ($nameStmt->fetchColumn() ?: $slug);

        $views = $this->countSafe(
            "SELECT COUNT(*)
             FROM analytics_series_views cv
             INNER JOIN series_genre_map cg ON cg.content_id = cv.content_id
             INNER JOIN series_genres g ON g.id = cg.genre_id
             WHERE g.slug = " . $this->pdo->quote($slug) . "
               AND cv.viewed_at >= {$startExpr}
               AND cv.viewed_at < {$endExpr}"
        );

        $follows = $this->countSafe(
            "SELECT COUNT(*)
             FROM user_series_follows f
             INNER JOIN series_genre_map cg ON cg.content_id = f.content_id
             INNER JOIN series_genres g ON g.id = cg.genre_id
             WHERE g.slug = " . $this->pdo->quote($slug) . "
               AND f.created_at >= {$startExpr}
               AND f.created_at < {$endExpr}"
        );

        $ratings = $this->countSafe(
            "SELECT COUNT(*)
             FROM ratings r
             INNER JOIN series_genre_map cg ON cg.content_id = r.content_id
             INNER JOIN series_genres g ON g.id = cg.genre_id
             WHERE g.slug = " . $this->pdo->quote($slug) . "
               AND r.updated_at >= {$startExpr}
               AND r.updated_at < {$endExpr}"
        );

        $comments = $this->countSafe(
            "SELECT COUNT(*)
             FROM social_comments cm
             LEFT JOIN chapters ch ON ch.id = cm.chapter_id
             INNER JOIN series_genre_map cg ON cg.content_id = COALESCE(cm.content_id, ch.content_id)
             INNER JOIN series_genres g ON g.id = cg.genre_id
             WHERE g.slug = " . $this->pdo->quote($slug) . "
               AND cm.created_at >= {$startExpr}
               AND cm.created_at < {$endExpr}"
        );

        return $this->buildEngagementPayload($slug, $genreName, $views, $follows, $ratings, $comments);
    }

    private function topGenresByEngagement(int $days, int $offsetDays, int $limit = 5): array
    {
        $days = $this->normalizeDays($days);
        $limit = max(1, min(50, $limit));
        $startExpr = sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $days + $offsetDays);
        $endExpr = $offsetDays === 0
            ? 'NOW()'
            : sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $offsetDays);

        $sql = "
            SELECT
                g.slug,
                g.name,
                COALESCE(v.view_total, 0) AS view_total,
                COALESCE(f.follow_total, 0) AS follow_total,
                COALESCE(r.rating_total, 0) AS rating_total,
                COALESCE(c.comment_total, 0) AS comment_total,
                (
                    (COALESCE(v.view_total, 0) * 1) +
                    (COALESCE(f.follow_total, 0) * 3) +
                    (COALESCE(r.rating_total, 0) * 2) +
                    (COALESCE(c.comment_total, 0) * 2)
                ) AS engagement_score
            FROM series_genres g
            LEFT JOIN (
                SELECT cg.genre_id, COUNT(*) AS view_total
                FROM analytics_series_views cv
                INNER JOIN series_genre_map cg ON cg.content_id = cv.content_id
                WHERE cv.viewed_at >= {$startExpr} AND cv.viewed_at < {$endExpr}
                GROUP BY cg.genre_id
            ) v ON v.genre_id = g.id
            LEFT JOIN (
                SELECT cg.genre_id, COUNT(*) AS follow_total
                FROM user_series_follows f
                INNER JOIN series_genre_map cg ON cg.content_id = f.content_id
                WHERE f.created_at >= {$startExpr} AND f.created_at < {$endExpr}
                GROUP BY cg.genre_id
            ) f ON f.genre_id = g.id
            LEFT JOIN (
                SELECT cg.genre_id, COUNT(*) AS rating_total
                FROM ratings r
                INNER JOIN series_genre_map cg ON cg.content_id = r.content_id
                WHERE r.updated_at >= {$startExpr} AND r.updated_at < {$endExpr}
                GROUP BY cg.genre_id
            ) r ON r.genre_id = g.id
            LEFT JOIN (
                SELECT cg.genre_id, COUNT(*) AS comment_total
                FROM social_comments cm
                LEFT JOIN chapters ch ON ch.id = cm.chapter_id
                INNER JOIN series_genre_map cg ON cg.content_id = COALESCE(cm.content_id, ch.content_id)
                WHERE cm.created_at >= {$startExpr} AND cm.created_at < {$endExpr}
                GROUP BY cg.genre_id
            ) c ON c.genre_id = g.id
            HAVING engagement_score > 0
            ORDER BY engagement_score DESC, g.name ASC
            LIMIT {$limit}
        ";

        return $this->rowsSafe($sql);
    }

    private function buildEngagementPayload(
        string $slug,
        string $name,
        int $views,
        int $follows,
        int $ratings,
        int $comments
    ): array {
        $score = ($views * 1) + ($follows * 3) + ($ratings * 2) + ($comments * 2);

        return [
            'genre_slug' => $slug,
            'genre_name' => $name,
            'view_total' => $views,
            'follow_total' => $follows,
            'rating_total' => $ratings,
            'comment_total' => $comments,
            'engagement_score' => $score,
        ];
    }

    private function topContentsSnapshot(int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $sql = 'SELECT
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    s.view_count AS view_count_7d,
                    0 AS comment_count_7d
                FROM analytics_snapshots_series_top s
                INNER JOIN series c ON c.id = s.content_id
                WHERE s.stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
                ORDER BY s.view_count DESC
                LIMIT :limit';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function dailyMetric(string $metricName): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT metric_value
                 FROM analytics_snapshots_daily
                 WHERE stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)
                   AND metric_name = :metric_name
                 ORDER BY stat_date DESC
                 LIMIT 1'
            );
            $stmt->execute(['metric_name' => $metricName]);
            $value = $stmt->fetchColumn();
            return (int) ($value !== false ? $value : 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function authSnapshot(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT failed_login_count, rate_limited_count
                 FROM analytics_snapshots_auth
                 WHERE stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)
                 ORDER BY stat_date DESC
                 LIMIT 1'
            );
            $row = $stmt === false ? false : $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function healthSnapshot(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT request_total_24h, server_error_total_24h, p95_duration_ms_24h, suspicious_login_ips_24h
                 FROM analytics_snapshots_health
                 WHERE stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)
                 ORDER BY stat_date DESC
                 LIMIT 1'
            );
            $row = $stmt === false ? false : $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function todayCountByRange(string $table, string $column): int
    {
        try {
            $sql = sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s >= CURDATE() AND %s < DATE_ADD(CURDATE(), INTERVAL 1 DAY)',
                $table,
                $column,
                $column
            );
            $value = $this->pdo->query($sql)->fetchColumn();
            return (int) ($value !== false ? $value : 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countSafe(string $sql): int
    {
        try {
            $value = $this->pdo->query($sql)->fetchColumn();
            return (int) ($value !== false ? $value : 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function scalarSafe(string $sql, float|int $default = 0): float|int
    {
        try {
            $value = $this->pdo->query($sql)->fetchColumn();
            if ($value === false || $value === null) {
                return $default;
            }
            return is_float($default) ? (float) $value : (int) $value;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function rowsSafe(string $sql): array
    {
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function pct(int|float $num, int|float $den): float
    {
        if ($den <= 0) {
            return 0.0;
        }
        return round(((float) $num / (float) $den) * 100, 2);
    }

    private function funnelMetrics(): array
    {
        $homeViews = $this->dailyMetric('home_view_total');
        $contentViews = $this->dailyMetric('content_view_total');
        $chapterOpens = $this->dailyMetric('chapter_view_total');
        $actions = $this->dailyMetric('action_total');

        return [
            'home_view_total' => $homeViews,
            'content_view_total' => $contentViews,
            'chapter_open_total' => $chapterOpens,
            'action_total' => $actions,
            'home_to_content_pct' => $this->pct($contentViews, $homeViews),
            'content_to_chapter_pct' => $this->pct($chapterOpens, $contentViews),
            'chapter_to_action_pct' => $this->pct($actions, $chapterOpens),
        ];
    }

    private function chapterCompletionMetrics(): array
    {
        $opens = $this->dailyMetric('chapter_view_total');
        $reads = $this->dailyMetric('chapter_read_total');
        return [
            'today_chapter_open_total' => $opens,
            'today_chapter_read_total' => $reads,
            'completion_rate_pct' => $this->pct($reads, $opens),
        ];
    }

    private function retentionMetrics(): array
    {
        $newUsers7d = $this->dailyMetric('new_users_7d_total');
        $retainedD1 = $this->dailyMetric('d1_retained_total');
        $retainedD7 = $this->dailyMetric('d7_retained_total');

        return [
            'new_users_7d_total' => $newUsers7d,
            'd1_retained_total' => $retainedD1,
            'd1_retention_pct' => $this->pct($retainedD1, $newUsers7d),
            'd7_retained_total' => $retainedD7,
            'd7_retention_pct' => $this->pct($retainedD7, $newUsers7d),
        ];
    }

    private function searchAnalytics(): array
    {
        $top = $this->rowsSafe(
            'SELECT query, SUM(search_count) as search_count, SUM(zero_result_count) as zero_result_count
             FROM analytics_snapshots_search
             WHERE stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
             GROUP BY query
             ORDER BY search_count DESC
             LIMIT 10'
        );
        $zero = $this->dailyMetric('zero_result_total_7d');
        $total = $this->dailyMetric('search_total_7d');

        return [
            'search_total_7d' => $total,
            'zero_result_total_7d' => $zero,
            'zero_result_rate_pct_7d' => $this->pct($zero, $total),
            'top_queries_7d' => $top,
        ];
    }

    private function notificationPerformance(): array
    {
        $generated = $this->countSafe('SELECT COUNT(*) FROM user_notifications WHERE DATE(created_at) = CURRENT_DATE()');
        $read = $this->countSafe('SELECT COUNT(*) FROM user_notifications WHERE read_at IS NOT NULL AND DATE(read_at) = CURRENT_DATE()');
        return [
            'generated_today' => $generated,
            'read_today' => $read,
            'read_rate_pct' => $this->pct($read, $generated),
            'unread_total' => $this->countSafe('SELECT COUNT(*) FROM user_notifications WHERE is_read = 0'),
        ];
    }

    private function contentQualityMetrics(): array
    {
        $top = $this->rowsSafe(
            'SELECT
                c.id,
                c.title,
                c.slug,
                c.type,
                (
                    (COALESCE(v.view_count_7d, 0) * 0.20) +
                    (COALESCE(f.follow_count_7d, 0) * 3.00) +
                    (c.rating_avg * c.rating_count) +
                    (COALESCE(cm.comment_count_7d, 0) * 2.00)
                ) AS quality_score
             FROM series c
             LEFT JOIN (
                SELECT content_id, SUM(view_count) AS view_count_7d
                FROM analytics_series_daily
                WHERE stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
                GROUP BY content_id
             ) v ON v.content_id = c.id
             LEFT JOIN (
                SELECT content_id, COUNT(*) AS follow_count_7d
                FROM user_series_follows
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY content_id
             ) f ON f.content_id = c.id
             LEFT JOIN (
                SELECT content_id, COUNT(*) AS comment_count_7d
                FROM social_comments
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY content_id
             ) cm ON cm.content_id = c.id
             ORDER BY quality_score DESC
             LIMIT 10'
        );

        return ['top_quality_contents_7d' => $top];
    }

    private function performanceSlo(): array
    {
        $health = $this->healthSnapshot();
        $total = (int) ($health['request_total_24h'] ?? 0);
        $errors = (int) ($health['server_error_total_24h'] ?? 0);
        $p95 = (int) ($health['p95_duration_ms_24h'] ?? 0);

        return [
            'request_total_24h' => $total,
            'server_error_total_24h' => $errors,
            'server_error_rate_pct_24h' => $this->pct($errors, $total),
            'p95_duration_ms_24h' => $p95,
        ];
    }

    private function moderationMetrics(): array
    {
        return [
            'actions_total_7d' => $this->countSafe('SELECT COUNT(*) FROM admin_actions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'delete_actions_7d' => $this->countSafe("SELECT COUNT(*) FROM admin_actions WHERE action = 'delete' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
            'ban_actions_7d' => $this->countSafe("SELECT COUNT(*) FROM admin_actions WHERE action = 'ban' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        ];
    }

    private function antiAbuseMetrics(): array
    {
        $auth = $this->authSnapshot();
        $health = $this->healthSnapshot();
        $suspiciousLoginIps = (int) ($health['suspicious_login_ips_24h'] ?? 0);

        return [
            'suspicious_login_ips_24h' => $suspiciousLoginIps,
            'failed_login_total_24h' => (int) ($auth['failed_login_count'] ?? 0),
            'rate_limited_login_total' => (int) ($auth['rate_limited_count'] ?? 0),
        ];
    }

    private function monetizationSignals(): array
    {
        $topGenres = $this->rowsSafe(
            'SELECT
                g.slug,
                g.name,
                COUNT(DISTINCT cg.content_id) AS content_total,
                COUNT(ucf.user_id) AS follow_total
             FROM series_genres g
             LEFT JOIN series_genre_map cg ON cg.genre_id = g.id
             LEFT JOIN user_series_follows ucf ON ucf.content_id = cg.content_id
             GROUP BY g.id, g.slug, g.name
             ORDER BY follow_total DESC, content_total DESC
             LIMIT 10'
        );

        return [
            'loyal_users_7d_total' => $this->countSafe(
                'SELECT COUNT(*) FROM (
                    SELECT user_id
                    FROM user_chapters_reads
                    WHERE read_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY user_id
                    HAVING COUNT(*) >= 10
                 ) u'
            ),
            'top_genres_by_follows' => $topGenres,
        ];
    }

    private function operabilityMetrics(): array
    {
        if ($this->cache === null) {
            return [
                'cache_get_hit_total' => 0,
                'cache_get_miss_total' => 0,
                'cache_write_total' => 0,
                'cache_delete_total' => 0,
                'rate_limit_login_allowed_total' => 0,
                'rate_limit_login_blocked_total' => 0,
                'rate_limit_comment_allowed_total' => 0,
                'rate_limit_comment_blocked_total' => 0,
                'cache_file_count' => 0,
                'cache_expired_count' => 0,
            ];
        }

        $cacheStats = $this->cache->stats();
        return [
            'cache_get_hit_total' => (int) $this->cache->get('sys_cache_get_hit', 0),
            'cache_get_miss_total' => (int) $this->cache->get('sys_cache_get_miss', 0),
            'cache_write_total' => (int) $this->cache->get('sys_cache_write', 0),
            'cache_delete_total' => (int) $this->cache->get('sys_cache_delete', 0),
            'rate_limit_login_allowed_total' => (int) $this->cache->get('sys_rate_limit_allowed_login', 0),
            'rate_limit_login_blocked_total' => (int) $this->cache->get('sys_rate_limit_blocked_login', 0),
            'rate_limit_comment_allowed_total' => (int) $this->cache->get('sys_rate_limit_allowed_comment', 0),
            'rate_limit_comment_blocked_total' => (int) $this->cache->get('sys_rate_limit_blocked_comment', 0),
            'cache_file_count' => (int) ($cacheStats['cache_file_count'] ?? 0),
            'cache_expired_count' => (int) ($cacheStats['cache_expired_count'] ?? 0),
        ];
    }
}
