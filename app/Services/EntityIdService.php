<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\BlogRepository;

final class EntityIdService
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly ChapterRepository $chapters,
        private readonly BlogRepository $blogs
    ) {
    }

    public function generateContentId(): string
    {
        return $this->generateUniqueId(fn (string $id): bool => $this->series->existsContentId($id), 6);
    }

    public function generateChapterId(): string
    {
        return $this->generateUniqueId(fn (string $id): bool => $this->chapters->existsChapterId($id), 6);
    }

    public function generateBlogId(): string
    {
        return $this->generateUniqueId(fn (string $id): bool => $this->blogs->existsBlogId($id), 6);
    }

    public function generateImageId(): string
    {
        return $this->randomBase36(32);
    }

    private function generateUniqueId(callable $exists, int $length = 6): string
    {
        for ($i = 0; $i < 20; $i++) {
            $id = $this->randomBase36($length);
            if (!$exists($id)) {
                return $id;
            }
        }

        throw new \RuntimeException('Unable to generate unique id');
    }

    private function randomBase36(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $maxIndex = strlen($alphabet) - 1;
        $id = '';

        for ($i = 0; $i < $length; $i++) {
            $id .= $alphabet[random_int(0, $maxIndex)];
        }

        return $id;
    }
}
