<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

/** Shared PDO helpers for extracted admin console repositories. */
abstract class AdminRepositoryBase
{
    /** @var bool|null */
    protected ?bool $blogsHasDeletedAt = null;

    /** @var bool|null */
    protected ?bool $commentsHasBlogId = null;

    public function __construct(protected readonly PDO $pdo)
    {
    }

    public function createModerationAction(
        ?string $moderatorUserId,
        string $targetType,
        string $targetId,
        string $action,
        ?string $reason,
        ?array $metadata = null,
        string $outcome = 'success'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_actions
                (moderator_user_id, target_type, target_id, action, reason, outcome, metadata, created_at)
             VALUES
                (:moderator_user_id, :target_type, :target_id, :action, :reason, :outcome, :metadata, NOW())'
        );
        $stmt->execute([
            'moderator_user_id' => $moderatorUserId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
            'outcome' => in_array($outcome, ['success', 'failure'], true) ? $outcome : 'success',
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    
        return (int) $this->pdo->lastInsertId();
    }

    protected function queryValue(string $sql, array $params = []): mixed
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function findUploadReferences(string $filePath): array
    {
        if ($filePath === '') return [];
    
        $references = [];
    
        $series = $this->pdo->prepare('SELECT id, title, slug, type FROM series WHERE deleted_at IS NULL AND cover_image = :path');
        $series->execute(['path' => $filePath]);
        foreach ($series->fetchAll() as $row) {
            $references[] = [
                'entity_type' => 'content',
                'entity_id' => (string) $row['id'],
                'relation' => 'cover_image',
                'label' => (string) $row['title'],
                'url' => '/' . str_replace('_', '-', (string) $row['type']) . '/' . (string) $row['slug'],
            ];
        }
    
        $blogs = $this->pdo->prepare('SELECT id, title, slug, cover_image, body FROM blogs WHERE deleted_at IS NULL AND (cover_image = :cover_path OR LOCATE(:body_path, body) > 0)');
        $blogs->execute(['cover_path' => $filePath, 'body_path' => $filePath]);
        foreach ($blogs->fetchAll() as $row) {
            if ((string) ($row['cover_image'] ?? '') === $filePath) {
                $references[] = [
                    'entity_type' => 'blog',
                    'entity_id' => (string) $row['id'],
                    'relation' => 'cover_image',
                    'label' => (string) $row['title'],
                    'url' => '/blogs/' . (string) $row['slug'],
                ];
            }
            if (str_contains((string) ($row['body'] ?? ''), $filePath)) {
                $references[] = [
                    'entity_type' => 'blog',
                    'entity_id' => (string) $row['id'],
                    'relation' => 'body',
                    'label' => (string) $row['title'],
                    'url' => '/blogs/' . (string) $row['slug'],
                ];
            }
        }
    
        $users = $this->pdo->prepare('SELECT id, username, profile_image, cover_image FROM users WHERE profile_image = :profile_path OR cover_image = :cover_path');
        $users->execute(['profile_path' => $filePath, 'cover_path' => $filePath]);
        foreach ($users->fetchAll() as $row) {
            if ((string) ($row['profile_image'] ?? '') === $filePath) {
                $references[] = [
                    'entity_type' => 'user',
                    'entity_id' => (string) $row['id'],
                    'relation' => 'profile_image',
                    'label' => '@' . (string) $row['username'],
                    'url' => '/profile/' . (string) $row['username'],
                ];
            }
            if ((string) ($row['cover_image'] ?? '') === $filePath) {
                $references[] = [
                    'entity_type' => 'user',
                    'entity_id' => (string) $row['id'],
                    'relation' => 'cover_image',
                    'label' => '@' . (string) $row['username'],
                    'url' => '/profile/' . (string) $row['username'],
                ];
            }
        }
    
        $chapters = $this->pdo->prepare('SELECT ch.id, ch.chapter_number, ch.data, s.title AS series_title, s.slug AS series_slug, s.type AS series_type FROM chapters ch INNER JOIN series s ON s.id = ch.content_id WHERE ch.deleted_at IS NULL AND s.deleted_at IS NULL AND LOCATE(:path, CAST(ch.data AS CHAR)) > 0');
        $chapters->execute(['path' => $filePath]);
        foreach ($chapters->fetchAll() as $row) {
            $references[] = [
                'entity_type' => 'chapter',
                'entity_id' => (string) $row['id'],
                'relation' => 'pages',
                'label' => (string) $row['series_title'] . ' · Bölüm ' . (string) $row['chapter_number'],
                'url' => '/' . str_replace('_', '-', (string) $row['series_type']) . '/' . (string) $row['series_slug'] . '/chapter/' . (string) $row['chapter_number'],
            ];
        }
    
        $comments = $this->pdo->prepare('SELECT id, target_type, target_id FROM comments WHERE deleted_at IS NULL AND LOCATE(:path, body) > 0');
        $comments->execute(['path' => $filePath]);
        foreach ($comments->fetchAll() as $row) {
            $references[] = [
                'entity_type' => 'comment',
                'entity_id' => (string) $row['id'],
                'relation' => 'body',
                'label' => 'Yorum #' . (string) $row['id'],
                'url' => null,
            ];
        }
    
        $unique = [];
        foreach ($references as $reference) {
            $key = $reference['entity_type'] . ':' . $reference['entity_id'] . ':' . $reference['relation'];
            $unique[$key] = $reference;
        }
        return array_values($unique);
    }

    protected function taxonomySortOrder(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT sort_order FROM taxonomies WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (int)$stmt->fetchColumn();
    }

    protected function rolePermissionOverrides(): array
    {
        try {
            $rows = $this->pdo->query(
                'SELECT role_slug, permission_code, effect FROM rbac_role_permission_overrides'
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['role_slug']][(string) $row['permission_code']] = (string) $row['effect'];
        }
        return $result;
    }

    protected function visitCount(int $days): int
    {
        $days = max(1, min(365, $days));
        $windowDays = max(0, $days - 1);
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(metric_value), 0) AS total
                 FROM analytics_snapshots_daily
                 WHERE metric_name = :metric_name
                   AND stat_date >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)'
            );
            $stmt->bindValue(':metric_name', 'total_views', PDO::PARAM_STR);
            $stmt->bindValue(':days', $windowDays, PDO::PARAM_INT);
            $stmt->execute();
            return (int) ($stmt->fetchColumn() ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function count(string $sql): int
    {
        try {
            $value = $this->pdo->query($sql)->fetchColumn();
            return (int) ($value !== false ? $value : 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function queryOne(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
            }
            $stmt->execute();
            $row = $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function queryByDaysAndLimit(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (str_contains($sql, ':days')) {
                $stmt->bindValue(':days', (int) ($params['days'] ?? 7), PDO::PARAM_INT);
            }
            if (str_contains($sql, ':limit')) {
                $stmt->bindValue(':limit', (int) ($params['limit'] ?? 10), PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function blogsNotDeletedCondition(string $alias): string
    {
        return $this->blogsHasDeletedAt() ? $alias . '.deleted_at IS NULL' : '1=1';
    }

    protected function blogsDeletedCondition(string $alias): string
    {
        return $this->blogsHasDeletedAt() ? $alias . '.deleted_at IS NOT NULL' : '0=1';
    }

    protected function blogsHasDeletedAt(): bool
    {
        if ($this->blogsHasDeletedAt !== null) {
            return $this->blogsHasDeletedAt;
        }
    
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM blogs LIKE 'deleted_at'");
            $this->blogsHasDeletedAt = $stmt !== false && (bool) $stmt->fetch();
        } catch (\Throwable) {
            $this->blogsHasDeletedAt = false;
        }
    
        return $this->blogsHasDeletedAt;
    }

    protected function commentsHasBlogId(): bool
    {
        if ($this->commentsHasBlogId !== null) {
            return $this->commentsHasBlogId;
        }
    
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM comments LIKE 'blog_id'");
            $this->commentsHasBlogId = $stmt !== false && (bool) $stmt->fetch();
        } catch (\Throwable) {
            $this->commentsHasBlogId = false;
        }
    
        return $this->commentsHasBlogId;
    }

    protected function queryList(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (str_contains($sql, ':days')) {
                $stmt->bindValue(':days', (int) ($params['days'] ?? 7), PDO::PARAM_INT);
            }
            if (str_contains($sql, ':limit')) {
                $stmt->bindValue(':limit', (int) ($params['limit'] ?? 10), PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }
}
