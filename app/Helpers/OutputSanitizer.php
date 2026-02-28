<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper for UI Output Sanitization.
 *
 * Mitigates XSS risks by stripping HTML series_tags from data before it is sent
 * to the frontend or rendered in SSR views.
 *
 * @package App\Helpers
 */
final class OutputSanitizer
{
    /**
     * Strips HTML series_tags from specific fields in a single associative array (row).
     *
     * @param array $row The data record.
     * @param array<int, string> $fields List of field keys to sanitize.
     * @return array The cleaned row.
     */
    public static function sanitizeFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = trim(strip_tags($row[$field]));
            }
        }

        return $row;
    }

    /**
     * Batch sanitizes a collection of records.
     *
     * @param array<int, array> $rows List of records.
     * @param array<int, string> $fields Fields to clean in each record.
     * @return array The cleaned collection.
     */
    public static function sanitizeRows(array $rows, array $fields): array
    {
        return array_map(static fn (array $row): array => self::sanitizeFields($row, $fields), $rows);
    }
}
