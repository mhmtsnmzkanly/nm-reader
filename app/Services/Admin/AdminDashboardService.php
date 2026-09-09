<?php

declare(strict_types=1);

namespace App\Services\Admin;

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
final class AdminDashboardService extends AdminConsoleServiceBase
{
    public function overview(): array
    {
        // Lazy timer: check whether the 60-second aggregation interval elapsed.
        $this->checkAndAutoTriggerAnalytics();
    
        $data = $this->cache->remember(AdminConsoleServiceBase::CACHE_KEY_KPI, AdminConsoleServiceBase::CACHE_TTL_KPI, function () {
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
}
