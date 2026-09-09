<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class StorageController extends AdminController
{
    public function uploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            [$page, $perPage] = $this->pagination($request);
            $query = $request->getQueryParams();
            $result = $this->storageService->listUploads($page, $perPage, (string)($query['q'] ?? ''), isset($query['mime']) ? (string)$query['mime'] : null, filter_var($query['orphans'] ?? false, FILTER_VALIDATE_BOOL));
            return ResponseHelper::paginate($result['items'], $page, $perPage, $result['meta']['total'] ?? null, ['stats' => $result['stats'] ?? []]);
        }
    
    public function deleteUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            $modId = (string) $request->getAttribute('user_id');
            $this->storageService->deleteUpload((int)$args['id'], $modId);
            return ResponseHelper::success();
        }
    
    public function cleanupUploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array) $request->getParsedBody();
            $paths = is_array($payload['paths'] ?? null) ? $payload['paths'] : [];
            $userId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->storageService->cleanupUnreferencedUploads($paths, $userId));
        }
    
    public function deleteUploads(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array)$request->getParsedBody();
            return ResponseHelper::success($this->storageService->deleteUploads((array)($payload['ids'] ?? []), (string)$request->getAttribute('user_id')));
        }
    
    public function optimizeUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            try { return ResponseHelper::success($this->storageService->optimizeUpload((int)$args['id'], (string)$request->getAttribute('user_id'))); }
            catch (\InvalidArgumentException|\DomainException $e) { return ResponseHelper::error(422, $e->getMessage()); }
        }
    
    public function uploadImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $files = $request->getUploadedFiles();
            $toProcess = [];
            $zipFile = null;
            $collector = function($item) use (&$collector, &$toProcess) {
                if ($item instanceof \Psr\Http\Message\UploadedFileInterface) $toProcess[] = $item;
                elseif (is_array($item)) foreach ($item as $sub) $collector($sub);
            };
            $collector($files);
            if (empty($toProcess)) return ResponseHelper::error(400, "No files.");
            usort($toProcess, fn($a, $b) => strnatcasecmp($a->getClientFilename() ?? '', $b->getClientFilename() ?? ''));
            $userId = (string) $request->getAttribute('user_id');
            $type = (string) ($request->getQueryParams()['type'] ?? 'chapters');
    
            foreach ($toProcess as $candidate) {
                $name = strtolower((string) ($candidate->getClientFilename() ?? ''));
                $mime = strtolower((string) ($candidate->getClientMediaType() ?? ''));
                if (str_ends_with($name, '.zip') || $mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
                    $zipFile = $candidate;
                    break;
                }
            }
    
            if ($zipFile !== null) {
                return ResponseHelper::success(['paths' => $this->uploadService->handleZipImageUpload($userId, $zipFile, $type)]);
            }
    
            return ResponseHelper::success(['paths' => $this->uploadService->handleBulkImageUpload($userId, $toProcess, $type)]);
        }
}
