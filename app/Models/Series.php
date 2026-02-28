<?php

declare(strict_types=1);

namespace App\Models;

final class Series
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $type
    ) {
    }
}
