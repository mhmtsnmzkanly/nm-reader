<?php

declare(strict_types=1);

namespace App\DTO;

use App\Helpers\ChapterNumber;

/**
 * Data Transfer Object for Chapter information.
 *
 * Provides a strictly typed structure for chapter metadata.
 * Ensures that chapter numbers are normalized and all fields
 * are correctly cast before being passed to the frontend or views.
 *
 * @package App\DTO
 */
final class ChapterDto
{
    /**
     * @param string $id 6-character unique identifier.
     * @param string $contentId Reference to the parent content ID.
     * @param string $chapterNumber Normalized numeric string (e.g., "1.5").
     * @param string|null $title Optional chapter title.
     * @param string $type Either 'text' or 'image'.
     * @param string $createdAt ISO-formatted date string.
     * @param string|null $body Textual body for novels.
     * @param array $pages Image pages for manga.
     * @param array $navigation Next/Prev navigation info.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $contentId,
        public readonly string $chapterNumber,
        public readonly ?string $title,
        public readonly string $type,
        public readonly string $createdAt,
        public readonly ?string $body = null,
        public readonly array $pages = [],
        public readonly array $navigation = ['next' => null, 'prev' => null],
        public readonly ?string $seriesTitle = null,
        public readonly ?string $seriesSlug = null,
        public readonly ?string $seriesType = null,
        public readonly bool $isMembersOnly = false,
        public readonly bool $isAdult = false
    ) {
    }

    /**
     * Factory method to create a DTO instance from a database row.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            contentId: (string) $row['content_id'],
            chapterNumber: ChapterNumber::normalize($row['chapter_number'] ?? ''),
            title: $row['title'] ?? null,
            type: (string) $row['type'],
            createdAt: (string) ($row['created_at'] ?? ''),
            body: $row['body'] ?? null,
            pages: $row['pages'] ?? [],
            navigation: $row['adjacent_chapters'] ?? ['next' => null, 'prev' => null],
            seriesTitle: isset($row['series_title']) ? (string) $row['series_title'] : null,
            seriesSlug: isset($row['series_slug']) ? (string) $row['series_slug'] : null,
            seriesType: isset($row['series_type']) ? (string) $row['series_type'] : null,
            isMembersOnly: (bool) ($row['is_members_only'] ?? false),
            isAdult: (bool) ($row['is_adult'] ?? false)
        );
    }

    /**
     * Converts the DTO properties back to a primitive associative array.
     */
    public function toArray(?string $baseUrl = null): array
    {
        $data = [
            'id' => $this->id,
            'content_id' => $this->contentId,
            'chapter_number' => $this->chapterNumber,
            'title' => $this->title,
            'type' => $this->type,
            'created_at' => $this->createdAt,
            'body' => $this->body,
            'pages' => $this->pages,
            'adjacent_chapters' => $this->navigation,
            'is_members_only' => $this->isMembersOnly,
            'is_adult' => $this->isAdult,
        ];

        if ($this->seriesTitle !== null) {
            $data['series_title'] = $this->seriesTitle;
        }
        if ($this->seriesSlug !== null) {
            $data['series_slug'] = $this->seriesSlug;
        }
        if ($this->seriesType !== null) {
            $data['series_type'] = $this->seriesType;
        }

        return $data;
    }
}
