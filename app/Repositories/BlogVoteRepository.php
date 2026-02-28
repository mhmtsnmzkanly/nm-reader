<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Blog Voting database operations.
 *
 * Handles raw SQL interactions for upvotes and downvotes on blog posts.
 * Includes methods for summarizing vote counts and individual user participation.
 *
 * @package App\Repositories
 */
final class BlogVoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retrieves the specific vote value (-1, 1) a user has cast on a blog.
     *
     * @return int|null Vote value or null if user hasn't voted.
     */
    public function findUserVote(string $userId, string $blogId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT vote FROM blog_votes WHERE user_id = :user_id AND blog_id = :blog_id LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'blog_id' => $blogId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['vote'];
    }

    /**
     * Records or updates a user's vote on a blog post.
     *
     * @param int $vote 1 for upvote, -1 for downvote.
     */
    public function setVote(string $userId, string $blogId, int $vote): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO blog_votes (user_id, blog_id, vote, created_at, updated_at)
             VALUES (:user_id, :blog_id, :vote, NOW(), NOW())
             ON DUPLICATE KEY UPDATE vote = VALUES(vote), updated_at = NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'blog_id' => $blogId,
            'vote' => $vote,
        ]);
    }

    /**
     * Removes a user's vote from a blog post.
     */
    public function removeVote(string $userId, string $blogId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM blog_votes WHERE user_id = :user_id AND blog_id = :blog_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'blog_id' => $blogId,
        ]);
    }

    /**
     * Aggregates total upvotes and downvotes for a blog.
     *
     * Also includes the 'my_vote' status if a viewer ID is provided.
     *
     * @param int $blogId
     * @param string|null $viewerUserId
     * @return array [my_vote, upvote_count, downvote_count, score]
     */
    public function getSummary(string $blogId, ?string $viewerUserId = null): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) AS upvote_count,
                SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) AS downvote_count
             FROM blog_votes
             WHERE blog_id = :blog_id'
        );
        $stmt->execute(['blog_id' => $blogId]);
        $totals = $stmt->fetch() ?: [];

        $myVote = 0;
        if ($viewerUserId !== null) {
            $myVote = $this->findUserVote($viewerUserId, $blogId) ?? 0;
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
