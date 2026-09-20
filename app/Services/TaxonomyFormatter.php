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

    /** @return list<array{name:string,slug:string,ui_config:array<string,mixed>}> */
    public function items(string $raw): array
    {
        if ($raw === '') return [];
        $items = [];
        foreach (explode('||', $raw) as $entry) {
            $parts = explode('::', $entry, 3);
            $name = trim((string) ($parts[0] ?? ''));
            $slug = trim((string) ($parts[1] ?? ''));
            if ($name === '' || $slug === '') continue;
            $config = json_decode((string) ($parts[2] ?? '{}'), true);
            $items[$slug] = [
                'name' => $name,
                'slug' => $slug,
                'ui_config' => is_array($config) ? $config : [],
            ];
        }
        return array_values($items);
    }
}
