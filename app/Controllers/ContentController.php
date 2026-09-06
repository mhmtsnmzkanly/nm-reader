<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AnalyticsService;
use App\Services\SeriesService;
use App\Services\ChapterService;
use App\Services\WalletService;
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
        private readonly AnalyticsService $analytics,
        private readonly WalletService $wallets
    ) {
    }

    // --- DISCOVERY & LISTING ---

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
        $items = $this->seriesService->home($page, $perPage, is_string($userId) ? $userId : null);
        $this->analytics->track('home_view', is_string($userId) ? $userId : null, 'home', null, ['page' => $page, 'per_page' => $perPage], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'));
        return ResponseHelper::paginate($items, $page, $perPage);
    }

    public function byType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
            return ResponseHelper::paginate($this->seriesService->byType((string)$args['type'], $page, $perPage, is_string($userId) ? $userId : null), $page, $perPage);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function latestChapters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
        return ResponseHelper::paginate($this->seriesService->latestChapters($page, $perPage, is_string($userId) ? $userId : null), $page, $perPage);
    }

    public function latestChaptersByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
            return ResponseHelper::paginate($this->seriesService->latestChaptersByType((string)$args['type'], $page, $perPage, is_string($userId) ? $userId : null), $page, $perPage);
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
            $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
            $item = $this->seriesService->contentDetailByType((string)$args['type'], (string)$args['slug'], $ip, is_string($userId) ? $userId : null);
            return $item ? ResponseHelper::success($item) : ResponseHelper::error(404, 'Content not found');
        } catch (\DomainException $e) {
            $code = str_contains($e->getMessage(), 'MEMBERS_ONLY_REQUIRED') ? 401 : 400;
            return ResponseHelper::error($code, $e->getMessage());
        }
    }

    // --- CHAPTERS ---

    public function chaptersByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            [$page, $perPage] = $this->pagination($request);
            $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
            $items = $this->seriesService->chaptersByType((string)$args['type'], (string)$args['slug'], $page, $perPage, is_string($userId) ? $userId : null);
            return ResponseHelper::paginate($items, $page, $perPage);
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function chapterDetail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $num = (string) $args['chapterNumber'];
        $type = (string) $args['type'];
        $slug = (string) $args['slug'];
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);

        try {
            $chapter = $this->chapterService->getByTypeSlugAndNumber($type, $slug, $num, $ip, is_string($userId) ? $userId : null);
            if (!$chapter) return ResponseHelper::error(404, 'Chapter not found');
            return ResponseHelper::success($chapter);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'MEDIA_SECRET')) {
                return ResponseHelper::error(503, 'Protected media is temporarily unavailable.', 'media.signing_unavailable');
            }
            throw $e;
        } catch (\DomainException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- TAXONOMY (GENRES & TAGS) ---

    public function genres(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::paginate($this->seriesService->series_genres($page, $perPage), $page, $perPage);
    }

    public function genre(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
        return ResponseHelper::paginate($this->seriesService->byGenre((string)$args['slug'], $page, $perPage, is_string($userId) ? $userId : null), $page, $perPage);
    }

    public function tags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        return ResponseHelper::paginate($this->seriesService->series_tags($page, $perPage), $page, $perPage);
    }

    public function tag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
        return ResponseHelper::paginate($this->seriesService->byTag((string)$args['slug'], $page, $perPage, is_string($userId) ? $userId : null), $page, $perPage);
    }

    // --- SEARCH ---

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $params = $request->getQueryParams();
        $query = trim((string) ($params['q'] ?? ''));
        $genres = !empty($params['genres']) ? array_values(array_filter(is_array($params['genres']) ? $params['genres'] : explode(',', (string) $params['genres']))) : [];
        $tags = !empty($params['tags']) ? array_values(array_filter(is_array($params['tags']) ? $params['tags'] : explode(',', (string) $params['tags']))) : [];
        $status = trim((string) ($params['status'] ?? ''));
        $sort = trim((string) ($params['sort'] ?? ''));

        $filters = [
            'genres' => $genres,
            'tags' => $tags,
            'status' => $status,
            'sort' => $sort,
        ];

        $userId = $request->getAttribute('user_id') ?: ($_SESSION['user_id'] ?? null);
        $items = $this->seriesService->search($query, $page, $perPage, $filters, is_string($userId) ? $userId : null);
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $this->seriesService->logSearch($query, count($items), is_string($userId) ? $userId : null, $ip);

        $meta = ['q' => $query];
        if (!empty($genres)) $meta['genres'] = $genres;
        if (!empty($tags)) $meta['tags'] = $tags;
        if ($status !== '') $meta['status'] = $status;
        if ($sort !== '') $meta['sort'] = $sort;

        return ResponseHelper::paginate($items, $page, $perPage, null, $meta);
    }

    public function suggest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        if (mb_strlen($query) < 2) return ResponseHelper::success([]);
        return ResponseHelper::success($this->seriesService->suggest($query));
    }

    public function shopPackages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->wallets->packages($page, $perPage, true);
        return ResponseHelper::paginate(
            $result['items'],
            $page,
            $perPage,
            $result['meta']['total'] ?? null,
            ['checkout_available' => false]
        );
    }

    public function shopFeatures(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->wallets->featureProducts(true));
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
        return ResponseHelper::paginate($this->seriesService->followedContents($userId, $page, $perPage), $page, $perPage);
    }

    public function unlockByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->unlockSeries($userId, (string) $args['type'], (string) $args['slug']));
        } catch (\DomainException $e) {
            $code = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 402;
            return ResponseHelper::error($code, $e->getMessage());
        }
    }

    public function unlockChapter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->unlockChapter($userId, (string) $args['chapterId']));
        } catch (\DomainException $e) {
            $message = strtolower($e->getMessage());
            $code = str_contains($message, 'not found') ? 404 : (str_contains($message, 'not individually') ? 400 : 402);
            return ResponseHelper::error($code, $e->getMessage());
        }
    }

    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        return [max(1, (int) ($query['page'] ?? 1)), max(1, min(50, (int) ($query['per_page'] ?? 20)))];
    }
}
