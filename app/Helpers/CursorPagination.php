<?php

declare(strict_types=1);

namespace App\Helpers;

final class CursorPagination
{
    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $parts = explode('|', $cursor, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $createdAt = trim($parts[0]);
        $id = (int) trim($parts[1]);
        if ($createdAt === '' || $id <= 0) {
            return null;
        }

        if (strtotime($createdAt) === false) {
            return null;
        }

        return [$createdAt, $id];
    }

    public static function encode(?string $createdAt, mixed $id): ?string
    {
        if (!is_string($createdAt) || $createdAt === '') {
            return null;
        }
        if (!is_numeric($id)) {
            return null;
        }
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return $createdAt . '|' . $id;
    }
}
