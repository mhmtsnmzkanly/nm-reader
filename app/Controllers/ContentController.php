<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AnalyticsService;
use App\Services\SeriesService;
use App\Services\ChapterService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unified Controller for public Content (Series & Chapters) API endpoints.
 *
 * Provides functionality for discovering novels and manga, reading chapters,
 * taxonomy navigation, searching, and managing user follows.
 *
 * @package App\Controllers
 */
final class ContentController
{
    public function __construct(
        private readonly SeriesService $seriesService,
        private readonly ChapterService $chapterService,
        private readonly AnalyticsService $analytics
    ) {
    }

    // --- DISCOVERY & LISTING ---

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $items = $this->seriesService->home($page, $perPage);
        $userId = $request->getAttribute('user_id');
        $this->analytics->track('home_view', is_string($userId) ? $userId : null, 'home', null, ['page' => $page, 'per_page' => $perPage], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'));
        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    public function byType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            return ResponseHelper::success($this->seriesService->byType((string)$args['type'], $page, $perPage), ['page' => $page, 'per_page' => $perPage]);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function latestChapters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->latestChapters($page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    public function latestChaptersByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            return ResponseHelper::success($this->seriesService->latestChaptersByType((string)$args['type'], $page, $perPage), ['page' => $page, 'per_page' => $perPage]);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- SERIES DETAIL ---

    public function content(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $item = $this->seriesService->contentDetail((string)$args['slug'], $ip);
        return $item ? ResponseHelper::success($item) : ResponseHelper::error(404, 'Not found');
    }

    public function contentByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
            $item = $this->seriesService->contentDetailByType((string)$args['type'], (string)$args['slug'], $ip, $userId);
            return $item ? ResponseHelper::success($item) : ResponseHelper::error(404, 'Not found');
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- CHAPTERS ---

    public function chaptersByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $items = $this->seriesService->chaptersByType((string)$args['type'], (string)$args['slug'], $page, $perPage);
            return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function chapterDetail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $num = (string) $args['chapterNumber'];
        $type = (string) $args['type'];
        $slug = (string) $args['slug'];
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        try {
            $chapter = $this->chapterService->getByTypeSlugAndNumber($type, $slug, $num, $ip, $userId);
            if (!$chapter) return ResponseHelper::error(404, 'Chapter not found');
            if ($userId) $this->chapterService->markRead($userId, (string)$chapter['id']);
            return ResponseHelper::success($chapter);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- TAXONOMY (GENRES & TAGS) ---

    public function genres(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->series_genres($page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    public function genre(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->byGenre((string)$args['slug'], $page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    public function tags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->series_tags($page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    public function tag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->byTag((string)$args['slug'], $page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    // --- SEARCH ---

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        $items = $this->seriesService->search($query, $page, $perPage);
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $userId = $request->getAttribute('user_id');
        $this->seriesService->logSearch($query, count($items), is_string($userId) ? $userId : null, $ip);
        return ResponseHelper::success($items, ['q' => $query, 'page' => $page, 'per_page' => $perPage]);
    }

    public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        if (mb_strlen($query) < 2) return ResponseHelper::success([]);
        return ResponseHelper::success($this->seriesService->suggest($query));
    }

    // --- USER INTERACTIONS (FOLLOWS) ---

    public function followByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $this->seriesService->followByType($userId, (string)$args['type'], (string)$args['slug']);
            return ResponseHelper::success(['followed' => true]);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function unfollowByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $this->seriesService->unfollowByType($userId, (string)$args['type'], (string)$args['slug']);
            return ResponseHelper::success(['followed' => false]);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function followed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->followedContents($userId, $page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        return [max(1, (int) ($query['page'] ?? 1)), max(1, min(50, (int) ($query['per_page'] ?? 20)))];
    }
}
