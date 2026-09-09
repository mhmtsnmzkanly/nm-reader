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
    public function logImageUpload(
        string $userId,
        string $imageId,
        string $originalName,
        string $mimeType,
        int $fileSize,
        string $filePath,
        ?string $checksum = null,
        ?array $metadata = null,
        string $processingStatus = 'ready'
    ): void
    {
        $sql = 'INSERT INTO system_uploads
                    (user_id, image_id, original_name, file_path, storage_provider, storage_key, mime_type, file_size, checksum, processing_status, metadata, created_at)
                VALUES
                    (:user_id, :image_id, :original_name, :file_path, "local", :storage_key, :mime_type, :file_size, :checksum, :processing_status, :metadata, NOW())';
        $this->pdo->prepare($sql)->execute([
            'user_id' => $userId,
            'image_id' => $imageId,
            'original_name' => $originalName,
            'file_path' => $filePath,
            'storage_key' => ltrim($filePath, '/'),
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'checksum' => $checksum,
            'processing_status' => in_array($processingStatus, ['uploaded', 'processing', 'ready', 'failed', 'deleted'], true) ? $processingStatus : 'ready',
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function markProcessing(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE system_uploads SET processing_status = "processing", processing_error = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function markProcessed(int $id, int $fileSize, ?array $metadata = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE system_uploads
             SET file_size = :file_size, processing_status = "ready", processing_error = NULL,
                 optimized_at = NOW(), metadata = COALESCE(:metadata, metadata)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'file_size' => $fileSize,
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function markProcessingFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE system_uploads SET processing_status = "failed", processing_error = :error WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'error' => mb_substr($error, 0, 1000)]);
    }
}
