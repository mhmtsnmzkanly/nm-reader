<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ChapterNumber;
use App\Repositories\ChapterRepository;

final class ChapterService
{
    private const TYPE_SEGMENT_TO_DB = [
        'light-novel' => 'light_novel',
        'web-novel' => 'web_novel',
        'novel' => 'novel',
        'manga' => 'manga',
        'manhua' => 'manhua',
        'manhwa' => 'manhwa',
        'webtoon' => 'webtoon',
    ];

    public function __construct(
        private readonly ChapterRepository $chapters,
        private readonly AnalyticsService $analytics,
        private readonly WalletService $wallets,
        private readonly MediaService $media
    )
    {
    }

    public function getByNumber(string $chapterNumber, string $ip, ?string $userId = null): ?array
    {
        $chapter = $this->chapters->findByChapterNumber($chapterNumber);
        if ($chapter === null) {
            return null;
        }

        $chapterId = (string) $chapter['id'];
        $contentId = (string) ($chapter['content_id'] ?? '');
        $this->analytics->track(
            'chapter_view',
            $userId,
            'chapter',
            $chapterId,
            $contentId !== '' ? ['content_id' => $contentId] : [],
            $ip
        );
        $this->chapters->recordChapterView($chapterId, hash('sha256', $ip));
        $chapter['chapter_number'] = ChapterNumber::normalize($chapter['chapter_number'] ?? '');

        $access = $this->wallets->chapterAccess($contentId, $chapterId, $userId);

        if (($access['granted'] ?? false) === true) {
            if ($chapter['type'] === 'text') {
                $chapter['body'] = $this->chapters->findChapterText($chapterId) ?? '';
                $chapter['pages'] = [];
            } else {
                $chapter['body'] = null;
                $rawPages = $this->chapters->findChapterPages($chapterId);
                $chapter['pages'] = array_map(function (array $page) use ($chapterId, $userId): array {
                    $url = $this->media->generateChapterPageUrl($chapterId, (int) ($page['page_order'] ?? 1), (string) ($page['image_path'] ?? ''), $userId);
                    return [
                        'page_order' => (int) ($page['page_order'] ?? 1),
                        'url'        => $url,
                        'image_path' => $url,
                    ];
                }, $rawPages);
            }
            if ($userId !== null && $userId !== '') {
                $this->markRead($userId, $chapterId);
            }
        } else {
            $chapter['body'] = null;
            $chapter['pages'] = [];
        }
        
        $chapter['adjacent_chapters'] = $this->chapters->findAdjacentChapters($contentId, (string) $chapter['chapter_number']);
        $chapter = \App\DTO\ChapterDto::fromArray($chapter)->toArray();
        $chapter['access'] = $access;
        $chapter['price_coin'] = (int) ($access['chapter_unlock_price'] ?? 0);
        $chapter['is_locked'] = !(bool) ($access['granted'] ?? false);

        return $chapter;
    }

    public function getByTypeSlugAndNumber(string $typeSegment, string $slug, string $chapterNumber, string $ip, ?string $userId = null): ?array
    {
        $dbType = $this->toDbType($typeSegment);
        $chapter = $this->chapters->findByTypeSlugAndChapterNumber($dbType, $slug, $chapterNumber);
        if ($chapter === null) {
            return null;
        }

        $chapterId = (string) $chapter['id'];
        $contentId = (string) ($chapter['content_id'] ?? '');
        $this->analytics->track(
            'chapter_view',
            $userId,
            'chapter',
            $chapterId,
            $contentId !== '' ? ['content_id' => $contentId] : [],
            $ip
        );
        $this->chapters->recordChapterView($chapterId, hash('sha256', $ip));
        $chapter['chapter_number'] = ChapterNumber::normalize($chapter['chapter_number'] ?? '');

        $access = $this->wallets->chapterAccess($contentId, $chapterId, $userId);

        if (($access['granted'] ?? false) === true) {
            if ($chapter['type'] === 'text') {
                $chapter['body'] = $this->chapters->findChapterText($chapterId) ?? '';
                $chapter['pages'] = [];
            } else {
                $chapter['body'] = null;
                $rawPages = $this->chapters->findChapterPages($chapterId);
                $chapter['pages'] = array_map(function (array $page) use ($chapterId, $userId): array {
                    $url = $this->media->generateChapterPageUrl($chapterId, (int) ($page['page_order'] ?? 1), (string) ($page['image_path'] ?? ''), $userId);
                    return [
                        'page_order' => (int) ($page['page_order'] ?? 1),
                        'url'        => $url,
                        'image_path' => $url,
                    ];
                }, $rawPages);
            }
            if ($userId !== null && $userId !== '') {
                $this->markRead($userId, $chapterId);
            }
        } else {
            $chapter['body'] = null;
            $chapter['pages'] = [];
        }
        
        $chapter['adjacent_chapters'] = $this->chapters->findAdjacentChapters($contentId, (string) $chapter['chapter_number']);
        $chapter = \App\DTO\ChapterDto::fromArray($chapter)->toArray();
        $chapter['access'] = $access;
        $chapter['price_coin'] = (int) ($access['chapter_unlock_price'] ?? 0);
        $chapter['is_locked'] = !(bool) ($access['granted'] ?? false);

        return $chapter;
    }

    public function markRead(string $userId, string $chapterId): void
    {
        $chapter = $this->chapters->findById($chapterId);
        if ($chapter === null) {
            throw new \DomainException('Chapter not found');
        }

        $contentId = (string) ($chapter['content_id'] ?? '');
        $this->analytics->track(
            'chapter_read',
            $userId,
            'chapter',
            $chapterId,
            $contentId !== '' ? ['content_id' => $contentId] : []
        );
        $this->chapters->markRead($userId, $chapterId);
    }

    private function toDbType(string $typeSegment): string
    {
        $normalized = strtolower(trim($typeSegment));
        if (!array_key_exists($normalized, self::TYPE_SEGMENT_TO_DB)) {
            throw new \DomainException('Invalid content type');
        }

        return self::TYPE_SEGMENT_TO_DB[$normalized];
    }
}
