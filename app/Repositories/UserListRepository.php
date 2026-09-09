<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Persists named user lists and their series membership.
 */
final class UserListRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listForUser(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.id, l.name, l.description, l.visibility, l.created_at, l.updated_at,
                    COUNT(i.content_id) AS item_count
             FROM user_lists l
             LEFT JOIN user_list_items i ON i.list_id = l.id
             WHERE l.user_id = :user_id
             GROUP BY l.id
             ORDER BY l.sort_order ASC, l.name ASC, l.id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findForUser(string $userId, int $listId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, description, visibility, sort_order, created_at, updated_at
             FROM user_lists WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['id' => $listId, 'user_id' => $userId]);
        $list = $stmt->fetch();
        if ($list === false) {
            return null;
        }

        $items = $this->pdo->prepare(
            'SELECT i.content_id, i.position, i.added_at,
                    s.title, s.slug, s.type, s.cover_image, s.status,
                    s.rating_avg, s.rating_count
             FROM user_list_items i
             INNER JOIN series s ON s.id = i.content_id
             WHERE i.list_id = :list_id
             ORDER BY i.position ASC, i.added_at DESC, i.content_id ASC'
        );
        $items->execute(['list_id' => $listId]);
        $list['items'] = $items->fetchAll();
        return $list;
    }

    public function create(string $userId, string $name, ?string $description, string $visibility): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_lists (user_id, name, description, visibility)
             VALUES (:user_id, :name, :description, :visibility)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'visibility' => $visibility,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $userId, int $listId, string $name, ?string $description, string $visibility): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_lists
             SET name = :name, description = :description, visibility = :visibility
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $listId,
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'visibility' => $visibility,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(string $userId, int $listId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_lists WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $listId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function addItem(string $userId, int $listId, string $contentId, int $position = 0): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_list_items (list_id, content_id, position)
             SELECT l.id, :content_id, :position
             FROM user_lists l
             INNER JOIN series s ON s.id = :content_id_lookup AND s.deleted_at IS NULL
             WHERE l.id = :list_id AND l.user_id = :user_id
             ON DUPLICATE KEY UPDATE position = VALUES(position)'
        );
        $stmt->execute([
            'content_id' => $contentId,
            'content_id_lookup' => $contentId,
            'position' => max(0, $position),
            'list_id' => $listId,
            'user_id' => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function removeItem(string $userId, int $listId, string $contentId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE i FROM user_list_items i
             INNER JOIN user_lists l ON l.id = i.list_id AND l.user_id = :user_id
             WHERE i.list_id = :list_id AND i.content_id = :content_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'list_id' => $listId,
            'content_id' => $contentId,
        ]);
        return $stmt->rowCount() > 0;
    }
}
