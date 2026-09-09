<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\AdminConsoleRepository;
use App\Repositories\UserRepository;
use App\Services\AdminConsoleService;
use App\Services\AdminService;
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
 * Domain controllers intentionally share this small compatibility base during
 * the first extraction phase. Services and repositories can be narrowed later
 * without changing the public API route contract.
 */
abstract class AdminController
{
    public function __construct(
        protected readonly AdminService $adminService,
        protected readonly AdminConsoleService $console,
        protected readonly SiteConfigService $siteConfig,
        protected readonly MetricsService $metricsService,
        protected readonly RetentionService $retentionService,
        protected readonly UploadService $uploadService,
        protected readonly SystemLogService $logs,
        protected readonly AnalyticsAggregationService $aggregation,
        protected readonly WalletService $wallets,
        protected readonly WebhookService $webhooks,
        protected readonly AdminConsoleRepository $adminConsoleRepo,
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
