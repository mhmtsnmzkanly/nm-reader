<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ChapterNumber;
use App\Helpers\Validator;
use App\Repositories\ChapterRepository;
use App\Repositories\SeriesRepository;
use PDO;

/**
 * Service for administrative operations.
 *
 * Handles content creation, updates, chapter management, taxonomy (series_genres/series_tags),
 * and cache invalidation. This service enforces business rules for data integrity
 * and proper workflow.
 *
 * @package App\Services
 */
final class AdminService
{
    private const TYPE_SEGMENT_TO_DB = [
        'light-novel' => 'light_novel',
        'web-novel' => 'web_novel',
        'novel' => 'novel',
        'manga' => 'manga',
        'manhua' => 'manhua',
        'manhwa' => 'manhwa',
        'webtoon' => 'webtoon',
    ];

    private const ALLOWED_STATUSES = ['ongoing', 'completed', 'hiatus'];
    private const ALLOWED_CHAPTER_TYPES = ['text', 'image'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly SeriesRepository $series,
        private readonly ChapterRepository $chapters,
        private readonly EntityIdService $entityIds,
        private readonly SlugService $slugService,
        private readonly CacheService $cache,
        private readonly QueueService $queue,
        private readonly \App\Repositories\AdminConsoleRepository $adminConsole
    ) {
    }

    /**
     * Creates a new content (series) entry.
     *
     * Validates input, generates a unique slug and ID, and inserts the record
     * into the database. Also handles metadata upsertion and cache invalidation.
     *
     * @param array $payload Input data (title, type, status, description, etc.).
     * @param string|null $moderatorId
     * @return array The created content's basic info including its generated URL path.
     * @throws \InvalidArgumentException If validation fails (e.g., missing title, invalid type).
     */
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
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
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

        $sql = 'INSERT INTO series (
                    id, title, slug, description, type, status, cover_image,
                    rating_avg, rating_count, chapter_count, comment_count, created_at
                ) VALUES (
                    :id, :title, :slug, :description, :type, :status, :cover_image,
                    0, 0, 0, 0, NOW()
                )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'type' => $dbType,
            'status' => $status,
            'cover_image' => $coverImage,
        ]);

        $this->upsertContentMetadata($id, $author, $artist, $alternativeTitles, $country, $releaseYear);

        if ($moderatorId !== null) {
            $this->adminConsole->createModerationAction($moderatorId, 'content', $id, 'update', "New series created: $title");
        }

        $this->invalidateListingCaches();

        return [
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'type' => $dbType,
            'status' => $status,
            'url_path' => '/' . str_replace('_', '-', $dbType) . '/' . $slug,
        ];
    }

    /**
     * Updates an existing content entry.
     *
     * Modifies core fields (title, desc, status) and metadata. Handles cache clearing
     * for the specific content.
     *
     * @param string $id The content ID.
     * @param array $payload Data to update.
     * @param string|null $moderatorId
     * @throws \InvalidArgumentException If required fields are missing.
     */
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
        $stmt = $this->pdo->prepare('SELECT title, description, status, cover_image, slug, type FROM series WHERE id = :id');
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
            $updates[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($coverImage !== null) {
            $updates[] = 'cover_image = :cover_image';
            $params['cover_image'] = $coverImage === '' ? null : $coverImage;
        }

        $this->pdo->beginTransaction();
        try {
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

    private function upsertContentMetadata(string $contentId, ?string $author, ?string $artist, ?string $alternativeTitles, ?string $country, ?int $releaseYear): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO series_metadata (content_id, author, artist, alternative_titles, country, release_year, created_at, updated_at)
                 VALUES (:content_id, :author, :artist, :alternative_titles, :country, :release_year, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE author = VALUES(author), artist = VALUES(artist), alternative_titles = VALUES(alternative_titles), country = VALUES(country), release_year = VALUES(release_year), updated_at = NOW()'
            );
            $stmt->execute([
                'content_id' => $contentId,
                'author' => $author,
                'artist' => $artist,
                'alternative_titles' => $alternativeTitles,
                'country' => $country,
                'release_year' => $releaseYear,
            ]);
        } catch (\Throwable) {
            // metadata table may not exist; fail silently
        }
    }

    private function sanitizePerson(string $value): ?string
    {
        $v = trim(Validator::sanitizeText($value));
        return $v === '' ? null : mb_substr($v, 0, 120);
    }

    private function sanitizeCountry(string $value): ?string
    {
        $v = strtoupper(trim(Validator::sanitizeText($value)));
        if ($v === '') {
            return null;
        }
        return mb_substr($v, 0, 64);
    }

    private function sanitizeYear(string $value): ?int
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        $year = (int) $v;
        if ($year < 1800 || $year > ((int) date('Y') + 1)) {
            throw new \InvalidArgumentException('release_year must be between 1800 and next year');
        }
        return $year;
    }

    /**
     * Creates a new chapter for a specific content.
     *
     * Validates input, prevents duplicates, handles text vs image types,
     * and queues notification jobs.
     *
     * @param string $typeSegment URL segment for content type (e.g., 'manga').
     * @param string $slug Content slug.
     * @param array $payload Chapter data (number, title, body/pages).
     * @param string|null $moderatorId
     * @return array Created chapter details.
     * @throws \DomainException If content not found.
     * @throws \InvalidArgumentException If input is invalid or chapter already exists.
     */
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
        if (!in_array($chapterType, self::ALLOWED_CHAPTER_TYPES, true)) {
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
        $chapterId = $this->entityIds->generateChapterId();
        $contentId = (string) $content['id'];

        $this->pdo->beginTransaction();

        try {
            if ($chapterType === 'text') {
                $body = trim((string) ($payload['body'] ?? ''));
                if ($body === '') {
                    throw new \InvalidArgumentException('body is required for text chapters');
                }
                $dataVal = $body;
            } else {
                $pages = $payload['pages'] ?? null;
                if (!is_array($pages) || count($pages) === 0) {
                    throw new \InvalidArgumentException('pages is required for image chapters');
                }

                $validPages = [];
                foreach (array_values($pages) as $page) {
                    $imagePath = is_array($page)
                        ? trim((string) ($page['image_path'] ?? ''))
                        : trim((string) $page);
                    if ($imagePath === '') {
                        throw new \InvalidArgumentException('pages contains empty image path');
                    }
                    $validPages[] = $imagePath;
                }
                $dataVal = implode('|', $validPages);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO chapters (id, content_id, chapter_number, title, type, `data`, created_by, created_at)
                 VALUES (:cid, :content_id, :chapter_number, :title, :type, :data, :created_by, NOW())'
            );
            $stmt->execute([
                'cid' => $chapterId,
                'content_id' => $contentId,
                'chapter_number' => $chapterNumber,
                'title' => $title,
                'type' => $chapterType,
                'data' => $dataVal,
                'created_by' => $moderatorId,
            ]);

            // Update series chapter_count
            $this->pdo->prepare(
                'UPDATE series SET chapter_count = (SELECT COUNT(*) FROM chapters WHERE content_id = :cid) WHERE id = :sid'
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

    /**
     * Lists chapters for a content with pagination.
     *
     * @param string $contentId
     * @param int $page
     * @param int $perPage
     * @return array ['items' => [...], 'meta' => [...]]
     */
    public function listChapters(string $contentId, int $page, int $perPage): array
    {
        $items = $this->chapters->listByContentId($contentId, $page, $perPage);
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

    /**
     * Retrieves chapter details including its body or pages.
     *
     * @param string $chapterId
     * @return array Chapter data.
     * @throws \DomainException If not found.
     */
    public function getChapter(string $chapterId): array
    {
        $chapter = $this->chapters->findById($chapterId);
        if ($chapter === null) {
            throw new \DomainException('Chapter not found');
        }
        $chapter['chapter_number'] = ChapterNumber::normalize($chapter['chapter_number'] ?? '');

        $type = strtolower((string) ($chapter['type'] ?? 'text'));
        if ($type === 'text') {
            $chapter['body'] = $this->chapters->findChapterText($chapterId) ?? '';
            $chapter['pages'] = [];
        } else {
            $pages = $this->chapters->findChapterPages($chapterId);
            $chapter['body'] = null;
            $chapter['pages'] = array_values(array_filter(array_map(
                static fn (array $row): string => trim((string) ($row['image_path'] ?? '')),
                $pages
            ), static fn (string $path): bool => $path !== ''));
        }

        return $chapter;
    }

    /**
     * Deletes a chapter and updates the content's chapter count.
     *
     * @param string $chapterId
     * @param string|null $moderatorId
     * @throws \DomainException If not found.
     */
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

    /**
     * Updates an existing chapter.
     *
     * Handles type changes (e.g., text to image) by cleaning up old data tables.
     *
     * @param string $chapterId
     * @param array $payload
     * @param string|null $moderatorId
     * @throws \DomainException If not found.
     * @throws \InvalidArgumentException If input is invalid.
     */
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
        $type = strtolower(trim((string) ($payload['type'] ?? 'text')));
        if (!in_array($type, self::ALLOWED_CHAPTER_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid chapter type');
        }

        $contentId = (string) $identity['content_id'];
        if ($this->chapters->existsChapterNumberForContent($contentId, $chapterNumber, $chapterId)) {
            throw new \InvalidArgumentException(sprintf('Chapter %s already exists for this content', $chapterNumber));
        }

        // Fetch current to calculate diff
        $stmt = $this->pdo->prepare('SELECT chapter_number, title, type, `data` FROM chapters WHERE id = :id');
        $stmt->execute(['id' => $chapterId]);
        $current = $stmt->fetch();

        $body = '';
        $pages = [];
        if ($type === 'text') {
            $body = array_key_exists('body', $payload)
                ? trim((string) $payload['body'])
                : trim((string) ($this->chapters->findChapterText($chapterId) ?? ''));
            if ($body === '') {
                throw new \InvalidArgumentException('body is required for text chapters');
            }
            $dataVal = $body;
        } else {
            $pages = $this->normalizePagesPayload($payload['pages'] ?? null);
            if (count($pages) === 0) {
                $pages = $this->normalizePagesPayload($this->chapters->findChapterPages($chapterId));
            }
            if (count($pages) === 0) {
                throw new \InvalidArgumentException('pages is required for image chapters');
            }
            $dataVal = implode('|', $pages);
        }

        $diff = [];
        $newValues = [
            'chapter_number' => $chapterNumber,
            'title' => $title,
            'type' => $type,
            'data' => $dataVal
        ];

        foreach ($newValues as $key => $val) {
            if (($current[$key] ?? null) !== $val) {
                $beforeVal = $current[$key];
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

            $this->pdo->prepare('UPDATE chapters SET `data` = :data WHERE id = :id')
                ->execute(['data' => $dataVal, 'id' => $chapterId]);

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

    public function createGenre(string $name): array
    {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Name is required');
        $slug = $this->slugService->normalize($name);
        
        $stmt = $this->pdo->prepare('INSERT INTO series_genres (name, slug) VALUES (:name, :slug)');
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
        
        $stmt = $this->pdo->prepare('INSERT INTO series_tags (name, slug) VALUES (:name, :slug)');
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
            // Genres
            $this->pdo->prepare('DELETE FROM series_genre_map WHERE content_id = :id')->execute(['id' => $contentId]);
            $insGenre = $this->pdo->prepare('INSERT INTO series_genre_map (content_id, genre_id) VALUES (:cid, :gid)');
            foreach ($genreIds as $gid) {
                $insGenre->execute(['cid' => $contentId, 'gid' => (int)$gid]);
            }

            // Tags
            $this->pdo->prepare('DELETE FROM series_tag_map WHERE content_id = :id')->execute(['id' => $contentId]);
            $insTag = $this->pdo->prepare('INSERT INTO series_tag_map (content_id, tag_id) VALUES (:cid, :tid)');
            foreach ($tagIds as $tid) {
                $insTag->execute(['cid' => $contentId, 'tid' => (int)$tid]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $this->clearContentCaches((string) $content['slug'], (string) $content['type']);
        
        $this->invalidateListingCaches();
    }

    private function toDbType(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (isset(self::TYPE_SEGMENT_TO_DB[$normalized])) {
            return self::TYPE_SEGMENT_TO_DB[$normalized];
        }

        $dbStyle = str_replace('-', '_', $normalized);
        if (in_array($dbStyle, self::TYPE_SEGMENT_TO_DB, true)) {
            return $dbStyle;
        }

        throw new \InvalidArgumentException('Invalid content type');
    }

    private function normalizePagesPayload(mixed $value): array
    {
        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            return array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                $lines
            ), static fn (string $line): bool => $line !== ''));
        }

        if (!is_array($value)) {
            return [];
        }

        $pages = [];
        foreach (array_values($value) as $item) {
            if (is_array($item)) {
                $path = trim((string) ($item['image_path'] ?? $item['url'] ?? ''));
            } else {
                $path = trim((string) $item);
            }
            if ($path !== '') {
                $pages[] = $path;
            }
        }

        return $pages;
    }

    private function invalidateListingCaches(): void
    {
        $this->cache->deleteByPrefix('homepage_popular_');
        $this->cache->deleteByPrefix('type_list_');
        $this->cache->deleteByPrefix('genre_list_');
        $this->cache->deleteByPrefix('tag_list_');
        $this->cache->deleteByPrefix('latest_chapters_');
        $this->cache->deleteByPrefix('genres_');
        $this->cache->deleteByPrefix('tags_');
    }

    private function clearContentCaches(string $slug, string $type): void
    {
        $typeSegment = str_replace('_', '-', $type);

        $this->cache->delete(sprintf('content_%s', $slug));
        $this->cache->delete(sprintf('content_%s_%s', $type, $slug));
        $this->cache->delete(sprintf('content_%s_%s', $typeSegment, $slug));

        $this->cache->deleteByPrefix(sprintf('content_%s_%s_', $type, $slug));
        $this->cache->deleteByPrefix(sprintf('content_%s_%s_', $typeSegment, $slug));
    }


}
