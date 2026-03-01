<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\CommentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for managing user Comments on Chapters and Blogs.
 *
 * Provides API endpoints for adding comments, retrieving paginated lists of comments
 * (including threaded replies), and voting on comments.
 *
 * @package App\Controllers
 */
final class CommentController
{
    public function __construct(private readonly CommentService $comments)
    {
    }

    /**
     * Adds a new comment or reply to a chapter.
     *
     * @param array $args Must contain 'chapterId'.
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $chapterId = (string) $args['chapterId'];
            $payload = (array) $request->getParsedBody();
            $body = (string) ($payload['body'] ?? '');
            $parentId = isset($payload['parent_id']) ? (int) $payload['parent_id'] : null;

            $commentId = $this->comments->addToChapter($userId, $chapterId, $body, $parentId);

            return ResponseHelper::created(['comment_id' => $commentId]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Adds a new comment or reply to a series.
     */
    public function createSeries(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $type = (string) $args['type'];
            $slug = (string) $args['slug'];
            $payload = (array) $request->getParsedBody();
            $body = (string) ($payload['body'] ?? '');
            $parentId = isset($payload['parent_id']) ? (int) $payload['parent_id'] : null;

            $commentId = $this->comments->addToSeries($userId, $type, $slug, $body, $parentId);

            return ResponseHelper::created(['comment_id' => $commentId]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Adds a new comment or reply to a blog post.
     */
     * @param array $args Must contain 'slug'.
     */
    public function createBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $slug = (string) $args['slug'];
            $payload = (array) $request->getParsedBody();
            $body = (string) ($payload['body'] ?? '');
            $parentId = isset($payload['parent_id']) ? (int) $payload['parent_id'] : null;

            $commentId = $this->comments->addToBlog($userId, $slug, $body, $parentId);

            return ResponseHelper::created(['comment_id' => $commentId]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Lists comments for a specific chapter with pagination.
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $chapterId = (string) $args['chapterId'];
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
        $viewerUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        $items = $this->comments->listByChapter($chapterId, $page, $perPage, $viewerUserId);

        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Lists comments for a specific series.
     */
    public function listSeries(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
        $viewerUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        $items = $this->comments->listBySeriesSlug($slug, $page, $perPage, $viewerUserId);

        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Lists comments for a specific blog post with pagination.
     */
    public function listBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
        $viewerUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;

        $items = $this->comments->listByBlogSlug($slug, $page, $perPage, $viewerUserId);

        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Records or retracts a user's vote on a comment.
     */
    public function vote(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $commentId = (int) $args['commentId'];
            $payload = (array) $request->getParsedBody();
            $vote = (int) ($payload['vote'] ?? 0);

            $result = $this->comments->vote($userId, $commentId, $vote);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Specialised endpoint for voting on blog-related comments.
     */
    public function voteBlogComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $slug = (string) $args['slug'];
            $commentId = (int) $args['commentId'];
            $payload = (array) $request->getParsedBody();
            $vote = (int) ($payload['vote'] ?? 0);

            return ResponseHelper::success($this->comments->voteBlogComment($userId, $slug, $commentId, $vote));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }
}
