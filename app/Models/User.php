<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public ?string $bio = null
    ) {
    }
}
