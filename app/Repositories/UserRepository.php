<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for User-related database operations.
 *
 * Handles raw SQL interactions for user authentication, profiles, 
 * reading history, site preferences, and social notifications.
 *
 * @package App\Repositories
 */
final class UserRepository
{
    /** @var bool|null Cache for checking existence of 'lang' column in preferences table. */
    private ?bool $userPreferencesHasLang = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return PDO Internal database connection.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Locates a user by their email address.
     *
     * @param string $email
     * @return array|null User record or null if not found.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, username, email, password_hash, bio, created_at FROM users WHERE email = :email LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Locates a user by their unique username.
     *
     * @param string $username
     * @return array|null User record or null if not found.
     */
    public function findByUsername(string $username): ?array
    {
        $sql = 'SELECT id, username, email, password_hash, bio, created_at FROM users WHERE username = :username LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Creates a new user entry in the database.
     *
     * @param string $id 8-character entity ID.
     * @param string $username
     * @param string $email
     * @param string $passwordHash Pre-hashed password.
     * @return string The assigned user ID.
     */
    public function create(string $id, string $username, string $email, string $passwordHash): string
    {
        $sql = 'INSERT INTO users (id, username, email, password_hash) VALUES (:id, :username, :email, :password_hash)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        return $id;
    }

    /**
     * Finds a user by their primary ID.
     *
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT id, username, email, bio, profile_image, cover_image, created_at FROM users WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Finds a user by either ID or username. Used for public routing.
     *
     * @param string $person ID or Username.
     * @return array|null
     */
    public function findPublicByPerson(string $person): ?array
    {
        $sql = 'SELECT id, username, bio, profile_image, cover_image, created_at
                FROM users
                WHERE id = :person_id OR username = :person_username
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'person_id' => $person,
            'person_username' => $person,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Retrieves paginated reading history for a user.
     *
     * @param string $userId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getHistory(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT 
                    ucr.chapter_id,
                    ucr.read_at,
                    c.chapter_number,
                    c.title AS chapter_title,
                    ct.slug AS content_slug,
                    ct.title AS content_title
                FROM user_chapters_reads ucr
                INNER JOIN chapters c ON c.id = ucr.chapter_id
                INNER JOIN series ct ON ct.id = c.content_id
                WHERE ucr.user_id = :user_id
                ORDER BY ucr.read_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Fetches stored site and reader preferences for a user.
     *
     * @param string $userId
     * @return array|null
     */
    public function getPreferencesByUserId(string $userId): ?array
    {
        $selectLang = $this->hasUserPreferencesLangColumn() ? "lang,\n" : '';
        $sql = 'SELECT
                    user_id,
                    ' . $selectLang . '
                    theme,
                    reader_layout,
                    reader_font_size,
                    reader_font_family,
                    reader_line_height,
                    reader_font_weight,
                    reader_reading_direction,
                    reader_image_fit,
                    updated_at
                FROM user_preferences
                WHERE user_id = :user_id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Updates or inserts (upserts) user preferences.
     *
     * Handles schema variations (presence of 'lang' column).
     */
    public function upsertPreferences(
        string $userId,
        string $lang,
        string $theme,
        string $layout,
        int $fontSize,
        string $fontFamily,
        string $lineHeight,
        int $fontWeight,
        string $readingDirection,
        string $imageFit
    ): void {
        if ($this->hasUserPreferencesLangColumn()) {
            $sql = 'INSERT INTO user_preferences (
                        user_id,
                        lang,
                        theme,
                        reader_layout,
                        reader_font_size,
                        reader_font_family,
                        reader_line_height,
                        reader_font_weight,
                        reader_reading_direction,
                        reader_image_fit
                    ) VALUES (
                        :user_id,
                        :lang,
                        :theme,
                        :reader_layout,
                        :reader_font_size,
                        :reader_font_family,
                        :reader_line_height,
                        :reader_font_weight,
                        :reader_reading_direction,
                        :reader_image_fit
                    )
                    ON DUPLICATE KEY UPDATE
                        lang = VALUES(lang),
                        theme = VALUES(theme),
                        reader_layout = VALUES(reader_layout),
                        reader_font_size = VALUES(reader_font_size),
                        reader_font_family = VALUES(reader_font_family),
                        reader_line_height = VALUES(reader_line_height),
                        reader_font_weight = VALUES(reader_font_weight),
                        reader_reading_direction = VALUES(reader_reading_direction),
                        reader_image_fit = VALUES(reader_image_fit),
                        updated_at = NOW()';
        } else {
            $sql = 'INSERT INTO user_preferences (
                        user_id,
                        theme,
                        reader_layout,
                        reader_font_size,
                        reader_font_family,
                        reader_line_height,
                        reader_font_weight,
                        reader_reading_direction,
                        reader_image_fit
                    ) VALUES (
                        :user_id,
                        :theme,
                        :reader_layout,
                        :reader_font_size,
                        :reader_font_family,
                        :reader_line_height,
                        :reader_font_weight,
                        :reader_reading_direction,
                        :reader_image_fit
                    )
                    ON DUPLICATE KEY UPDATE
                        theme = VALUES(theme),
                        reader_layout = VALUES(reader_layout),
                        reader_font_size = VALUES(reader_font_size),
                        reader_font_family = VALUES(reader_font_family),
                        reader_line_height = VALUES(reader_line_height),
                        reader_font_weight = VALUES(reader_font_weight),
                        reader_reading_direction = VALUES(reader_reading_direction),
                        reader_image_fit = VALUES(reader_image_fit),
                        updated_at = NOW()';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = [
            'user_id' => $userId,
            'theme' => $theme,
            'reader_layout' => $layout,
            'reader_font_size' => $fontSize,
            'reader_font_family' => $fontFamily,
            'reader_line_height' => $lineHeight,
            'reader_font_weight' => $fontWeight,
            'reader_reading_direction' => $readingDirection,
            'reader_image_fit' => $imageFit,
        ];
        if ($this->hasUserPreferencesLangColumn()) {
            $params['lang'] = $lang;
        }
        $stmt->execute($params);
    }

    /**
     * Detects if the 'user_preferences' table has a 'lang' column.
     */
    private function hasUserPreferencesLangColumn(): bool
    {
        if ($this->userPreferencesHasLang !== null) {
            return $this->userPreferencesHasLang;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                   AND column_name = :column_name
                 LIMIT 1'
            );
            $stmt->execute([
                'table_name' => 'user_preferences',
                'column_name' => 'lang',
            ]);

            $this->userPreferencesHasLang = $stmt->fetchColumn() !== false;
            return $this->userPreferencesHasLang;
        } catch (\Throwable) {
            $this->userPreferencesHasLang = false;
            return false;
        }
    }

    /**
     * Lists approved blog posts written by a user.
     */
    public function listApprovedBlogsByUser(string $userId, int $page = 1, int $perPage = 5): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT id, title, slug, approved_at, created_at
                FROM blogs
                WHERE user_id = :user_id AND approved = 1
                ORDER BY approved_at DESC, created_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lists latest comments written by a user.
     */
    public function listRecentCommentsByUser(string $userId, int $page = 1, int $perPage = 10): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    c.id,
                    c.body,
                    c.created_at,
                    c.chapter_id,
                    c.content_id,
                    c.upvote_count,
                    c.downvote_count,
                    ch.chapter_number,
                    ct.slug AS content_slug,
                    ct.type AS content_type
                FROM social_comments c
                LEFT JOIN chapters ch ON ch.id = c.chapter_id
                LEFT JOIN series ct ON ct.id = COALESCE(c.content_id, ch.content_id)
                WHERE c.user_id = :user_id
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
     * Retrieves high-level reputation and activity stats for a user.
     *
     * @param string $userId
     * @return array [votes_cast, upvotes_received, downvotes_received, blog_count, comment_count]
     */
    public function getPublicStatsByUser(string $userId): array
    {
        $sql = 'SELECT
                    COALESCE(uvs.votes_cast, 0) AS votes_cast,
                    COALESCE(uvs.upvotes_received, 0) AS upvotes_received,
                    COALESCE(uvs.downvotes_received, 0) AS downvotes_received,
                    (SELECT COUNT(*) FROM blogs b WHERE b.user_id = :user_id2 AND b.approved = 1) AS approved_blog_count,
                    (SELECT COUNT(*) FROM social_comments c WHERE c.user_id = :user_id3) AS comment_count
                FROM users u
                LEFT JOIN analytics_users_votes uvs ON uvs.user_id = u.id
                WHERE u.id = :user_id
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'user_id2' => $userId,
            'user_id3' => $userId,
        ]);
        $row = $stmt->fetch();

        if ($row === false) {
            return [
                'votes_cast' => 0,
                'upvotes_received' => 0,
                'downvotes_received' => 0,
                'approved_blog_count' => 0,
                'comment_count' => 0,
            ];
        }

        return $row;
    }

    /**
     * Updates user bio and image URLs.
     */
    public function updatePublicProfile(string $userId, ?string $bio, ?string $profileImage, ?string $coverImage): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET bio = :bio,
                 profile_image = :profile_image,
                 cover_image = :cover_image
             WHERE id = :id'
        );
        $stmt->execute([
            'bio' => $bio,
            'profile_image' => $profileImage,
            'cover_image' => $coverImage,
            'id' => $userId,
        ]);
    }

    /**
     * Lists notifications for a user.
     */
    public function listNotifications(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    n.id,
                    n.type,
                    n.title,
                    n.body,
                    n.`data`,
                    n.is_read,
                    n.created_at,
                    n.actor_user_id,
                    u.username AS actor_username
                FROM user_notifications n
                LEFT JOIN users u ON u.id = n.actor_user_id
                WHERE n.user_id = :user_id
                ORDER BY n.created_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Batch marks all unread notifications as read.
     */
    public function markNotificationsRead(string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_notifications
             SET is_read = 1, read_at = NOW()
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Lists users that the current user is following.
     */
    public function listFollowedUsers(string $userId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT
                    u.id,
                    u.username,
                    u.bio,
                    u.profile_image,
                    u.cover_image,
                    u.created_at
                FROM user_follows f
                INNER JOIN users u ON u.id = f.followed_id
                WHERE f.follower_id = :user_id
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Inserts a follow record. IGNORE prevents errors on duplicate.
     *
     * @return bool True if a new follow was created.
     */
    public function followUser(string $followerId, string $followingId): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO user_follows (follower_id, followed_id)
             VALUES (:follower_id, :followed_id)'
        );
        $stmt->execute([
            'follower_id' => $followerId,
            'followed_id' => $followingId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Deletes a follow record.
     */
    public function unfollowUser(string $followerId, string $followingId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_follows
             WHERE follower_id = :follower_id AND followed_id = :followed_id'
        );
        $stmt->execute([
            'follower_id' => $followerId,
            'followed_id' => $followingId,
        ]);
    }

    /**
     * Sends or updates a notification when someone follows a user.
     */
    public function upsertUserFollowNotification(string $targetUserId, string $actorUserId): void
    {
        $data = json_encode([
            'source' => 'user_follow',
            'actor_user_id' => $actorUserId,
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_notifications
             WHERE user_id = :user_id
               AND actor_user_id = :actor_user_id
               AND type = :type
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'type' => 'user_follow',
        ]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            $update = $this->pdo->prepare(
                'UPDATE user_notifications
                 SET title = :title, body = :body, `data` = :data, is_read = 0, created_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'title' => 'Yeni takipci',
                'body' => 'Bir kullanici seni takip etti.',
                'data' => $data,
                'id' => (int) $existing['id'],
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO user_notifications (user_id, actor_user_id, type, title, body, `data`, is_read, created_at)
             VALUES (:user_id, :actor_user_id, :type, :title, :body, :data, 0, NOW())'
        );
        $insert->execute([
            'user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'type' => 'user_follow',
            'title' => 'Yeni takipci',
            'body' => 'Bir kullanici seni takip etti.',
            'data' => $data,
        ]);
    }

    /**
     * Removes follow notifications (e.g., when unfollowing).
     */
    public function removeUserFollowNotification(string $targetUserId, string $actorUserId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_notifications
             WHERE user_id = :user_id
               AND actor_user_id = :actor_user_id
               AND type = :type'
        );
        $stmt->execute([
            'user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'type' => 'user_follow',
        ]);
    }

    /**
     * Checks if a user is currently banned via moderation logs.
     */
    public function isBanned(string $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1
                 FROM admin_actions
                 WHERE target_type = 'user'
                   AND action = 'ban'
                   AND (
                        target_id = :target_id
                        OR target_id = (
                            SELECT username FROM users WHERE id = :target_id LIMIT 1
                        )
                   )
                 LIMIT 1"
            );
            $stmt->execute(['target_id' => $userId]);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
