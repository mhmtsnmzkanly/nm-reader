<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Admin\AdminContentService;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\AdminModerationService;
use App\Services\Admin\AdminOperationsService;
use App\Services\Admin\AdminSettingsService;
use App\Services\Admin\AdminStorageService;
use App\Services\Admin\AdminUserService;

/**
 * @deprecated Use the domain services under App\Services\Admin.
 *
 * This compatibility facade keeps existing integrations working while the
 * admin console implementation is organized by domain.
 */
final class AdminConsoleService
{
    /** @var list<object> */
    private array $delegates;

    public function __construct(
        AdminDashboardService $dashboard,
        AdminContentService $content,
        AdminUserService $users,
        AdminModerationService $moderation,
        AdminStorageService $storage,
        AdminOperationsService $operations,
        AdminSettingsService $settings,
    ) {
        $this->delegates = [
            $dashboard,
            $content,
            $users,
            $moderation,
            $storage,
            $operations,
            $settings,
        ];
    }

    public function __call(string $name, array $arguments): mixed
    {
        foreach ($this->delegates as $delegate) {
            if (method_exists($delegate, $name)) {
                return $delegate->{$name}(...$arguments);
            }
        }

        throw new \BadMethodCallException(sprintf('Unknown admin console action: %s', $name));
    }
}
