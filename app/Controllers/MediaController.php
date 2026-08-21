<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\MediaService;
use finfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;

/**
 * Controller for Media Delivery.
 *
 * Serves public media with strong HTTP caching,
 * and protected chapter pages via temporary signed access tokens.
 *
 * @package App\Controllers
 */
final class MediaController
{
    public function __construct(
        private readonly MediaService $mediaService
    ) {
    }

    /**
     * Serves public media assets (covers, avatars, blog images).
     */
    public function servePublicMedia(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $filename = (string) ($args['filename'] ?? '');

        if ($this->mediaService->isChapterMedia($filename)) {
            return ResponseHelper::error(403, 'Chapter media requires access token');
        }

        $filePath = $this->mediaService->resolveFile($filename);

        if ($filePath === null) {
            return ResponseHelper::error(404, 'Media not found');
        }

        return $this->streamFileResponse($request, $filePath, true);
    }

    /**
     * Serves protected chapter media pages using a temporary access token.
     */
    public function serveChapterMedia(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        $data = $this->mediaService->verifyChapterToken($token);

        if ($data === null) {
            return ResponseHelper::error(403, 'Invalid or expired chapter media token');
        }

        $filePath = $this->mediaService->resolveFile($data['f']);
        if ($filePath === null) {
            return ResponseHelper::error(404, 'Chapter page not found');
        }

        return $this->streamFileResponse($request, $filePath, false);
    }

    private function streamFileResponse(ServerRequestInterface $request, string $filePath, bool $isPublic): ResponseInterface
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath) ?: 'application/octet-stream';
        $mtime = filemtime($filePath) ?: time();
        $etag = sprintf('"%s-%s"', dechex($mtime), dechex((int) filesize($filePath)));

        // HTTP Conditional caching headers check
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');

        if ($ifNoneMatch === $etag || ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime)) {
            return (new Response(304))
                ->withHeader('ETag', $etag)
                ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s T', $mtime));
        }

        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            return ResponseHelper::error(500, 'Unable to read media file');
        }

        $stream = new Stream($fh);
        $res = (new Response(200))
            ->withBody($stream)
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Length', (string) filesize($filePath))
            ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s T', $mtime))
            ->withHeader('ETag', $etag);

        if ($isPublic) {
            return $res->withHeader('Cache-Control', 'public, max-age=86400, immutable');
        }

        return $res
            ->withHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache');
    }
}
