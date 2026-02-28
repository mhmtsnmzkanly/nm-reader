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
     */
    public function __construct(
        public readonly string $id,
        public readonly string $contentId,
        public readonly string $chapterNumber,
        public readonly ?string $title,
        public readonly string $type,
        public readonly string $createdAt
    ) {
    }

    /**
     * Factory method to create a DTO instance from a database row.
     *
     * @param array $row Associative array from PDO fetch.
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            contentId: (string) $row['content_id'],
            chapterNumber: ChapterNumber::normalize($row['chapter_number'] ?? ''),
            title: $row['title'] ?? null,
            type: (string) $row['type'],
            createdAt: (string) $row['created_at']
        );
    }

    /**
     * Converts the DTO properties back to a primitive associative array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content_id' => $this->contentId,
            'chapter_number' => $this->chapterNumber,
            'title' => $this->title,
            'type' => $this->type,
            'created_at' => $this->createdAt,
        ];
    }
}
