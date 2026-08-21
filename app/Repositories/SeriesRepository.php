<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for Content (Series) database operations.
 *
 * Handles raw SQL interactions for novels, manga, chapters, and taxonomy.
 * Implements full-text search, pagination, and statistical tracking.
 *
 * @package App\Repositories
 */
final class SeriesRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Fetches popular series for the homepage based on rating and activity.
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getHomepagePopular(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist
                FROM series c
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE c.deleted_at IS NULL
                ORDER BY c.rating_count DESC, c.rating_avg DESC, c.chapter_count DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Gets series that recently received new chapters.
     */
    public function getRecentlyUpdated(int $limit): array
    {
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist,
                    MAX(ch.created_at) as last_chapter_at
                FROM series c
                INNER JOIN chapters ch ON ch.content_id = c.id
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE c.deleted_at IS NULL AND ch.deleted_at IS NULL
                GROUP BY c.id
                ORDER BY last_chapter_at DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Gets the most recently created content entries.
     */
    public function getRecentlyAdded(int $limit): array
    {
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist
                FROM series c
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE c.deleted_at IS NULL
                ORDER BY c.created_at DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Retrieves detailed series data including metadata and joined series_genres/series_tags.
     *
     * @param string $slug
     * @return array|null
     */
    public function findContentBySlug(string $slug): ?array
    {
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.description,
                    cm.alternative_titles,
                    c.type,
                    c.status,
                    c.cover_image,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.created_at,
                    cm.author,
                    cm.artist,
                    cm.country,
                    cm.release_year,
                    GROUP_CONCAT(DISTINCT CONCAT(g.name, "::", g.slug, "::", COALESCE(g.ui_config, "{}")) ORDER BY g.name SEPARATOR "||") AS series_genres_raw,
                    GROUP_CONCAT(DISTINCT CONCAT(t.name, "::", t.slug, "::", COALESCE(t.ui_config, "{}")) ORDER BY t.name SEPARATOR "||") AS series_tags_raw
                FROM series c
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                LEFT JOIN series_genre_map cg ON cg.content_id = c.id
                LEFT JOIN series_genres g ON g.id = cg.genre_id
                LEFT JOIN series_tag_map ct ON ct.content_id = c.id
                LEFT JOIN series_tags t ON t.id = ct.tag_id
                WHERE c.slug = :slug AND c.deleted_at IS NULL
                GROUP BY c.id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Finds content by both type and slug, optionally checking if a user follows it.
     */
    public function findContentByTypeAndSlug(string $type, string $slug, ?string $userId = null): ?array
    {
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.description,
                    cm.alternative_titles,
                    c.type,
                    c.status,
                    c.cover_image,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.created_at,
                    cm.author,
                    cm.artist,
                    cm.country,
                    cm.release_year,
                    GROUP_CONCAT(DISTINCT CONCAT(g.name, "::", g.slug, "::", COALESCE(g.ui_config, "{}")) ORDER BY g.name SEPARATOR "||") AS series_genres_raw,
                    GROUP_CONCAT(DISTINCT CONCAT(t.name, "::", t.slug, "::", COALESCE(t.ui_config, "{}")) ORDER BY t.name SEPARATOR "||") AS series_tags_raw,
                    (CASE WHEN ucf.user_id IS NOT NULL THEN 1 ELSE 0 END) AS is_followed
                FROM series c
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                LEFT JOIN series_genre_map cg ON cg.content_id = c.id
                LEFT JOIN series_genres g ON g.id = cg.genre_id
                LEFT JOIN series_tag_map ct ON ct.content_id = c.id
                LEFT JOIN series_tags t ON t.id = ct.tag_id
                LEFT JOIN user_series_follows ucf ON ucf.content_id = c.id AND ucf.user_id = :user_id
                WHERE c.type = :type AND c.slug = :slug AND c.deleted_at IS NULL
                GROUP BY c.id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'type' => $type,
            'slug' => $slug,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lists chapters for a series identified by slug.
     */
    public function getChaptersByContentSlug(string $slug, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    ch.id,
                    ch.content_id,
                    ch.chapter_number,
                    ch.title,
                    ch.type,
                    ch.created_at
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE c.slug = :slug AND c.deleted_at IS NULL
                ORDER BY ch.chapter_number DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists chapters for a series identified by type and slug.
     */
    public function getChaptersByTypeAndSlug(string $type, string $slug, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    ch.id,
                    ch.content_id,
                    ch.chapter_number,
                    ch.title,
                    ch.type,
                    ch.created_at
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE c.type = :type AND c.deleted_at IS NULL AND c.slug = :slug
                ORDER BY ch.chapter_number DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Finds the very first chapter (usually 1.00) for a series.
     */
    public function findFirstChapterNumberByTypeAndSlug(string $type, string $slug): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT ch.chapter_number
             FROM chapters ch
             INNER JOIN series c ON c.id = ch.content_id
             WHERE c.type = :type AND c.deleted_at IS NULL AND c.slug = :slug
             ORDER BY CAST(ch.chapter_number AS DECIMAL(10,2)) ASC, ch.id ASC
             LIMIT 1'
        );
        $stmt->execute([
            'type' => $type,
            'slug' => $slug,
        ]);

        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    /**
     * Paginated listing of content by type (e.g., all manga).
     */
    public function getByType(string $type, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist
                FROM series c
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE c.type = :type AND c.deleted_at IS NULL
                ORDER BY c.rating_count DESC, c.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Paginated listing of content associated with a genre.
     */
    public function getByGenreSlug(string $slug, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist
                FROM series c
                INNER JOIN series_genre_map cg ON cg.content_id = c.id
                INNER JOIN series_genres g ON g.id = cg.genre_id
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE g.slug = :slug AND c.deleted_at IS NULL
                ORDER BY c.rating_count DESC, c.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Paginated listing of content associated with a tag.
     */
    public function getByTagSlug(string $slug, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist
                FROM series c
                INNER JOIN series_tag_map ct ON ct.content_id = c.id
                INNER JOIN series_tags t ON t.id = ct.tag_id
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE t.slug = :slug AND c.deleted_at IS NULL
                ORDER BY c.rating_count DESC, c.created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Performs a lightweight search for autocomplete suggestions.
     * Returns minimal data to ensure high performance.
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $sql = 'SELECT 
                    id, 
                    title, 
                    slug, 
                    type, 
                    cover_image 
                FROM series 
                WHERE title LIKE :q OR slug LIKE :q 
                ORDER BY rating_count DESC 
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':q', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Searches content with advanced filters.
     *
     * @param string $query Search term.
     * @param int $page
     * @param int $perPage
     * @param array $filters Optional filters: genres (slug array), tags (slug array), status, sort.
     * @return array
     */
    public function search(string $query, int $page, int $perPage, array $filters = []): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        
        $params = [];
        $where = ['c.deleted_at IS NULL'];
        $selectRelevance = '';
        $useFullText = false;

        if ($query !== '') {
            if ($this->canUseFullText($query)) {
                $useFullText = true;
                $booleanQuery = $this->toBooleanSearchQuery($query);
                $where[] = '(MATCH(c.title, c.slug, c.description) AGAINST (:q IN BOOLEAN MODE)
                    OR MATCH(cm.author, cm.artist, cm.alternative_titles) AGAINST (:q IN BOOLEAN MODE))';
                $params['q'] = $booleanQuery;
                $selectRelevance = ',
                    (COALESCE(MATCH(c.title, c.slug, c.description) AGAINST (:q IN BOOLEAN MODE), 0)
                        + COALESCE(MATCH(cm.author, cm.artist, cm.alternative_titles) AGAINST (:q IN BOOLEAN MODE), 0)) AS relevance';
            } else {
                $searchParam = '%' . $query . '%';
                $where[] = '(c.title LIKE :q1 OR c.slug LIKE :q2 OR c.description LIKE :q3 OR cm.author LIKE :q4 OR cm.artist LIKE :q5)';
                $params['q1'] = $searchParam;
                $params['q2'] = $searchParam;
                $params['q3'] = $searchParam;
                $params['q4'] = $searchParam;
                $params['q5'] = $searchParam;
            }
        }

        if (!empty($filters['genres'])) {
            $genreList = $filters['genres'];
            $placeholders = [];
            foreach ($genreList as $i => $slug) {
                $key = 'genre' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $slug;
            }
            $where[] = 'c.id IN (SELECT content_id FROM series_genre_map WHERE genre_id IN (SELECT id FROM series_genres WHERE slug IN (' . implode(',', $placeholders) . ')))';
        }

        if (!empty($filters['tags'])) {
            $tagList = $filters['tags'];
            $placeholders = [];
            foreach ($tagList as $i => $slug) {
                $key = 'tag' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $slug;
            }
            $where[] = 'c.id IN (SELECT content_id FROM series_tag_map WHERE tag_id IN (SELECT id FROM series_tags WHERE slug IN (' . implode(',', $placeholders) . ')))';
        }

        if (!empty($filters['status']) && $filters['status'] !== 'TÜMÜ') {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        $orderBy = 'c.rating_count DESC, c.created_at DESC';
        if (!empty($filters['sort'])) {
            $orderBy = match ($filters['sort']) {
                'EN YENİLER' => 'c.created_at DESC',
                'EN ÇOK OKUNAN' => 'c.rating_count DESC', // Fallback to rating_count
                'EN YÜKSEK PUAN' => 'c.rating_avg DESC',
                default => 'c.rating_count DESC',
            };
        } elseif ($useFullText) {
            $orderBy = 'relevance DESC, c.rating_count DESC, c.created_at DESC';
        }

        $sql = 'SELECT
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.cover_image,
                    cm.author,
                    cm.artist' . $selectRelevance . '
                FROM series c
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $orderBy . '
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Converts a raw query into a MySQL Boolean Mode string (+token*).
     */
    private function toBooleanSearchQuery(string $query): string
    {
        $tokens = preg_split('/\s+/', trim($query)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return $query;
        }

        $parts = array_map(static function (string $token): string {
            $clean = preg_replace('/[^\pL\pN]+/u', '', $token);
            if ($clean === null || $clean === '') {
                return '';
            }
            return '+' . $clean . '*';
        }, $tokens);
        $parts = array_values(array_filter($parts, static fn (string $t): bool => $t !== ''));
        if ($parts === []) {
            return $query;
        }
        return implode(' ', $parts);
    }

    private function canUseFullText(string $query): bool
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return false;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($trimmed) : strlen($trimmed);
        return $length >= 3;
    }

    /**
     * Lists all series_genres alphabetically.
     */
    public function getGenres(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT id, name, slug, ui_config FROM series_genres ORDER BY name ASC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lists all series_tags with their content count.
     */
    public function getTags(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    t.id,
                    t.name,
                    t.slug,
                    t.ui_config,
                    COUNT(ct.content_id) AS content_count
                FROM series_tags t
                LEFT JOIN series_tag_map ct ON ct.tag_id = t.id
                GROUP BY t.id, t.name, t.slug
                ORDER BY t.name ASC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Finds the 8-char primary ID by slug.
     */
    public function findContentIdBySlug(string $slug): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM series WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : (string) $row['id'];
    }

    /**
     * Checks if a content ID exists.
     */
    public function existsContentId(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM series WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Finds content ID by type and slug.
     */
    public function findContentIdByTypeAndSlug(string $type, string $slug): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM series WHERE type = :type AND slug = :slug LIMIT 1');
        $stmt->execute([
            'type' => $type,
            'slug' => $slug,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : (string) $row['id'];
    }

    /**
     * Adds a record to user_series_follows.
     */
    public function followContent(string $userId, string $contentId): void
    {
        $sql = 'INSERT IGNORE INTO user_series_follows (user_id, content_id) VALUES (:user_id, :content_id)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
        ]);
    }

    /**
     * Removes a record from user_series_follows.
     */
    public function unfollowContent(string $userId, string $contentId): void
    {
        $sql = 'DELETE FROM user_series_follows WHERE user_id = :user_id AND content_id = :content_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
        ]);
    }

    /**
     * Lists all series followed by a specific user.
     */
    public function listFollowedContents(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    c.id,
                    c.title,
                    c.slug,
                    c.type,
                    c.status,
                    c.cover_image,
                    c.rating_avg,
                    c.rating_count,
                    c.chapter_count,
                    c.comment_count,
                    c.created_at,
                    cm.author,
                    cm.artist
                FROM user_series_follows ucf
                INNER JOIN series c ON c.id = ucf.content_id
                LEFT JOIN series_metadata cm ON cm.content_id = c.id
                WHERE ucf.user_id = :user_id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Updates counters when a comment is added.
     */
    public function incrementCommentCount(string $contentId): void
    {
        $stmt = $this->pdo->prepare('UPDATE series SET comment_count = comment_count + 1 WHERE id = :id');
        $stmt->execute(['id' => $contentId]);

        $daily = $this->pdo->prepare(
            'INSERT INTO analytics_series_daily (content_id, stat_date, view_count, comment_count)
             VALUES (:content_id, CURRENT_DATE(), 0, 1)
             ON DUPLICATE KEY UPDATE comment_count = comment_count + 1'
        );
        $daily->execute(['content_id' => $contentId]);
    }

    /**
     * Records a view event and updates daily statistics.
     */
    public function recordContentView(string $contentId, string $ipHash): void
    {
        $sql = 'INSERT INTO analytics_series_views (content_id, ip_hash, viewed_at) VALUES (:content_id, :ip_hash, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'content_id' => $contentId,
            'ip_hash' => $ipHash,
        ]);

        $daily = $this->pdo->prepare(
            'INSERT INTO analytics_series_daily (content_id, stat_date, view_count, comment_count)
             VALUES (:content_id, CURRENT_DATE(), 1, 0)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1'
        );
        $daily->execute(['content_id' => $contentId]);
    }

    /**
     * Gets individual latest chapter entries across all series.
     */
    public function getLatestChapters(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    ch.chapter_number,
                    ch.title AS chapter_title,
                    c.title AS series_title,
                    c.slug AS series_slug,
                    c.type AS series_type,
                    c.cover_image,
                    ch.created_at
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                ORDER BY ch.created_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Gets latest chapters filtered by content type.
     */
    public function getLatestChaptersByType(string $type, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    ch.chapter_number,
                    ch.title AS chapter_title,
                    c.title AS series_title,
                    c.slug AS series_slug,
                    c.type AS series_type,
                    ch.created_at
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE c.type = :type AND c.deleted_at IS NULL
                ORDER BY ch.created_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Records a search query for analytics.
     */
    public function logSearchQuery(string $query, int $resultCount, ?string $userId, string $ipHash): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO analytics_search_logs (user_id, query, result_count, ip_hash, searched_at)
                 VALUES (:user_id, :query, :result_count, :ip_hash, NOW())'
            );
            $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':query', mb_substr($query, 0, 120));
            $stmt->bindValue(':result_count', $resultCount, PDO::PARAM_INT);
            $stmt->bindValue(':ip_hash', $ipHash, PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Throwable) {
            // analytics log should not break search endpoint.
        }
    }

    /**
     * Lists active series for sitemap generation.
     */
    public function listContentsForSitemap(int $limit = 50000): array
    {
        $sql = 'SELECT type, slug, created_at
                FROM series
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lists active chapters for sitemap generation.
     */
    public function listChaptersForSitemap(int $limit = 50000): array
    {
        $sql = 'SELECT 
                    c.type,
                    c.slug,
                    CAST(ch.chapter_number AS CHAR) AS chapter_number,
                    ch.created_at
                FROM chapters ch
                INNER JOIN series c ON c.id = ch.content_id
                WHERE ch.deleted_at IS NULL AND c.deleted_at IS NULL
                ORDER BY ch.created_at DESC
                LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
