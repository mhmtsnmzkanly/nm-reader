<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\UploadDto;
use App\Services\EntityIdService;
use App\Repositories\UploadRepository;
use finfo;
use Psr\Http\Message\UploadedFileInterface;
use ZipArchive;
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
    private const ZIP_MAX_FILES = 500;
    private const ZIP_MAX_TOTAL_BYTES = 209715200; // 200 MB
    private const ZIP_MAX_FILE_BYTES = 20971520; // 20 MB

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

        return $this->processImagePath(
            $dto->userId,
            $file->getStream()->getMetadata('uri'),
            $file->getClientFilename() ?? 'unknown',
            (int) ($file->getSize() ?? 0),
            $dto->targetSubdir
        );
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

    public function handleZipImageUpload(string $userId, UploadedFileInterface $file, string $subdir = 'chapters'): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Zip upload failed with error code: ' . $file->getError());
        }

        $tmpPath = $file->getStream()->getMetadata('uri');
        if (!is_string($tmpPath) || !is_file($tmpPath)) {
            throw new RuntimeException('Zip upload stream is not readable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new InvalidArgumentException('Invalid zip archive.');
        }

        $entries = [];
        $totalBytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }
            $name = (string) $stat['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }

            $base = basename($name);
            if ($base === '' || str_contains($base, '..')) {
                continue;
            }

            $size = (int) ($stat['size'] ?? 0);
            if ($size <= 0 || $size > self::ZIP_MAX_FILE_BYTES) {
                continue;
            }

            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $totalBytes += $size;
            $entries[] = ['index' => $i, 'name' => $name, 'base' => $base, 'size' => $size];
        }

        if (count($entries) > self::ZIP_MAX_FILES || $totalBytes > self::ZIP_MAX_TOTAL_BYTES) {
            $zip->close();
            throw new InvalidArgumentException('Zip contains too many files or is too large.');
        }

        usort($entries, static fn ($a, $b) => strnatcasecmp($a['base'], $b['base']));

        $paths = [];
        foreach ($entries as $entry) {
            $stream = $zip->getStream($entry['name']);
            if ($stream === false) {
                continue;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'nmzip_');
            if ($tmpFile === false) {
                fclose($stream);
                continue;
            }

            $out = fopen($tmpFile, 'wb');
            if ($out === false) {
                fclose($stream);
                @unlink($tmpFile);
                continue;
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            try {
                $paths[] = $this->processImagePath($userId, $tmpFile, $entry['base'], $entry['size'], $subdir);
            } catch (Throwable) {
                // Skip individual failures to allow partial success.
            } finally {
                @unlink($tmpFile);
            }
        }

        $zip->close();

        if (empty($paths)) {
            throw new InvalidArgumentException('All zip images failed validation.');
        }

        return $paths;
    }

    private function processImagePath(string $userId, mixed $path, string $originalName, int $size, ?string $targetSubdir): string
    {
        if (!is_string($path) || !is_file($path)) {
            throw new RuntimeException('Upload stream is not readable.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path) ?: 'application/octet-stream';
        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new InvalidArgumentException('Unsupported image type: ' . $mimeType);
        }

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('Invalid image data.');
        }

        $ext = self::ALLOWED_MIME_TYPES[$mimeType];
        $imageId = $this->entityIds->generateImageId();

        $prefix = match ($targetSubdir) {
            'users/profile' => 'user.profile',
            'users/cover'   => 'user.cover',
            'chapters'      => 'chapter',
            'series_cover'  => 'cover',
            'blogs', 'system' => 'content.cover',
            default => str_replace('/', '.', trim($targetSubdir ?? 'misc', '/'))
        };

        $fileName = $prefix . '.' . $imageId . '.' . $ext;
        $targetDir = rtrim($this->baseUploadDir, '/');
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new RuntimeException('Failed to create upload directory.');
            }
        }
        $targetPath = $targetDir . '/' . $fileName;

        if ($mimeType === 'image/gif') {
            if (!copy($path, $targetPath)) {
                throw new RuntimeException('Failed to store gif file.');
            }
        } else {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new RuntimeException('Failed to read uploaded file data.');
            }

            $image = @imagecreatefromstring($raw);
            if ($image === false) {
                throw new InvalidArgumentException('Invalid image data.');
            }

            if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            $saved = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, $targetPath, 85),
                'image/png' => imagepng($image, $targetPath, 6),
                'image/webp' => imagewebp($image, $targetPath, 80),
                default => false,
            };
            imagedestroy($image);

            if (!$saved) {
                throw new RuntimeException('Failed to write processed image.');
            }
        }

        $publicPath = '/uploads/' . $fileName;
        $this->repository->logImageUpload(
            $userId,
            $imageId,
            $originalName,
            $mimeType,
            $size,
            $publicPath
        );

        return $publicPath;
    }
}
