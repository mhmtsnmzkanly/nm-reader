<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Repositories\BlogRepository;
use App\Repositories\BlogVoteRepository;

/**
 * Service for managing Blog posts and interaction.
 *
 * Handles blog creation, approval, listing, voting, and cache management.
 * Blog posts require administrative approval before becoming publicly visible.
 *
 * @package App\Services
 */
final class BlogService
{
    public function __construct(
        private readonly BlogRepository $blogs,
        private readonly BlogVoteRepository $blogVotes,
        private readonly SlugService $slugService,
        private readonly CacheService $cache,
        private readonly EntityIdService $entityIds,
        private readonly \App\Repositories\AdminConsoleRepository $adminConsole,
        private readonly ContentSecurityScanner $scanner
    ) {
    }

    /**
     * Lists approved blog posts with pagination.
     *
     * @param int $page
     * @param int $perPage
     * @return array Sanitized blog list.
     */
    public function listApproved(int $page, int $perPage, string $sort = 'latest'): array
    {
        $cacheKey = sprintf('blogs_approved_%d_%d_%s', $page, $perPage, $sort);
        $rows = $this->cache->remember($cacheKey, 120, fn () => $this->blogs->listApproved($page, $perPage, $sort));
        return OutputSanitizer::sanitizeRows($rows, ['title', 'body', 'author_username', 'approver_username', 'cover_image', 'status', 'excerpt']);
    }

    /**
     * Lists related approved blog posts.
     */
    public function getRelatedApproved(string $slug, int $limit = 3): array
    {
        $limit = max(1, min(10, $limit));
        $cacheKey = sprintf('blogs_related_%s_%d', $slug, $limit);
        $rows = $this->cache->remember($cacheKey, 180, fn () => $this->blogs->listRelatedApproved($slug, $limit));
        return OutputSanitizer::sanitizeRows($rows, ['title', 'body', 'author_username', 'approver_username', 'cover_image', 'status', 'excerpt']);
    }

    /**
     * Retrieves a single approved blog post by its slug.
     *
     * @param string $slug Unique blog identifier.
     * @param string|null $userId Optional ID of the user viewing the blog (to include vote status).
     * @return array|null Post details or null.
     */
    public function getApprovedBySlug(string $slug, ?string $userId = null): ?array
    {
        $cacheKey = sprintf('blog_%s_%s', $slug, $userId ?? 'guest');
        $row = $this->cache->remember($cacheKey, 180, fn () => $this->blogs->findApprovedBySlug($slug, $userId));
        if (!is_array($row)) {
            return null;
        }

        return OutputSanitizer::sanitizeFields($row, ['title', 'body', 'author_username', 'approver_username', 'upvote_count', 'downvote_count', 'my_vote', 'cover_image', 'status', 'excerpt']);
    }

    /**
     * Records or updates a user's vote on a blog post.
     *
     * @param string $userId
     * @param string $slug Blog identifier.
     * @param int $vote 1 for upvote, -1 for downvote.
     * @return array Updated vote summary.
     * @throws \InvalidArgumentException If vote value is invalid or voting on own blog.
     * @throws \DomainException If blog not found.
     */
    public function vote(string $userId, string $slug, int $vote): array
    {
        if (!in_array($vote, [-1, 1], true)) {
            throw new \InvalidArgumentException('vote must be 1 or -1');
        }

        $blog = $this->blogs->findApprovedBySlug($slug);
        if ($blog === null) {
            throw new \DomainException('Blog not found');
        }

        $blogId = (string) $blog['id'];
        if ((string) $blog['user_id'] === $userId) {
            throw new \InvalidArgumentException('You cannot vote your own blog');
        }

        $previousVote = $this->blogVotes->findUserVote($userId, $blogId);
        if ($previousVote !== null && $previousVote === $vote) {
            $this->blogVotes->removeVote($userId, $blogId);
        } else {
            $this->blogVotes->setVote($userId, $blogId, $vote);
        }

        $this->invalidateCachesBySlug($slug);

        return array_merge([
            'blog_id' => $blogId,
            'slug' => $slug,
        ], $this->blogVotes->getSummary($blogId, $userId));
    }

    /**
     * Creates a new blog post in 'pending' or 'draft' state.
     *
     * @param string $userId
     * @param array $payload Title, body, cover_image, status.
     * @return array ID, slug, and status of the created post.
     * @throws \InvalidArgumentException If validation fails.
     */
    public function createPending(string $userId, array $payload): array
    {
        $error = Validator::requireFields($payload, ['title', 'body']);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $title = $this->scanner->assertSafe(Validator::sanitizeText((string) $payload['title']), 'blog_title');
        $body = $this->scanner->assertSafe(Validator::sanitizeMultilineText((string) $payload['body']), 'blog_body');
        $coverImage = !empty($payload['cover_image']) ? Validator::sanitizeText((string) $payload['cover_image']) : null;
        $status = in_array($payload['status'] ?? '', ['draft', 'pending'], true) ? (string) $payload['status'] : 'pending';

        if ($title === '' || strlen($title) < 3 || strlen($title) > 160) {
            throw new \InvalidArgumentException('Title must be 3-160 characters');
        }

        if ($body === '' || strlen($body) < 20 || strlen($body) > 10000) {
            throw new \InvalidArgumentException('Body must be 20-10000 characters');
        }

        $baseSlug = $this->slugService->normalize($title);
        if ($baseSlug === '') {
            $baseSlug = 'blog';
        }

        $slug = $baseSlug;
        $suffix = 2;
        while ($this->blogs->existsBySlug($slug)) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        $id = $this->entityIds->generateBlogId();
        $blogId = $this->blogs->create($id, $userId, $title, $slug, $body, $coverImage, $status);
        $this->cache->delete('home_latest_blogs_3');
        $this->cache->deleteByPrefix('blogs_approved_');

        return [
            'id' => $blogId,
            'slug' => $slug,
            'status' => $status,
            'approved' => false,
            'cover_image' => $coverImage,
        ];
    }

    /**
     * Updates an existing blog post belonging to the user.
     */
    public function updateBlog(string $blogId, string $userId, array $payload): array
    {
        $existing = $this->blogs->findByIdAndUser($blogId, $userId);
        if ($existing === null) {
            throw new \DomainException('Blog not found or unauthorized');
        }

        $title = !empty($payload['title'])
            ? $this->scanner->assertSafe(Validator::sanitizeText((string) $payload['title']), 'blog_title')
            : (string) $existing['title'];
        $body = !empty($payload['body'])
            ? $this->scanner->assertSafe(Validator::sanitizeMultilineText((string) $payload['body']), 'blog_body')
            : (string) $existing['body'];
        $coverImage = array_key_exists('cover_image', $payload)
            ? (!empty($payload['cover_image']) ? Validator::sanitizeText((string) $payload['cover_image']) : null)
            : ($existing['cover_image'] ?? null);
        $status = in_array($payload['status'] ?? '', ['draft', 'pending'], true)
            ? (string) $payload['status']
            : (string) ($existing['status'] ?? 'pending');

        if (strlen($title) < 3 || strlen($title) > 160) {
            throw new \InvalidArgumentException('Title must be 3-160 characters');
        }

        if (strlen($body) < 20 || strlen($body) > 10000) {
            throw new \InvalidArgumentException('Body must be 20-10000 characters');
        }

        $slug = (string) $existing['slug'];
        if ($title !== $existing['title']) {
            $baseSlug = $this->slugService->normalize($title);
            if ($baseSlug === '') {
                $baseSlug = 'blog';
            }
            $slug = $baseSlug;
            $suffix = 2;
            while ($this->blogs->existsBySlug($slug, $blogId)) {
                $slug = sprintf('%s-%d', $baseSlug, $suffix);
                $suffix++;
            }
        }

        $this->blogs->updateByUser($blogId, $userId, $title, $slug, $body, $coverImage, $status);
        $this->invalidateCachesBySlug((string) $existing['slug']);
        if ($slug !== $existing['slug']) {
            $this->invalidateCachesBySlug($slug);
        }

        return [
            'id' => $blogId,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'approved' => $status === 'published',
            'cover_image' => $coverImage,
        ];
    }

    /**
     * Deletes a user's own blog post.
     */
    public function deleteBlog(string $blogId, string $userId): void
    {
        $existing = $this->blogs->findByIdAndUser($blogId, $userId);
        if ($existing === null) {
            throw new \DomainException('Blog not found or unauthorized');
        }

        $this->blogs->deleteByUser($blogId, $userId);
        $this->invalidateCachesBySlug((string) ($existing['slug'] ?? ''));
    }

    /**
     * Gets a single blog owned by user (including draft/pending status).
     */
    public function getUserBlog(string $blogId, string $userId): ?array
    {
        $row = $this->blogs->findByIdAndUser($blogId, $userId);
        if ($row === null) {
            return null;
        }

        return OutputSanitizer::sanitizeFields($row, ['title', 'body', 'status']);
    }

    /**
     * Lists blog posts created by a specific user.
     *
     * @param string $userId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listByUser(string $userId, int $page, int $perPage): array
    {
        $rows = $this->blogs->listByUser($userId, $page, $perPage);
        return OutputSanitizer::sanitizeRows($rows, ['title', 'body', 'cover_image', 'status', 'excerpt']);
    }

    /**
     * Lists blog posts awaiting approval.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listPending(int $page, int $perPage): array
    {
        $rows = $this->blogs->listPending($page, $perPage);
        return OutputSanitizer::sanitizeRows($rows, ['title', 'body', 'author_username', 'cover_image', 'status']);
    }

    /**
     * Lists blog posts for administrative management.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function listForAdmin(int $page, int $perPage): array
    {
        $result = $this->blogs->listForAdmin($page, $perPage);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['title', 'slug', 'author_username', 'approver_username']);

        return [
            'items' => $items,
            'meta' => [
                'total' => (int) ($result['total'] ?? 0),
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * Approves a pending blog post.
     *
     * @param string $blogId
     * @param string $approverUserId
     * @throws \DomainException If blog not found or already approved.
     */
    public function approve(string $blogId, string $approverUserId): void
    {
        $blog = $this->blogs->findById($blogId);
        if ($blog === null) {
            throw new \DomainException('Blog not found');
        }

        if ((int) $blog['approved'] === 1) {
            throw new \DomainException('Blog is already approved');
        }

        $this->blogs->approve($blogId, $approverUserId);
        
        $this->adminConsole->createModerationAction($approverUserId, 'blog', $blogId, 'approve', "Blog post approved: " . ($blog['title'] ?? $blogId));

        $this->cache->delete('sitemap_xml');
        $this->cache->delete(sprintf('blog_%s', (string) $blog['slug']));
        $this->cache->delete('home_popular_blogs_3');
        $this->cache->delete('home_latest_blogs_3');
        $this->cache->deleteByPrefix('blogs_approved_');
    }

    /**
     * Hides a blog post from public view (internal moderation).
     *
     * @param string $blogId
     * @param string|null $moderatorId
     * @throws \DomainException
     */
    public function hide(string $blogId, ?string $moderatorId = null): void
    {
        $blog = $this->blogs->findById($blogId);
        if ($blog === null) {
            throw new \DomainException('Blog not found');
        }
        if (isset($blog['deleted_at']) && $blog['deleted_at'] !== null) {
            throw new \DomainException('Blog already deleted');
        }

        $this->blogs->hideById($blogId, $moderatorId);

        if ($moderatorId !== null) {
            $this->adminConsole->createModerationAction($moderatorId, 'blog', $blogId, 'hide', "Blog post hidden: " . ($blog['title'] ?? $blogId));
        }

        $this->invalidateCachesBySlug((string) ($blog['slug'] ?? ''));
    }

    /**
     * Soft-deletes a blog post.
     *
     * @param string $blogId
     * @param string|null $moderatorId
     * @throws \DomainException
     */
    public function remove(string $blogId, ?string $moderatorId = null): void
    {
        $blog = $this->blogs->findById($blogId);
        if ($blog === null) {
            throw new \DomainException('Blog not found');
        }
        if (isset($blog['deleted_at']) && $blog['deleted_at'] !== null) {
            throw new \DomainException('Blog already deleted');
        }

        $this->blogs->softDeleteById($blogId, $moderatorId);

        if ($moderatorId !== null) {
            $this->adminConsole->createModerationAction($moderatorId, 'blog', $blogId, 'delete', "Blog post soft-deleted: " . ($blog['title'] ?? $blogId));
        }

        $this->invalidateCachesBySlug((string) ($blog['slug'] ?? ''));
    }

    private function invalidateCachesBySlug(string $slug): void
    {
        $this->cache->delete('sitemap_xml');
        if ($slug !== '') {
            $this->cache->delete(sprintf('blog_%s', $slug));
            $this->cache->deleteByPrefix(sprintf('blog_%s', $slug));
        }
        $this->cache->delete('home_popular_blogs_3');
        $this->cache->delete('home_latest_blogs_3');
        $this->cache->deleteByPrefix('blogs_approved_');
    }
}
