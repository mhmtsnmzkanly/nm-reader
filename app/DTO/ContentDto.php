<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for Content (Series) information.
 *
 * Consolodates all core data, statistics, and metadata for a series
 * into a strictly typed immutable structure. 
 *
 * @package App\DTO
 */
final class ContentDto
{
    /**
     * @param string $id 8-character entity ID.
     * @param string $title Series title.
     * @param string $slug Unique URL-friendly identifier.
     * @param string $type Content type (manga, novel, etc.).
     * @param string $status Current status (ongoing, completed, etc.).
     * @param float $ratingAvg Average user rating (0.0 to 5.0).
     * @param int $ratingCount Total number of ratings.
     * @param int $chapterCount Total number of chapters.
     * @param int $commentCount Total number of comments.
     * @param string|null $coverImage Relative path to cover image asset.
     * @param string|null $accentColor Hex color code for placeholders.
     * @param bool $isFollowed Whether the current viewer follows this series.
     * @param string|null $author Primary creator name.
     * @param string|null $artist Primary illustrator name.
     * @param string|null $alternativeTitles Alternative titles (comma separated).
     * @param string|null $country Origin country code or name.
     * @param string|null $releaseYear Publication year.
     * @param string|null $description Full series synopsis.
     * @param string|null $createdAt ISO date string.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $type,
        public readonly string $status,
        public readonly float $ratingAvg,
        public readonly int $ratingCount,
        public readonly int $chapterCount,
        public readonly int $commentCount,
        public readonly ?string $coverImage,
        public readonly ?string $accentColor = '#2a2a2a',
        public readonly bool $isFollowed = false,
        public readonly ?string $author = null,
        public readonly ?string $artist = null,
        public readonly ?string $alternativeTitles = null,
        public readonly ?string $country = null,
        public readonly ?string $releaseYear = null,
        public readonly ?string $description = null,
        public readonly ?string $createdAt = null
    ) {
    }

    /**
     * Factory method to populate DTO from database result set.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            type: (string) ($row['type'] ?? ''),
            status: (string) ($row['status'] ?? 'ongoing'),
            ratingAvg: (float) ($row['rating_avg'] ?? 0.0),
            ratingCount: (int) ($row['rating_count'] ?? 0),
            chapterCount: (int) ($row['chapter_count'] ?? 0),
            commentCount: (int) ($row['comment_count'] ?? 0),
            coverImage: $row['cover_image'] ?? null,
            accentColor: $row['accent_color'] ?? '#2a2a2a',
            isFollowed: (bool) ($row['is_followed'] ?? false),
            author: $row['author'] ?? null,
            artist: $row['artist'] ?? null,
            alternativeTitles: $row['alternative_titles'] ?? null,
            country: $row['country'] ?? null,
            releaseYear: (string) ($row['release_year'] ?? ''),
            description: $row['description'] ?? null,
            createdAt: $row['created_at'] ?? null
        );
    }

    /**
     * Converts properties back to an associative array for JSON/view consumption.
     */
    public function toArray(?string $baseUrl = null): array
    {
        $cover = $this->coverImage;
        if ($baseUrl && $cover && !str_starts_with($cover, 'http')) {
            $cover = rtrim($baseUrl, '/') . '/' . ltrim($cover, '/');
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'status' => $this->status,
            'rating_avg' => $this->ratingAvg,
            'rating_count' => $this->ratingCount,
            'chapter_count' => $this->chapterCount,
            'comment_count' => $this->commentCount,
            'cover_image' => $cover,
            'accent_color' => $this->accentColor,
            'is_followed' => $this->isFollowed,
            'author' => $this->author,
            'artist' => $this->artist,
            'alternative_titles' => $this->alternativeTitles,
            'country' => $this->country,
            'release_year' => $this->releaseYear,
            'description' => $this->description,
            'created_at' => $this->createdAt,
        ];
    }
}
