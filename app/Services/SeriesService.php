<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ChapterDto;
use App\DTO\ContentDto;
use App\Helpers\ChapterNumber;
use App\Helpers\OutputSanitizer;
use App\Repositories\BlogRepository;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;

/**
 * Service for managing Series (Content) and Chapters.
 *
 * This service handles data retrieval for the homepage, content details,
 * chapter listings, search functionality, and user content follows.
 * It integrates caching and analytics tracking.
 *
 * @package App\Services
 */
final class SeriesService
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
        private readonly SeriesRepository $series,
        private readonly ChapterRepository $chapters,
        private readonly BlogRepository $blogs,
        private readonly CacheService $cache,
        private readonly AnalyticsService $analytics,
        private readonly WalletService $wallets
    ) {
    }

    /**
     * Aggregates data for the homepage.
     *
     * Includes popular content, recently updated chapters, recently added content,
     * and popular/latest blogs.
     *
     * @param int $page Pagination page.
     * @param int $perPage Items per page.
     * @return array Consolidated homepage data.
     */
    public function home(int $page, int $perPage): array
    {
        // 1. Explore Section (mixed/popular logic)
        $cacheKeyExplore = sprintf('homepage_explore_%d_%d', $page, $perPage);
        $exploreItems = $this->cache->remember($cacheKeyExplore, 120, fn () => $this->series->getHomepagePopular($page, 5));
        $explore = $this->mapAndAppendPaths($exploreItems);

        // 2. Recently Updated (individual latest chapters)
        $cacheKeyChapters = 'homepage_recent_chapters_5';
        $recentChapters = $this->cache->remember($cacheKeyChapters, 60, fn () => $this->series->getLatestChapters(1, 5));
        $recentChapters = array_map(function($row) {
            $row['type_path'] = $this->toTypeSegment((string)($row['series_type'] ?? 'novel'));
            $row['slug'] = (string)($row['series_slug'] ?? ''); // Ensure slug key exists
            $row['chapter_number'] = ChapterNumber::normalize($row['chapter_number'] ?? '');
            $row['cover_image'] = $row['cover_image'] ?: '/assets/img/logo.svg'; 
            return $row;
        }, $recentChapters);

        // 3. Recently Added (new content entries)
        $cacheKeyAdded = 'homepage_recently_added_5';
        $addedItems = $this->cache->remember($cacheKeyAdded, 120, fn () => $this->series->getRecentlyAdded(5));
        $recentlyAdded = $this->mapAndAppendPaths($addedItems);

        $popularBlogs = $this->cache->remember('home_popular_blogs_3', 120, fn () => $this->blogs->homePopular(3));
        $latestBlogs = $this->cache->remember('home_latest_blogs_3', 120, fn () => $this->blogs->homeLatest(3));

        return [
            'explore' => $explore,
            'recent_chapters' => $recentChapters,
            'recently_added' => $recentlyAdded,
            'popular_blogs' => $popularBlogs,
            'latest_blogs' => $latestBlogs,
        ];
    }

    private function mapAndAppendPaths(array $items): array
    {
        $mapped = array_map(static fn (array $row) => ContentDto::fromArray($row)->toArray(), $items);
        return array_map(fn (array $row) => $this->appendTypePathFields($row), $mapped);
    }

    /**
     * Retrieves detailed information for a specific content entry by slug.
     *
     * @param string $slug The content identifier.
     * @param string $ip Client IP for view recording.
     * @return array|null Content details or null if not found.
     */
    public function contentDetail(string $slug, string $ip): ?array
    {
        $cacheKey = sprintf('content_%s', $slug);

        $content = $this->cache->remember($cacheKey, 180, fn () => $this->series->findContentBySlug($slug));

        if ($content === null) {
            return null;
        }

        $this->series->recordContentView((string) $content['id'], hash('sha256', $ip));
        $this->analytics->track('content_view', null, 'content', (string) $content['id'], [], $ip);

        $parseTaxonomy = static function(?string $raw): array {
            if ($raw === null || $raw === '') return [];
            $items = [];
            foreach (explode('||', $raw) as $group) {
                $parts = explode('::', $group);
                if (count($parts) >= 2) {
                    $items[] = [
                        'name' => $parts[0],
                        'slug' => $parts[1],
                        'ui_config' => json_decode($parts[2] ?? '{}', true) ?: []
                    ];
                }
            }
            return $items;
        };

        $content['series_genres'] = $parseTaxonomy($content['series_genres_raw'] ?? null);
        $content['series_tags'] = $parseTaxonomy($content['series_tags_raw'] ?? null);

        $content = OutputSanitizer::sanitizeFields($content, ['title', 'description']);
        return $this->appendTypePathFields($content);
    }

    /**
     * Retrieves content details filtered by type segment.
     *
     * @param string $typeSegment URL segment (e.g., 'manga').
     * @param string $slug Content slug.
     * @param string $ip Client IP.
     * @param string|null $userId ID of the logged-in user (optional).
     * @return array|null Content details or null.
     */
    public function contentDetailByType(string $typeSegment, string $slug, string $ip, ?string $userId = null): ?array
    {
        $dbType = $this->toDbType($typeSegment);
        $cacheKey = sprintf('content_%s_%s_%s', $dbType, $slug, $userId ?? 'anon');
        $row = $this->cache->remember($cacheKey, 180, fn () => $this->series->findContentByTypeAndSlug($dbType, $slug, $userId));

        if ($row === null) {
            return null;
        }

        $this->series->recordContentView((string) $row['id'], hash('sha256', $ip));
        $this->analytics->track('content_view', $userId, 'content', (string) $row['id'], [], $ip);

        $content = ContentDto::fromArray($row)->toArray();

        $parseTaxonomy = static function(?string $raw): array {
            if ($raw === null || $raw === '') return [];
            $items = [];
            foreach (explode('||', $raw) as $group) {
                $parts = explode('::', $group);
                if (count($parts) >= 2) {
                    $items[] = [
                        'name' => $parts[0],
                        'slug' => $parts[1],
                        'ui_config' => json_decode($parts[2] ?? '{}', true) ?: []
                    ];
                }
            }
            return $items;
        };

        $content['series_genres'] = $parseTaxonomy($row['series_genres_raw'] ?? null);
        $content['series_tags'] = $parseTaxonomy($row['series_tags_raw'] ?? null);

        // Fetch reading progress if user is logged in
        $content['reading_progress'] = null;
        if ($userId !== null) {
            $content['reading_progress'] = $this->chapters->findReadingProgress($userId, (string)$row['id']);
        }

        $content = array_merge($content, $this->wallets->contentAccess((string) $row['id'], $userId));

        $content = OutputSanitizer::sanitizeFields($content, ['title', 'description']);
        return $this->appendTypePathFields($content);
    }

    /**
     * Fetches a paginated list of chapters for a specific content.
     *
     * @param string $slug Content slug.
     * @param int $page
     * @param int $perPage
     * @return array List of Chapter DTOs as arrays.
     */
    public function chapters(string $slug, int $page, int $perPage): array
    {
        $items = $this->series->getChaptersByContentSlug($slug, $page, $perPage);

        return array_map(static fn (array $row) => ChapterDto::fromArray($row)->toArray(), $items);
    }

    /**
     * Fetches chapters for content filtered by type.
     *
     * @param string $typeSegment
     * @param string $slug
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function chaptersByType(string $typeSegment, string $slug, int $page, int $perPage, ?string $userId = null): array
    {
        $dbType = $this->toDbType($typeSegment);
        $items = $this->series->getChaptersByTypeAndSlug($dbType, $slug, $page, $perPage);

        return array_map(function (array $row) use ($userId): array {
            $chapter = ChapterDto::fromArray($row)->toArray();
            $chapter['access'] = $this->wallets->chapterAccess((string) $row['content_id'], (string) $row['id'], $userId);
            $chapter['price_coin'] = (int) ($chapter['access']['chapter_unlock_price'] ?? 0);
            $chapter['is_locked'] = !(bool) ($chapter['access']['granted'] ?? false);
            return $chapter;
        }, $items);
    }

    /**
     * Lists content by its type (e.g., all manga).
     *
     * @param string $typeSegment
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function byType(string $typeSegment, int $page, int $perPage): array
    {
        $dbType = $this->toDbType($typeSegment);
        $cacheKey = sprintf('type_list_%s_%d_%d', $dbType, $page, $perPage);
        $items = $this->cache->remember($cacheKey, 180, fn () => $this->series->getByType($dbType, $page, $perPage));
        $mapped = array_map(static fn (array $row) => ContentDto::fromArray($row)->toArray(), $items);

        return array_map(fn (array $row) => $this->appendTypePathFields($row), $mapped);
    }

    /**
     * Lists content by a specific genre slug.
     *
     * @param string $slug Genre identifier.
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function byGenre(string $slug, int $page, int $perPage): array
    {
        $cacheKey = sprintf('genre_list_%s_%d_%d', $slug, $page, $perPage);
        $items = $this->cache->remember($cacheKey, 180, fn () => $this->series->getByGenreSlug($slug, $page, $perPage));
        $mapped = array_map(static fn (array $row) => ContentDto::fromArray($row)->toArray(), $items);

        return array_map(fn (array $row) => $this->appendTypePathFields($row), $mapped);
    }

    /**
     * Lists content by a specific tag slug.
     *
     * @param string $slug Tag identifier.
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function byTag(string $slug, int $page, int $perPage): array
    {
        $cacheKey = sprintf('tag_list_%s_%d_%d', $slug, $page, $perPage);
        $items = $this->cache->remember($cacheKey, 180, fn () => $this->series->getByTagSlug($slug, $page, $perPage));
        $mapped = array_map(static fn (array $row) => ContentDto::fromArray($row)->toArray(), $items);

        return array_map(fn (array $row) => $this->appendTypePathFields($row), $mapped);
    }

    /**
     * Fetches all available series_genres.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function series_genres(int $page, int $perPage): array
    {
        $cacheKey = sprintf('genres_%d_%d', $page, $perPage);
        return $this->cache->remember($cacheKey, 180, fn () => $this->series->getGenres($page, $perPage));
    }

    /**
     * Fetches all available series_tags.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function series_tags(int $page, int $perPage): array
    {
        $cacheKey = sprintf('tags_%d_%d', $page, $perPage);
        return $this->cache->remember($cacheKey, 180, fn () => $this->series->getTags($page, $perPage));
    }

    /**
     * Gets fast autocomplete suggestions for a search query.
     */
    public function suggest(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'search_suggest_' . md5($query);
        return $this->cache->remember($cacheKey, 1800, fn () => $this->series->suggest($query, 8));
    }

    /**
     * Performs a text-based search with advanced filters across content.
     *
     * @param string $query Search term.
     * @param int $page
     * @param int $perPage
     * @param array $filters Advanced filters.
     * @return array
     */
    public function search(string $query, int $page, int $perPage, array $filters = []): array
    {
        $items = $this->series->search($query, $page, $perPage, $filters);
        $mapped = array_map(static fn (array $row) => ContentDto::fromArray($row)->toArray(), $items);

        return array_map(fn (array $row) => $this->appendTypePathFields($row), $mapped);
    }

    /**
     * Logs a search event for analytics purposes.
     *
     * @param string $query
     * @param int $resultCount
     * @param string|null $userId
     * @param string $ip
     */
    public function logSearch(string $query, int $resultCount, ?string $userId, string $ip): void
    {
        if ($query === '') {
            return;
        }

        $this->analytics->track(
            'search',
            $userId,
            'search',
            null,
            [
                'query' => mb_substr($query, 0, 120),
                'result_count' => $resultCount,
            ],
            $ip
        );
        $this->series->logSearchQuery($query, $resultCount, $userId, hash('sha256', $ip));
    }

    /**
     * Allows a user to follow a specific content (series).
     *
     * @param string $userId
     * @param string $slug Content identifier.
     * @throws \DomainException If content is not found.
     */
    public function follow(string $userId, string $slug): void
    {
        $contentId = $this->series->findContentIdBySlug($slug);
        if ($contentId === null) {
            throw new \DomainException('Content not found');
        }

        $this->series->followContent($userId, $contentId);
        $this->analytics->track('content_follow', $userId, 'content', $contentId);
        $this->clearContentCache($slug);
    }

    /**
     * Follows content filtered by type.
     *
     * @param string $userId
     * @param string $typeSegment
     * @param string $slug
     * @throws \DomainException
     */
    public function followByType(string $userId, string $typeSegment, string $slug): void
    {
        $dbType = $this->toDbType($typeSegment);
        $contentId = $this->series->findContentIdByTypeAndSlug($dbType, $slug);
        if ($contentId === null) {
            throw new \DomainException('Content not found');
        }

        $this->series->followContent($userId, $contentId);
        $this->analytics->track('content_follow', $userId, 'content', $contentId);
        $this->clearContentCacheByType($typeSegment, $slug);
    }

    /**
     * Allows a user to unfollow a specific content.
     *
     * @param string $userId
     * @param string $typeSegment
     * @param string $slug
     * @throws \DomainException
     */
    public function unfollowByType(string $userId, string $typeSegment, string $slug): void
    {
        $dbType = $this->toDbType($typeSegment);
        $contentId = $this->series->findContentIdByTypeAndSlug($dbType, $slug);
        if ($contentId === null) {
            throw new \DomainException('Content not found');
        }

        $this->series->unfollowContent($userId, $contentId);
        $this->clearContentCacheByType($typeSegment, $slug);
    }

    /**
     * Lists content currently followed by a user.
     *
     * @param string $userId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function followedContents(string $userId, int $page, int $perPage): array
    {
        $rows = $this->series->listFollowedContents($userId, $page, $perPage);
        $mapped = array_map(static fn (array $row) => ContentDto::fromArray($row)->toArray(), $rows);
        return array_map(fn (array $row) => $this->appendTypePathFields($row), $mapped);
    }

    /**
     * Clears cache for a specific content and all listing caches.
     *
     * @param string $slug
     */
    public function clearContentCache(string $slug): void
    {
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->invalidateListingCaches();
    }

    /**
     * Clears content cache by type and slug.
     *
     * @param string $typeSegment
     * @param string $slug
     */
    public function clearContentCacheByType(string $typeSegment, string $slug): void
    {
        $dbType = $this->toDbType($typeSegment);
        $this->cache->delete(sprintf('content_%s_%s', $dbType, $slug));
        $this->invalidateListingCaches();
    }

    /**
     * Increments the comment count for a specific content.
     *
     * @param string $slug
     */
    public function incrementCommentCount(string $slug): void
    {
        $contentId = $this->series->findContentIdBySlug($slug);
        if ($contentId !== null) {
            $this->series->incrementCommentCount($contentId);
        }
    }

    /**
     * Maps a URL type segment to its corresponding database value.
     *
     * @param string $typeSegment (e.g., 'light-novel')
     * @return string (e.g., 'light_novel')
     * @throws \DomainException If type is invalid.
     */
    public function toDbType(string $typeSegment): string
    {
        $normalized = strtolower(trim($typeSegment));
        if (!array_key_exists($normalized, self::TYPE_SEGMENT_TO_DB)) {
            throw new \DomainException('Invalid content type');
        }

        return self::TYPE_SEGMENT_TO_DB[$normalized];
    }

    /**
     * Converts a database type to a URL-friendly type segment.
     *
     * @param string $dbType
     * @return string
     */
    public function toTypeSegment(string $dbType): string
    {
        return str_replace('_', '-', $dbType);
    }

    private function appendTypePathFields(array $content): array
    {
        $type = (string) ($content['type'] ?? 'novel');
        $typeSegment = $this->toTypeSegment($type);
        $slug = (string) ($content['slug'] ?? '');

        $content['type_path'] = $typeSegment;
        $content['url_path'] = sprintf('/%s/%s', $typeSegment, $slug);

        return $content;
    }

    /**
     * Retrieves full chapter details including its content (text or pages).
     *
     * @param string $typeSegment Content type.
     * @param string $slug Content slug.
     * @param string $chapterNumber
     * @param string $ip Client IP for view tracking.
     * @return array|null Chapter details or null.
     */
    public function chapterDetailByTypeSlugAndNumber(string $typeSegment, string $slug, string $chapterNumber, string $ip): ?array
    {
        $dbType = $this->toDbType($typeSegment);
        $chapter = $this->chapters->findByTypeSlugAndChapterNumber($dbType, $slug, $chapterNumber);
        if (!is_array($chapter)) {
            return null;
        }

        $chapterId = (string) $chapter['id'];
        $this->chapters->recordChapterView($chapterId, hash('sha256', $ip));
        $this->analytics->track('chapter_view', null, 'chapter', $chapterId, [], $ip);

        $content = $this->series->findContentByTypeAndSlug($dbType, $slug);
        if (is_array($content)) {
            $chapter['series_title'] = (string) ($content['title'] ?? '');
            $chapter['series_slug'] = (string) ($content['slug'] ?? $slug);
            $chapter['series_type'] = (string) ($content['type'] ?? $dbType);
        }

        if ($chapter['type'] === 'text') {
            $chapter['body'] = $this->chapters->findChapterText($chapterId) ?? '';
            $chapter['pages'] = [];
        } else {
            $chapter['body'] = null;
            $chapter['pages'] = $this->chapters->findChapterPages($chapterId);
        }
        $chapter['chapter_number'] = ChapterNumber::normalize($chapter['chapter_number'] ?? '');
        $chapter['adjacent_chapters'] = $this->chapters->findAdjacentChapters((string) ($chapter['content_id'] ?? ''), (string) $chapter['chapter_number']);

        return $chapter;
    }

    /**
     * Fetches globally latest chapters.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function latestChapters(int $page, int $perPage): array
    {
        $cacheKey = sprintf('latest_chapters_%d_%d', $page, $perPage);
        $rows = $this->cache->remember($cacheKey, 60, fn () => $this->series->getLatestChapters($page, $perPage));
        return array_map(function($row) {
            $row['type_path'] = $this->toTypeSegment((string)($row['series_type'] ?? 'novel'));
            $row['chapter_number'] = ChapterNumber::normalize($row['chapter_number'] ?? '');
            return $row;
        }, $rows);
    }

    /**
     * Fetches latest chapters filtered by content type.
     *
     * @param string $typeSegment
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function latestChaptersByType(string $typeSegment, int $page, int $perPage): array
    {
        $dbType = $this->toDbType($typeSegment);
        $cacheKey = sprintf('latest_chapters_%s_%d_%d', $dbType, $page, $perPage);
        $rows = $this->cache->remember($cacheKey, 60, fn () => $this->series->getLatestChaptersByType($dbType, $page, $perPage));

        return array_map(function ($row) {
            $row['type_path'] = $this->toTypeSegment((string) ($row['series_type'] ?? 'novel'));
            $row['chapter_number'] = ChapterNumber::normalize($row['chapter_number'] ?? '');
            return $row;
        }, $rows);
    }

    private function invalidateListingCaches(): void
    {
        $this->cache->deleteByPrefix('homepage_popular_');
        $this->cache->deleteByPrefix('type_list_');
        $this->cache->deleteByPrefix('genre_list_');
        $this->cache->deleteByPrefix('tag_list_');
        $this->cache->deleteByPrefix('latest_chapters_');
        $this->cache->deleteByPrefix('genres_');
        $this->cache->deleteByPrefix('tags_');
    }
}
