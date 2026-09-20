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

    public function createGenre(string $name, string $moderatorId, array $uiConfig = []): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $this->pdo->beginTransaction();
        try {
            $genre = $this->repo->createGenre($name, $this->taxonomySlug($name), $this->normalizeTaxonomyUiConfig($uiConfig));
            $this->repo->createModerationAction($moderatorId, 'system', (string) $genre['id'], 'create_genre', "New genre created: $name");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $genre;
    }

    public function createTag(string $name, string $moderatorId, array $uiConfig = []): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');

        $this->pdo->beginTransaction();
        try {
            $tag = $this->repo->createTag($name, $this->taxonomySlug($name), $this->normalizeTaxonomyUiConfig($uiConfig));
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
        $uiConfig = array_key_exists('ui_config', $payload)
            ? $this->normalizeTaxonomyUiConfig((array) $payload['ui_config'])
            : $this->decodeTaxonomyUiConfig($existing['ui_config'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $updated = $this->repo->updateTaxonomy($id, $name, $this->taxonomySlug($name), $uiConfig);
            $this->repo->createModerationAction($moderatorId, 'system', (string) $id, 'update_taxonomy', "Taxonomy renamed: {$existing['name']} -> $name");
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $updated ?? [];
    }

    private function normalizeTaxonomyUiConfig(array $uiConfig): array
    {
        if (array_is_list($uiConfig)) {
            throw new \InvalidArgumentException('ui_config must be a JSON object');
        }
        if (isset($uiConfig['description'])) {
            $description = trim(strip_tags((string) $uiConfig['description']));
            $length = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
            if ($length > 200) throw new \InvalidArgumentException('Description must be 200 characters or fewer');
            if ($description === '') {
                unset($uiConfig['description']);
            } else {
                $uiConfig['description'] = $description;
            }
        }
        try {
            json_encode($uiConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('ui_config must contain valid JSON values');
        }
        return $uiConfig;
    }

    private function decodeTaxonomyUiConfig(mixed $uiConfig): array
    {
        if (is_array($uiConfig)) return $uiConfig;
        if (!is_string($uiConfig) || trim($uiConfig) === '') return [];
        $decoded = json_decode($uiConfig, true);
        return is_array($decoded) ? $decoded : [];
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
