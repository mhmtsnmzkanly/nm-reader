<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Blog-related database operations.
 *
 * Handles raw SQL interactions for user-generated blog posts,
 * including approval workflows, voting counts, and sitemap generation.
 * Supports optional soft-deletion if 'deleted_at' column is present.
 *
 * @package App\Repositories
 */
final class BlogRepository
{
    /** @var bool|null Cache for checking if 'deleted_at' column exists in 'blogs' table. */
    private ?bool $hasDeletedAt = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Lists approved blog posts with author and approver details.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listApproved(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    b.id,
                    b.user_id,
                    b.title,
                    b.slug,
                    b.body,
                    b.approved,
                    b.approver_user_id,
                    b.approved_at,
                    b.created_at,
                    b.updated_at,
                    u.username AS author_username,
                    au.username AS approver_username
                FROM blogs b
                INNER JOIN users u ON u.id = b.user_id
                LEFT JOIN users au ON au.id = b.approver_user_id
                WHERE b.approved = 1' . $this->deletedAtCondition('b') . '
                ORDER BY b.approved_at DESC, b.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Finds a single approved blog by slug and includes the current user's vote.
     *
     * @param string $slug
     * @param string|null $userId ID of user viewing the blog (for 'my_vote' field).
     * @return array|null
     */
    public function findApprovedBySlug(string $slug, ?string $userId = null): ?array
    {
        $sql = 'SELECT
                    b.id,
                    b.user_id,
                    b.title,
                    b.slug,
                    b.body,
                    b.approved,
                    b.approver_user_id,
                    b.approved_at,
                    b.created_at,
                    b.updated_at,
                    u.username AS author_username,
                    au.username AS approver_username,
                    (SELECT COUNT(*) FROM blog_votes WHERE blog_id = b.id AND vote = 1) AS upvote_count,
                    (SELECT COUNT(*) FROM blog_votes WHERE blog_id = b.id AND vote = -1) AS downvote_count,
                    (CASE WHEN :user_id IS NOT NULL THEN (SELECT vote FROM blog_votes WHERE blog_id = b.id AND user_id = :user_id2) ELSE 0 END) AS my_vote
                FROM blogs b
                INNER JOIN users u ON u.id = b.user_id
                LEFT JOIN users au ON au.id = b.approver_user_id
                WHERE b.slug = :slug AND b.approved = 1' . $this->deletedAtCondition('b') . '
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'slug' => $slug,
            'user_id' => $userId,
            'user_id2' => $userId
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Retrieves raw blog data by string ID.
     */
    public function findById(string $blogId): ?array
    {
        $sql = 'SELECT id, user_id, title, slug, body, approved, ' . $this->deletedAtSelectExpr() . ' AS deleted_at, approver_user_id, approved_at, created_at, updated_at
                FROM blogs
                WHERE id = :id
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $blogId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Checks if a slug is already taken.
     */
    public function existsBySlug(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM blogs WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() !== false;
    }

    /**
     * Checks if a specific ID exists in the blogs table.
     */
    public function existsBlogId(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM blogs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Creates a new pending blog entry.
     *
     * @return string The inserted ID.
     */
    public function create(string $id, string $userId, string $title, string $slug, string $body): string
    {
        $sql = 'INSERT INTO blogs (id, user_id, title, slug, body, approved)
                VALUES (:id, :user_id, :title, :slug, :body, 0)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
        ]);

        return $id;
    }

    /**
     * Lists blog posts created by a specific user.
     */
    public function listByUser(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    id,
                    user_id,
                    title,
                    slug,
                    body,
                    approved,
                    approver_user_id,
                    approved_at,
                    created_at,
                    updated_at
                FROM blogs
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists posts that are not yet approved.
     */
    public function listPending(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    b.id,
                    b.user_id,
                    b.title,
                    b.slug,
                    b.body,
                    b.approved,
                    b.created_at,
                    b.updated_at,
                    u.username AS author_username
                FROM blogs b
                INNER JOIN users u ON u.id = b.user_id
                WHERE b.approved = 0' . $this->deletedAtCondition('b') . '
                ORDER BY b.created_at ASC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Marks a blog post as approved and sets the approver user.
     */
    public function approve(string $blogId, string $approverUserId): void
    {
        $this->pdo->beginTransaction();
        try {
            $sql = 'UPDATE blogs
                    SET approved = 1,
                        approver_user_id = :approver_user_id,
                        approved_at = NOW()
                    WHERE id = :id';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'approver_user_id' => $approverUserId,
                'id' => $blogId,
            ]);

            // Audit the approval
            $audit = $this->pdo->prepare(
                'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                 VALUES (:mod, "blog", :bid, "approve", "Approved via admin panel", NOW())'
            );
            $audit->execute(['mod' => $approverUserId, 'bid' => $blogId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieves popular approved blogs for homepage.
     */
    public function homePopular(int $limit = 3): array
    {
        $sql = 'SELECT
                    b.id,
                    b.user_id,
                    b.title,
                    b.slug,
                    b.approved_at,
                    b.created_at,
                    u.username AS author_username
                FROM blogs b
                INNER JOIN users u ON u.id = b.user_id
                WHERE b.approved = 1' . $this->deletedAtCondition('b') . '
                ORDER BY b.approved_at DESC, b.created_at DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Retrieves latest approved blogs for homepage.
     */
    public function homeLatest(int $limit = 3): array
    {
        $sql = 'SELECT
                    b.id,
                    b.user_id,
                    b.title,
                    b.slug,
                    b.approved_at,
                    b.created_at,
                    u.username AS author_username
                FROM blogs b
                INNER JOIN users u ON u.id = b.user_id
                WHERE b.approved = 1' . $this->deletedAtCondition('b') . '
                ORDER BY b.created_at DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists approved blogs for sitemap generation.
     */
    public function listApprovedForSitemap(int $limit = 50000): array
    {
        $sql = 'SELECT
                    slug,
                    COALESCE(approved_at, created_at) AS lastmod
                FROM blogs
                WHERE approved = 1' . $this->deletedAtCondition() . '
                ORDER BY COALESCE(approved_at, created_at) DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Comprehensive listing for administration purposes.
     *
     * @return array ['items' => [...], 'total' => int]
     */
    public function listForAdmin(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $totalStmt = $this->pdo->query('SELECT COUNT(*) FROM blogs');
        $total = (int) ($totalStmt !== false ? $totalStmt->fetchColumn() : 0);

        $sql = 'SELECT
                    b.id,
                    b.user_id,
                    b.title,
                    b.slug,
                    b.approved,
                    ' . $this->deletedAtSelectExpr('b') . ' AS deleted_at,
                    b.approved_at,
                    b.created_at,
                    b.updated_at,
                    u.username AS author_username,
                    au.username AS approver_username
                FROM blogs b
                INNER JOIN users u ON u.id = b.user_id
                LEFT JOIN users au ON au.id = b.approver_user_id
                ORDER BY b.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Resets approval status to 0 (hidden from public).
     */
    public function hideById(string $blogId, ?string $moderatorId = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE blogs
                 SET approved = 0,
                     approver_user_id = NULL,
                     approved_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id' . $this->deletedAtCondition()
            );
            $stmt->execute(['id' => $blogId]);
            $success = $stmt->rowCount() > 0;

            if ($success && $moderatorId !== null) {
                $audit = $this->pdo->prepare(
                    'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES (:mod, "blog", :bid, "hide", "Hidden via admin panel", NOW())'
                );
                $audit->execute(['mod' => $moderatorId, 'bid' => $blogId]);
            }
            $this->pdo->commit();
            return $success;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Soft-deletes a post if possible, otherwise performs hard delete.
     */
    public function softDeleteById(string $blogId, ?string $moderatorId = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            if ($this->supportsDeletedAt()) {
                $stmt = $this->pdo->prepare(
                    'UPDATE blogs
                     SET deleted_at = NOW(),
                         approved = 0,
                         approver_user_id = NULL,
                         approved_at = NULL,
                         updated_at = NOW()
                     WHERE id = :id AND deleted_at IS NULL'
                );
                $stmt->execute(['id' => $blogId]);
                $success = $stmt->rowCount() > 0;
            } else {
                $stmt = $this->pdo->prepare('DELETE FROM blogs WHERE id = :id');
                $stmt->execute(['id' => $blogId]);
                $success = $stmt->rowCount() > 0;
            }

            if ($success && $moderatorId !== null) {
                $audit = $this->pdo->prepare(
                    'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES (:mod, "blog", :bid, "delete", "Deleted via admin panel", NOW())'
                );
                $audit->execute(['mod' => $moderatorId, 'bid' => $blogId]);
            }

            $this->pdo->commit();
            return $success;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Dynamic feature detection for soft-delete support.
     */
    private function supportsDeletedAt(): bool
    {
        if ($this->hasDeletedAt !== null) {
            return $this->hasDeletedAt;
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM blogs LIKE 'deleted_at'");
            $this->hasDeletedAt = $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable) {
            $this->hasDeletedAt = false;
        }

        return $this->hasDeletedAt;
    }

    private function deletedAtCondition(string $alias = ''): string
    {
        if (!$this->supportsDeletedAt()) {
            return '';
        }

        $prefix = $alias !== '' ? ($alias . '.') : '';
        return sprintf(' AND %sdeleted_at IS NULL', $prefix);
    }

    private function deletedAtSelectExpr(string $alias = ''): string
    {
        if ($this->supportsDeletedAt()) {
            $prefix = $alias !== '' ? ($alias . '.') : '';
            return $prefix . 'deleted_at';
        }

        return 'NULL';
    }
}
