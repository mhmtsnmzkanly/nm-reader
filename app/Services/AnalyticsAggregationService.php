<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AnalyticsAggregationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function aggregateAll(int $days = 30): array
    {
        $days = max(1, min(365, $days));

        return [
            'days' => $days,
            'daily_views_rows' => $this->aggregateDailyViews($days),
            'daily_funnel_rows' => $this->aggregateDailyFunnel($days),
            'hourly_views_rows' => $this->aggregateHourlyViews(2),
            'top_content_rows' => $this->aggregateTopContent($days),
            'top_chapter_rows' => $this->aggregateTopChapters($days),
            'search_rows' => $this->aggregateSearchSnapshot($days),
            'auth_rows' => $this->aggregateAuthSnapshot($days),
            'health_rows' => $this->aggregateSystemHealth(),
        ];
    }

    private function aggregateDailyViews(int $days): int
    {
        $total = 0;
        $total += $this->upsertMetricByDay('total_views', ['content_view', 'chapter_view'], $days);
        $total += $this->upsertMetricByDay('content_view_total', ['content_view'], $days);
        $total += $this->upsertMetricByDay('chapter_view_total', ['chapter_view'], $days);
        $total += $this->upsertMetricByDay('home_view_total', ['home_view'], $days);
        $total += $this->upsertMetricByDay('chapter_read_total', ['chapter_read'], $days);
        $total += $this->upsertMetricByDay('action_total', ['content_follow', 'content_rate', 'comment_create'], $days);
        $total += $this->upsertMetricByDay('auth_login_success_total', ['auth_login_success'], $days);
        $total += $this->upsertMetricByDay('auth_login_failed_total', ['auth_login_failed'], $days);
        $total += $this->upsertMetricByDay('content_follow_total', ['content_follow'], $days);
        $total += $this->upsertMetricByDay('content_rate_total', ['content_rate'], $days);
        $total += $this->upsertMetricByDay('comment_create_total', ['comment_create'], $days);

        return $total;
    }

    private function aggregateDailyFunnel(int $days): int
    {
        $total = 0;
        $total += $this->upsertMetricFromSql(
            "INSERT INTO analytics_snapshots_daily (stat_date, metric_name, metric_value)
             SELECT CURRENT_DATE(), 'search_total_7d', COUNT(*)
             FROM analytics_events
             WHERE event_type = 'search' AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $days
        );
        $total += $this->upsertMetricFromSql(
            "INSERT INTO analytics_snapshots_daily (stat_date, metric_name, metric_value)
             SELECT CURRENT_DATE(), 'zero_result_total_7d', COUNT(*)
             FROM analytics_events
             WHERE event_type = 'search'
               AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.result_count')) AS SIGNED) = 0
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $days
        );
        $total += $this->upsertMetricFromSql(
            "INSERT INTO analytics_snapshots_daily (stat_date, metric_name, metric_value)
             SELECT CURRENT_DATE(), 'new_users_7d_total', COUNT(*)
             FROM users
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $days
        );
        $total += $this->upsertMetricFromSql(
            "INSERT INTO analytics_snapshots_daily (stat_date, metric_name, metric_value)
             SELECT CURRENT_DATE(), 'd1_retained_total', COUNT(DISTINCT u.id)
             FROM users u
             INNER JOIN user_chapters_reads r ON r.user_id = u.id
             WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND r.read_at >= DATE_ADD(u.created_at, INTERVAL 1 DAY)
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $days
        );
        $total += $this->upsertMetricFromSql(
            "INSERT INTO analytics_snapshots_daily (stat_date, metric_name, metric_value)
             SELECT CURRENT_DATE(), 'd7_retained_total', COUNT(DISTINCT u.id)
             FROM users u
             INNER JOIN user_chapters_reads r ON r.user_id = u.id
             WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND r.read_at >= DATE_ADD(u.created_at, INTERVAL 7 DAY)
             ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)",
            $days
        );

        return $total;
    }

    private function aggregateHourlyViews(int $days): int
    {
        $sql = "INSERT INTO analytics_snapshots_hourly (bucket_start, metric_name, metric_value)
                SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS bucket_start, 'total_views' AS metric_name, COUNT(*) AS metric_value
                FROM analytics_events
                WHERE event_type IN ('content_view', 'chapter_view')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
                ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function aggregateTopContent(int $days): int
    {
        $sql = "INSERT INTO analytics_snapshots_series_top (content_id, stat_date, view_count)
                SELECT entity_id, CURRENT_DATE(), COUNT(*)
                FROM analytics_events
                WHERE event_type = 'content_view'
                  AND entity_type = 'content'
                  AND entity_id IS NOT NULL
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY entity_id
                ON DUPLICATE KEY UPDATE view_count = VALUES(view_count)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function aggregateTopChapters(int $days): int
    {
        $sql = "INSERT INTO analytics_snapshots_chapters_top (chapter_id, stat_date, view_count)
                SELECT entity_id, CURRENT_DATE(), COUNT(*)
                FROM analytics_events
                WHERE event_type = 'chapter_view'
                  AND entity_type = 'chapter'
                  AND entity_id IS NOT NULL
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY entity_id
                ON DUPLICATE KEY UPDATE view_count = VALUES(view_count)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function aggregateSearchSnapshot(int $days): int
    {
        $sql = "INSERT INTO analytics_snapshots_search (stat_date, query, search_count, zero_result_count)
                SELECT
                    CURRENT_DATE() AS stat_date,
                    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.query')) AS query,
                    COUNT(*) AS search_count,
                    SUM(CASE WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.result_count')) AS SIGNED) = 0 THEN 1 ELSE 0 END) AS zero_result_count
                FROM analytics_events
                WHERE event_type = 'search'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  AND metadata IS NOT NULL
                GROUP BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.query'))
                HAVING query IS NOT NULL AND query <> ''
                ON DUPLICATE KEY UPDATE
                    search_count = VALUES(search_count),
                    zero_result_count = VALUES(zero_result_count)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function aggregateAuthSnapshot(int $days): int
    {
        $sql = "INSERT INTO analytics_snapshots_auth (stat_date, failed_login_count, rate_limited_count)
                SELECT
                    CURRENT_DATE() AS stat_date,
                    COALESCE(SUM(CASE WHEN event_type = 'auth_login_failed' THEN 1 ELSE 0 END), 0) AS failed_login_count,
                    COALESCE(SUM(CASE WHEN event_type = 'auth_login_failed'
                         AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.failure_reason')) = 'rate_limited' THEN 1 ELSE 0 END), 0) AS rate_limited_count
                FROM analytics_events
                WHERE event_type IN ('auth_login_success', 'auth_login_failed')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ON DUPLICATE KEY UPDATE
                    failed_login_count = VALUES(failed_login_count),
                    rate_limited_count = VALUES(rate_limited_count)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function aggregateSystemHealth(): int
    {
        $sql = "INSERT INTO analytics_snapshots_health (
                    stat_date,
                    request_total_24h,
                    server_error_total_24h,
                    p95_duration_ms_24h,
                    suspicious_login_ips_24h
                )
                SELECT
                    CURRENT_DATE() AS stat_date,
                    (SELECT COUNT(*) FROM system_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS request_total_24h,
                    (SELECT COUNT(*) FROM system_audit_logs WHERE status_code >= 500 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS server_error_total_24h,
                    (
                        SELECT COALESCE(MIN(duration_ms), 0)
                        FROM (
                            SELECT
                                duration_ms,
                                ROW_NUMBER() OVER (ORDER BY duration_ms) AS rn,
                                COUNT(*) OVER () AS cnt
                            FROM system_audit_logs
                            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                        ) t
                        WHERE rn >= CEIL(cnt * 0.95)
                    ) AS p95_duration_ms_24h,
                    (
                        SELECT COUNT(*) FROM (
                            SELECT ip_hash
                            FROM analytics_events
                            WHERE event_type = 'auth_login_failed'
                              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                            GROUP BY ip_hash
                            HAVING COUNT(*) >= 10
                        ) suspicious
                    ) AS suspicious_login_ips_24h
                ON DUPLICATE KEY UPDATE
                    request_total_24h = VALUES(request_total_24h),
                    server_error_total_24h = VALUES(server_error_total_24h),
                    p95_duration_ms_24h = VALUES(p95_duration_ms_24h),
                    suspicious_login_ips_24h = VALUES(suspicious_login_ips_24h)";

        return (int) $this->pdo->exec($sql);
    }

    private function upsertMetricByDay(string $metricName, array $eventTypes, int $days): int
    {
        if ($eventTypes === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($eventTypes), '?'));
        $sql = "INSERT INTO analytics_snapshots_daily (stat_date, metric_name, metric_value)
                SELECT DATE(created_at), ?, COUNT(*)
                FROM analytics_events
                WHERE event_type IN ($placeholders)
                  AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)";

        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        $stmt->bindValue($i++, $metricName, PDO::PARAM_STR);
        foreach ($eventTypes as $eventType) {
            $stmt->bindValue($i++, $eventType, PDO::PARAM_STR);
        }
        $stmt->bindValue($i, $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function upsertMetricFromSql(string $sql, int $days, array $extra = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        if (str_contains($sql, ':days')) {
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        }
        foreach ($extra as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->rowCount();
    }
}
