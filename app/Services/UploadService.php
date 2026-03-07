<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\UploadDto;
use App\Services\EntityIdService;
use App\Repositories\UploadRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Service for handling unified file uploads.
 *
 * It manages physical file relocation, validation, type checking, 
 * and logging the upload via UploadRepository.
 *
 * @package App\Services
 */
final class UploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public function __construct(
        private readonly UploadRepository $repository,
        private readonly EntityIdService $entityIds,
        private readonly string $baseUploadDir = __DIR__ . '/../../public/uploads/'
    ) {
    }

    /**
     * Processes a single file upload using the UploadDto.
     *
     * @param UploadDto $dto Data transfer object encapsulating the upload details.
     * @return string The relative public path of the uploaded image.
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function handleImageUpload(UploadDto $dto): string
    {
        $file = $dto->file;

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $msg = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large. Check server limits (upload_max_filesize).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                default => 'Upload failed with error code: ' . $file->getError(),
            };
            throw new InvalidArgumentException($msg);
        }

        $mimeType = $file->getClientMediaType() ?? 'application/octet-stream';
        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new InvalidArgumentException('Unsupported image type: ' . $mimeType);
        }

        $ext = self::ALLOWED_MIME_TYPES[$mimeType];
        $imageId = $this->entityIds->generateImageId();

        // Map subdirs to prefixes as per user requirement
        $prefix = match ($dto->targetSubdir) {
            'users/profile' => 'user.profile',
            'users/cover'   => 'user.cover',
            'chapters'      => 'chapter',
            'series_cover'  => 'cover',
            'blogs', 'system' => 'content.cover',
            default => str_replace('/', '.', trim($dto->targetSubdir ?? 'misc', '/'))
        };

        $fileName = $prefix . '.' . $imageId . '.' . $ext;
        $targetDir = rtrim($this->baseUploadDir, '/');

        // Ensure base upload directory exists
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new RuntimeException('Failed to create upload directory.');
            }
        }

        $targetPath = $targetDir . '/' . $fileName;

        try {
            $file->moveTo($targetPath);
            $publicPath = '/uploads/' . $fileName;
            
            $this->repository->logImageUpload(
                $dto->userId,
                $imageId,
                $file->getClientFilename() ?? 'unknown',
                $mimeType,
                (int)$file->getSize(),
                $publicPath
            );

            // Return relative path from public root - flattened
            return $publicPath;
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to move uploaded file: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Handles bulk uploads.
     *
     * @param string $userId
     * @param array $files
     * @param string $subdir
     * @return array Array of relative image paths
     */
    public function handleBulkImageUpload(string $userId, array $files, string $subdir = 'chapters'): array
    {
        if (empty($files)) {
            throw new InvalidArgumentException('No files provided');
        }

        $paths = [];
        $errors = [];

        foreach ($files as $index => $file) {
            try {
                $dto = new UploadDto($userId, $file, $subdir);
                $paths[] = $this->handleImageUpload($dto);
            } catch (Throwable $e) {
                $errors[] = "File #$index: " . $e->getMessage();
            }
        }

        if (empty($paths) && !empty($errors)) {
            throw new InvalidArgumentException('All uploads failed: ' . implode('; ', $errors));
        }

        return $paths;
    }
}
