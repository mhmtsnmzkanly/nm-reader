<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminModerationRepository extends AdminRepositoryBase
{
    public function listAuditLogs(int $page, int $perPage, string $query = '', ?string $method = null, ?string $statusGroup = null, ?string $userId = null, ?string $dateFrom = null, ?string $dateTo = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(al.path LIKE :query_path OR al.action LIKE :query_action OR al.request_id LIKE :query_request_id OR al.user_agent LIKE :query_user_agent OR u.username LIKE :query_username)';
            $queryValue = '%' . $query . '%';
            $params['query_path'] = $queryValue;
            $params['query_action'] = $queryValue;
            $params['query_request_id'] = $queryValue;
            $params['query_user_agent'] = $queryValue;
            $params['query_username'] = $queryValue;
        }
        if ($method !== null && in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) { $where[] = 'al.method = :method'; $params['method'] = $method; }
        if ($statusGroup === '2xx') $where[] = 'al.status_code BETWEEN 200 AND 299';
        elseif ($statusGroup === '4xx') $where[] = 'al.status_code BETWEEN 400 AND 499';
        elseif ($statusGroup === '5xx') $where[] = 'al.status_code >= 500';
        if ($userId !== null && $userId !== '') { $where[] = 'al.user_id = :user_id'; $params['user_id'] = $userId; }
        if ($dateFrom !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'al.created_at >= :date_from'; $params['date_from'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'al.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to'] = $dateTo . ' 00:00:00'; }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM system_audit_logs al LEFT JOIN users u ON u.id = al.user_id' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $orderSql = $sort === 'oldest' ? 'al.id ASC' : ($sort === 'slowest' ? 'al.duration_ms DESC, al.id DESC' : 'al.id DESC');
    
        $stmt = $this->pdo->prepare(
            'SELECT
                al.id,
                al.request_id,
                al.user_id,
                u.username,
                al.method,
                al.path,
                al.action,
                al.outcome,
                al.status_code,
                al.ip_hash,
                al.user_agent,
                al.duration_ms,
                al.context_json,
                al.created_at
             FROM system_audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ' . $whereSql . '
             ORDER BY ' . $orderSql . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function listLoginEvents(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->count('SELECT COUNT(*) FROM user_login_logs');
    
        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                user_id,
                email,
                ip_hash,
                user_agent,
                success,
                failure_reason,
                attempted_at
             FROM user_login_logs
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function listComments(int $page, int $perPage, string $query = '', ?string $targetType = null, string $sort = 'newest', ?string $moderationStatus = null, ?string $userId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['1 = 1'];
        $params = [];
        if ($query !== '') {
            $where[] = '(c.body LIKE :query_body OR u.username LIKE :query_username)';
            $queryValue = '%' . $query . '%';
            $params['query_body'] = $queryValue;
            $params['query_username'] = $queryValue;
        }
        if ($targetType !== null && in_array($targetType, ['series', 'chapter', 'blog'], true)) {
            $where[] = 'c.target_type = :target_type';
            $params['target_type'] = $targetType;
        }
        if ($moderationStatus !== null && in_array($moderationStatus, ['pending', 'approved', 'hidden', 'deleted'], true)) {
            $where[] = 'c.moderation_status = :moderation_status';
            $params['moderation_status'] = $moderationStatus;
        }
        if ($userId !== null && $userId !== '') {
            $where[] = 'c.user_id = :comment_user_id';
            $params['comment_user_id'] = $userId;
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = $sort === 'oldest' ? 'c.created_at ASC' : 'c.created_at DESC';
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM comments c INNER JOIN users u ON u.id = c.user_id WHERE ' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    
        $stmt = $this->pdo->prepare(
            'SELECT
                c.id,
                c.user_id,
                u.username,
                c.body,
                c.moderation_status,
                c.deleted_at,
                c.created_at,
                c.upvote_count,
                c.downvote_count,
                c.target_type,
                c.target_id,
                (CASE 
                    WHEN c.target_type = "series" THEN s.title
                    WHEN c.target_type = "chapter" THEN s2.title
                    ELSE NULL 
                 END) AS content_title,
                (CASE WHEN c.target_type = "blog" THEN b.title ELSE NULL END) AS blog_title,
                ch.chapter_number
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN chapters ch ON (c.target_type = "chapter" AND ch.id = c.target_id)
             LEFT JOIN series s ON (c.target_type = "series" AND s.id = c.target_id)
             LEFT JOIN series s2 ON (c.target_type = "chapter" AND s2.id = ch.content_id)
             LEFT JOIN blogs b ON (c.target_type = "blog" AND b.id = c.target_id)
             WHERE ' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function deleteComment(int $id, string $moderatorId): bool
    {
        // 1. Fetch data before deletion for auditing
        $stmt = $this->pdo->prepare(
            'SELECT c.user_id, c.body, c.target_type, c.target_id, u.username as author_name
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $comment = $stmt->fetch();
        if (!$comment) {
            return false;
        }
    
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE comments SET moderation_status = "deleted", deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(['id' => $id]);
            $success = $stmt->rowCount() > 0;
    
            if ($success) {
                // Clean up any votes on this comment
                $delVotes = $this->pdo->prepare('DELETE FROM votes WHERE target_type = "comment" AND target_id = :id');
                $delVotes->execute(['id' => (string) $id]);
    
                $context = [
                    'author_id' => $comment['user_id'],
                    'author_name' => $comment['author_name'],
                    'body' => $comment['body'],
                    'location' => [
                        'target_type' => $comment['target_type'],
                        'target_id' => $comment['target_id'],
                    ]
                ];
    
                $audit = $this->pdo->prepare(
                    'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES (:mod, "comment", :cid, "delete", :reason, NOW())'
                );
                $audit->execute([
                    'mod' => $moderatorId,
                    'cid' => (string)$id,
                    'reason' => json_encode($context, JSON_UNESCAPED_UNICODE)
                ]);
            }
    
            $this->pdo->commit();
            return $success;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function moderateComment(int $id, string $status, string $moderatorId, ?string $reason = null): bool
    {
        $allowed = ['pending', 'approved', 'hidden', 'deleted'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid comment moderation status');
        }
    
        $stmt = $this->pdo->prepare('SELECT id, moderation_status FROM comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetch() === false) {
            return false;
        }
    
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(
                'UPDATE comments
                 SET moderation_status = :status,
                     deleted_at = CASE WHEN :status_deleted = "deleted" THEN COALESCE(deleted_at, NOW()) ELSE NULL END
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $status,
                'status_deleted' => $status,
                'id' => $id,
            ]);
    
            $audit = $this->pdo->prepare(
                'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                 VALUES (:mod, "comment", :cid, :action, :reason, NOW())'
            );
            $audit->execute([
                'mod' => $moderatorId,
                'cid' => (string) $id,
                'action' => 'moderate_' . $status,
                'reason' => $reason,
            ]);
            $this->pdo->commit();
            return $update->rowCount() > 0;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listModerationActions(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = $this->count('SELECT COUNT(*) FROM admin_actions');
    
        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                moderator_user_id,
                target_type,
                target_id,
                action,
                reason,
                outcome,
                metadata,
                created_at
             FROM admin_actions
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function listBlogs(int $page, int $perPage, string $query = '', ?string $status = null, string $sort = 'newest', ?string $userId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($status === 'deleted') {
            $where[] = 'b.deleted_at IS NOT NULL';
        } else {
            $where[] = 'b.deleted_at IS NULL';
            if ($status !== null && in_array($status, ['draft', 'pending', 'published', 'rejected', 'hidden'], true)) {
                $where[] = 'b.status = :status';
                $params['status'] = $status;
            }
        }
        if ($query !== '') {
            $where[] = '(b.title LIKE :query_title OR b.slug LIKE :query_slug OR u.username LIKE :query_username)';
            $queryValue = '%' . $query . '%';
            $params['query_title'] = $queryValue;
            $params['query_slug'] = $queryValue;
            $params['query_username'] = $queryValue;
        }
        if ($userId !== null && $userId !== '') {
            $where[] = 'b.user_id = :blog_user_id';
            $params['blog_user_id'] = $userId;
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = $sort === 'oldest' ? 'b.created_at ASC' : 'b.created_at DESC';
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM blogs b INNER JOIN users u ON u.id = b.user_id WHERE ' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    
        $stmt = $this->pdo->prepare(
            'SELECT
                b.id,
                b.user_id,
                u.username,
                b.title,
                b.slug,
                b.status,
                b.approved,
                b.created_at,
                b.approved_at
             FROM blogs b
             INNER JOIN users u ON u.id = b.user_id
             WHERE ' . $whereClause . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function hideBlog(string $id, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE blogs SET approved = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);

            $this->createModerationAction($moderatorId, 'blog', $id, 'hide', 'Blog hidden by moderator');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteBlog(string $id, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            // Check if soft delete column exists
            if ($this->blogsHasDeletedAt()) {
                $stmt = $this->pdo->prepare('UPDATE blogs SET deleted_at = NOW(), approved = 0 WHERE id = :id');
            } else {
                $stmt = $this->pdo->prepare('DELETE FROM blogs WHERE id = :id');
            }
            $stmt->execute(['id' => $id]);

            $this->createModerationAction($moderatorId, 'blog', $id, 'delete', 'Blog permanently deleted or soft-deleted by moderator');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
