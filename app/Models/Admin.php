<?php

declare(strict_types=1);

namespace App\Models;

final class Admin
{
    public function __construct(
        public int $id,
        public string $username
    ) {
    }
}
