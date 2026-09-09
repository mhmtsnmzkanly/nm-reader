<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\Admin\AdminContentRepository;
use App\Repositories\Admin\AdminUserRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\AdminContentService;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\AdminModerationService;
use App\Services\Admin\AdminOperationsService;
use App\Services\Admin\AdminSettingsService;
use App\Services\Admin\AdminStorageService;
use App\Services\Admin\AdminUserService;
use App\Services\Admin\ChapterAdminService;
use App\Services\Admin\ContentAdminService;
use App\Services\Admin\TaxonomyAdminService;
use App\Services\AnalyticsAggregationService;
use App\Services\MetricsService;
use App\Services\RetentionService;
use App\Services\SiteConfigService;
use App\Services\SystemLogService;
use App\Services\UploadService;
use App\Services\WalletService;
use App\Services\WebhookService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared dependencies and request helpers for admin API controllers.
 *
 * Domain controllers share the extracted services while keeping the public
 * API route contract stable.
 */
abstract class AdminController
{
    public function __construct(
        protected readonly ContentAdminService $adminContentService,
        protected readonly ChapterAdminService $adminChapterService,
        protected readonly TaxonomyAdminService $adminTaxonomyService,
        protected readonly AdminDashboardService $dashboardService,
        protected readonly AdminContentService $contentService,
        protected readonly AdminUserService $usersService,
        protected readonly AdminModerationService $moderationService,
        protected readonly AdminStorageService $storageService,
        protected readonly AdminOperationsService $operationsService,
        protected readonly AdminSettingsService $settingsService,
        protected readonly SiteConfigService $siteConfig,
        protected readonly MetricsService $metricsService,
        protected readonly RetentionService $retentionService,
        protected readonly UploadService $uploadService,
        protected readonly SystemLogService $logs,
        protected readonly AnalyticsAggregationService $aggregation,
        protected readonly WalletService $wallets,
        protected readonly WebhookService $webhooks,
        protected readonly AdminContentRepository $adminContentRepo,
        protected readonly AdminUserRepository $adminUserRepo,
        protected readonly UserRepository $users,
    ) {
    }

    protected function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        return [
            max(1, (int) ($query['page'] ?? 1)),
            max(1, min(100, (int) ($query['per_page'] ?? 20))),
        ];
    }
}
