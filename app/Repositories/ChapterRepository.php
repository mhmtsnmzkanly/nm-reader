<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Chapter-related database operations.
 *
 * Handles raw SQL interactions for individual chapters, including their content
 * (text body or image pages), view tracking, and reading progress.
 *
 * @package App\Repositories
 */
final class ChapterRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retrieves basic chapter metadata by its 6-character ID.
     *
     * @param string $chapterId
     * @return array|null
     */
    public function findById(string $chapterId): ?array
    {
        $sql = 'SELECT ch.id, ch.content_id, ch.chapter_number, ch.title, ch.type, ch.is_members_only, ch.`data`, ch.created_at, ch.created_by, u.username
                FROM chapters ch
                LEFT JOIN users u ON u.id = ch.created_by
                WHERE ch.id = :id AND ch.deleted_at IS NULL LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $chapterId]);
        $chapter = $stmt->fetch();

        return $chapter === false ? null : $chapter;
    }

    /**
     * Checks if a chapter ID exists in the database.
     */
    public function existsChapterId(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM chapters WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Finds the parent series identity (ID, slug, type) for a given chapter.
     */
    public function findContentIdentityByChapterId(string $chapterId): ?array
    {
        $sql = 'SELECT c.id AS content_id, c.slug, c.type
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE ch.id = :chapter_id AND ch.deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['chapter_id' => $chapterId]);
        $row = $stmt->fetch();

        return $row === false ? null : [
            'content_id' => (string) $row['content_id'],
            'slug' => (string) $row['slug'],
            'type' => (string) $row['type'],
        ];
    }

    /**
     * Finds a chapter by its number. Includes a decimal-safe fallback.
     */
    public function findByChapterNumber(string $chapterNumber): ?array
    {
        $sql = 'SELECT ch.id, ch.content_id, ch.chapter_number, ch.title, ch.type, ch.is_members_only, ch.created_at,
                       c.is_adult, c.is_members_only AS series_is_members_only
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE ch.chapter_number = :chapter_number AND ch.deleted_at IS NULL AND c.deleted_at IS NULL
                  AND (c.lifecycle_status = "published" OR (c.lifecycle_status = "scheduled" AND c.scheduled_at <= NOW()))
                ORDER BY ch.id DESC
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['chapter_number' => $chapterNumber]);
        $chapter = $stmt->fetch();

        if ($chapter !== false) {
            return $chapter;
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $chapterNumber)) {
            return null;
        }

        // Decimal-safe fallback for imprecise string matches.
        $fallback = $this->pdo->prepare(
            'SELECT ch.id, ch.content_id, ch.chapter_number, ch.title, ch.type, ch.is_members_only, ch.created_at,
                    c.is_adult, c.is_members_only AS series_is_members_only
             FROM chapters ch
             INNER JOIN series c ON c.id = ch.content_id
             WHERE CAST(ch.chapter_number AS DECIMAL(10,2)) = CAST(:chapter_number AS DECIMAL(10,2))
               AND ch.deleted_at IS NULL AND c.deleted_at IS NULL
               AND (c.lifecycle_status = "published" OR (c.lifecycle_status = "scheduled" AND c.scheduled_at <= NOW()))
             ORDER BY ch.id DESC
             LIMIT 1'
        );
        $fallback->execute(['chapter_number' => $chapterNumber]);
        $chapter = $fallback->fetch();

        return $chapter === false ? null : $chapter;
    }

    /**
     * Advanced lookup for a chapter using type, slug, and chapter number.
     * Essential for routing chapter pages (e.g., /manga/one-piece/chapter/100).
     */
    public function findByTypeSlugAndChapterNumber(string $type, string $slug, string $chapterNumber): ?array
    {
        $sql = 'SELECT
                    ch.id,
                    ch.content_id,
                    ch.chapter_number,
                    ch.title,
                    ch.type,
                    ch.is_members_only,
                    ch.created_at,
                    c.title AS series_title,
                    c.slug AS series_slug,
                    c.type AS series_type,
                    c.is_adult,
                    c.is_members_only AS series_is_members_only
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE c.type = :type
                  AND c.slug = :slug
                  AND ch.chapter_number = :chapter_number
                  AND ch.deleted_at IS NULL
                  AND c.deleted_at IS NULL
                  AND (c.lifecycle_status = "published" OR (c.lifecycle_status = "scheduled" AND c.scheduled_at <= NOW()))
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'type' => $type,
            'slug' => $slug,
            'chapter_number' => $chapterNumber,
        ]);
        $chapter = $stmt->fetch();

        if ($chapter !== false) {
            return $chapter;
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $chapterNumber)) {
            return null;
        }

        $fallback = $this->pdo->prepare(
            'SELECT
                ch.id,
                ch.content_id,
                ch.chapter_number,
                ch.title,
                ch.type,
                ch.is_members_only,
                ch.created_at,
                c.title AS series_title,
                c.slug AS series_slug,
                c.type AS series_type,
                c.is_adult,
                c.is_members_only AS series_is_members_only
             FROM chapters ch
             INNER JOIN series c ON c.id = ch.content_id
             WHERE c.type = :type
               AND c.slug = :slug
               AND CAST(ch.chapter_number AS DECIMAL(10,2)) = CAST(:chapter_number AS DECIMAL(10,2))
               AND ch.deleted_at IS NULL
               AND c.deleted_at IS NULL
               AND (c.lifecycle_status = "published" OR (c.lifecycle_status = "scheduled" AND c.scheduled_at <= NOW()))
             ORDER BY ch.id ASC
             LIMIT 1'
        );
        $fallback->execute([
            'type' => $type,
            'slug' => $slug,
            'chapter_number' => $chapterNumber,
        ]);
        $chapter = $fallback->fetch();

        return $chapter === false ? null : $chapter;
    }

    /**
     * Resolves structured chapter content (body, pages, translator_note) from the JSON data column.
     *
     * @param string $chapterId
     * @return array{type:string,body:?string,pages:array<int,array{page_order:int,image_path:string}>,translator_note:?string}
     */
    public function findChapterContent(string $chapterId): array
    {
        $stmt = $this->pdo->prepare('SELECT `type`, `data` FROM chapters WHERE id = :chapter_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['chapter_id' => $chapterId]);
        $row = $stmt->fetch();

        if ($row === false || empty($row['data'])) {
            return [
                'type' => 'image',
                'body' => null,
                'pages' => [],
                'translator_note' => null,
            ];
        }

        $type = (string) ($row['type'] ?? 'image');
        $raw = (string) $row['data'];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'type' => $type,
                'body' => null,
                'pages' => [],
                'translator_note' => null,
            ];
        }

        $translatorNote = isset($decoded['translator_note']) && is_string($decoded['translator_note'])
            ? trim($decoded['translator_note'])
            : null;

        if ($type === 'text') {
            return [
                'type' => 'text',
                'body' => (string) ($decoded['body'] ?? ''),
                'pages' => [],
                'translator_note' => $translatorNote !== '' ? $translatorNote : null,
            ];
        }

        // Image type: body contains list of page objects
        $body = $decoded['body'] ?? [];
        $pages = [];
        if (is_array($body)) {
            foreach (array_values($body) as $idx => $item) {
                $order = is_array($item) ? (int) ($item['page'] ?? ($item['page_order'] ?? ($idx + 1))) : ($idx + 1);
                $path = is_array($item) ? (string) ($item['url'] ?? ($item['image_path'] ?? '')) : (string) $item;
                if ($path !== '') {
                    $pages[] = [
                        'page_order' => $order,
                        'image_path' => $path,
                    ];
                }
            }
        }

        return [
            'type' => 'image',
            'body' => null,
            'pages' => $pages,
            'translator_note' => $translatorNote !== '' ? $translatorNote : null,
        ];
    }

    /**
     * Confirms that an image filename and page order belong to a live chapter.
     */
    public function ownsMediaPage(string $chapterId, int $pageOrder, string $filename): bool
    {
        $content = $this->findChapterContent($chapterId);
        if (($content['type'] ?? '') !== 'image') {
            return false;
        }

        $expectedFilename = basename($filename);
        foreach ($content['pages'] ?? [] as $page) {
            if ((int) ($page['page_order'] ?? 0) === $pageOrder
                && hash_equals($expectedFilename, basename((string) ($page['image_path'] ?? '')))
            ) {
                return true;
            }
        }

        return false;
    }

    public function findChapterText(string $chapterId): ?string
    {
        $content = $this->findChapterContent($chapterId);
        return $content['type'] === 'text' ? (string) ($content['body'] ?? '') : null;
    }

    public function findChapterPages(string $chapterId): array
    {
        $content = $this->findChapterContent($chapterId);
        return $content['pages'] ?? [];
    }

    public function findChapterTranslatorNote(string $chapterId): ?string
    {
        $content = $this->findChapterContent($chapterId);
        return $content['translator_note'] ?? null;
    }

    /**
     * Lists all chapters for a specific content ID with pagination.
     */
    public function listByContentId(string $contentId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->pdo->prepare(
            'SELECT ch.id, ch.content_id, ch.chapter_number, ch.title, ch.type, ch.is_members_only, ch.created_at, u.username
             FROM chapters ch
             LEFT JOIN users u ON u.id = ch.created_by
             WHERE ch.content_id = :content_id AND ch.deleted_at IS NULL
             ORDER BY CAST(ch.chapter_number AS DECIMAL(10,2)) DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':content_id', $contentId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Finds the next and previous chapters for navigation.
     */
    public function findAdjacentChapters(string $contentId, string $currentNumber): array
    {
        $nextSql = 'SELECT chapter_number FROM chapters 
                    WHERE content_id = :content_id 
                      AND CAST(chapter_number AS DECIMAL(10,2)) > CAST(:current AS DECIMAL(10,2))
                      AND deleted_at IS NULL
                    ORDER BY CAST(chapter_number AS DECIMAL(10,2)) ASC 
                    LIMIT 1';
        
        $prevSql = 'SELECT chapter_number FROM chapters 
                    WHERE content_id = :content_id 
                      AND CAST(chapter_number AS DECIMAL(10,2)) < CAST(:current AS DECIMAL(10,2))
                      AND deleted_at IS NULL
                    ORDER BY CAST(chapter_number AS DECIMAL(10,2)) DESC 
                    LIMIT 1';

        $nextStmt = $this->pdo->prepare($nextSql);
        $nextStmt->execute(['content_id' => $contentId, 'current' => $currentNumber]);
        $next = $nextStmt->fetchColumn();

        $prevStmt = $this->pdo->prepare($prevSql);
        $prevStmt->execute(['content_id' => $contentId, 'current' => $currentNumber]);
        $prev = $prevStmt->fetchColumn();

        return [
            'next' => $next !== false ? (string) $next : null,
            'prev' => $prev !== false ? (string) $prev : null
        ];
    }

    /**
     * Total chapter count for a series.
     */
    public function countByContentId(string $contentId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM chapters WHERE content_id = :content_id AND deleted_at IS NULL');
        $stmt->execute(['content_id' => $contentId]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteChapter(string $chapterId): void
    {
        $this->pdo->prepare('UPDATE chapters SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $chapterId]);
    }

    /**
     * Updates core chapter fields.
     */
    public function updateChapter(string $chapterId, string $chapterNumber, ?string $title, string $type): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE chapters
             SET chapter_number = :chapter_number, title = :title, type = :type
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $chapterId,
            'chapter_number' => $chapterNumber,
            'title' => $title,
            'type' => $type,
        ]);
    }

    public function existsChapterNumberForContent(string $contentId, string $chapterNumber, string $excludeChapterId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM chapters
             WHERE content_id = :content_id
               AND chapter_number = :chapter_number
               AND deleted_at IS NULL
               AND id <> :exclude_id
             LIMIT 1'
        );
        $stmt->execute([
            'content_id' => $contentId,
            'chapter_number' => $chapterNumber,
            'exclude_id' => $excludeChapterId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Records that a chapter was opened without falsely marking it complete.
     * Explicit progress updates are handled by UserRepository::recordHistory().
     */
    public function markRead(string $userId, string $chapterId): void
    {
        // 1. Identify the series and chapter number
        $contentStmt = $this->pdo->prepare('SELECT content_id, COALESCE(number, CAST(chapter_number AS DECIMAL(8,2)), 0.00) AS chapter_num FROM chapters WHERE id = :chapter_id');
        $contentStmt->execute(['chapter_id' => $chapterId]);
        $row = $contentStmt->fetch();
        $contentId = $row !== false ? (string) $row['content_id'] : null;
        $chapterNum = $row !== false ? (float) ($row['chapter_num'] ?? 0.00) : 0.00;

        // 2. Log individual chapter read history
        $this->pdo->prepare(
            'INSERT INTO user_chapters_reads (user_id, chapter_id, content_id, read_at)
             VALUES (:user_id, :chapter_id, :content_id, NOW())
             ON DUPLICATE KEY UPDATE read_at = NOW(), content_id = VALUES(content_id)'
        )->execute(['user_id' => $userId, 'chapter_id' => $chapterId, 'content_id' => $contentId]);

        if ($contentId) {
            // 3. Update or Insert overall series reading progress
            $this->pdo->prepare(
                'INSERT INTO user_reading_progress (user_id, series_id, last_chapter_id, updated_at)
                 VALUES (:user_id, :series_id, :chapter_id, NOW())
                 ON DUPLICATE KEY UPDATE last_chapter_id = VALUES(last_chapter_id), updated_at = NOW()'
            )->execute(['user_id' => $userId, 'series_id' => $contentId, 'chapter_id' => $chapterId]);

            // 4. Create history on first open, preserving any progress already saved.
            try {
                $this->pdo->prepare(
                    'INSERT INTO user_reading_history (user_id, content_id, chapter_id, chapter_number, progress_pct, is_completed, last_read_at)
                     VALUES (:user_id, :content_id, :chapter_id, :chapter_number, 0, 0, NOW())
                     ON DUPLICATE KEY UPDATE chapter_number = VALUES(chapter_number), read_count = read_count + 1, last_read_at = NOW()'
                )->execute([
                    'user_id' => $userId,
                    'content_id' => $contentId,
                    'chapter_id' => $chapterId,
                    'chapter_number' => $chapterNum,
                ]);
            } catch (\Throwable) {}
        }
    }

    /**
     * Retrieves the last read chapter for a specific user in a series.
     */
    public function findReadingProgress(string $userId, string $seriesId): ?array
    {
        $sql = 'SELECT ch.id, ch.chapter_number, ch.title
                FROM user_reading_progress p
                INNER JOIN chapters ch ON ch.id = p.last_chapter_id
                WHERE p.user_id = :user_id AND p.series_id = :series_id
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'series_id' => $seriesId]);
        $res = $stmt->fetch();
        return $res === false ? null : $res;
    }

    /**
     * Increments the total and daily view count for a chapter.
     */
    public function recordChapterView(string $chapterId, string $ipHash): void
    {
        try {
            $sql = 'INSERT INTO analytics_chapters_views (chapter_id, ip_hash, viewed_at) VALUES (:chapter_id, :ip_hash, NOW())';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'chapter_id' => $chapterId,
                'ip_hash' => $ipHash,
            ]);

            $daily = $this->pdo->prepare(
                'INSERT INTO analytics_chapters_daily (chapter_id, stat_date, view_count, comment_count)
                 VALUES (:chapter_id, CURRENT_DATE(), 1, 0)
                 ON DUPLICATE KEY UPDATE view_count = view_count + 1'
            );
            $daily->execute(['chapter_id' => $chapterId]);
        } catch (\Throwable) {
            // Non-blocking for reader execution
        }
    }

    /**
     * Increments the daily comment counter for stats.
     */
    public function incrementDailyCommentCount(string $chapterId): void
    {
        try {
            $daily = $this->pdo->prepare(
                'INSERT INTO analytics_chapters_daily (chapter_id, stat_date, view_count, comment_count)
                 VALUES (:chapter_id, CURRENT_DATE(), 0, 1)
                 ON DUPLICATE KEY UPDATE comment_count = comment_count + 1'
            );
            $daily->execute(['chapter_id' => $chapterId]);
        } catch (\Throwable) {
            // Non-blocking
        }
    }


}
