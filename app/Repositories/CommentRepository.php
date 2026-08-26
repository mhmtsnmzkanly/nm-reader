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

    /** @var bool|null Cache for checking existence of 'target_type' column in comments table. */
    private ?bool $commentsHasTargetType = null;

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
    /**
     * Inserts a new comment into the database.
     *
     * @param string $userId
     * @param string|null $contentId Link to a series.
     * @param string|null $chapterId Link to a specific chapter.
     * @param string $body Comment text.
     * @param int|null $parentId ID of the parent comment for replies.
     * @param string|null $blogId Link to a blog post.
     * @return int The ID of the created comment.
     */
    public function addComment(
        string $userId,
        ?string $contentId,
        ?string $chapterId,
        string $body,
        ?int $parentId = null,
        ?string $blogId = null
    ): int {
        $targetType = 'series';
        $targetId = (string) ($contentId ?? '');
        if ($chapterId !== null && $chapterId !== '') {
            $targetType = 'chapter';
            $targetId = (string) $chapterId;
        } elseif ($blogId !== null && $blogId !== '') {
            $targetType = 'blog';
            $targetId = (string) $blogId;
        }

        $sql = 'INSERT INTO comments (user_id, target_type, target_id, parent_id, body)
                VALUES (:user_id, :target_type, :target_id, :parent_id, :body)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':target_type', $targetType, PDO::PARAM_STR);
        $stmt->bindValue(':target_id', $targetId, PDO::PARAM_STR);
        $stmt->bindValue(':parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':body', $body);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lists comments for a blog post, including user vote status.
     */
    public function getByBlogId(string $blogId, int $page, int $perPage, ?string $viewerUserId = null, ?string $cursorCreatedAt = null, ?int $cursorId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $whereParts = ['c.target_type = "blog" AND c.target_id = :blog_id'];
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $whereParts[] = '(c.created_at < :cursor_created OR (c.created_at = :cursor_created AND c.id < :cursor_id))';
        }
        $where = implode(' AND ', $whereParts);
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
                FROM comments c
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN votes cv
                    ON cv.target_type = "comment"
                   AND cv.target_id = c.id
                   AND cv.user_id = :viewer_user_id
                WHERE ' . $where . '
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT :limit' . ($cursorCreatedAt !== null && $cursorId !== null ? '' : ' OFFSET :offset');

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':viewer_user_id', $viewerUserId, $viewerUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':blog_id', $blogId, PDO::PARAM_STR);
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $stmt->bindValue(':cursor_created', $cursorCreatedAt, PDO::PARAM_STR);
            $stmt->bindValue(':cursor_id', $cursorId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        if ($cursorCreatedAt === null || $cursorId === null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists comments for a chapter, including user vote status.
     */
    public function getByChapterId(string $chapterId, int $page, int $perPage, ?string $viewerUserId = null, ?string $cursorCreatedAt = null, ?int $cursorId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $whereParts = ['c.target_type = "chapter" AND c.target_id = :chapter_id'];
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $whereParts[] = '(c.created_at < :cursor_created OR (c.created_at = :cursor_created AND c.id < :cursor_id))';
        }
        $where = implode(' AND ', $whereParts);
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
                FROM comments c
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN votes cv
                    ON cv.target_type = "comment"
                   AND cv.target_id = c.id
                   AND cv.user_id = :viewer_user_id
                WHERE ' . $where . '
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT :limit' . ($cursorCreatedAt !== null && $cursorId !== null ? '' : ' OFFSET :offset');

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':viewer_user_id', $viewerUserId, $viewerUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':chapter_id', $chapterId, PDO::PARAM_STR);
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $stmt->bindValue(':cursor_created', $cursorCreatedAt, PDO::PARAM_STR);
            $stmt->bindValue(':cursor_id', $cursorId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        if ($cursorCreatedAt === null || $cursorId === null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists comments for a series (legacy or general content comments).
     */
    public function getByContentId(string $contentId, int $page, int $perPage, ?string $viewerUserId = null, ?string $cursorCreatedAt = null, ?int $cursorId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $whereParts = ['c.target_type = "series" AND c.target_id = :content_id'];
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $whereParts[] = '(c.created_at < :cursor_created OR (c.created_at = :cursor_created AND c.id < :cursor_id))';
        }
        $where = implode(' AND ', $whereParts);
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
                FROM comments c
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN votes cv
                    ON cv.target_type = "comment"
                   AND cv.target_id = c.id
                   AND cv.user_id = :viewer_user_id
                WHERE ' . $where . '
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT :limit' . ($cursorCreatedAt !== null && $cursorId !== null ? '' : ' OFFSET :offset');

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':viewer_user_id', $viewerUserId, $viewerUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':content_id', $contentId, PDO::PARAM_STR);
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $stmt->bindValue(':cursor_created', $cursorCreatedAt, PDO::PARAM_STR);
            $stmt->bindValue(':cursor_id', $cursorId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        if ($cursorCreatedAt === null || $cursorId === null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Fetches a comment by its numeric ID.
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT id, user_id, target_type, target_id, upvote_count, downvote_count
                FROM comments WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        // Add compatibility fields for any callers expecting chapter_id / content_id / blog_id
        $row['content_id'] = $row['target_type'] === 'series' ? $row['target_id'] : null;
        $row['chapter_id'] = $row['target_type'] === 'chapter' ? $row['target_id'] : null;
        $row['blog_id'] = $row['target_type'] === 'blog' ? $row['target_id'] : null;

        return $row;
    }
}
