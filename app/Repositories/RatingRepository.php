<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Content Rating database operations.
 *
 * Manages user-to-content ratings and handles the aggregation of 
 * average scores and total counts back into the main series table.
 *
 * @package App\Repositories
 */
final class RatingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Submits or updates a user's rating for a specific content entry.
     *
     * @param string $userId
     * @param string $contentId
     * @param int $rating Score (usually 1-5).
     */
    public function upsert(string $userId, string $contentId, int $rating): void
    {
        $sql = 'INSERT INTO series_ratings (user_id, content_id, rating)
                VALUES (:user_id, :content_id, :rating)
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    updated_at = NOW()';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
            'rating' => $rating,
        ]);
    }

    /**
     * Recalculates and updates the average rating and total count for a content entry.
     *
     * This syncs data from the 'series_ratings' table back to the 'series' table for performance.
     *
     * @param string $contentId
     */
    public function refreshContentSummary(string $contentId): void
    {
        $sql = 'UPDATE series c
                INNER JOIN (
                    SELECT 
                        content_id,
                        AVG(rating) AS rating_avg,
                        COUNT(id) AS rating_count
                    FROM series_ratings
                    WHERE content_id = :content_id
                    GROUP BY content_id
                ) r ON r.content_id = c.id
                SET c.rating_avg = ROUND(r.rating_avg, 2),
                    c.rating_count = r.rating_count
                WHERE c.id = :content_id_2';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'content_id' => $contentId,
            'content_id_2' => $contentId,
        ]);
    }
}
