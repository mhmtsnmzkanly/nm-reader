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
final class AdminContentService extends AdminConsoleServiceBase
{
    public function listContents(int $page, int $perPage, string $query = '', ?string $status = null, ?string $type = null, ?string $lifecycle = null, string $sort = 'newest'): array
    {
        $result = $this->repo->listContents($page, $perPage, $query, $status, $type, $lifecycle, $sort);
        $items = OutputSanitizer::sanitizeRows($result['items'], ['title']);
    
        return $this->withMeta($items, $result['total'], $page, $perPage);
    }

    public function createGenre(string $name, string $moderatorId): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $this->pdo->beginTransaction();
        try {
            $genre = $this->repo->createGenre($name, $this->taxonomySlug($name));
            $this->repo->createModerationAction($moderatorId, 'system', (string) $genre['id'], 'create_genre', "New genre created: $name");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $genre;
    }

    public function createTag(string $name, string $moderatorId): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $this->pdo->beginTransaction();
        try {
            $tag = $this->repo->createTag($name, $this->taxonomySlug($name));
            $this->repo->createModerationAction($moderatorId, 'system', (string) $tag['id'], 'create_tag', "New tag created: $name");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $tag;
    }

    public function updateContentTaxonomy(string $contentId, array $genreIds, array $tagIds, string $moderatorId): void
    {
        $this->repo->updateContentTaxonomy($contentId, $genreIds, $tagIds, $moderatorId);
    }

    public function listAllGenres(): array
    {
        return $this->repo->listAllGenres();
    }

    public function listAllTags(): array
    {
        return $this->repo->listAllTags();
    }

    public function updateTaxonomy(int $id, array $payload, string $moderatorId): array
    {
        $existing = $this->repo->taxonomyById($id);
        if (!$existing) throw new \InvalidArgumentException('Taxonomy not found');
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $this->pdo->beginTransaction();
        try {
            $updated = $this->repo->updateTaxonomy($id, $name, $this->taxonomySlug($name));
            $this->repo->createModerationAction($moderatorId, 'system', (string) $id, 'update_taxonomy', "Taxonomy renamed: {$existing['name']} -> $name");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $updated ?? [];
    }

    public function deleteTaxonomy(int $id, string $moderatorId): void
    {
        $existing = $this->repo->taxonomyById($id);
        if (!$existing) throw new \InvalidArgumentException('Taxonomy not found');
        if ((int)$existing['usage_count'] > 0) throw new \InvalidArgumentException('Used taxonomy must be merged before deletion');

        $this->pdo->beginTransaction();
        try {
            if (!$this->repo->deleteTaxonomy($id)) throw new \RuntimeException('Taxonomy could not be deleted');
            $this->repo->createModerationAction($moderatorId, 'system', (string) $id, 'delete', "Taxonomy deleted: {$existing['name']}");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function mergeTaxonomies(array $payload, string $moderatorId): array
    {
        $sourceId = (int)($payload['source_id'] ?? 0);
        $targetId = (int)($payload['target_id'] ?? 0);
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) throw new \InvalidArgumentException('Valid, different source_id and target_id are required');
        return $this->repo->mergeTaxonomies($sourceId, $targetId, $moderatorId);
    }

    public function reorderTaxonomies(array $payload, string $moderatorId): void
    {
        $items = (array)($payload['items'] ?? []);
        if ($items === []) throw new \InvalidArgumentException('items is required');
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item) || (int)($item['id'] ?? 0) <= 0) throw new \InvalidArgumentException('Each item must contain a valid id');
            $normalized[] = ['id' => (int)$item['id'], 'sort_order' => max(0, (int)($item['sort_order'] ?? 0))];
        }
        $this->repo->reorderTaxonomies($normalized, $moderatorId);
    }
}
