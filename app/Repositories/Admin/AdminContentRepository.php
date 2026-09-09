<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminContentRepository extends AdminRepositoryBase
{
    public function listContents(int $page, int $perPage, string $query = '', ?string $status = null, ?string $type = null, ?string $lifecycle = null, string $sort = 'newest'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($query !== '') {
            $where[] = '(c.id LIKE :query OR c.title LIKE :query OR c.slug LIKE :query OR c.alternative_titles LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if ($status !== null && in_array($status, ['ongoing', 'completed', 'hiatus', 'dropped'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }
        if ($type !== null && in_array($type, ['manga', 'manhua', 'manhwa', 'webtoon', 'novel', 'light-novel', 'web-novel'], true)) {
            $where[] = 'REPLACE(c.type, "_", "-") = :type';
            $params['type'] = $type;
        }
        if ($lifecycle !== null && in_array($lifecycle, ['draft', 'scheduled', 'published', 'archived'], true)) {
            $where[] = 'c.lifecycle_status = :lifecycle';
            $params['lifecycle'] = $lifecycle;
        }
        $whereClause = implode(' AND ', $where);
        $orderBy = match ($sort) {
            'oldest' => 'c.created_at ASC',
            'title' => 'c.title ASC',
            'updated' => 'c.updated_at DESC',
            default => 'c.created_at DESC',
        };
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM series c WHERE ' . $whereClause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
    
        $stmt = $this->pdo->prepare(
            'SELECT
                c.id,
                c.title,
                c.slug,
                c.alternative_titles,
                c.type,
                c.status,
                c.lifecycle_status,
                c.scheduled_at,
                c.published_at,
                c.archived_at,
                c.is_adult,
                c.is_members_only,
                c.disable_comments,
                c.cover_image,
                c.description,
                c.chapter_count,
                c.comment_count,
                c.rating_avg,
                c.rating_count,
                c.created_at,
                c.author,
                c.artist,
                c.country,
                c.release_year,
                (SELECT GROUP_CONCAT(stm.taxonomy_id) FROM series_taxonomy_map stm INNER JOIN taxonomies t ON t.id = stm.taxonomy_id WHERE stm.content_id = c.id AND t.type = "genre") as genre_ids,
                (SELECT GROUP_CONCAT(stm.taxonomy_id) FROM series_taxonomy_map stm INNER JOIN taxonomies t ON t.id = stm.taxonomy_id WHERE stm.content_id = c.id AND t.type = "tag") as tag_ids
             FROM series c
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

    public function updateContentTaxonomy(string $contentId, array $genreIds, array $tagIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM series_taxonomy_map WHERE content_id = :id AND taxonomy_id IN (SELECT id FROM taxonomies WHERE type IN ("genre", "tag"))')->execute(['id' => $contentId]);
            $allTaxIds = array_filter(array_merge($genreIds, $tagIds));
            if (!empty($allTaxIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO series_taxonomy_map (content_id, taxonomy_id) VALUES (:id, :tid) ON DUPLICATE KEY UPDATE content_id = VALUES(content_id)');
                foreach ($allTaxIds as $tid) {
                    if ($tid) $stmt->execute(['id' => $contentId, 'tid' => (int) $tid]);
                }
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listAllGenres(): array
    {
        $stmt = $this->pdo->query('SELECT t.id, t.type, t.name, t.slug, t.ui_config, t.sort_order, COUNT(stm.content_id) AS usage_count FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.type = "genre" GROUP BY t.id ORDER BY t.sort_order ASC, t.name ASC');
        return $stmt->fetchAll();
    }

    public function listAllTags(): array
    {
        $stmt = $this->pdo->query('SELECT t.id, t.type, t.name, t.slug, t.ui_config, t.sort_order, COUNT(stm.content_id) AS usage_count FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.type = "tag" GROUP BY t.id ORDER BY t.sort_order ASC, t.name ASC');
        return $stmt->fetchAll();
    }

    public function listSeriesTeam(string $seriesId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.series_id, t.user_id, t.role, t.created_at, u.username, u.email, u.profile_image
             FROM series_team_assignments t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.series_id = :series_id
             ORDER BY t.created_at ASC'
        );
        $stmt->execute(['series_id' => $seriesId]);
        return $stmt->fetchAll();
    }

    public function assignTeamMember(string $seriesId, string $userId, string $role): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO series_team_assignments (series_id, user_id, role, created_at)
             VALUES (:sid, :uid, :role, NOW())
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        );
        $stmt->execute([
            'sid' => $seriesId,
            'uid' => $userId,
            'role' => $role,
        ]);
        $id = (int) $this->pdo->lastInsertId();
    
        return [
            'id' => $id,
            'series_id' => $seriesId,
            'user_id' => $userId,
            'role' => $role,
        ];
    }

    public function removeTeamMember(int $assignmentId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM series_team_assignments WHERE id = :id');
        $stmt->execute(['id' => $assignmentId]);
        return $stmt->rowCount() > 0;
    }

    public function canUserManageSeries(string $userId, string $seriesId): bool
    {
        // Check if user is assigned to this series
        $stmt = $this->pdo->prepare('SELECT 1 FROM series_team_assignments WHERE series_id = :sid AND user_id = :uid LIMIT 1');
        $stmt->execute(['sid' => $seriesId, 'uid' => $userId]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    
        // Check if user is super admin / admin
        $uStmt = $this->pdo->prepare('SELECT roles FROM users WHERE id = :uid LIMIT 1');
        $uStmt->execute(['uid' => $userId]);
        $roles = (string) $uStmt->fetchColumn();
        return str_contains($roles, '1') || str_contains($roles, '2') || str_contains($roles, 'admin');
    }
}
