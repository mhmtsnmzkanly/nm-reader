<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTO\UserActivityDto;
use App\Helpers\ResponseHelper;
use App\Services\CommentService;
use App\Services\RatingService;
use App\Services\UserActivityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unified Controller for all User Interaction API endpoints.
 *
 * Consolidates Comments, Ratings, and Activity tracking logic.
 *
 * @package App\Controllers
 */
final class UserInteractionController
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly RatingService $ratings,
        private readonly UserActivityService $activity
    ) {
    }

    // --- COMMENTS ---

    public function createChapterComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            $commentId = $this->comments->addToChapter($userId, (string)$args['chapterId'], (string)($payload['body'] ?? ''), isset($payload['parent_id']) ? (int)$payload['parent_id'] : null);
            return ResponseHelper::created(['comment_id' => $commentId]);
        } catch (\InvalidArgumentException $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function createSeriesComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            $commentId = $this->comments->addToSeries($userId, (string)$args['type'], (string)$args['slug'], (string)($payload['body'] ?? ''), isset($payload['parent_id']) ? (int)$payload['parent_id'] : null);
            return ResponseHelper::created(['comment_id' => $commentId]);
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function createBlogComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            $commentId = $this->comments->addToBlog($userId, (string)$args['slug'], (string)($payload['body'] ?? ''), isset($payload['parent_id']) ? (int)$payload['parent_id'] : null);
            return ResponseHelper::created(['comment_id' => $commentId]);
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function listChapterComments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $viewerId = $_SESSION['user_id'] ?? null;
        return ResponseHelper::success($this->comments->listByChapter((string)$args['chapterId'], $page, $perPage, $viewerId), ['page' => $page, 'per_page' => $perPage]);
    }

    public function listSeriesComments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $viewerId = $_SESSION['user_id'] ?? null;
        return ResponseHelper::success($this->comments->listBySeriesSlug((string)$args['slug'], $page, $perPage, $viewerId), ['page' => $page, 'per_page' => $perPage]);
    }

    public function listBlogComments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $viewerId = $_SESSION['user_id'] ?? null;
        return ResponseHelper::success($this->comments->listByBlogSlug((string)$args['slug'], $page, $perPage, $viewerId), ['page' => $page, 'per_page' => $perPage]);
    }

    public function voteComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            return ResponseHelper::success($this->comments->vote($userId, (int)$args['commentId'], (int)($payload['vote'] ?? 0)));
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function voteBlogComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            return ResponseHelper::success($this->comments->voteBlogComment($userId, (string)$args['slug'], (int)$args['commentId'], (int)($payload['vote'] ?? 0)));
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- RATINGS ---

    public function rate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            $this->ratings->rate($userId, (string)$args['slug'], (int)($payload['rating'] ?? 0));
            return ResponseHelper::success(['rated' => true]);
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    public function rateByType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();
            $this->ratings->rateByType($userId, (string)$args['type'], (string)$args['slug'], (int)($payload['rating'] ?? 0));
            return ResponseHelper::success(['rated' => true]);
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    // --- ACTIVITY ---

    public function trackActivity(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) return ResponseHelper::error(401, 'Unauthorized');
        $body = (array) $request->getParsedBody();
        $tabId = (string) ($body['tab_id'] ?? '');
        $duration = (int) ($body['duration'] ?? 0);
        if ($tabId === '' || $duration <= 0) return ResponseHelper::error(400, 'Invalid data');
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $ua = $request->getServerParams()['HTTP_USER_AGENT'] ?? null;
        try {
            $this->activity->logActivity(new UserActivityDto((string)$userId, $tabId, $duration, hash('sha256', $ip), $ua));
            return ResponseHelper::success(['tracked' => true]);
        } catch (\Exception $e) { return ResponseHelper::error(400, $e->getMessage()); }
    }

    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        return [max(1, (int) ($query['page'] ?? 1)), max(1, min(50, (int) ($query['per_page'] ?? 20)))];
    }
}
