<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Comment Voting database operations.
 *
 * Handles raw SQL interactions for upvotes/downvotes on comments.
 * Manages aggregate counters, reputation statistics, and associated
 * user notifications for social feedback.
 *
 * @package App\Repositories
 */
final class CommentVoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retrieves the specific vote value (-1, 1) a user has cast on a comment.
     *
     * @return int|null Vote value or null if user hasn't voted.
     */
    public function findUserVote(string $userId, int $commentId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT vote FROM comment_votes WHERE user_id = :user_id AND comment_id = :comment_id LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'comment_id' => $commentId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : (int) $row['vote'];
    }

    /**
     * Records or updates a user's vote on a comment.
     *
     * @param int $vote 1 for upvote, -1 for downvote.
     */
    public function setVote(string $userId, int $commentId, int $vote): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO comment_votes (user_id, comment_id, vote, created_at, updated_at)
             VALUES (:user_id, :comment_id, :vote, NOW(), NOW())
             ON DUPLICATE KEY UPDATE vote = VALUES(vote), updated_at = NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'comment_id' => $commentId,
            'vote' => $vote,
        ]);
    }

    /**
     * Removes a user's vote from a comment.
     */
    public function removeVote(string $userId, int $commentId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM comment_votes WHERE user_id = :user_id AND comment_id = :comment_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'comment_id' => $commentId,
        ]);
    }

    /**
     * Recalculates and updates the cached upvote/downvote counters in the  'social_comments' table.
     */
    public function refreshCommentCounters(int $commentId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE social_comments c
             SET
                upvote_count = (SELECT COUNT(*) FROM comment_votes WHERE comment_id = :comment_id AND vote = 1),
                downvote_count = (SELECT COUNT(*) FROM comment_votes WHERE comment_id = :comment_id2 AND vote = -1)
             WHERE c.id = :comment_id3'
        );
        $stmt->execute([
            'comment_id' => $commentId,
            'comment_id2' => $commentId,
            'comment_id3' => $commentId,
        ]);
    }

    /**
     * Finds the primary owner (author) of a specific comment.
     */
    public function getCommentOwnerId(int $commentId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM social_comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $commentId]);
        $row = $stmt->fetch();

        return $row === false ? null : (string) $row['user_id'];
    }

    /**
     * Updates global voting statistics for a user.
     *
     * Aggregates total votes cast by the user AND total upvotes/downvotes
     * received by all comments owned by that user.
     */
    public function refreshUserVoteStats(string $userId): void
    {
        try {
            $sql = 'INSERT INTO analytics_users_votes (user_id, votes_cast, upvotes_received, downvotes_received, updated_at)
                    VALUES (
                        :user_id,
                        (SELECT COUNT(*) FROM comment_votes WHERE user_id = :user_id2),
                        (SELECT COALESCE(SUM(c.upvote_count), 0) FROM social_comments c WHERE c.user_id = :user_id3),
                        (SELECT COALESCE(SUM(c.downvote_count), 0) FROM social_comments c WHERE c.user_id = :user_id4),
                        NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        votes_cast = VALUES(votes_cast),
                        upvotes_received = VALUES(upvotes_received),
                        downvotes_received = VALUES(downvotes_received),
                        updated_at = NOW()';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'user_id2' => $userId,
                'user_id3' => $userId,
                'user_id4' => $userId,
            ]);
        } catch (\Throwable) {
            // Non-blocking background analytics update
        }
    }

    /**
     * Sends or updates a notification when a user's comment is voted on.
     */
    public function upsertVoteNotification(
        string $targetUserId,
        string $actorUserId,
        int $commentId,
        int $vote
    ): void {
        $title = $vote === 1 ? 'Yorumuna upvote geldi' : 'Yorumuna downvote geldi';
        $body = sprintf('Bir kullanici yorumunu %s etti.', $vote === 1 ? 'upvote' : 'downvote');
        $data = json_encode([
            'comment_id' => $commentId,
            'vote' => $vote,
            'source' => 'comment_vote',
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_notifications
             WHERE user_id = :user_id AND actor_user_id = :actor_user_id AND type = :type
               AND JSON_UNQUOTE(JSON_EXTRACT(data, "$.comment_id")) = :comment_id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'type' => 'comment_vote',
            'comment_id' => (string) $commentId,
        ]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            $update = $this->pdo->prepare(
                'UPDATE user_notifications
                 SET title = :title, body = :body, `data` = :data, is_read = 0, created_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'id' => (int) $existing['id'],
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, actor_user_id, type, title, body, `data`, is_read, created_at)
             VALUES (:user_id, :actor_user_id, :type, :title, :body, :data, 0, NOW())'
        );
        $insert->execute([
            'user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'type' => 'comment_vote',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    /**
     * Removes vote notifications when a vote is retracted.
     */
    public function removeVoteNotification(string $targetUserId, string $actorUserId, int $commentId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_notifications
             WHERE user_id = :user_id
               AND actor_user_id = :actor_user_id
               AND type = :type
               AND JSON_UNQUOTE(JSON_EXTRACT(data, "$.comment_id")) = :comment_id'
        );
        $stmt->execute([
            'user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'type' => 'comment_vote',
            'comment_id' => (string) $commentId,
        ]);
    }
}
