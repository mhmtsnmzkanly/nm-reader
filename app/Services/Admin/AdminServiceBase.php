<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Helpers\ChapterNumber;
use App\Helpers\Validator;
use App\Repositories\AdminConsoleRepository;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\WalletRepository;
use App\Services\AdminConsoleService;
use App\Services\CacheService;
use App\Services\ContentSecurityScanner;
use App\Services\EntityIdService;
use App\Services\QueueService;
use App\Services\SlugService;
use PDO;

/** Shared infrastructure for extracted administrative content services. */
abstract class AdminServiceBase
{
protected const TYPE_SEGMENT_TO_DB = [
        'light-novel' => 'light_novel',
        'web-novel' => 'web_novel',
        'novel' => 'novel',
        'manga' => 'manga',
        'manhua' => 'manhua',
        'manhwa' => 'manhwa',
        'webtoon' => 'webtoon',
    ];

    protected const ALLOWED_STATUSES = ['ongoing', 'completed', 'hiatus', 'dropped'];
    protected const ALLOWED_LIFECYCLE_STATUSES = ['draft', 'scheduled', 'published', 'archived'];
    protected const ALLOWED_CHAPTER_TYPES = ['text', 'image'];

    public function __construct(
        protected readonly PDO $pdo,
        protected readonly SeriesRepository $series,
        protected readonly ChapterRepository $chapters,
        protected readonly WalletRepository $wallets,
        protected readonly EntityIdService $entityIds,
        protected readonly SlugService $slugService,
        protected readonly CacheService $cache,
        protected readonly QueueService $queue,
        protected readonly AdminConsoleService $adminConsole,
        protected readonly ContentSecurityScanner $scanner,
    ) {
    }

    protected function contentSnapshot(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM series WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected function recordSeriesRevision(string $id, ?string $moderatorId, string $action, ?array $snapshot = null): void
    {
        $snapshot ??= $this->contentSnapshot($id);
        if ($snapshot === null) return;
        $stmt = $this->pdo->prepare(
            'INSERT INTO series_revisions (series_id, moderator_user_id, action, snapshot_json)
             VALUES (:series_id, :moderator_id, :action, :snapshot)'
        );
        $stmt->execute([
            'series_id' => $id,
            'moderator_id' => $moderatorId,
            'action' => $action,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    protected function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid date/time');
        }
    }

    protected function upsertContentMetadata(string $contentId, ?string $author, ?string $artist, ?string $alternativeTitles, ?string $country, ?int $releaseYear): void
    {
        try {
            $this->pdo->prepare(
                'UPDATE series
                 SET author = :author, artist = :artist, alternative_titles = :alternative_titles, country = :country, release_year = :release_year
                 WHERE id = :content_id'
            )->execute([
                'content_id' => $contentId,
                'author' => $author,
                'artist' => $artist,
                'alternative_titles' => $alternativeTitles,
                'country' => $country,
                'release_year' => $releaseYear !== null ? (string) $releaseYear : null,
            ]);
        } catch (\Throwable) {}
    }

    protected function sanitizePerson(string $value): ?string
    {
        $v = trim(Validator::sanitizeText($value));
        return $v === '' ? null : mb_substr($v, 0, 120);
    }

    protected function sanitizeCountry(string $value): ?string
    {
        $v = strtoupper(trim(Validator::sanitizeText($value)));
        if ($v === '') {
            return null;
        }
        return mb_substr($v, 0, 64);
    }

    protected function sanitizeYear(string $value): ?int
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        $year = (int) $v;
        if ($year < 1800 || $year > ((int) date('Y') + 1)) {
            throw new \InvalidArgumentException('release_year must be between 1800 and next year');
        }
        return $year;
    }

    protected function toDbType(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (isset(self::TYPE_SEGMENT_TO_DB[$normalized])) {
            return self::TYPE_SEGMENT_TO_DB[$normalized];
        }
    
        $dbStyle = str_replace('-', '_', $normalized);
        if (in_array($dbStyle, self::TYPE_SEGMENT_TO_DB, true)) {
            return $dbStyle;
        }
    
        throw new \InvalidArgumentException('Invalid content type');
    }

    protected function normalizePagesPayload(mixed $value): array
    {
        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            return array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                $lines
            ), static fn (string $line): bool => $line !== ''));
        }
    
        if (!is_array($value)) {
            return [];
        }
    
        $pages = [];
        foreach (array_values($value) as $item) {
            if (is_array($item)) {
                $path = trim((string) ($item['image_path'] ?? $item['url'] ?? ''));
            } else {
                $path = trim((string) $item);
            }
            if ($path !== '') {
                $pages[] = $path;
            }
        }
    
        return $pages;
    }

    protected function assertValidChapterPagePath(string $path): void
    {
        if (mb_strlen($path) > 255 || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new \InvalidArgumentException('Invalid chapter page path');
        }
    
        $isSafeLocal = str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '..');
        // Older chapter records may contain a basename (for example
        // `chapter.1_01.webp`) instead of the newer `/media/public/...` URL.
        // Keep those safe relative paths editable while rejecting traversal or
        // scheme-like values.
        $isSafeRelative = preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $path) === 1
            && !str_contains($path, '..');
        $scheme = strtolower((string) parse_url($path, PHP_URL_SCHEME));
        $isRemote = in_array($scheme, ['http', 'https'], true);
        if (!$isSafeLocal && !$isSafeRelative && !$isRemote) {
            throw new \InvalidArgumentException('Chapter page paths must be local or http(s) URLs');
        }
    }

    protected function invalidateListingCaches(): void
    {
        $this->cache->delete('sitemap_xml');
        $this->cache->deleteByPrefix('homepage_popular_');
        $this->cache->deleteByPrefix('type_list_');
        $this->cache->deleteByPrefix('genre_list_');
        $this->cache->deleteByPrefix('tag_list_');
        $this->cache->deleteByPrefix('latest_chapters_');
        $this->cache->deleteByPrefix('genres_');
        $this->cache->deleteByPrefix('tags_');
    }

    protected function clearContentCaches(string $slug, string $type): void
    {
        $typeSegment = str_replace('_', '-', $type);
    
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $type, $slug));
        $this->cache->delete(sprintf('content_%s_%s', $typeSegment, $slug));
    
        $this->cache->deleteByPrefix(sprintf('content_%s_%s_', $type, $slug));
        $this->cache->deleteByPrefix(sprintf('content_%s_%s_', $typeSegment, $slug));
    }
}
