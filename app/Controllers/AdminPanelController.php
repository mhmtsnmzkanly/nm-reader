<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Admin\CommerceController;
use App\Controllers\Admin\ContentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ModerationController;
use App\Controllers\Admin\OperationsController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\StorageController;
use App\Controllers\Admin\UsersController;

/**
 * @deprecated Use the domain-specific controllers under App\Controllers\Admin.
 *
 * Kept as a compatibility facade for integrations that still resolve the old
 * controller class directly. New routes should point at a domain controller.
 */
final class AdminPanelController
{
    /** @var list<object> */
    private array $delegates;

    public function __construct(
        DashboardController $dashboard,
        ContentController $content,
        UsersController $users,
        ModerationController $moderation,
        StorageController $storage,
        OperationsController $operations,
        SettingsController $settings,
        CommerceController $commerce,
    ) {
        $this->delegates = [
            $dashboard,
            $content,
            $users,
            $moderation,
            $storage,
            $operations,
            $settings,
            $commerce,
        ];
    }

    public function __call(string $name, array $arguments): mixed
    {
        foreach ($this->delegates as $delegate) {
            if (method_exists($delegate, $name)) {
                return $delegate->{$name}(...$arguments);
            }
        }

        throw new \BadMethodCallException(sprintf('Unknown admin action: %s', $name));
    }
}
