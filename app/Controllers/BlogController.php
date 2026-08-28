<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\BlogService;
use App\Services\UploadService;
use App\DTO\UploadDto;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for Blog platform API endpoints.
 *
 * Handles public blog discovery, individual post viewing (with vote integration),
 * user submission of new posts, and administrative approval workflows.
 *
 * @package App\Controllers
 */
final class BlogController
{
    public function __construct(
        private readonly BlogService $blogs,
        private readonly UploadService $uploadService
    )
    {
    }

    /**
     * Lists approved blog posts with pagination and sorting.
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $query = $request->getQueryParams();
        $sort = ($query['sort'] ?? '') === 'popular' ? 'popular' : 'latest';

        return ResponseHelper::paginate(
            $this->blogs->listApproved($page, $perPage, $sort),
            $page,
            $perPage
        );
    }

    /**
     * Lists related approved blog posts.
     */
    public function related(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $query = $request->getQueryParams();
        $limit = max(1, min(10, (int) ($query['limit'] ?? 3)));
        $items = $this->blogs->getRelatedApproved($slug, $limit);

        return ResponseHelper::success($items);
    }

    /**
     * Retrieves a single blog post by its unique slug.
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $userId = (string) $request->getAttribute('user_id');
        $blog = $this->blogs->getApprovedBySlug($slug, $userId ?: null);
        if ($blog === null) {
            return ResponseHelper::error(404, 'Blog not found');
        }

        return ResponseHelper::success($blog);
    }

    /**
     * Submits a new blog post for approval.
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $payload = (array) $request->getParsedBody();

            $created = $this->blogs->createPending($userId, $payload);
            return ResponseHelper::created($created);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Records or retacts a user's vote on a blog post.
     */
    public function vote(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $slug = (string) $args['slug'];
            $payload = (array) $request->getParsedBody();
            $vote = (int) ($payload['vote'] ?? 0);

            return ResponseHelper::success($this->blogs->vote($userId, $slug, $vote));
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Lists blog posts written by the authenticated user.
     */
    public function listMyBlogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        [$page, $perPage] = $this->pagination($request);

        return ResponseHelper::paginate(
            $this->blogs->listByUser($userId, $page, $perPage),
            $page,
            $perPage
        );
    }

    /**
     * Lists all blog posts awaiting moderator approval.
     */
    public function pending(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);

        return ResponseHelper::paginate(
            $this->blogs->listPending($page, $perPage),
            $page,
            $perPage
        );
    }

    /**
     * Marks a pending blog post as approved.
     */
    public function approve(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $approverUserId = (string) $request->getAttribute('user_id');
            $blogId = (string) $args['id'];
            $this->blogs->approve($blogId, $approverUserId);

            return ResponseHelper::success([
                'approved' => true,
                'approver_user_id' => $approverUserId,
            ]);
        } catch (\DomainException $exception) {
            $message = $exception->getMessage();
            $statusCode = $message === 'Blog is already approved' ? 409 : 404;
            return ResponseHelper::error($statusCode, $message);
        }
    }

    /**
     * Extracts pagination metadata from request.
     */
    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));

        return [$page, $perPage];
    }

    /**
     * Updates an existing blog post owned by the authenticated user.
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $blogId = (string) $args['id'];
            $payload = (array) $request->getParsedBody();

            $updated = $this->blogs->updateBlog($blogId, $userId, $payload);
            return ResponseHelper::success($updated);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Deletes a blog post owned by the authenticated user.
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $blogId = (string) $args['id'];
            $this->blogs->deleteBlog($blogId, $userId);

            return ResponseHelper::success(['deleted' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Retrieves a single blog post owned by user for editing (including drafts/pending).
     */
    public function showMyBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $blogId = (string) $args['id'];
        $blog = $this->blogs->getUserBlog($blogId, $userId);
        if ($blog === null) {
            return ResponseHelper::error(404, 'Blog not found');
        }

        return ResponseHelper::success($blog);
    }

    /**
     * Uploads an image for a blog post.
     */
    public function uploadImage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $files = $request->getUploadedFiles();
            $file = $files['image'] ?? null;

            if (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
                return ResponseHelper::error(400, "No 'image' file provided.");
            }

            $dto = new UploadDto($userId, $file, 'blogs');
            $path = $this->uploadService->handleImageUpload($dto);
            $publicUrl = '/media/public/' . ltrim($path, '/');

            return ResponseHelper::success([
                'path' => $path,
                'url' => $publicUrl,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }
}
