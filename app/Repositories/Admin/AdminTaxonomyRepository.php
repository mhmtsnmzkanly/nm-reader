<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminTaxonomyRepository extends AdminRepositoryBase
{
    public function createGenre(string $name, string $slug): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO taxonomies (type, name, slug, sort_order) SELECT "genre", :name, :slug, COALESCE(MAX(sort_order), -1) + 1 FROM taxonomies WHERE type = "genre"');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        $id = (int)$this->pdo->lastInsertId();
    
        return ['id' => $id, 'type' => 'genre', 'name' => $name, 'slug' => $slug, 'sort_order' => $this->taxonomySortOrder($id)];
    }

    public function createTag(string $name, string $slug): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO taxonomies (type, name, slug, sort_order) SELECT "tag", :name, :slug, COALESCE(MAX(sort_order), -1) + 1 FROM taxonomies WHERE type = "tag"');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        $id = (int)$this->pdo->lastInsertId();
    
        return ['id' => $id, 'type' => 'tag', 'name' => $name, 'slug' => $slug, 'sort_order' => $this->taxonomySortOrder($id)];
    }

    public function taxonomyById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT t.id, t.type, t.name, t.slug, t.ui_config, t.sort_order, COUNT(stm.content_id) AS usage_count FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.id = :id GROUP BY t.id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateTaxonomy(int $id, string $name, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE taxonomies SET name = :name, slug = :slug WHERE id = :id');
        $stmt->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
        return $this->taxonomyById($id);
    }

    public function deleteTaxonomy(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE t FROM taxonomies t LEFT JOIN series_taxonomy_map stm ON stm.taxonomy_id = t.id WHERE t.id = :id AND stm.taxonomy_id IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function mergeTaxonomies(int $sourceId, int $targetId): array
    {
        $this->pdo->beginTransaction();
        try {
            $source = $this->taxonomyById($sourceId);
            $target = $this->taxonomyById($targetId);
            if (!$source || !$target || $source['type'] !== $target['type']) {
                throw new \InvalidArgumentException('Taxonomies must exist and have the same type');
            }
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO series_taxonomy_map (content_id, taxonomy_id) SELECT content_id, :target_id FROM series_taxonomy_map WHERE taxonomy_id = :source_id');
            $stmt->execute(['target_id' => $targetId, 'source_id' => $sourceId]);
            $this->pdo->prepare('DELETE FROM series_taxonomy_map WHERE taxonomy_id = :id')->execute(['id' => $sourceId]);
            $this->pdo->prepare('DELETE FROM taxonomies WHERE id = :id')->execute(['id' => $sourceId]);
            $this->pdo->commit();
            return $this->taxonomyById($targetId) ?? $target;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reorderTaxonomies(array $items): void
    {
        $stmt = $this->pdo->prepare('UPDATE taxonomies SET sort_order = :sort_order WHERE id = :id');
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $stmt->execute(['id' => (int)$item['id'], 'sort_order' => (int)$item['sort_order']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
