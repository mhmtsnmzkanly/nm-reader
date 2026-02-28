<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper for Chapter Number Formatting.
 *
 * Provides logic to clean up chapter number strings (e.g., converting "1.00" to "1"
 * or "1.50" to "1.5") for better UI display while maintaining decimal precision.
 *
 * @package App\Helpers
 */
final class ChapterNumber
{
    /**
     * Normalizes a numeric string by removing redundant trailing zeros.
     *
     * @param mixed $value Raw chapter number.
     * @return string Cleaned number string.
     */
    public static function normalize(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
            return $raw;
        }

        if (!str_contains($raw, '.')) {
            return $raw;
        }

        $normalized = rtrim(rtrim($raw, '0'), '.');
        return $normalized === '-0' ? '0' : $normalized;
    }
}
