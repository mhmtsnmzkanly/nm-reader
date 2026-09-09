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
final class ChapterAdminService extends AdminServiceBase
{
    public function createChapter(string $typeSegment, string $slug, array $payload, ?string $moderatorId = null): array
    {
        $dbType = $this->toDbType($typeSegment);
        $content = $this->series->findContentByTypeAndSlug($dbType, $slug);
        if ($content === null) {
            throw new \DomainException('Content not found');
        }
    
        $error = Validator::requireFields($payload, ['chapter_number', 'type']);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }
    
        $chapterType = strtolower(trim((string) $payload['type']));
        if (!in_array($chapterType, AdminServiceBase::ALLOWED_CHAPTER_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid chapter type');
        }
    
        $chapterNumber = trim((string) $payload['chapter_number']);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $chapterNumber)) {
            throw new \InvalidArgumentException('chapter_number must be numeric and max 2 decimals');
        }
    
        // Check for duplicate chapter
        $existing = $this->chapters->findByTypeSlugAndChapterNumber($dbType, $slug, $chapterNumber);
        if ($existing !== null) {
            throw new \InvalidArgumentException(sprintf('Chapter %s already exists for this content', $chapterNumber));
        }
    
        $title = trim((string) ($payload['title'] ?? ''));
        $title = $title === '' ? null : Validator::sanitizeText($title);
        if ($title !== null && mb_strlen($title) > 200) {
            throw new \InvalidArgumentException('title must be 200 characters or fewer');
        }
        $chapterId = $this->entityIds->generateChapterId();
        $contentId = (string) $content['id'];
    
        $this->pdo->beginTransaction();
    
        try {
            $translatorNote = isset($payload['translator_note']) && is_string($payload['translator_note'])
                ? trim($payload['translator_note'])
                : null;
            if ($translatorNote !== null && mb_strlen($translatorNote) > 2000) {
                throw new \InvalidArgumentException('translator_note must be 2000 characters or fewer');
            }
    
            if ($chapterType === 'text') {
                $body = trim((string) ($payload['body'] ?? ''));
                if ($body === '') {
                    throw new \InvalidArgumentException('body is required for text chapters');
                }
                $safeBody = $this->scanner->assertSafe($body, 'novel_chapter');
                $dataObj = [
                    'body' => $safeBody,
                    'translator_note' => $translatorNote !== '' ? $translatorNote : null,
                ];
                $dataVal = json_encode($dataObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $pages = $payload['pages'] ?? null;
                if (!is_array($pages) || count($pages) === 0) {
                    throw new \InvalidArgumentException('pages is required for image chapters');
                }
    
                $validPages = [];
                foreach (array_values($pages) as $idx => $page) {
                    $imagePath = is_array($page)
                        ? trim((string) ($page['url'] ?? ($page['image_path'] ?? '')))
                        : trim((string) $page);
                    if ($imagePath === '') {
                        throw new \InvalidArgumentException('pages contains empty image path');
                    }
                    $this->assertValidChapterPagePath($imagePath);
                    $validPages[] = [
                        'page' => $idx + 1,
                        'url' => $imagePath,
                    ];
                }
                $dataObj = [
                    'body' => $validPages,
                    'translator_note' => $translatorNote !== '' ? $translatorNote : null,
                ];
                $dataVal = json_encode($dataObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
    
            $publishedAt = !empty($payload['published_at']) ? date('Y-m-d H:i:s', strtotime((string)$payload['published_at'])) : date('Y-m-d H:i:s');
            $priceAmount = max(0, (int)($payload['price_amount'] ?? 0));
            $isFreeAfter = !empty($payload['is_free_after']) ? date('Y-m-d H:i:s', strtotime((string)$payload['is_free_after'])) : null;
            $isMembersOnly = !empty($payload['is_members_only']) ? 1 : 0;
    
            $stmt = $this->pdo->prepare(
                'INSERT INTO chapters (id, content_id, `number`, chapter_number, title, type, is_members_only, `data`, price_amount, published_at, is_free_after, created_by, created_at)
                 VALUES (:cid, :content_id, :number, :chapter_number, :title, :type, :is_members_only, :data, :price_amount, :published_at, :is_free_after, :created_by, NOW())'
            );
            $stmt->execute([
                'cid' => $chapterId,
                'content_id' => $contentId,
                'number' => (float) $chapterNumber,
                'chapter_number' => $chapterNumber,
                'title' => $title,
                'type' => $chapterType,
                'is_members_only' => $isMembersOnly,
                'data' => $dataVal,
                'price_amount' => $priceAmount,
                'published_at' => $publishedAt,
                'is_free_after' => $isFreeAfter,
                'created_by' => $moderatorId,
            ]);
    
            // Update series chapter_count
            $this->pdo->prepare(
                'UPDATE series SET chapter_count = (SELECT COUNT(*) FROM chapters WHERE content_id = :cid AND deleted_at IS NULL) WHERE id = :sid'
            )->execute(['cid' => $contentId, 'sid' => $contentId]);
    
            if ($moderatorId !== null) {
                $this->adminConsole->createModerationAction(
                    $moderatorId, 
                    'chapter', 
                    $chapterId, 
                    'create', 
                    "Chapter $chapterNumber added to series $contentId"
                );
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $dbType, $slug));
        $this->invalidateListingCaches();
        $this->queue->enqueue('notify_new_chapter', [
            'content_id' => $contentId,
            'chapter_id' => $chapterId,
            'chapter_number' => ChapterNumber::normalize($chapterNumber),
            'series_title' => (string) ($content['title'] ?? 'Series'),
        ]);
    
        return [
            'id' => $chapterId,
            'content_id' => $contentId,
            'chapter_number' => ChapterNumber::normalize($chapterNumber),
            'title' => $title,
            'type' => $chapterType,
            'price_amount' => $priceAmount,
            'published_at' => $publishedAt,
            'is_free_after' => $isFreeAfter,
        ];
    }

    public function createChapterByContentId(string $contentId, array $payload, ?string $moderatorId = null): array
    {
        $stmt = $this->pdo->prepare('SELECT slug, type FROM series WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $contentId]);
        $content = $stmt->fetch();
        if (!$content) {
            throw new \DomainException('Content not found');
        }
    
        $typeSegment = str_replace('_', '-', (string) $content['type']);
        return $this->createChapter($typeSegment, (string) $content['slug'], $payload, $moderatorId);
    }

    public function listChapters(string $contentId, int $page, int $perPage): array
    {
        $items = $this->chapters->listByContentId($contentId, $page, $perPage, true);
        $items = array_map(static function (array $row): array {
            $row['chapter_number'] = ChapterNumber::normalize($row['chapter_number'] ?? '');
            return $row;
        }, $items);
        $total = $this->chapters->countByContentId($contentId);
    
        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    public function getChapter(string $chapterId): array
    {
        $chapter = $this->chapters->findById($chapterId);
        if ($chapter === null) {
            throw new \DomainException('Chapter not found');
        }
        $chapter['chapter_number'] = ChapterNumber::normalize($chapter['chapter_number'] ?? '');
    
        $type = strtolower((string) ($chapter['type'] ?? 'text'));
        $contentData = $this->chapters->findChapterContent($chapterId);
        $chapter['translator_note'] = $contentData['translator_note'] ?? null;
    
        if ($type === 'text') {
            $chapter['body'] = (string) ($contentData['body'] ?? '');
            $chapter['pages'] = [];
        } else {
            $chapter['body'] = null;
            $chapter['pages'] = array_values(array_filter(array_map(
                static fn (array $row): string => trim((string) ($row['image_path'] ?? '')),
                $contentData['pages'] ?? []
            ), static fn (string $path): bool => $path !== ''));
        }
    
        $chapter['pricing'] = $this->wallets->getChapterPricing($chapterId);
    
        return $chapter;
    }

    public function deleteChapter(string $chapterId, ?string $moderatorId = null): void
    {
        $identity = $this->chapters->findContentIdentityByChapterId($chapterId);
        if ($identity === null) {
            throw new \DomainException('Chapter not found');
        }
        $contentId = (string) $identity['content_id'];
        $this->pdo->beginTransaction();
        try {
            $this->chapters->deleteChapter($chapterId);
            $count = $this->chapters->countByContentId($contentId);
            $this->pdo->prepare('UPDATE series SET chapter_count = :cnt WHERE id = :id')
                ->execute(['cnt' => $count, 'id' => $contentId]);
    
            if ($moderatorId !== null) {
                $this->adminConsole->createModerationAction($moderatorId, 'chapter', $chapterId, 'delete', "Chapter deleted from series $contentId");
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    
        $slug = (string) ($identity['slug'] ?? '');
        $type = (string) ($identity['type'] ?? '');
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $type, $slug));
        $this->invalidateListingCaches();
    }

    public function updateChapter(string $chapterId, array $payload, ?string $moderatorId = null): void
    {
        $identity = $this->chapters->findContentIdentityByChapterId($chapterId);
        if ($identity === null) {
            throw new \DomainException('Chapter not found');
        }
    
        $chapterNumber = trim((string) ($payload['chapter_number'] ?? ''));
        if (!preg_match('/^\\d+(?:\\.\\d{1,2})?$/', $chapterNumber)) {
            throw new \InvalidArgumentException('chapter_number must be numeric and max 2 decimals');
        }
    
        $title = trim((string) ($payload['title'] ?? ''));
        $title = $title === '' ? null : Validator::sanitizeText($title);
        if ($title !== null && mb_strlen($title) > 200) {
            throw new \InvalidArgumentException('title must be 200 characters or fewer');
        }
        $type = strtolower(trim((string) ($payload['type'] ?? 'text')));
        if (!in_array($type, AdminServiceBase::ALLOWED_CHAPTER_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid chapter type');
        }
    
        $contentId = (string) $identity['content_id'];
        if ($this->chapters->existsChapterNumberForContent($contentId, $chapterNumber, $chapterId)) {
            throw new \InvalidArgumentException(sprintf('Chapter %s already exists for this content', $chapterNumber));
        }
    
        // Fetch current to calculate diff
        $stmt = $this->pdo->prepare('SELECT chapter_number, title, type, `data`, is_members_only, price_amount, price_last_update, published_at, is_free_after FROM chapters WHERE id = :id');
        $stmt->execute(['id' => $chapterId]);
        $current = $stmt->fetch();
    
        $existingContent = $this->chapters->findChapterContent($chapterId);
        $translatorNote = array_key_exists('translator_note', $payload)
            ? (is_string($payload['translator_note']) && trim($payload['translator_note']) !== '' ? trim($payload['translator_note']) : null)
            : ($existingContent['translator_note'] ?? null);
        if ($translatorNote !== null && mb_strlen($translatorNote) > 2000) {
            throw new \InvalidArgumentException('translator_note must be 2000 characters or fewer');
        }
    
        if ($type === 'text') {
            $body = array_key_exists('body', $payload)
                ? trim((string) $payload['body'])
                : trim((string) ($existingContent['body'] ?? ''));
            if ($body === '') {
                throw new \InvalidArgumentException('body is required for text chapters');
            }
            $safeBody = $this->scanner->assertSafe($body, 'novel_chapter');
            $dataVal = json_encode([
                'body' => $safeBody,
                'translator_note' => $translatorNote,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $rawPages = $this->normalizePagesPayload($payload['pages'] ?? null);
            if (count($rawPages) === 0) {
                $rawPages = array_values(array_filter(array_map(
                    static fn (array $row): string => trim((string) ($row['image_path'] ?? '')),
                    $existingContent['pages'] ?? []
                ), static fn (string $path): bool => $path !== ''));
            }
            if (count($rawPages) === 0) {
                throw new \InvalidArgumentException('pages is required for image chapters');
            }
    
            $validPages = [];
            foreach (array_values($rawPages) as $idx => $page) {
                $this->assertValidChapterPagePath($page);
                $validPages[] = [
                    'page' => $idx + 1,
                    'url' => $page,
                ];
            }
            $dataVal = json_encode([
                'body' => $validPages,
                'translator_note' => $translatorNote,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    
        $priceAmount = array_key_exists('price_amount', $payload) ? max(0, (int) $payload['price_amount']) : (int) ($current['price_amount'] ?? 0);
        $publishedAt = array_key_exists('published_at', $payload) ? (!empty($payload['published_at']) ? date('Y-m-d H:i:s', strtotime((string)$payload['published_at'])) : null) : ($current['published_at'] ?? null);
        $isFreeAfter = array_key_exists('is_free_after', $payload) ? (!empty($payload['is_free_after']) ? date('Y-m-d H:i:s', strtotime((string)$payload['is_free_after'])) : null) : ($current['is_free_after'] ?? null);
    
        $diff = [];
        $isMembersOnly = isset($payload['is_members_only']) ? (!empty($payload['is_members_only']) ? 1 : 0) : null;
        $newValues = [
            'chapter_number' => $chapterNumber,
            'title' => $title,
            'type' => $type,
            'data' => $dataVal,
            'price_amount' => $priceAmount,
            'published_at' => $publishedAt,
            'is_free_after' => $isFreeAfter,
        ];
        if ($isMembersOnly !== null) {
            $newValues['is_members_only'] = $isMembersOnly;
        }
    
        foreach ($newValues as $key => $val) {
            if (($current[$key] ?? null) !== $val) {
                $beforeVal = $current[$key] ?? null;
                $afterVal = $val;
                if ($key === 'data' && strlen((string)$val) > 255) {
                    $beforeVal = '[LARGE CONTENT]';
                    $afterVal = '[UPDATED LARGE CONTENT]';
                }
                $diff[$key] = ['before' => $beforeVal, 'after' => $afterVal];
            }
        }
    
        $this->pdo->beginTransaction();
        try {
            $this->chapters->updateChapter($chapterId, $chapterNumber, $title, $type);
    
            $updateFields = ['`data` = :data', '`number` = :number', '`price_amount` = :price', '`published_at` = :pub', '`is_free_after` = :free'];
            $params = [
                'data' => $dataVal,
                'number' => (float) $chapterNumber,
                'price' => $priceAmount,
                'pub' => $publishedAt,
                'free' => $isFreeAfter,
                'id' => $chapterId
            ];
            if ((int) ($current['price_amount'] ?? 0) !== $priceAmount) {
                $updateFields[] = '`price_last_update` = NOW()';
            }
            if ($isMembersOnly !== null) {
                $updateFields[] = '`is_members_only` = :is_members_only';
                $params['is_members_only'] = $isMembersOnly;
            }
    
            $this->pdo->prepare('UPDATE chapters SET ' . implode(', ', $updateFields) . ' WHERE id = :id')
                ->execute($params);
    
            if ($moderatorId !== null && !empty($diff)) {
                $this->adminConsole->createModerationAction($moderatorId, "chapter", $chapterId, "update", json_encode(['diff' => $diff], JSON_UNESCAPED_UNICODE));
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    
        $slug = (string) ($identity['slug'] ?? '');
        $typeSlug = (string) ($identity['type'] ?? '');
        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $typeSlug, $slug));
        $this->invalidateListingCaches();
    }

    public function bulkChapterAction(array $chapterIds, string $action, array $params = [], ?string $moderatorId = null): array
    {
        $chapterIds = array_values(array_unique(array_filter(array_map('strval', $chapterIds))));
        if (empty($chapterIds)) {
            throw new \InvalidArgumentException('No chapters selected');
        }
    
        $affected = 0;
        $this->pdo->beginTransaction();
        try {
            switch ($action) {
                case 'delete':
                    $inPlaceholders = implode(',', array_fill(0, count($chapterIds), '?'));
                    $stmt = $this->pdo->prepare("UPDATE chapters SET deleted_at = NOW() WHERE id IN ($inPlaceholders) AND deleted_at IS NULL");
                    $stmt->execute($chapterIds);
                    $affected = $stmt->rowCount();
                    break;
                case 'publish':
                    $inPlaceholders = implode(',', array_fill(0, count($chapterIds), '?'));
                    $stmt = $this->pdo->prepare("UPDATE chapters SET published_at = NOW() WHERE id IN ($inPlaceholders)");
                    $stmt->execute($chapterIds);
                    $affected = $stmt->rowCount();
                    break;
                case 'schedule':
                    $date = !empty($params['published_at']) ? date('Y-m-d H:i:s', strtotime((string) $params['published_at'])) : date('Y-m-d H:i:s');
                    $inPlaceholders = implode(',', array_fill(0, count($chapterIds), '?'));
                    $stmt = $this->pdo->prepare("UPDATE chapters SET published_at = ? WHERE id IN ($inPlaceholders)");
                    $stmt->execute(array_merge([$date], $chapterIds));
                    $affected = $stmt->rowCount();
                    break;
                case 'set_price':
                    $price = max(0, (int) ($params['price_amount'] ?? 0));
                    $freeAfter = !empty($params['is_free_after']) ? date('Y-m-d H:i:s', strtotime((string) $params['is_free_after'])) : null;
                    $inPlaceholders = implode(',', array_fill(0, count($chapterIds), '?'));
                    $stmt = $this->pdo->prepare("UPDATE chapters SET price_amount = ?, is_free_after = ? WHERE id IN ($inPlaceholders)");
                    $stmt->execute(array_merge([$price, $freeAfter], $chapterIds));
                    $affected = $stmt->rowCount();
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported bulk action: {$action}");
            }
    
            if ($moderatorId !== null && $affected > 0) {
                $this->adminConsole->createModerationAction(
                    $moderatorId,
                    'chapter',
                    implode(',', array_slice($chapterIds, 0, 5)),
                    'bulk_' . $action,
                    "Bulk {$action} applied to {$affected} chapters"
                );
            }
    
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    
        $this->invalidateListingCaches();
        return ['action' => $action, 'affected' => $affected];
    }
}
