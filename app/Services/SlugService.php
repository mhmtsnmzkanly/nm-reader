<?php

declare(strict_types=1);

namespace App\Services;

final class SlugService
{
    public function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        return trim($value, '-');
    }
}
