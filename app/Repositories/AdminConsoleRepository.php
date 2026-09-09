<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Admin\AdminContentRepository;
use App\Repositories\Admin\AdminDashboardRepository;
use App\Repositories\Admin\AdminModerationRepository;
use App\Repositories\Admin\AdminOperationsRepository;
use App\Repositories\Admin\AdminStorageRepository;
use App\Repositories\Admin\AdminTaxonomyRepository;
use App\Repositories\Admin\AdminUserRepository;

/**
 * Backwards-compatible entry point for the split admin repositories.
 *
 * Existing services can continue to depend on this class while new code can
 * depend on the domain-specific repositories directly. Route URLs and public
 * method signatures are intentionally unchanged during the migration.
 */
final class AdminConsoleRepository
{
    /** @var list<object> */
    private array $delegates;

    public function __construct(
        AdminDashboardRepository $dashboard,
        AdminContentRepository $content,
        AdminUserRepository $users,
        AdminModerationRepository $moderation,
        AdminStorageRepository $storage,
        AdminTaxonomyRepository $taxonomy,
        AdminOperationsRepository $operations,
    ) {
        $this->delegates = [
            $dashboard,
            $content,
            $users,
            $moderation,
            $storage,
            $taxonomy,
            $operations,
        ];
    }

    /**
     * Forward legacy calls to the repository owning the requested operation.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        foreach ($this->delegates as $delegate) {
            if (method_exists($delegate, $name)) {
                return $delegate->{$name}(...$arguments);
            }
        }

        throw new \BadMethodCallException(sprintf('Unknown admin repository method: %s', $name));
    }
}
