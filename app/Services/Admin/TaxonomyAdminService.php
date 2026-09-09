<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Helpers\ChapterNumber;
use App\Helpers\Validator;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\WalletRepository;
use App\Services\CacheService;
use App\Services\ContentSecurityScanner;
use App\Services\EntityIdService;
use App\Services\QueueService;
use App\Services\SlugService;
use PDO;

use App\Services\Admin\AdminServiceBase;

/** Domain service extracted from the legacy administrative service. */
final class TaxonomyAdminService extends AdminServiceBase
{
    public function createGenre(string $name): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');
        $slug = $this->slugService->normalize($name);
        
        $stmt = $this->pdo->prepare('INSERT INTO taxonomies (type, name, slug) VALUES ("genre", :name, :slug)');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        $id = (int) $this->pdo->lastInsertId();
        
        $this->invalidateListingCaches();
        return ['id' => $id, 'name' => $name, 'slug' => $slug];
    }

    public function createTag(string $name): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');
        $slug = $this->slugService->normalize($name);
        
        $stmt = $this->pdo->prepare('INSERT INTO taxonomies (type, name, slug) VALUES ("tag", :name, :slug)');
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        $id = (int) $this->pdo->lastInsertId();
        
        $this->invalidateListingCaches();
        return ['id' => $id, 'name' => $name, 'slug' => $slug];
    }

    public function updateContentTaxonomy(string $contentId, array $genreIds, array $tagIds): void
    {
        $stmt = $this->pdo->prepare('SELECT slug, type FROM series WHERE id = :id');
        $stmt->execute(['id' => $contentId]);
        $content = $stmt->fetch();
        
        if (!$content) {
            throw new \DomainException('Content not found');
        }
    
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM series_taxonomy_map WHERE content_id = :id AND taxonomy_id IN (SELECT id FROM taxonomies WHERE type IN ("genre", "tag"))')->execute(['id' => $contentId]);
            $allTaxIds = array_filter(array_merge($genreIds, $tagIds));
            if (!empty($allTaxIds)) {
                $stmt = $this->pdo->prepare('INSERT INTO series_taxonomy_map (content_id, taxonomy_id) VALUES (:cid, :tid) ON DUPLICATE KEY UPDATE content_id = VALUES(content_id)');
                foreach ($allTaxIds as $tid) {
                    if ($tid) $stmt->execute(['cid' => $contentId, 'tid' => (int) $tid]);
                }
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    
        $this->clearContentCaches((string) $content['slug'], (string) $content['type']);
        
        $this->invalidateListingCaches();
    }
}
