<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AdminConsoleService;
use App\Services\SystemLogService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for the dedicated Admin Console API.
 *
 * Provides granular control over the entire system, including:
 * - Real-time system monitoring (KPIs, Logs, Audit trail).
 * - Comprehensive User and Content management.
 * - RBAC (Role-Based Access Control) configuration.
 * - Manual background task execution (Queue, Cleanup).
 * - Advanced platform analytics snapshots.
 *
 * @package App\Controllers
 */
final class AdminConsoleController
{
    public function __construct(
        private readonly AdminConsoleService $console,
        private readonly SystemLogService $logs,
        private readonly \App\Services\AnalyticsAggregationService $aggregation
    ) {
    }

    /**
     * Retrieves high-level dashboard KPIs.
     */
    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->overview());
    }

    /**
     * Fetches raw web server access logs.
     */
    public function systemAccessLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $limit = max(1, min(200, (int) ($query['limit'] ?? 50)));
        return ResponseHelper::success($this->logs->getAccessLogs($limit));
    }

    /**
     * Fetches raw application error logs.
     */
    public function systemErrorLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $limit = max(1, min(200, (int) ($query['limit'] ?? 50)));
        return ResponseHelper::success($this->logs->getErrorLogs($limit));
    }

    /**
     * Lists all series with admin metadata and pagination.
     */
    public function series(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listContents($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Lists all users with roles and status flags.
     */
    public function users(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listUsers($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Lists blog posts for administrative management.
     */
    public function blogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listBlogs($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Updates user identity, role, and moderation status.
     *
     * @param array $args Must contain 'id'.
     */
    public function updateUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->updateUser((string) $args['id'], $payload, $moderatorId);
            return ResponseHelper::success();
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    /**
     * Forcefully revokes a user's session.
     */
    public function revokeUserSession(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $userId = (string) ($payload['user_id'] ?? '');
            $sessionKey = (string) ($payload['session_key'] ?? '');
            $moderatorId = (string) $request->getAttribute('user_id');

            if ($userId === '' || $sessionKey === '') {
                return ResponseHelper::error(400, 'user_id and session_key are required');
            }

            $this->console->revokeUserSession($userId, $sessionKey, $moderatorId);
            return ResponseHelper::success(['revoked' => true]);
        } catch (\Throwable $e) {
            return ResponseHelper::error(500, $e->getMessage());
        }
    }

    /**
     * Lists background jobs currently in the queue.
     */
    public function queueJobs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listQueueJobs($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Manually triggers queue processing and statistical aggregation.
     */
    public function runQueueOnce(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $jobType = isset($payload['type']) ? (string)$payload['type'] : null;
        $limit = (int) ($payload['limit'] ?? 10);
        $moderatorId = (string) $request->getAttribute('user_id');

        $results = $this->console->runQueueOnce($jobType, $limit, $moderatorId);
        
        // After running queue, also update stats snapshots
        try {
            $this->aggregation->aggregateAll();
        } catch (\Throwable) {
            // Stats aggregation error should not block queue response
        }

        return ResponseHelper::success($results);
    }

    /**
     * Manually triggers data retention cleanup.
     */
    public function cleanupRetention(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $days = (int) ($payload['days'] ?? 30);

        return ResponseHelper::success($this->console->cleanupRetention($days));
    }

    /**
     * Retrieves application audit logs (who did what).
     */
    public function auditLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listAuditLogs($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Lists authentication events (login attempts).
     */
    public function loginEvents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listLoginEvents($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Lists all platform comments for moderation.
     */
    public function comments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listComments($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Forcefully deletes a comment.
     *
     * @param array $args Must contain 'id'.
     */
    public function deleteComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        $this->console->deleteComment((int) ($args['id'] ?? 0), $moderatorId);
        return ResponseHelper::success(['deleted' => true]);
    }

    /**
     * Retrieves content performance statistics.
     */
    public function viewStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        $limit = (int) ($query['limit'] ?? 10);

        return ResponseHelper::success($this->console->viewStats($days, $limit));
    }

    /**
     * Retrieves blog platform metrics.
     */
    public function blogStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $days = (int) ($query['days'] ?? 7);
        $limit = (int) ($query['limit'] ?? 10);

        return ResponseHelper::success($this->console->blogStats($days, $limit));
    }

    /**
     * Retrieves raw traffic statistics.
     */
    public function siteVisits(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ResponseHelper::success($this->console->siteVisits());
    }

    /**
     * Fetches user reputation leaderboard.
     */
    public function userReputation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $limit = (int) ($query['limit'] ?? 20);
        return ResponseHelper::success($this->console->userReputation($limit));
    }

    /**
     * Lists moderation history (bans, etc.).
     */
    public function moderationActions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listModerationActions($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Creates a new moderation action manually.
     */
    public function createModerationAction(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorUserId = $request->getAttribute('user_id');
            $created = $this->console->createModerationAction(
                $moderatorUserId === null ? null : (string) $moderatorUserId,
                $payload
            );

            return ResponseHelper::created($created);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        }
    }

    /**
     * Hides a blog post (approval status 0).
     */
    public function hideBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) ($args['id'] ?? '');
        if ($id === '') {
            return ResponseHelper::error(400, 'Invalid blog id');
        }

        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->hideBlog($id, $moderatorId);
            return ResponseHelper::success(['hidden' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Permanently deletes a blog post.
     */
    public function deleteBlog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) ($args['id'] ?? '');
        if ($id === '') {
            return ResponseHelper::error(400, 'Invalid blog id');
        }

        try {
            $moderatorId = (string) $request->getAttribute('user_id');
            $this->console->deleteBlog($id, $moderatorId);
            return ResponseHelper::success(['deleted' => true]);
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Lists all system roles and their permissions.
     */
    public function rbacRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->console->listRbacRoles();
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Lists role assignments for users.
     */
    public function rbacAssignments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        [$page, $perPage] = $this->pagination($request);
        $result = $this->console->listRbacAssignments($page, $perPage);

        return ResponseHelper::success($result['items'], $result['meta']);
    }

    /**
     * Grants a permission code to a specific role.
     */
    public function assignPermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->assignPermissionToRole($payload, $moderatorId));
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Revokes a permission from a role.
     */
    public function revokePermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $moderatorId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->console->revokePermissionFromRole($payload, $moderatorId));
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Assigns a role slug to a user.
     */
    public function assignRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            return ResponseHelper::success($this->console->assignRoleToUser($payload));
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\DomainException $e) {
            return ResponseHelper::error(404, $e->getMessage());
        }
    }

    /**
     * Creates a new genre globally.
     */
    public function createGenre(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->console->createGenre((string)($payload['name'] ?? ''), $moderatorId));
    }

    /**
     * Creates a new tag globally.
     */
    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::created($this->console->createTag((string)($payload['name'] ?? ''), $moderatorId));
    }

    /**
     * Updates the genre and tag assignments for a series.
     */
    public function updateTaxonomy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $payload = (array) $request->getParsedBody();
        $moderatorId = (string) $request->getAttribute('user_id');
        $this->console->updateContentTaxonomy(
            (string)$args['id'],
            (array)($payload['series_genres'] ?? []),
            (array)($payload['series_tags'] ?? []),
            $moderatorId
        );
        return ResponseHelper::success();
    }

    /**
     * Triggers a manual system backup.
     */
    public function triggerBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerBackup($moderatorId));
    }

    /**
     * Triggers manual analytics aggregation.
     */
    public function triggerAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerAnalytics($moderatorId));
    }

    /**
     * Triggers manual sitemap generation.
     */
    public function triggerSitemap(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerSitemap($moderatorId));
    }

    /**
     * Triggers manual cache warmup.
     */
    public function triggerCacheWarmup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $moderatorId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->console->triggerCacheWarmup($moderatorId));
    }

    /**
     * Helper to extract pagination metadata from query string.
     */
    private function pagination(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 20)));

        return [$page, $perPage];
    }
}
