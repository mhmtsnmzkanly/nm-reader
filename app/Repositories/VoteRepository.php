<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Unified Repository for polymorphic voting operations across all entity types.
 *
 * Handles raw SQL interactions for upvotes (+1) and downvotes (-1) on blogs,
 * comments, and future voteable targets.
 *
 * @package App\Repositories
 */
final class VoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retrieves the specific vote value (-1, 1) a user has cast on a target entity.
     */
    public function findUserVote(string $userId, string $targetType, string $targetId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT vote FROM votes WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['vote'];
    }

    /**
     * Records or updates a user's vote on a target entity.
     */
    public function setVote(string $userId, string $targetType, string $targetId, int $vote): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO votes (user_id, target_type, target_id, vote, created_at, updated_at)
             VALUES (:user_id, :target_type, :target_id, :vote, NOW(), NOW())
             ON DUPLICATE KEY UPDATE vote = VALUES(vote), updated_at = NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'vote' => $vote,
        ]);
    }

    /**
     * Removes a user's vote from a target entity.
     */
    public function removeVote(string $userId, string $targetType, string $targetId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM votes WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
    }

    /**
     * Aggregates total upvotes and downvotes for a target entity.
     *
     * @return array{my_vote: int, upvote_count: int, downvote_count: int, score: int}
     */
    public function getSummary(string $targetType, string $targetId, ?string $viewerUserId = null): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) AS upvote_count,
                SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) AS downvote_count
             FROM votes
             WHERE target_type = :target_type AND target_id = :target_id'
        );
        $stmt->execute([
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
        $totals = $stmt->fetch() ?: [];

        $myVote = 0;
        if ($viewerUserId !== null) {
            $myVote = $this->findUserVote($viewerUserId, $targetType, $targetId) ?? 0;
        }

        $up = (int) ($totals['upvote_count'] ?? 0);
        $down = (int) ($totals['downvote_count'] ?? 0);

        return [
            'my_vote' => $myVote,
            'upvote_count' => $up,
            'downvote_count' => $down,
            'score' => $up - $down,
        ];
    }
}
