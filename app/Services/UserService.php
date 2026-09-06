<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\OutputSanitizer;
use App\Helpers\CursorPagination;
use App\Helpers\Validator;
use App\Repositories\UserRepository;
use App\Services\UploadService;
use App\DTO\UploadDto;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Service for managing User profiles, preferences, and social interactions.
 *
 * This service handles retrieving and updating user-specific data, including
 * reading history, site preferences (theme, lang), public profiles,
 * notifications, and follower/following relationships.
 *
 * @package App\Services
 */
final class UserService
{
    private const ALLOWED_THEMES = ['default', 'dark', 'royal', 'bootstrap', 'material', 'apple', 'glass'];
    private const ALLOWED_LANGS = ['tr', 'en'];
    private const ALLOWED_LAYOUTS = ['vertical', 'single', 'double'];
    private const ALLOWED_FONT_FAMILIES = ['var(--font-sans)', 'serif', 'var(--font-mono)'];
    private const ALLOWED_FONT_WEIGHTS = [300, 400, 600];
    private const ALLOWED_DIRECTIONS = ['ltr', 'rtl'];
    private const ALLOWED_IMAGE_FITS = ['width', 'height', 'original'];
    
    /**
     * Weights used for calculating user reputation score.
     */
    private const SCORE_FORMULA = [
        'votes_cast' => 1,
        'upvotes_received' => 2,
        'downvotes_received' => -1,
        'approved_blog_count' => 5,
        'comment_count' => 1,
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly UploadService $uploadService,
        private readonly ContentSecurityScanner $scanner
    )
    {
    }

    /**
     * Retrieves basic profile data for a specific user.
     *
     * @param string $userId
     * @return array|null Sanitized profile data or null if not found.
     */
    public function profile(string $userId): ?array
    {
        $row = $this->users->findById($userId);
        if (!is_array($row)) {
            return null;
        }

        return OutputSanitizer::sanitizeFields($row, ['username', 'email', 'bio']);
    }

    /**
     * Gets paginated reading history for a user.
     *
     * @param string $userId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function history(string $userId, int $page, int $perPage): array
    {
        return array_map(static function (array $row): array {
            return $row + [
                'content' => [
                    'id' => (string) ($row['content_id'] ?? ''),
                    'type' => str_replace('_', '-', (string) ($row['content_type'] ?? 'manga')),
                    'slug' => (string) ($row['content_slug'] ?? ''),
                    'title' => (string) ($row['content_title'] ?? ''),
                    'cover' => (string) ($row['content_cover_image'] ?? ''),
                    'status' => (string) ($row['content_status'] ?? ''),
                    'rating' => (float) ($row['content_rating'] ?? 0),
                ],
                'chapter' => [
                    'id' => (string) ($row['chapter_id'] ?? ''),
                    'number' => (string) ($row['chapter_number'] ?? ''),
                    'title' => $row['chapter_title'] ?? null,
                ],
            ];
        }, $this->users->getHistory($userId, $page, $perPage));
    }

    public function recordHistory(string $userId, array $payload): void
    {
        $chapterId = trim((string) ($payload['chapterId'] ?? $payload['chapter_id'] ?? ''));
        if (!preg_match('/^[a-z0-9]{6}$/', $chapterId)) {
            throw new \InvalidArgumentException('Valid chapterId is required');
        }
        $this->users->recordHistory($userId, $chapterId, (int) ($payload['progress'] ?? 0));
    }

    public function deleteHistory(string $userId, string $historyId): bool
    {
        return $this->users->deleteHistory($userId, $historyId);
    }

    public function clearHistory(string $userId): void
    {
        $this->users->clearHistory($userId);
    }

    /**
     * Retrieves all site and reader preferences for a user.
     *
     * @param string $userId
     * @return array Nested preferences array including language, theme, and reader settings.
     * @throws \DomainException If user does not exist.
     */
    public function preferences(string $userId): array
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new \DomainException('User not found');
        }

        $row = $this->users->getPreferencesByUserId($userId);
        $lastSync = $row['updated_at'] ?? gmdate('Y-m-d H:i:s');

        return [
            'lang' => (string) ($row['lang'] ?? 'tr'),
            'theme' => (string) ($row['theme'] ?? 'default'),
            'reader' => [
                'layout' => (string) ($row['reader_layout'] ?? 'vertical'),
                'fontSize' => (string) ($row['reader_font_size'] ?? '18'),
                'fontFamily' => (string) ($row['reader_font_family'] ?? 'var(--font-sans)'),
                'lineHeight' => (string) ($row['reader_line_height'] ?? '1.8'),
                'fontWeight' => (string) ($row['reader_font_weight'] ?? '400'),
                'readingDirection' => (string) ($row['reader_reading_direction'] ?? 'ltr'),
                'imageFit' => (string) ($row['reader_image_fit'] ?? 'width'),
            ],
            'account' => [
                'is_logged_in' => true,
                'email' => (string) $user['email'],
                'last_sync' => gmdate('Y-m-d\\TH:i:s\\Z', strtotime((string) $lastSync)),
            ],
        ];
    }

    /**
     * Updates user preferences with validation.
     *
     * @param string $userId
     * @param array $payload Incoming preference data.
     * @return array Updated preferences.
     * @throws \InvalidArgumentException If any preference value is invalid.
     */
    public function updatePreferences(string $userId, array $payload): array
    {
        $current = $this->preferences($userId);

        $lang = strtolower(trim((string) ($payload['lang'] ?? ($payload['language']['locale'] ?? $current['lang']))));
        if (!in_array($lang, self::ALLOWED_LANGS, true)) {
            throw new \InvalidArgumentException('Invalid lang');
        }

        $theme = (string) ($payload['theme'] ?? $current['theme']);
        if (!in_array($theme, self::ALLOWED_THEMES, true)) {
            throw new \InvalidArgumentException('Invalid theme');
        }

        $reader = is_array($payload['reader'] ?? null) ? $payload['reader'] : [];

        $layout = (string) ($reader['layout'] ?? $current['reader']['layout']);
        if (!in_array($layout, self::ALLOWED_LAYOUTS, true)) {
            throw new \InvalidArgumentException('Invalid reader.layout');
        }

        $fontSize = (int) ($reader['fontSize'] ?? $current['reader']['fontSize']);
        if ($fontSize < 10 || $fontSize > 72) {
            throw new \InvalidArgumentException('Invalid reader.fontSize');
        }

        $fontFamily = (string) ($reader['fontFamily'] ?? $current['reader']['fontFamily']);
        if (!in_array($fontFamily, self::ALLOWED_FONT_FAMILIES, true)) {
            throw new \InvalidArgumentException('Invalid reader.fontFamily');
        }

        $lineHeight = (string) ($reader['lineHeight'] ?? $current['reader']['lineHeight']);
        $lineHeightFloat = (float) $lineHeight;
        if ($lineHeightFloat < 1.0 || $lineHeightFloat > 3.0) {
            throw new \InvalidArgumentException('Invalid reader.lineHeight');
        }

        $fontWeight = (int) ($reader['fontWeight'] ?? $current['reader']['fontWeight']);
        if (!in_array($fontWeight, self::ALLOWED_FONT_WEIGHTS, true)) {
            throw new \InvalidArgumentException('Invalid reader.fontWeight');
        }

        $readingDirection = (string) ($reader['readingDirection'] ?? $current['reader']['readingDirection']);
        if (!in_array($readingDirection, self::ALLOWED_DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Invalid reader.readingDirection');
        }

        $imageFit = (string) ($reader['imageFit'] ?? $current['reader']['imageFit']);
        if (!in_array($imageFit, self::ALLOWED_IMAGE_FITS, true)) {
            throw new \InvalidArgumentException('Invalid reader.imageFit');
        }

        $this->users->upsertPreferences(
            userId: $userId,
            lang: $lang,
            theme: $theme,
            layout: $layout,
            fontSize: $fontSize,
            fontFamily: $fontFamily,
            lineHeight: number_format($lineHeightFloat, 1, '.', ''),
            fontWeight: $fontWeight,
            readingDirection: $readingDirection,
            imageFit: $imageFit
        );

        return $this->preferences($userId);
    }

    /**
     * Aggregates data for a public profile view.
     *
     * Includes user stats, reputation score, approved blogs, recent comments,
     * and follower counts.
     *
     * @param string $person Username or ID of the user being viewed.
     * @param int $blogPage
     * @param int $blogPerPage
     * @param int $commentPage
     * @param int $commentPerPage
     * @return array|null Full public profile data or null.
     */
    public function publicProfile(
        string $person,
        int $blogPage = 1,
        int $blogPerPage = 5,
        int $commentPage = 1,
        int $commentPerPage = 10
    ): ?array
    {
        $user = $this->users->findPublicByPerson($person);
        if ($user === null) {
            return null;
        }

        $userId = (string) $user['id'];
        $blogs = $this->users->listApprovedBlogsByUser($userId, $blogPage, $blogPerPage);
        $comments = $this->users->listRecentCommentsByUser($userId, $commentPage, $commentPerPage);
        $stats = $this->users->getPublicStatsByUser($userId);

        // Add counts for followers and following
        $stmt = $this->users->getPdo()->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $stats['followers_count'] = (int)$stmt->fetchColumn();

        $stmt = $this->users->getPdo()->prepare('SELECT COUNT(*) FROM user_follows WHERE follower_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $stats['following_count'] = (int)$stmt->fetchColumn();

        $isFollowing = false;
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($currentUserId !== null && $currentUserId !== $userId) {
            $stmt = $this->users->getPdo()->prepare('SELECT 1 FROM user_follows WHERE follower_id = :fid AND followed_id = :tid LIMIT 1');
            $stmt->execute(['fid' => $currentUserId, 'tid' => $userId]);
            $isFollowing = $stmt->fetchColumn() !== false;
        }

        $weights = [
            'votes_cast' => self::SCORE_FORMULA['votes_cast'],
            'upvotes_received' => self::SCORE_FORMULA['upvotes_received'],
            'downvotes_received' => self::SCORE_FORMULA['downvotes_received'],
            'approved_blog_count' => self::SCORE_FORMULA['approved_blog_count'],
            'comment_count' => self::SCORE_FORMULA['comment_count'],
        ];

        $comments = OutputSanitizer::sanitizeRows($comments, ['body']);
        $blogs = OutputSanitizer::sanitizeRows($blogs, ['title']);

        $comments = array_map(function (array $row): array {
            $type = isset($row['content_type']) ? str_replace('_', '-', (string) $row['content_type']) : null;
            $slug = $row['content_slug'] ?? null;
            $chapterNumber = $row['chapter_number'] ?? null;

            $row['url_path'] = null;
            if ($type !== null && $slug !== null) {
                if ($chapterNumber !== null) {
                    $row['url_path'] = sprintf('/%s/%s/chapter/%s', $type, $slug, $chapterNumber);
                } else {
                    $row['url_path'] = sprintf('/%s/%s', $type, $slug);
                }
            }

            $row['score'] = (int) ($row['upvote_count'] ?? 0) - (int) ($row['downvote_count'] ?? 0);
            return $row;
        }, $comments);

        $score = (((int) $stats['votes_cast']) * $weights['votes_cast'])
            + (((int) $stats['upvotes_received']) * $weights['upvotes_received'])
            + (((int) $stats['downvotes_received']) * $weights['downvotes_received'])
            + (((int) $stats['approved_blog_count']) * $weights['approved_blog_count'])
            + (((int) $stats['comment_count']) * $weights['comment_count']);

        return [
            'user' => array_merge(
                OutputSanitizer::sanitizeFields($user, ['username', 'bio']),
                [
                    'avatar' => $user['profile_image'] ?? null,
                    'cover_image' => $user['cover_image'] ?? null,
                ]
            ),
            'is_following' => $isFollowing,
            'statistics' => [
                'score' => $score,
                'score_formula' => $weights,
                'votes_cast' => (int) $stats['votes_cast'],
                'upvotes_received' => (int) $stats['upvotes_received'],
                'downvotes_received' => (int) $stats['downvotes_received'],
                'approved_blog_count' => (int) $stats['approved_blog_count'],
                'comment_count' => (int) $stats['comment_count'],
                'followers_count' => (int) $stats['followers_count'],
                'following_count' => (int) $stats['following_count'],
            ],
            'blogs' => $blogs,
            'recent_comments' => $comments,
            'meta' => [
                'blogs' => ['page' => $blogPage, 'per_page' => $blogPerPage],
                'recent_comments' => ['page' => $commentPage, 'per_page' => $commentPerPage],
            ],
        ];
    }

    /**
     * Updates public profile details (bio, images).
     *
     * @param string $userId
     * @param array $payload
     * @return array|null Updated sanitized profile or null.
     * @throws \InvalidArgumentException If inputs are too long or invalid.
     */
    public function updateProfile(string $userId, array $payload): ?array
    {
        $current = $this->users->findById($userId);
        if ($current === null) {
            return null;
        }

        $bio = $this->scanner->assertSafe(Validator::sanitizeMultilineText((string) ($payload['bio'] ?? (string) ($current['bio'] ?? ''))), 'user_bio');
        if (strlen($bio) > 1000) {
            throw new \InvalidArgumentException('bio must be at most 1000 characters');
        }

        $profileImage = $payload['profile_image'] ?? null;
        if ($profileImage instanceof UploadedFileInterface) {
            if ($profileImage->getError() === UPLOAD_ERR_OK) {
                $profileImage = $this->uploadService->handleImageUpload(
                    new UploadDto($userId, $profileImage, 'users/profile')
                );
            } else {
                $profileImage = (string) ($current['profile_image'] ?? '');
            }
        } elseif (is_string($profileImage)) {
            $profileImage = trim($profileImage);
        } else {
            $profileImage = (string) ($current['profile_image'] ?? '');
        }

        $coverImage = $payload['cover_image'] ?? null;
        if ($coverImage instanceof UploadedFileInterface) {
            if ($coverImage->getError() === UPLOAD_ERR_OK) {
                $coverImage = $this->uploadService->handleImageUpload(
                    new UploadDto($userId, $coverImage, 'users/cover')
                );
            } else {
                $coverImage = (string) ($current['cover_image'] ?? '');
            }
        } elseif (is_string($coverImage)) {
            $coverImage = trim($coverImage);
        } else {
            $coverImage = (string) ($current['cover_image'] ?? '');
        }

        $profileImage = $profileImage === '' ? null : $profileImage;
        $coverImage = $coverImage === '' ? null : $coverImage;

        if ($profileImage !== null && strlen($profileImage) > 255) {
            throw new \InvalidArgumentException('profile_image is too long');
        }
        if ($coverImage !== null && strlen($coverImage) > 255) {
            throw new \InvalidArgumentException('cover_image is too long');
        }

        $this->users->updatePublicProfile($userId, $bio === '' ? null : $bio, $profileImage, $coverImage);

        $updated = $this->users->findById($userId);
        return $updated === null ? null : OutputSanitizer::sanitizeFields($updated, ['username', 'email', 'bio']);
    }

    /**
     * Gets notifications for a user.
     *
     * @param string $userId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function notifications(string $userId, int $page, int $perPage, ?string $cursor = null): array
    {
        try {
            $cursorData = CursorPagination::decode($cursor);
            if ($cursorData !== null) {
                [$cursorCreatedAt, $cursorId] = $cursorData;
                $rows = $this->users->listNotifications($userId, $page, $perPage, $cursorCreatedAt, $cursorId);
            } else {
                $rows = $this->users->listNotifications($userId, $page, $perPage);
            }
            if (!is_array($rows)) {
                return [];
            }
            return OutputSanitizer::sanitizeRows($rows, ['title', 'body', 'actor_username']);
        } catch (\Throwable $e) {
            // Log the error for diagnosis but don't crash the entire page load
            error_log('UserService::notifications error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Marks all notifications as read for a user.
     *
     * @param string $userId
     */
    public function markNotificationsRead(string $userId, ?int $notificationId = null): bool
    {
        if ($notificationId !== null) {
            return $this->users->markNotificationRead($userId, $notificationId);
        }
        $this->users->markNotificationsRead($userId);
        return true;
    }

    public function deleteNotification(string $userId, int $notificationId): bool
    {
        return $this->users->deleteNotification($userId, $notificationId);
    }


    /**
     * Lists users followed by the specified user.
     *
     * @param string $userId
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function followedUsers(string $userId, int $page, int $perPage): array
    {
        $rows = $this->users->listFollowedUsers($userId, $page, $perPage);
        return OutputSanitizer::sanitizeRows($rows, ['username', 'bio']);
    }

    /**
     * Establishes a follow relationship between two users.
     *
     * @param string $userId The follower.
     * @param string $person Username or ID of the user to follow.
     * @throws \DomainException If target user not found.
     * @throws \InvalidArgumentException If trying to follow self.
     */
    public function followUser(string $userId, string $person): array
    {
        $target = $this->users->findPublicByPerson($person);
        if ($target === null) {
            throw new \DomainException('User not found');
        }

        $targetId = (string) $target['id'];
        if ($targetId === $userId) {
            throw new \InvalidArgumentException('You cannot follow yourself');
        }

        $created = $this->users->followUser($userId, $targetId);
        if ($created) {
            $this->users->upsertUserFollowNotification($targetId, $userId);
        }

        return [
            'followed' => true,
            'is_following' => true,
            'followers_count' => $this->users->countFollowers($targetId),
        ];
    }

    /**
     * Removes a follow relationship between two users.
     *
     * @param string $userId The follower.
     * @param string $person Target user.
     * @throws \DomainException If target user not found.
     * @throws \InvalidArgumentException If trying to unfollow self.
     */
    public function unfollowUser(string $userId, string $person): array
    {
        $target = $this->users->findPublicByPerson($person);
        if ($target === null) {
            throw new \DomainException('User not found');
        }

        $targetId = (string) $target['id'];
        if ($targetId === $userId) {
            throw new \InvalidArgumentException('You cannot unfollow yourself');
        }

        $this->users->unfollowUser($userId, $targetId);
        $this->users->removeUserFollowNotification($targetId, $userId);

        return [
            'followed' => false,
            'is_following' => false,
            'followers_count' => $this->users->countFollowers($targetId),
        ];
    }
}
