<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Helpers\ChapterNumber;
use App\Helpers\Validator;
use App\Repositories\AdminConsoleRepository;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\WalletRepository;
use App\Services\AdminConsoleService;
use App\Services\CacheService;
use App\Services\ContentSecurityScanner;
use App\Services\EntityIdService;
use App\Services\QueueService;
use App\Services\SlugService;
use PDO;

use App\Services\Admin\AdminServiceBase;

/** Domain service extracted from the legacy administrative service. */
final class ContentAdminService extends AdminServiceBase
{
    public function createContent(array $payload, ?string $moderatorId = null): array
    {
        $error = Validator::requireFields($payload, ['title', 'type']);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }
    
        $title = Validator::sanitizeText((string) $payload['title']);
        if ($title === '') {
            throw new \InvalidArgumentException('title is required');
        }
    
        $dbType = $this->toDbType((string) $payload['type']);
        $status = strtolower(trim((string) ($payload['status'] ?? 'ongoing')));
        if (!in_array($status, AdminServiceBase::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid status');
        }
    
        $description = trim((string) ($payload['description'] ?? ''));
        $alternativeTitles = trim((string) ($payload['alternative_titles'] ?? ''));
        $author = $this->sanitizePerson((string) ($payload['author'] ?? ''));
        $artist = $this->sanitizePerson((string) ($payload['artist'] ?? ''));
        $country = $this->sanitizeCountry((string) ($payload['country'] ?? ''));
        $releaseYear = $this->sanitizeYear((string) ($payload['release_year'] ?? ''));
        $description = $description === '' ? null : $description;
        $alternativeTitles = $alternativeTitles === '' ? null : $alternativeTitles;
        $coverImage = trim((string) ($payload['cover_image'] ?? ''));
        $coverImage = $coverImage === '' ? null : $coverImage;
    
        $baseSlug = trim((string) ($payload['slug'] ?? ''));
        $baseSlug = $baseSlug === '' ? $this->slugService->normalize($title) : $this->slugService->normalize($baseSlug);
        if ($baseSlug === '') {
            throw new \InvalidArgumentException('slug is invalid');
        }
    
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->series->findContentIdBySlug($slug) !== null) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }
    
        $id = $this->entityIds->generateContentId();
        $isAdult = !empty($payload['is_adult']) ? 1 : 0;
        $isMembersOnly = !empty($payload['is_members_only']) ? 1 : 0;
        $lifecycleStatus = strtolower(trim((string) ($payload['lifecycle_status'] ?? 'published')));
        if (!in_array($lifecycleStatus, AdminServiceBase::ALLOWED_LIFECYCLE_STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid lifecycle_status');
        }
        $scheduledAt = $this->normalizeDateTime($payload['scheduled_at'] ?? null);
        if ($lifecycleStatus === 'scheduled' && $scheduledAt === null) {
            throw new \InvalidArgumentException('scheduled_at is required for scheduled content');
        }
        $publishedAt = $lifecycleStatus === 'published' ? date('Y-m-d H:i:s') : null;
        $archivedAt = $lifecycleStatus === 'archived' ? date('Y-m-d H:i:s') : null;
    
        $sql = 'INSERT INTO series (
                    id, title, slug, description, type, status, lifecycle_status, scheduled_at, published_at, archived_at, is_adult, is_members_only, cover_image,
                    rating_avg, rating_count, chapter_count, comment_count, created_at
                ) VALUES (
                    :id, :title, :slug, :description, :type, :status, :lifecycle_status, :scheduled_at, :published_at, :archived_at, :is_adult, :is_members_only, :cover_image,
                    0, 0, 0, 0, NOW()
                )';
    
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'type' => $dbType,
                'status' => $status,
                'lifecycle_status' => $lifecycleStatus,
                'scheduled_at' => $scheduledAt,
                'published_at' => $publishedAt,
                'archived_at' => $archivedAt,
                'is_adult' => $isAdult,
                'is_members_only' => $isMembersOnly,
                'cover_image' => $coverImage,
            ]);

            $this->upsertContentMetadata($id, $author, $artist, $alternativeTitles, $country, $releaseYear);
            $this->recordSeriesRevision($id, $moderatorId, 'create');

            if ($moderatorId !== null) {
                $this->adminConsole->createModerationAction($moderatorId, 'content', $id, 'create', "New series created: $title");
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    
        $this->invalidateListingCaches();
    
        return [
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'type' => $dbType,
            'status' => $status,
            'lifecycle_status' => $lifecycleStatus,
            'scheduled_at' => $scheduledAt,
            'is_adult' => (bool) $isAdult,
            'is_members_only' => (bool) $isMembersOnly,
            'url_path' => '/' . str_replace('_', '-', $dbType) . '/' . $slug,
        ];
    }

    public function updateContent(string $id, array $payload, ?string $moderatorId = null): void
    {
        $title = isset($payload['title']) ? Validator::sanitizeText((string) $payload['title']) : null;
        $status = isset($payload['status']) ? strtolower(trim((string) $payload['status'])) : null;
        $description = isset($payload['description']) ? trim((string) $payload['description']) : null;
        $alternativeTitles = isset($payload['alternative_titles']) ? trim((string) $payload['alternative_titles']) : null;
        $coverImage = isset($payload['cover_image']) ? trim((string) $payload['cover_image']) : null;
        
        $author = isset($payload['author']) ? $this->sanitizePerson((string) $payload['author']) : null;
        $artist = isset($payload['artist']) ? $this->sanitizePerson((string) $payload['artist']) : null;
        $country = isset($payload['country']) ? $this->sanitizeCountry((string) $payload['country']) : null;
        $releaseYear = isset($payload['release_year']) ? $this->sanitizeYear((string) $payload['release_year']) : null;
    
        // Fetch current to check existence and for cache clearing
        $stmt = $this->pdo->prepare('SELECT * FROM series WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetch();
    
        if (!$current) {
            throw new \DomainException('Content not found');
        }
    
        $updates = [];
        $params = ['id' => $id];
    
        if ($title !== null && $title !== '') {
            $updates[] = 'title = :title';
            $params['title'] = $title;
        }
        if ($description !== null) {
            $updates[] = 'description = :description';
            $params['description'] = $description === '' ? null : $description;
        }
        if ($status !== null) {
            if (!in_array($status, AdminServiceBase::ALLOWED_STATUSES, true)) {
                throw new \InvalidArgumentException('Invalid status');
            }
            $updates[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($coverImage !== null) {
            $updates[] = 'cover_image = :cover_image';
            $params['cover_image'] = $coverImage === '' ? null : $coverImage;
        }
        if (isset($payload['is_adult'])) {
            $updates[] = 'is_adult = :is_adult';
            $params['is_adult'] = !empty($payload['is_adult']) ? 1 : 0;
        }
        if (isset($payload['is_members_only'])) {
            $updates[] = 'is_members_only = :is_members_only';
            $params['is_members_only'] = !empty($payload['is_members_only']) ? 1 : 0;
        }
        if (isset($payload['disable_comments'])) {
            $updates[] = 'disable_comments = :disable_comments';
            $params['disable_comments'] = !empty($payload['disable_comments']) ? 1 : 0;
        }
        if (isset($payload['lifecycle_status'])) {
            $lifecycleStatus = strtolower(trim((string) $payload['lifecycle_status']));
            if (!in_array($lifecycleStatus, AdminServiceBase::ALLOWED_LIFECYCLE_STATUSES, true)) {
                throw new \InvalidArgumentException('Invalid lifecycle_status');
            }
            $scheduledAt = $this->normalizeDateTime($payload['scheduled_at'] ?? null);
            if ($lifecycleStatus === 'scheduled' && $scheduledAt === null) {
                throw new \InvalidArgumentException('scheduled_at is required for scheduled content');
            }
            $updates[] = 'lifecycle_status = :lifecycle_status';
            $updates[] = 'scheduled_at = :scheduled_at';
            $updates[] = 'published_at = CASE WHEN :lifecycle_status_published = "published" THEN COALESCE(published_at, NOW()) ELSE published_at END';
            $updates[] = 'archived_at = CASE WHEN :lifecycle_status_archived = "archived" THEN NOW() ELSE NULL END';
            $params['lifecycle_status'] = $lifecycleStatus;
            $params['lifecycle_status_published'] = $lifecycleStatus;
            $params['lifecycle_status_archived'] = $lifecycleStatus;
            $params['scheduled_at'] = $scheduledAt;
        }
    
        $this->pdo->beginTransaction();
        try {
            $this->recordSeriesRevision($id, $moderatorId, 'before_update', $current);
            if (!empty($updates)) {
                $sql = 'UPDATE series SET ' . implode(', ', $updates) . ' WHERE id = :id';
                $this->pdo->prepare($sql)->execute($params);
            }
    
            $this->upsertContentMetadata($id, $author, $artist, $alternativeTitles, $country, $releaseYear);
    
            if ($moderatorId !== null) {
                $this->adminConsole->createModerationAction($moderatorId, 'content', $id, 'update', "Content updated: " . ($title ?? $current['title']));
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    
        $this->clearContentCaches((string) $current['slug'], (string) $current['type']);
        $this->invalidateListingCaches();
    }

    public function changeContentLifecycle(string $id, string $action, ?string $scheduledAt, ?string $moderatorId): array
    {
        $current = $this->contentSnapshot($id);
        if ($current === null) throw new \DomainException('Content not found');
    
        $target = match ($action) {
            'draft' => 'draft',
            'schedule' => 'scheduled',
            'publish' => 'published',
            'archive' => 'archived',
            'restore' => 'draft',
            default => throw new \InvalidArgumentException('Invalid lifecycle action'),
        };
        $normalizedSchedule = $this->normalizeDateTime($scheduledAt);
        if ($target === 'scheduled' && $normalizedSchedule === null) {
            throw new \InvalidArgumentException('scheduled_at is required');
        }
    
        $this->pdo->beginTransaction();
        try {
            $this->recordSeriesRevision($id, $moderatorId, 'before_' . $action, $current);
            $stmt = $this->pdo->prepare(
                'UPDATE series SET lifecycle_status = :status, scheduled_at = :scheduled_at,
                    published_at = CASE WHEN :publish = 1 THEN NOW() ELSE published_at END,
                    archived_at = CASE WHEN :archive = 1 THEN NOW() ELSE NULL END
                 WHERE id = :id'
            );
            $stmt->execute([
                'status' => $target,
                'scheduled_at' => $target === 'scheduled' ? $normalizedSchedule : null,
                'publish' => $target === 'published' ? 1 : 0,
                'archive' => $target === 'archived' ? 1 : 0,
                'id' => $id,
            ]);
            if ($moderatorId !== null) {
                $this->adminConsole->createModerationAction($moderatorId, 'content', $id, 'lifecycle_' . $action, 'Lifecycle changed to ' . $target);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        $this->clearContentCaches((string) $current['slug'], (string) $current['type']);
        $this->invalidateListingCaches();
        return ['id' => $id, 'lifecycle_status' => $target, 'scheduled_at' => $target === 'scheduled' ? $normalizedSchedule : null];
    }

    public function contentPreview(string $id): array
    {
        $content = $this->contentSnapshot($id);
        if ($content === null) throw new \DomainException('Content not found');
        $content['url_path'] = '/' . str_replace('_', '-', (string) $content['type']) . '/' . $content['slug'];
        return $content;
    }

    public function contentRevisions(string $id, int $limit = 50): array
    {
        if ($this->contentSnapshot($id) === null) throw new \DomainException('Content not found');
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.action, r.snapshot_json, r.created_at, r.moderator_user_id, u.username AS moderator_username
             FROM series_revisions r LEFT JOIN users u ON u.id = r.moderator_user_id
             WHERE r.series_id = :id ORDER BY r.id DESC LIMIT :limit'
        );
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static function (array $row): array {
            $row['snapshot'] = json_decode((string) $row['snapshot_json'], true) ?: [];
            unset($row['snapshot_json']);
            return $row;
        }, $stmt->fetchAll());
    }
}
