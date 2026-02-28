<?php

declare(strict_types=1);

namespace App\Models;

final class Blog
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $title,
        public string $slug,
        public string $body,
        public bool $approved,
        public ?int $approverUserId = null
    ) {
    }
}
