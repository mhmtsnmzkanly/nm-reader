<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\ChapterService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for Chapter-related API endpoints.
 *
 * Provides functionality to retrieve specific chapters by their series context
 * and handles reading status tracking for authenticated users.
 *
 * @package App\Controllers
 */
final class ChapterController
{
    public function __construct(private readonly ChapterService $chapterService)
    {
    }

    /**
     * Stub for ambiguous direct chapter routing. 
     * Encourages the use of series-contextual routing.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ResponseHelper::error(400, 'Ambiguous chapter route. Use /api/v1/content/{type}/{slug}/chapter/{chapterNumber}');
    }

    /**
     * Retrieves chapter details (text/images) for a specific series.
     *
     * Tracks views and automatically marks the chapter as read for authenticated users.
     *
     * @param array $args Must contain 'type', 'slug', and 'chapterNumber'.
     */
    public function showByContent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $chapterNumber = (string) $args['chapterNumber'];
        $type = (string) $args['type'];
        $slug = (string) $args['slug'];
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        try {
            $chapter = $this->chapterService->getByTypeSlugAndNumber($type, $slug, $chapterNumber, $ip, $userId);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }

        if (!is_array($chapter)) {
            return ResponseHelper::error(404, 'Chapter not found');
        }

        if ($userId !== null) {
            $this->chapterService->markRead((string) $userId, (string) $chapter['id']);
        }

        return ResponseHelper::success($chapter);
    }
}
