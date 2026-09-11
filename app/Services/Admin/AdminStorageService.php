<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Config;
use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Services\AnalyticsAggregationService;
use App\Services\BackupService;
use App\Services\CacheService;
use App\Services\QueueService;
use App\Services\RetentionService;
use App\Services\SeriesService;
use App\Services\SlugService;
use App\Services\SitemapService;

use App\Services\Admin\AdminConsoleServiceBase;

/** Domain service extracted from the legacy admin console service. */
final class AdminStorageService extends AdminConsoleServiceBase
{
    public function listUploads(int $page, int $perPage, string $query = '', ?string $mime = null, bool $orphansOnly = false): array
    {
        $result = $this->repo->listUploads($page, $perPage, trim($query), $mime, $orphansOnly);
        $result['stats'] = $this->repo->uploadStats();
        return $result;
    }

    public function deleteUpload(int $id, string $moderatorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $info = $this->repo->deleteUpload($id);
            if (!$info) {
                $this->pdo->commit();
                return;
            }
            $this->createModerationAction($moderatorId, 'system', (string) $id, 'delete', "Deleted system upload record: " . (string) ($info['image_id'] ?? ''));
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        if ($info) {
            $filePath = (string) ($info['file_path'] ?? '');
            if ($filePath !== '') {
                $basePath = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\');
                $cleanName = basename($filePath);
                $storageDiskPath = $basePath . '/storage/media/' . $cleanName;
                if (is_file($storageDiskPath)) {
                    @unlink($storageDiskPath);
                }
                $publicDiskPath = $basePath . '/public' . $filePath;
                if (is_file($publicDiskPath)) {
                    @unlink($publicDiskPath);
                }
            }
        }
    }

    public function deleteUploads(array $ids, string $moderatorId): array
    {
        $deleted = 0;
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            if ($id <= 0) continue;
            $before = $this->repo->uploadById($id);
            if (!$before) continue;
            $this->deleteUpload($id, $moderatorId);
            $deleted++;
        }
        return ['deleted' => $deleted];
    }

    public function cleanupUnreferencedUploads(array $paths, string $userId): array
    {
        $ids = $this->repo->unreferencedUploadIdsByPaths($paths, $userId);
        $deleted = 0;
        foreach ($ids as $id) {
            $info = $this->repo->deleteUpload((int) $id);
            if (!$info) continue;
            $filePath = (string) ($info['file_path'] ?? '');
            if ($filePath !== '') {
                $basePath = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\');
                $cleanName = basename($filePath);
                foreach ([$basePath . '/storage/media/' . $cleanName, $basePath . '/public' . $filePath] as $diskPath) {
                    if (is_file($diskPath)) @unlink($diskPath);
                }
            }
            $deleted++;
        }
        return ['deleted' => $deleted];
    }

    public function optimizeUpload(int $id, string $moderatorId): array
    {
        $upload = $this->repo->uploadById($id);
        if (!$upload) throw new \InvalidArgumentException('Upload not found');
        $mime = (string)$upload['mime_type'];
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !function_exists('imagecreatefromstring')) {
            throw new \DomainException('This image type cannot be optimized on this server');
        }
        $this->repo->markUploadProcessing($id);
        try {
        $basePath = rtrim((string) (Config::getSettings()['app']['base_path'] ?? dirname(__DIR__, 3)), '/\\');
        $path = $basePath . '/storage/media/' . basename((string)$upload['file_path']);
        if (!is_file($path)) throw new \DomainException('Physical file not found');
        $raw = file_get_contents($path);
        $image = $raw === false ? false : @imagecreatefromstring($raw);
        if ($image === false) throw new \DomainException('Image data is invalid');
        $temporary = tempnam(dirname($path), 'opt_');
        if ($temporary === false) { imagedestroy($image); throw new \RuntimeException('Temporary file could not be created'); }
        if (in_array($mime, ['image/png', 'image/webp'], true)) { imagealphablending($image, false); imagesavealpha($image, true); }
        $saved = match ($mime) { 'image/jpeg' => imagejpeg($image, $temporary, 82), 'image/png' => imagepng($image, $temporary, 8), 'image/webp' => imagewebp($image, $temporary, 78), default => false };
        imagedestroy($image);
        if (!$saved) { @unlink($temporary); throw new \RuntimeException('Optimized image could not be written'); }
        $oldSize = (int)(filesize($path) ?: 0);
        $newSize = (int)(filesize($temporary) ?: 0);
        if ($newSize > 0 && ($oldSize === 0 || $newSize < $oldSize)) {
            if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Optimized image could not replace original'); }
        } else {
            @unlink($temporary);
            $newSize = $oldSize;
        }
        $this->pdo->beginTransaction();
        try {
            $this->repo->updateUploadFileSize($id, $newSize, hash_file('sha256', $path) ?: null);
            $this->repo->createModerationAction($moderatorId, 'system', (string) $id, 'update', "Upload optimized: $oldSize -> $newSize bytes");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return ['id' => $id, 'old_size' => $oldSize, 'new_size' => $newSize, 'saved_bytes' => max(0, $oldSize - $newSize)];
        } catch (\Throwable $e) {
            try { $this->repo->markUploadProcessingFailed($id, $e->getMessage()); } catch (\Throwable) {}
            throw $e;
        }
    }
}
