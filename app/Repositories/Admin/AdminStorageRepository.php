<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminStorageRepository extends AdminRepositoryBase
{
    public function listUploads(int $page, int $perPage, string $query = '', ?string $mime = null, bool $orphansOnly = false): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $referenceSql = '(EXISTS (SELECT 1 FROM series s WHERE s.deleted_at IS NULL AND s.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM blogs b WHERE b.deleted_at IS NULL AND (b.cover_image = u.file_path OR LOCATE(u.file_path, b.body) > 0)) OR EXISTS (SELECT 1 FROM users profile WHERE profile.profile_image = u.file_path OR profile.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM chapters ch WHERE ch.deleted_at IS NULL AND LOCATE(u.file_path, CAST(ch.data AS CHAR)) > 0) OR EXISTS (SELECT 1 FROM comments cm WHERE cm.deleted_at IS NULL AND LOCATE(u.file_path, cm.body) > 0))';
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(u.original_name LIKE :query OR u.image_id LIKE :query OR u.file_path LIKE :query OR us.username LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if ($mime !== null && $mime !== '') {
            $where[] = 'u.mime_type = :mime';
            $params['mime'] = $mime;
        }
        if ($orphansOnly) $where[] = 'NOT ' . $referenceSql;
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            'SELECT u.*, us.username, ' . $referenceSql . ' AS is_referenced
             FROM system_uploads u
             LEFT JOIN users us ON u.user_id = us.id
             ' . $whereSql . '
             ORDER BY u.created_at DESC 
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $references = $this->findUploadReferences((string) ($item['file_path'] ?? ''));
            $item['references'] = $references;
            $item['reference_count'] = count($references);
            $item['is_referenced'] = $item['reference_count'] > 0 ? 1 : 0;
        }
        unset($item);
    
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM system_uploads u LEFT JOIN users us ON u.user_id = us.id' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
    
        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    public function uploadStats(): array
    {
        $row = $this->pdo->query('SELECT COUNT(*) AS total_files, COALESCE(SUM(file_size), 0) AS total_bytes, COALESCE(AVG(file_size), 0) AS average_bytes, SUM(mime_type = "image/jpeg") AS jpeg_files, SUM(mime_type = "image/png") AS png_files, SUM(mime_type = "image/webp") AS webp_files, SUM(mime_type = "image/gif") AS gif_files FROM system_uploads')->fetch();
        return $row ?: [];
    }

    public function uploadById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM system_uploads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function unreferencedUploadIdsByPaths(array $paths, string $userId): array
    {
        $paths = array_values(array_unique(array_filter(array_map('strval', $paths))));
        if ($paths === [] || $userId === '') return [];
    
        $placeholders = implode(',', array_fill(0, count($paths), '?'));
        $referenceSql = '(EXISTS (SELECT 1 FROM series s WHERE s.deleted_at IS NULL AND s.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM blogs b WHERE b.deleted_at IS NULL AND (b.cover_image = u.file_path OR LOCATE(u.file_path, b.body) > 0)) OR EXISTS (SELECT 1 FROM users profile WHERE profile.profile_image = u.file_path OR profile.cover_image = u.file_path) OR EXISTS (SELECT 1 FROM chapters ch WHERE ch.deleted_at IS NULL AND LOCATE(u.file_path, CAST(ch.data AS CHAR)) > 0) OR EXISTS (SELECT 1 FROM comments cm WHERE cm.deleted_at IS NULL AND LOCATE(u.file_path, cm.body) > 0))';
        $stmt = $this->pdo->prepare("SELECT u.id FROM system_uploads u WHERE u.user_id = ? AND u.file_path IN ($placeholders) AND NOT $referenceSql");
        $stmt->execute(array_merge([$userId], $paths));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function updateUploadFileSize(int $id, int $size, ?string $checksum = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE system_uploads SET file_size = :size, checksum = COALESCE(:checksum, checksum), processing_status = "ready", processing_error = NULL, optimized_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'size' => $size, 'checksum' => $checksum]);
    }

    public function markUploadProcessing(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE system_uploads SET processing_status = "processing", processing_error = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function markUploadProcessingFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare('UPDATE system_uploads SET processing_status = "failed", processing_error = :error WHERE id = :id');
        $stmt->execute(['id' => $id, 'error' => mb_substr($error, 0, 1000)]);
    }

    public function deleteUpload(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT image_id, file_path FROM system_uploads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
    
        $stmt = $this->pdo->prepare('DELETE FROM system_uploads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return [
            'image_id' => (string) $row['image_id'],
            'file_path' => (string) ($row['file_path'] ?? '')
        ];
    }
}
