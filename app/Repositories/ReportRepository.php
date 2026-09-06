<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Content Reporting database operations.
 *
 * Handles reporting of series, chapters, blogs, and comments by users,
 * and review/triage workflows by administrators.
 *
 * @package App\Repositories
 */
final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Creates a new report record.
     */
    public function create(
        string $userId,
        string $targetType,
        string $targetId,
        string $reason,
        ?string $description = null
    ): int {
        $sql = 'INSERT INTO reports (user_id, target_type, target_id, reason, description, status)
                VALUES (:user_id, :target_type, :target_id, :reason, :description, "pending")';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'description' => $description,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Checks if a user has already reported the target with the same reason.
     */
    public function exists(string $userId, string $targetType, string $targetId, string $reason): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM reports
             WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id AND reason = :reason
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Retrieves a single report by ID with full context and usernames.
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT
                    r.id,
                    r.user_id,
                    u.username AS reporter_username,
                    r.target_type,
                    r.target_id,
                    r.reason,
                    r.description,
                    r.status,
                    r.reviewed_by,
                    ru.username AS reviewer_username,
                    r.reviewed_at,
                    r.admin_note,
                    r.created_at,
                    r.updated_at,
                    (CASE
                        WHEN r.target_type = "series" THEN s.title
                        WHEN r.target_type = "chapter" THEN s2.title
                        WHEN r.target_type = "blog" THEN b.title
                        ELSE NULL
                    END) AS target_title,
                    ch.chapter_number,
                    c.body AS comment_body,
                    (CASE
                        WHEN r.target_type = "series" THEN CONCAT("/", s.type, "/", s.slug)
                        WHEN r.target_type = "chapter" THEN CONCAT("/", s2.type, "/", s2.slug, "/chapter/", ch.chapter_number)
                        WHEN r.target_type = "blog" THEN CONCAT("/blogs/", b.slug)
                        ELSE NULL
                    END) AS target_url
                FROM reports r
                INNER JOIN users u ON u.id = r.user_id
                LEFT JOIN users ru ON ru.id = r.reviewed_by
                LEFT JOIN series s ON (r.target_type = "series" AND s.id = r.target_id)
                LEFT JOIN chapters ch ON (r.target_type = "chapter" AND ch.id = r.target_id)
                LEFT JOIN series s2 ON (r.target_type = "chapter" AND s2.id = ch.content_id)
                LEFT JOIN blogs b ON (r.target_type = "blog" AND b.id = r.target_id)
                LEFT JOIN comments c ON (r.target_type = "comment" AND c.id = r.target_id)
                WHERE r.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lists reports for administrative review with filtering and pagination.
     *
     * @return array ['items' => [...], 'total' => int]
     */
    public function listForAdmin(
        int $page,
        int $perPage,
        ?string $status = null,
        ?string $targetType = null
    ): array {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        if ($targetType !== null && $targetType !== '') {
            $where[] = 'r.target_type = :target_type';
            $params['target_type'] = $targetType;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = 'SELECT COUNT(*) FROM reports r ' . $whereClause;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch paginated items
        $sql = 'SELECT
                    r.id,
                    r.user_id,
                    u.username AS reporter_username,
                    r.target_type,
                    r.target_id,
                    r.reason,
                    r.description,
                    r.status,
                    r.reviewed_by,
                    ru.username AS reviewer_username,
                    r.reviewed_at,
                    r.admin_note,
                    r.created_at,
                    r.updated_at,
                    (CASE
                        WHEN r.target_type = "series" THEN s.title
                        WHEN r.target_type = "chapter" THEN s2.title
                        WHEN r.target_type = "blog" THEN b.title
                        ELSE NULL
                    END) AS target_title,
                    ch.chapter_number,
                    SUBSTRING(c.body, 1, 120) AS comment_snippet,
                    (CASE
                        WHEN r.target_type = "series" THEN CONCAT("/", s.type, "/", s.slug)
                        WHEN r.target_type = "chapter" THEN CONCAT("/", s2.type, "/", s2.slug, "/chapter/", ch.chapter_number)
                        WHEN r.target_type = "blog" THEN CONCAT("/blogs/", b.slug)
                        ELSE NULL
                    END) AS target_url
                FROM reports r
                INNER JOIN users u ON u.id = r.user_id
                LEFT JOIN users ru ON ru.id = r.reviewed_by
                LEFT JOIN series s ON (r.target_type = "series" AND s.id = r.target_id)
                LEFT JOIN chapters ch ON (r.target_type = "chapter" AND ch.id = r.target_id)
                LEFT JOIN series s2 ON (r.target_type = "chapter" AND s2.id = ch.content_id)
                LEFT JOIN blogs b ON (r.target_type = "blog" AND b.id = r.target_id)
                LEFT JOIN comments c ON (r.target_type = "comment" AND c.id = r.target_id)
                ' . $whereClause . '
                ORDER BY
                    CASE r.status
                        WHEN "pending" THEN 1
                        WHEN "reviewing" THEN 2
                        ELSE 3
                    END ASC,
                    r.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Updates the status and review details of a report.
     */
    public function updateStatus(int $id, string $status, ?string $adminNote, string $reviewedBy): bool
    {
        $sql = 'UPDATE reports
                SET status = :status,
                    admin_note = :admin_note,
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'status' => $status,
            'admin_note' => $adminNote,
            'reviewed_by' => $reviewedBy,
        ]);
    }

    /**
     * Counts reports grouped by status.
     */
    public function countByStatus(): array
    {
        $stmt = $this->pdo->query(
            'SELECT status, COUNT(*) AS total
             FROM reports
             GROUP BY status'
        );

        $counts = [
            'pending' => 0,
            'reviewing' => 0,
            'resolved' => 0,
            'rejected' => 0,
        ];

        if ($stmt !== false) {
            while ($row = $stmt->fetch()) {
                $status = (string) $row['status'];
                if (isset($counts[$status])) {
                    $counts[$status] = (int) $row['total'];
                }
            }
        }

        return $counts;
    }
}
