<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Comment-related database operations.
 *
 * Handles raw SQL interactions for user comments on chapters, series, and blogs.
 * Supports threaded replies via 'parent_id' and includes feature detection
 * for newer schema columns like 'blog_id'.
 *
 * @package App\Repositories
 */
final class CommentRepository
{
    /** @var bool|null Cache for checking existence of 'blog_id' column in comments table. */
    private ?bool $commentsHasBlogId = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Inserts a new comment into the database.
     *
     * Handles backward compatibility for schemas without an explicit 'blog_id' column
     * by mapping blog comments to the 'content_id' field.
     *
     * @param string $userId
     * @param string|null $contentId Link to a series.
     * @param string|null $chapterId Link to a specific chapter.
     * @param string $body Comment text.
     * @param int|null $parentId ID of the parent comment for replies.
     * @param int|null $blogId Link to a blog post.
     * @return int The ID of the created comment.
     */
    public function addComment(
        string $userId,
        ?string $contentId,
        ?string $chapterId,
        string $body,
        ?int $parentId = null,
        ?int $blogId = null
    ): int {
        $hasBlogId = $this->commentsHasBlogId();
        if (!$hasBlogId && $blogId !== null && $contentId === null) {
            // Backward compatibility for schemas where blog comments are stored in content_id.
            $contentId = (string) $blogId;
        }

        $sql = $hasBlogId
            ? 'INSERT INTO social_comments (user_id, content_id, chapter_id, blog_id, parent_id, body)
               VALUES (:user_id, :content_id, :chapter_id, :blog_id, :parent_id, :body)'
            : 'INSERT INTO social_comments (user_id, content_id, chapter_id, parent_id, body)
               VALUES (:user_id, :content_id, :chapter_id, :parent_id, :body)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':content_id', $contentId, $contentId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':chapter_id', $chapterId, $chapterId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        if ($hasBlogId) {
            $stmt->bindValue(':blog_id', $blogId, $blogId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        }
        $stmt->bindValue(':parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':body', $body);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lists comments for a blog post, including user vote status.
     */
    public function getByBlogId(int $blogId, int $page, int $perPage, ?string $viewerUserId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = $this->commentsHasBlogId()
            ? 'c.blog_id = :blog_id'
            : 'c.content_id = :blog_id_legacy';
        $sql = 'SELECT 
                    c.id,
                    c.parent_id,
                    c.body,
                    c.upvote_count,
                    c.downvote_count,
                    c.created_at,
                    u.id AS user_id,
                    u.username,
                    cv.vote AS my_vote
                FROM social_comments c
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN comment_votes cv
                    ON cv.comment_id = c.id
                   AND cv.user_id = :viewer_user_id
                WHERE ' . $where . '
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':viewer_user_id', $viewerUserId, $viewerUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        if ($this->commentsHasBlogId()) {
            $stmt->bindValue(':blog_id', $blogId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':blog_id_legacy', (string) $blogId, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists comments for a chapter, including user vote status.
     */
    public function getByChapterId(string $chapterId, int $page, int $perPage, ?string $viewerUserId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    c.id,
                    c.parent_id,
                    c.body,
                    c.upvote_count,
                    c.downvote_count,
                    c.created_at,
                    u.id AS user_id,
                    u.username,
                    cv.vote AS my_vote
                FROM social_comments c
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN comment_votes cv
                    ON cv.comment_id = c.id
                   AND cv.user_id = :viewer_user_id
                WHERE c.chapter_id = :chapter_id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':viewer_user_id', $viewerUserId, $viewerUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':chapter_id', $chapterId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists comments for a series (legacy or general content comments).
     */
    public function getByContentId(string $contentId, int $page, int $perPage, ?string $viewerUserId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    c.id,
                    c.parent_id,
                    c.body,
                    c.upvote_count,
                    c.downvote_count,
                    c.created_at,
                    u.id AS user_id,
                    u.username,
                    cv.vote AS my_vote
                FROM social_comments c
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN comment_votes cv
                    ON cv.comment_id = c.id
                   AND cv.user_id = :viewer_user_id
                WHERE c.content_id = :content_id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':viewer_user_id', $viewerUserId, $viewerUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':content_id', $contentId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Fetches a comment by its numeric ID.
     */
    public function findById(int $id): ?array
    {
        $sql = $this->commentsHasBlogId()
            ? 'SELECT id, user_id, chapter_id, content_id, blog_id, upvote_count, downvote_count
               FROM social_comments WHERE id = :id LIMIT 1'
            : 'SELECT id, user_id, chapter_id, content_id, content_id AS blog_id, upvote_count, downvote_count
               FROM social_comments WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Feature detection for Schema updates.
     */
    private function commentsHasBlogId(): bool
    {
        if ($this->commentsHasBlogId !== null) {
            return $this->commentsHasBlogId;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM social_comments LIKE 'blog_id'");
            $this->commentsHasBlogId = $stmt !== false && (bool) $stmt->fetch();
        } catch (\Throwable) {
            $this->commentsHasBlogId = false;
        }

        return $this->commentsHasBlogId;
    }
}
