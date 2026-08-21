<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RatingRepository;
use App\Repositories\SeriesRepository;
use App\Services\CacheService;
use App\Services\AnalyticsService;

/**
 * Service for managing user Ratings for content.
 *
 * This service allows users to rate series, ensures ratings are within
 * valid ranges, updates content aggregate statistics, and handles cache invalidation.
 *
 * @package App\Services
 */
final class RatingService
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
        private readonly RatingRepository $ratings,
        private readonly SeriesRepository $series,
        private readonly CacheService $cache,
        private readonly AnalyticsService $analytics
    ) {
    }

    /**
     * Submits or updates a rating for a specific content entry by slug.
     *
     * @param string $userId
     * @param string $slug Content identifier.
     * @param int $rating Value between 1 and 5.
     * @throws \InvalidArgumentException If rating is out of bounds.
     * @throws \DomainException If content is not found.
     */
    public function rate(string $userId, string $slug, int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        $contentId = $this->series->findContentIdBySlug($slug);
        if ($contentId === null) {
            throw new \DomainException('Content not found');
        }

        $this->ratings->upsert($userId, $contentId, $rating);
        $this->ratings->refreshContentSummary($contentId);
        $this->analytics->track('content_rate', $userId, 'content', $contentId, ['rating' => $rating]);
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->deleteByPrefix(sprintf('content_%s', $slug));
        $this->invalidateListingCaches();
    }

    /**
     * Submits or updates a rating filtered by content type and slug.
     *
     * @param string $userId
     * @param string $typeSegment
     * @param string $slug
     * @param int $rating
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function rateByType(string $userId, string $typeSegment, string $slug, int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        $dbType = $this->toDbType($typeSegment);
        $contentId = $this->series->findContentIdByTypeAndSlug($dbType, $slug);
        if ($contentId === null) {
            throw new \DomainException('Content not found');
        }

        $this->ratings->upsert($userId, $contentId, $rating);
        $this->ratings->refreshContentSummary($contentId);
        $this->analytics->track('content_rate', $userId, 'content', $contentId, ['rating' => $rating]);
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $dbType, $slug));
        $this->cache->deleteByPrefix(sprintf('content_%s_%s', $dbType, $slug));
        $this->invalidateListingCaches();
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

    private function toDbType(string $typeSegment): string
    {
        $normalized = strtolower(trim($typeSegment));
        if (!array_key_exists($normalized, self::TYPE_SEGMENT_TO_DB)) {
            throw new \DomainException('Invalid content type');
        }

        return self::TYPE_SEGMENT_TO_DB[$normalized];
    }
}
