<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\OutputSanitizer;
use App\Helpers\CursorPagination;
use App\Helpers\Validator;
use App\Repositories\ChapterRepository;
use App\Repositories\CommentRepository;
use App\Repositories\CommentVoteRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\BlogRepository;
use PDO;

/**
 * Service for managing user Comments across Chapters and Blogs.
 *
 * This service handles comment creation, threaded replies, voting, and
 * administrative tasks. It manages notifications and cache invalidation
 * for commented content.
 *
 * @package App\Services
 */
final class CommentService
{
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly CommentVoteRepository $commentVotes,
        private readonly ChapterRepository $chapters,
        private readonly SeriesRepository $series,
        private readonly BlogRepository $blogs,
        private readonly CacheService $cache,
        private readonly PDO $pdo,
        private readonly AnalyticsService $analytics
    ) {
    }

    /**
     * Adds a comment to a specific chapter.
     *
     * @param string $userId
     * @param string $chapterId
     * @param string $body
     * @param int|null $parentId Optional ID for threaded replies.
     * @return int The ID of the created comment.
     * @throws \InvalidArgumentException If validation fails or parent ID is invalid.
     */
    public function addToChapter(string $userId, string $chapterId, string $body, ?int $parentId = null): int
    {
        if ($parentId !== null && $parentId <= 0) {
            throw new \InvalidArgumentException('parent_id must be a positive integer');
        }

        $body = Validator::sanitizeMultilineText($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Comment body is required');
        }
        if (strlen($body) > 1000) {
            throw new \InvalidArgumentException('Comment body must be at most 1000 characters');
        }

        if ($parentId !== null) {
            $parent = $this->comments->findById($parentId);
            if ($parent === null || (string) ($parent['chapter_id'] ?? '') !== $chapterId) {
                throw new \InvalidArgumentException('parent_id must belong to the same chapter');
            }
        }

        $commentId = $this->comments->addComment(
            userId: $userId,
            contentId: null,
            chapterId: $chapterId,
            body: $body,
            parentId: $parentId
        );
        $this->analytics->track('comment_create', $userId, 'chapter', $chapterId, ['comment_id' => $commentId]);
        $this->chapters->incrementDailyCommentCount($chapterId);

        $content = $this->chapters->findContentIdentityByChapterId($chapterId);
        if ($content !== null) {
            $slug = $content['slug'];
            $type = $content['type'];

            $contentId = $this->series->findContentIdBySlug($slug);
            if ($contentId !== null) {
                $this->series->incrementCommentCount($contentId);
            }

            $this->cache->delete(sprintf('content_%s', $slug));
            $this->cache->delete(sprintf('content_%s_%s', $type, $slug));
            $this->invalidateListingCaches();
        }

        return $commentId;
    }

    /**
     * Adds a comment to a specific series.
     */
    public function addToSeries(string $userId, string $type, string $slug, string $body, ?int $parentId = null): int
    {
        if ($parentId !== null && $parentId <= 0) {
            throw new \InvalidArgumentException('parent_id must be a positive integer');
        }

        $body = Validator::sanitizeMultilineText($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Comment body is required');
        }
        if (strlen($body) > 1000) {
            throw new \InvalidArgumentException('Comment body must be at most 1000 characters');
        }

        $contentId = $this->series->findContentIdBySlug($slug);
        if ($contentId === null) {
            throw new \DomainException('Series not found');
        }

        if ($parentId !== null) {
            $parent = $this->comments->findById($parentId);
            if ($parent === null || (string) ($parent['content_id'] ?? '') !== $contentId || ($parent['chapter_id'] ?? null) !== null) {
                throw new \InvalidArgumentException('parent_id must belong to the same series');
            }
        }

        $commentId = $this->comments->addComment(
            userId: $userId,
            contentId: $contentId,
            chapterId: null,
            body: $body,
            parentId: $parentId
        );
        $this->analytics->track('comment_create', $userId, 'series', $contentId, ['comment_id' => $commentId]);
        $this->series->incrementCommentCount($contentId);

        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $type, $slug));
        $this->invalidateListingCaches();

        return $commentId;
    }

    /**
     * Adds a comment to a specific blog post.
     *
     * @param string $userId
     * @param string $blogSlug
     * @param string $body
     * @param int|null $parentId Optional ID for replies.
     * @return int Created comment ID.
     * @throws \InvalidArgumentException If validation fails.
     * @throws \DomainException If blog not found.
     */
    public function addToBlog(string $userId, string $blogSlug, string $body, ?int $parentId = null): int
    {
        if ($parentId !== null && $parentId <= 0) {
            throw new \InvalidArgumentException('parent_id must be a positive integer');
        }

        $body = Validator::sanitizeMultilineText($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Comment body is required');
        }
        if (strlen($body) > 1000) {
            throw new \InvalidArgumentException('Comment body must be at most 1000 characters');
        }

        $blog = $this->blogs->findApprovedBySlug($blogSlug);
        if ($blog === null) {
            throw new \DomainException('Blog not found or not approved');
        }

        if ($parentId !== null) {
            $parent = $this->comments->findById($parentId);
            if ($parent === null
                || (string) ($parent['blog_id'] ?? '') !== (string) $blog['id']
                || ($parent['chapter_id'] ?? null) !== null
            ) {
                throw new \InvalidArgumentException('parent_id must belong to the same blog');
            }
        }

        $commentId = $this->comments->addComment(
            userId: $userId,
            contentId: null,
            chapterId: null,
            body: $body,
            parentId: $parentId,
            blogId: (string) $blog['id']
        );
        $this->analytics->track('comment_create', $userId, 'blog', (string) $blog['id'], ['comment_id' => $commentId]);

        return $commentId;
    }

    /**
     * Lists all comments for a specific chapter.
     *
     * @param string $chapterId
     * @param int $page
     * @param int $perPage
     * @param string|null $viewerUserId ID of user viewing the comments (for vote tracking).
     * @return array
     */
    public function listByChapter(string $chapterId, int $page, int $perPage, ?string $viewerUserId = null, ?string $cursor = null): array
    {
        $cursorData = CursorPagination::decode($cursor);
        if ($cursorData !== null) {
            [$cursorCreatedAt, $cursorId] = $cursorData;
            $rows = $this->comments->getByChapterId($chapterId, $page, $perPage, $viewerUserId, $cursorCreatedAt, $cursorId);
        } else {
            $rows = $this->comments->getByChapterId($chapterId, $page, $perPage, $viewerUserId);
        }
        return OutputSanitizer::sanitizeRows($rows, ['body', 'username']);
    }

    /**
     * Lists all comments for a specific series.
     */
    public function listBySeriesSlug(string $slug, int $page, int $perPage, ?string $viewerUserId = null, ?string $cursor = null): array
    {
        $contentId = $this->series->findContentIdBySlug($slug);
        if ($contentId === null) {
            throw new \DomainException('Series not found');
        }

        $cursorData = CursorPagination::decode($cursor);
        if ($cursorData !== null) {
            [$cursorCreatedAt, $cursorId] = $cursorData;
            $rows = $this->comments->getByContentId($contentId, $page, $perPage, $viewerUserId, $cursorCreatedAt, $cursorId);
        } else {
            $rows = $this->comments->getByContentId($contentId, $page, $perPage, $viewerUserId);
        }
        return OutputSanitizer::sanitizeRows($rows, ['body', 'username']);
    }

    /**
     * Lists all comments for a specific blog post.
     *
     * @param string $slug
     * @param int $page
     * @param int $perPage
     * @param string|null $viewerUserId
     * @return array
     * @throws \DomainException If blog not found.
     */
    public function listByBlogSlug(string $slug, int $page, int $perPage, ?string $viewerUserId = null, ?string $cursor = null): array
    {
        $blog = $this->blogs->findApprovedBySlug($slug);
        if ($blog === null) {
            throw new \DomainException('Blog not found');
        }

        $cursorData = CursorPagination::decode($cursor);
        if ($cursorData !== null) {
            [$cursorCreatedAt, $cursorId] = $cursorData;
            $rows = $this->comments->getByBlogId((string) $blog['id'], $page, $perPage, $viewerUserId, $cursorCreatedAt, $cursorId);
        } else {
            $rows = $this->comments->getByBlogId((string) $blog['id'], $page, $perPage, $viewerUserId);
        }
        return OutputSanitizer::sanitizeRows($rows, ['body', 'username']);
    }


    /**
     * Records or toggles a user's vote on a comment.
     *
     * @param string $userId
     * @param int $commentId
     * @param int $vote 1 for upvote, -1 for downvote.
     * @return array Updated counters and current user's vote.
     * @throws \InvalidArgumentException If vote value invalid or voting on own comment.
     * @throws \DomainException If comment not found.
     */
    public function vote(string $userId, int $commentId, int $vote): array
    {
        if (!in_array((int)$vote, [-1, 1], true)) {
            throw new \InvalidArgumentException('vote must be 1 or -1');
        }

        $comment = $this->comments->findById($commentId);
        if ($comment === null) {
            throw new \DomainException('Comment not found');
        }

        if ((string) $comment['user_id'] === $userId) {
            throw new \InvalidArgumentException('You cannot vote your own comment');
        }

        $ownerId = (string) $comment['user_id'];
        $previousVote = $this->commentVotes->findUserVote($userId, $commentId);

        $this->pdo->beginTransaction();

        try {
            if ($previousVote !== null && $previousVote === $vote) {
                $this->commentVotes->removeVote($userId, $commentId);
                $this->commentVotes->removeVoteNotification($ownerId, $userId, $commentId);
                $currentVote = 0;
            } else {
                $this->commentVotes->setVote($userId, $commentId, $vote);
                $this->commentVotes->upsertVoteNotification($ownerId, $userId, $commentId, $vote);
                $currentVote = $vote;
            }

            $this->commentVotes->refreshCommentCounters($commentId);
            $this->commentVotes->refreshUserVoteStats($userId);
            $this->commentVotes->refreshUserVoteStats($ownerId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $updated = $this->comments->findById($commentId);
        if ($updated === null) {
            throw new \DomainException('Comment not found');
        }

        $this->analytics->track('comment_vote', $userId, 'comment', (string) $commentId, ['vote' => $currentVote]);

        return [
            'comment_id' => $commentId,
            'my_vote' => $currentVote,
            'upvote_count' => (int) $updated['upvote_count'],
            'downvote_count' => (int) $updated['downvote_count'],
            'score' => (int) $updated['upvote_count'] - (int) $updated['downvote_count'],
        ];
    }

    /**
     * Specialized vote method for blog comments to ensure context.
     *
     * @param string $userId
     * @param string $blogSlug
     * @param int $commentId
     * @param int $vote
     * @return array
     * @throws \DomainException If blog or comment not found.
     */
    public function voteBlogComment(string $userId, string $blogSlug, int $commentId, int $vote): array
    {
        $blog = $this->blogs->findApprovedBySlug($blogSlug);
        if ($blog === null) {
            throw new \DomainException('Blog not found');
        }

        $comment = $this->comments->findById($commentId);
        if ($comment === null
            || (string) ($comment['blog_id'] ?? '') !== (string) $blog['id']
            || ($comment['chapter_id'] ?? null) !== null
        ) {
            throw new \DomainException('Comment not found');
        }

        return $this->vote($userId, $commentId, $vote);
    }

    private function invalidateListingCaches(): void
    {
        $this->cache->deleteByPrefix('homepage_popular_');
        $this->cache->deleteByPrefix('type_list_');
        $this->cache->deleteByPrefix('genre_list_');
        $this->cache->deleteByPrefix('tag_list_');
        $this->cache->deleteByPrefix('latest_chapters_');
    }
}
