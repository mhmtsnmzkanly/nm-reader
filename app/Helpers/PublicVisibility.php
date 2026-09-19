<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * SQL predicates shared by public content lookups.
 *
 * These fragments intentionally describe visibility only. Privileged preview
 * queries must not use them because editors/admins need to inspect unpublished
 * records.
 */
final class PublicVisibility
{
    public static function series(string $alias = 'c'): string
    {
        return sprintf(
            '%1$s.deleted_at IS NULL AND (%1$s.lifecycle_status = "published" OR (%1$s.lifecycle_status = "scheduled" AND %1$s.scheduled_at <= NOW()))',
            $alias,
        );
    }

    public static function chapter(string $alias = 'ch'): string
    {
        return sprintf(
            '%1$s.deleted_at IS NULL AND %1$s.published_at IS NOT NULL AND %1$s.published_at <= NOW()',
            $alias,
        );
    }
}
