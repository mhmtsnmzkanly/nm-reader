<?php

declare(strict_types=1);

namespace App\Services;

/** Converts repository taxonomy aggregates into unique public display names. */
final class TaxonomyFormatter
{
    /** @return list<string> */
    public function names(string $raw): array
    {
        if ($raw === '') return [];
        $names = [];
        foreach (explode('||', $raw) as $item) {
            $name = trim((string) (explode('::', $item, 2)[0] ?? ''));
            if ($name !== '') $names[] = $name;
        }
        return array_values(array_unique($names));
    }
}
