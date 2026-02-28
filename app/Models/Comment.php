<?php

declare(strict_types=1);

namespace App\Models;

final class Comment
{
    public function __construct(
        public int $id,
        public int $userId,
        public ?int $chapterId,
        public string $body
    ) {
    }
}
