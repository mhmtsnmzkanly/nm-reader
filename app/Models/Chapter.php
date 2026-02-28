<?php

declare(strict_types=1);

namespace App\Models;

final class Chapter
{
    public function __construct(
        public int $id,
        public int $contentId,
        public string $chapterNumber,
        public string $type
    ) {
    }
}
