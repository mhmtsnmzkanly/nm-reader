<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AnalyticsService;
use App\Services\SeriesService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for public Series (Content) API endpoints.
 *
 * Provides functionality for discovering novels and manga, listing chapters,
 * performing searches, and managing user content follows. 
 * Includes integrated analytics tracking for views and interactions.
 *
 * @package App\Controllers
 */
final class SeriesController
{
    public function __construct(
        private readonly SeriesService $seriesService,
        private readonly AnalyticsService $analytics
    )
    {
    }

    /**
     * Aggregates and returns data for the homepage dashboard.
     */
    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $items = $this->seriesService->home($page, $perPage);
        $userId = $request->getAttribute('user_id');
        $this->analytics->track(
            'home_view',
            is_string($userId) ? $userId : null,
            'home',
            null,
            ['page' => $page, 'per_page' => $perPage],
            (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')
        );

        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Retrieves details for a specific content entry by slug.
     */
    public function content(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        $item = $this->seriesService->contentDetail($slug, $ip);
        if ($item === null) {
            return ResponseHelper::error(404, 'Content not found');
        }

        return ResponseHelper::success($item);
    }

    /**
     * Retrieves details for content filtered by its type and slug.
     */
    public function contentByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $type = (string) $args['type'];
            $slug = (string) $args['slug'];
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

            $item = $this->seriesService->contentDetailByType($type, $slug, $ip, $userId);
            if ($item === null) {
                return ResponseHelper::error(404, 'Content not found');
            }

            return ResponseHelper::success($item);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Lists chapters for a series by slug with pagination.
     */
    public function chapters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $slug = (string) $args['slug'];

        $items = $this->seriesService->chapters($slug, $page, $perPage);

        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Lists chapters for a series by type and slug with pagination.
     */
    public function chaptersByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $type = (string) $args['type'];
            $slug = (string) $args['slug'];

            $items = $this->seriesService->chaptersByType($type, $slug, $page, $perPage);

            return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Lists content belonging to a specific type (e.g., all manga).
     */
    public function byType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $type = (string) $args['type'];

            $items = $this->seriesService->byType($type, $page, $perPage);

            return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Lists content associated with a specific genre slug.
     */
    public function genre(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $slug = (string) $args['slug'];

        return ResponseHelper::success(
            $this->seriesService->byGenre($slug, $page, $perPage),
            ['page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * Lists all available series_genres.
     */
    public function series_genres(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->series_genres($page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Fetches globally latest chapters across all content.
     */
    public function latestChapters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::success($this->seriesService->latestChapters($page, $perPage), ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Fetches latest chapters filtered by content type.
     */
    public function latestChaptersByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $type = (string) $args['type'];

            return ResponseHelper::success(
                $this->seriesService->latestChaptersByType($type, $page, $perPage),
                ['page' => $page, 'per_page' => $perPage]
            );
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Lists content associated with a specific tag slug.
     */
    public function tag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $slug = (string) $args['slug'];

        return ResponseHelper::success(
            $this->seriesService->byTag($slug, $page, $perPage),
            ['page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * Lists all available series_tags with content counts.
     */
    public function series_tags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);

        return ResponseHelper::success(
            $this->seriesService->series_tags($page, $perPage),
            ['page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * Performs a text-based search and logs the event.
     */
    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = trim((string) (($request->getQueryParams()['q'] ?? '')));
        $items = $this->seriesService->search($query, $page, $perPage);
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $userId = $request->getAttribute('user_id');
        $this->seriesService->logSearch($query, count($items), is_string($userId) ? $userId : null, $ip);

        return ResponseHelper::success(
            $items,
            ['q' => $query, 'page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * Provides fast autocomplete search suggestions.
     */
    public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = trim((string) (($request->getQueryParams()['q'] ?? '')));
        if (mb_strlen($query) < 2) {
            return ResponseHelper::success([]);
        }

        $items = $this->seriesService->suggest($query);
        return ResponseHelper::success($items);
    }

    /**
     * Allows a user to follow a series by slug.
     */
    public function follow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $slug = (string) $args['slug'];
            $this->seriesService->follow($userId, $slug);
            return ResponseHelper::success(['followed' => true]);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Allows a user to follow a series by type and slug.
     */
    public function followByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $type = (string) $args['type'];
            $slug = (string) $args['slug'];
            $this->seriesService->followByType($userId, $type, $slug);
            return ResponseHelper::success(['followed' => true]);
        } catch (\DomainException $exception) {
            $code = $exception->getMessage() === 'Invalid content type' ? 400 : 404;
            return ResponseHelper::error($code, $exception->getMessage());
        }
    }

    /**
     * Allows a user to unfollow a series.
     */
    public function unfollowByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $type = (string) $args['type'];
            $slug = (string) $args['slug'];
            $this->seriesService->unfollowByType($userId, $type, $slug);
            return ResponseHelper::success(['followed' => false]);
        } catch (\DomainException $exception) {
            $code = $exception->getMessage() === 'Invalid content type' ? 400 : 404;
            return ResponseHelper::error($code, $exception->getMessage());
        }
    }

    /**
     * Lists series followed by the authenticated user.
     */
    public function followed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        [$page, $perPage] = $this->pagination($request);
        $items = $this->seriesService->followedContents($userId, $page, $perPage);
        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));

        return [$page, $perPage];
    }
}
