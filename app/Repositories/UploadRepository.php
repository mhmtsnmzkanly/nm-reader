<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for managing uploaded files tracking
 *
 * @package App\Repositories
 */
final class UploadRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Records a new image upload mapping.
     */
    public function logImageUpload(string $userId, string $imageId, string $originalName, string $mimeType, int $fileSize, string $filePath): void
    {
        $sql = 'INSERT INTO system_uploads (user_id, image_id, original_name, file_path, mime_type, file_size, created_at)
                VALUES (:user_id, :image_id, :original_name, :file_path, :mime_type, :file_size, NOW())';
        $this->pdo->prepare($sql)->execute([
            'user_id' => $userId,
            'image_id' => $imageId,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize
        ]);
    }
}
