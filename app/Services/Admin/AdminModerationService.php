<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Helpers\OutputSanitizer;
use App\Helpers\Validator;
use App\Services\AnalyticsAggregationService;
use App\Services\BackupService;
use App\Services\CacheService;
use App\Services\QueueService;
use App\Services\RetentionService;
use App\Services\SeriesService;
use App\Services\SlugService;
use App\Services\SitemapService;

use App\Services\Admin\AdminConsoleServiceBase;

/** Domain service extracted from the legacy admin console service. */
final class AdminModerationService extends AdminConsoleServiceBase
{
    public function listAuditLogs(int $page, int $perPage, string $query = '', ?string $method = null, ?string $statusGroup = null, ?string $userId = null, ?string $dateFrom = null, ?string $dateTo = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listAuditLogs($page, $perPage, trim($query), $method, $statusGroup, $userId, $dateFrom, $dateTo, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['username']);
    
        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function listLoginEvents(int $page, int $perPage): array
    {
        $result = $this->repo->listLoginEvents($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function listComments(int $page, int $perPage, string $query = '', ?string $targetType = null, string $sort = 'newest', ?string $moderationStatus = null, ?string $userId = null): array
    {
        $result = $this->repo->listComments($page, $perPage, $query, $targetType, $sort, $moderationStatus, $userId);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['body', 'username', 'content_title', 'blog_title', 'moderation_status']);
    
        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function deleteComment(int $id, string $moderatorId): bool
    {
        return $this->repo->deleteComment($id, $moderatorId);
    }

    public function moderateComment(int $id, string $status, string $moderatorId, ?string $reason = null): bool
    {
        return $this->repo->moderateComment($id, $status, $moderatorId, $reason);
    }

    public function listModerationActions(int $page, int $perPage): array
    {
        $result = $this->repo->listModerationActions($page, $perPage);
        return $this->withMeta($result['items'], $result['total'], $page, $perPage);
    }

    public function listBlogs(int $page, int $perPage, string $query = '', ?string $status = null, string $sort = 'newest', ?string $userId = null): array
    {
        $result = $this->repo->listBlogs($page, $perPage, $query, $status, $sort, $userId);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['title', 'username']);
        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function hideBlog(string $id, string $moderatorId): void
    {
        $this->repo->hideBlog($id, $moderatorId);
    }

    public function deleteBlog(string $id, string $moderatorId): void
    {
        $this->repo->deleteBlog($id, $moderatorId);
    }
}
