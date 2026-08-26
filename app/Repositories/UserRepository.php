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
    public function getPreferences(string $userId): ?array
    {
        try {
            $userStmt = $this->pdo->prepare('SELECT settings FROM users WHERE id = :user_id LIMIT 1');
            $userStmt->execute(['user_id' => $userId]);
            $settingsRaw = $userStmt->fetchColumn();
            if ($settingsRaw && ($decoded = json_decode((string) $settingsRaw, true))) {
                return [
                    'user_id' => $userId,
                    'lang' => $decoded['lang'] ?? 'tr',
                    'theme' => $decoded['theme'] ?? 'dark',
                    'reader_layout' => $decoded['reader']['layout'] ?? ($decoded['reader_layout'] ?? 'single'),
                    'reader_font_size' => (int) ($decoded['reader']['fontSize'] ?? ($decoded['reader_font_size'] ?? 18)),
                    'reader_font_family' => $decoded['reader']['fontFamily'] ?? ($decoded['reader_font_family'] ?? 'Inter'),
                    'reader_line_height' => (string) ($decoded['reader']['lineHeight'] ?? ($decoded['reader_line_height'] ?? '1.6')),
                    'reader_font_weight' => (int) ($decoded['reader']['fontWeight'] ?? ($decoded['reader_font_weight'] ?? 400)),
                    'reader_reading_direction' => $decoded['reader']['readingDirection'] ?? ($decoded['reader_reading_direction'] ?? 'ltr'),
                    'reader_image_fit' => $decoded['reader']['imageFit'] ?? ($decoded['reader_image_fit'] ?? 'contain'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        } catch (\Throwable) {}

        return null;
    }

    public function getPreferencesByUserId(string $userId): ?array
    {
        return $this->getPreferences($userId);
    }

    /**
     * Updates or inserts (upserts) user preferences.
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
        $settingsJson = json_encode([
            'lang' => $lang,
            'theme' => $theme,
            'reader' => [
                'layout' => $layout,
                'fontSize' => $fontSize,
                'fontFamily' => $fontFamily,
                'lineHeight' => $lineHeight,
                'fontWeight' => $fontWeight,
                'readingDirection' => $readingDirection,
                'imageFit' => $imageFit,
            ],
            'reader_layout' => $layout,
            'reader_font_size' => $fontSize,
            'reader_font_family' => $fontFamily,
            'reader_line_height' => $lineHeight,
            'reader_font_weight' => $fontWeight,
            'reader_reading_direction' => $readingDirection,
            'reader_image_fit' => $imageFit,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->pdo->prepare(
            'UPDATE users SET settings = :settings WHERE id = :user_id'
        )->execute([
            'settings' => $settingsJson,
            'user_id' => $userId,
        ]);
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
                    (SELECT COUNT(*) FROM comment_votes cv WHERE cv.user_id = :user_id1) AS votes_cast,
                    (SELECT COALESCE(SUM(c1.upvote_count), 0) FROM social_comments c1 WHERE c1.user_id = :user_id2) AS upvotes_received,
                    (SELECT COALESCE(SUM(c2.downvote_count), 0) FROM social_comments c2 WHERE c2.user_id = :user_id3) AS downvotes_received,
                    (SELECT COUNT(*) FROM blogs b WHERE b.user_id = :user_id4 AND b.approved = 1) AS approved_blog_count,
                    (SELECT COUNT(*) FROM social_comments c3 WHERE c3.user_id = :user_id5) AS comment_count
                FROM users u
                WHERE u.id = :user_id
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'user_id1' => $userId,
            'user_id2' => $userId,
            'user_id3' => $userId,
            'user_id4' => $userId,
            'user_id5' => $userId,
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
    public function listNotifications(string $userId, int $page, int $perPage, ?string $cursorCreatedAt = null, ?int $cursorId = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $whereParts = ['n.user_id = :user_id'];
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $whereParts[] = '(n.created_at < :cursor_created OR (n.created_at = :cursor_created AND n.id < :cursor_id))';
        }
        $where = implode(' AND ', $whereParts);
        $sql = 'SELECT
                    n.id,
                    COALESCE(e.type, n.type) AS type,
                    COALESCE(e.title, n.title) AS title,
                    COALESCE(e.body, n.body) AS body,
                    COALESCE(e.`data`, n.`data`) AS `data`,
                    n.is_read,
                    n.created_at,
                    COALESCE(e.actor_user_id, n.actor_user_id) AS actor_user_id,
                    u.username AS actor_username
                FROM user_notifications n
                LEFT JOIN notification_events e ON e.id = n.event_id
                LEFT JOIN users u ON u.id = COALESCE(e.actor_user_id, n.actor_user_id)
                WHERE ' . $where . '
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT :limit' . ($cursorCreatedAt !== null && $cursorId !== null ? '' : ' OFFSET :offset');
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        if ($cursorCreatedAt !== null && $cursorId !== null) {
            $stmt->bindValue(':cursor_created', $cursorCreatedAt, PDO::PARAM_STR);
            $stmt->bindValue(':cursor_id', $cursorId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        if ($cursorCreatedAt === null || $cursorId === null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
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
