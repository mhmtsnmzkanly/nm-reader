<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AdminService;
use App\Services\SiteConfigService;
use App\Services\QueueService;
use App\Services\MetricsService;
use App\Services\RetentionService;
use App\Services\UploadService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for Administrative API operations.
 *
 * Provides secured endpoints for:
 * - Content (Series) and Chapter management (CRUD).
 * - Triggering manual background jobs and cleanup tasks.
 * - Modifying global site configuration.
 * - Monitoring system metrics.
 *
 * @package App\Controllers
 */
final class AdminController
{
    public function __construct(
        private readonly AdminService $adminService,
        private readonly SiteConfigService $siteConfig,
        private readonly QueueService $queueService,
        private readonly MetricsService $metricsService,
        private readonly RetentionService $retentionService,
        private readonly UploadService $uploadService
    )
    {
    }

    /**
     * Retrieves administrative dashboard overview data.
     */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success([
            'message' => 'Admin endpoint is active',
            'metrics' => $this->metricsService->snapshot(),
        ]);
    }

    /**
     * Creates a new content (series) entry.
     */
    public function createContent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $created = $this->adminService->createContent($payload, $moderatorId);
            return ResponseHelper::created($created);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Updates an existing content entry.
     */
    public function updateContent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->adminService->updateContent((string) $args['id'], $payload, $moderatorId);
            return ResponseHelper::success();
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Adds a new chapter to a series.
     *
     * @param array $args Must contain 'type' and 'slug' of the parent content.
     */
    public function createChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $created = $this->adminService->createChapter(
                (string) $args['type'],
                (string) $args['slug'],
                $payload,
                $moderatorId
            );

            return ResponseHelper::created($created);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Lists chapters for a specific content entry with pagination.
     */
    public function listChapters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $contentId = (string) $args['id'];
        $result = $this->adminService->listChapters($contentId, $page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Fetches details for a single chapter.
     */
    public function getChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $chapter = $this->adminService->getChapter((string) $args['id']);
            return ResponseHelper::success($chapter);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Permanently deletes a chapter.
     */
    public function deleteChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->adminService->deleteChapter((string) $args['id'], $moderatorId);
            return ResponseHelper::success(['deleted' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Updates an existing chapter (title, number, content).
     */
    public function updateChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->adminService->updateChapter((string) $args['id'], $payload, $moderatorId);
            return ResponseHelper::success(['updated' => true]);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Manually triggers background job processing.
     */
    public function runJobsOnce(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $limit = max(1, min(100, (int) ($payload['limit'] ?? 10)));

        return ResponseHelper::success($this->queueService->runOnce($limit));
    }

    /**
     * Manually triggers data retention cleanup.
     */
    public function cleanupRetention(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $days = max(1, min(3650, (int) ($payload['days'] ?? 30)));
        return ResponseHelper::success($this->retentionService->cleanup($days));
    }

    /**
     * Helper to extract pagination parameters from the query string.
     */
    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20)));

        return [$page, $perPage];
    }

    /**
     * Endpoint for bulk image uploading.
     */
    public function uploadImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $postSize = (int) ($request->getHeaderLine('Content-Length') ?: 0);
            $files = $request->getUploadedFiles();
            
            // Check for post_max_size excess (empty POST/FILES with Content-Length > 0)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($files) && $postSize > 0) {
                return ResponseHelper::error(400, "The uploaded files are too large. Total size ($postSize bytes) exceeds server limit.");
            }

            $toProcess = [];
            $collector = function($item) use (&$collector, &$toProcess) {
                if ($item instanceof \Psr\Http\Message\UploadedFileInterface) {
                    $toProcess[] = $item;
                } elseif (is_array($item)) {
                    foreach ($item as $sub) $collector($sub);
                }
            };
            $collector($files);

            if (empty($toProcess)) {
                return ResponseHelper::error(400, "No valid files detected in the request. Content-Length: $postSize. PSR7-Count: " . count($files));
            }

            // Sort files by original filename using natural sort (1.png, 2.png, 10.png)
            usort($toProcess, function ($a, $b) {
                return strnatcasecmp($a->getClientFilename() ?? '', $b->getClientFilename() ?? '');
            });

            $userId = (string) $request->getAttribute('user_id');
            $body = (array) ($request->getParsedBody() ?? []);
            $queryParams = $request->getQueryParams();
            // Try multiple sources for 'type' to ensure reliability across environments
            $type = (string) ($queryParams['type'] ?? $body['type'] ?? $_POST['type'] ?? 'chapters');
            
            $paths = $this->uploadService->handleBulkImageUpload($userId, $toProcess, $type);
            return ResponseHelper::success(['paths' => $paths]);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\Throwable $e) {
            error_log("Upload Crash: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return ResponseHelper::error(500, "Internal Server Error: " . $e->getMessage());
        }
    }
}
