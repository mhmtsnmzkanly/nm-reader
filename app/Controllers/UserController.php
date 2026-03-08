<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\UserService;
use App\Services\WalletService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for User-related API endpoints.
 *
 * Provides functionality for managing the current user's profile,
 * synchronization of preferences, viewing reading history,
 * social discovery (public profiles), and notification management.
 *
 * @package App\Controllers
 */
final class UserController
{
    public function __construct(
        private readonly UserService $users,
        private readonly WalletService $wallets
    )
    {
    }

    /**
     * Retrieves the current user's private profile information.
     *
     * Falls back to a guest structure if no session is found.
     */
    public function profile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null || $userId === '') {
            $userId = $_SESSION['user_id'] ?? null;
        }

        if ($userId === null || $userId === '') {
            return ResponseHelper::success([
                'is_guest' => true,
                'id' => null,
                'username' => 'guest',
                'email' => null,
                'bio' => null,
                'profile_image' => null,
                'cover_image' => null,
                'created_at' => null,
            ]);
        }

        $userId = (string) $userId;
        $profile = $this->users->profile($userId);

        if ($profile === null) {
            return ResponseHelper::success([
                'is_guest' => true,
                'id' => null,
                'username' => 'guest',
                'email' => null,
                'bio' => null,
                'profile_image' => null,
                'cover_image' => null,
                'created_at' => null,
            ]);
        }

        $profile['is_guest'] = false;
        return ResponseHelper::success($profile);
    }

    /**
     * Retrieves the paginated reading history for the authenticated user.
     */
    public function history(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));

        $items = $this->users->history($userId, $page, $perPage);

        return ResponseHelper::success($items, ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Fetches current site/reader preferences.
     */
    public function preferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $preferences = $this->users->preferences($userId);

            return ResponseHelper::success($preferences);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Bulk updates user preferences.
     */
    public function updatePreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $body = $request->getParsedBody();
            $payload = is_array($body) ? $body : [];
            $preferences = $this->users->updatePreferences($userId, $payload);

            return ResponseHelper::success($preferences);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Retrieves public information for any user (blogs, comments, stats).
     *
     * @param array $args Must contain 'person' (username or ID).
     */
    public function publicProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $person = (string) $args['person'];
        $query = $request->getQueryParams();
        $blogPage = max(1, (int) ($query['blog_page'] ?? 1));
        $blogPerPage = max(1, min(20, (int) ($query['blog_per_page'] ?? 5)));
        $commentPage = max(1, (int) ($query['comment_page'] ?? 1));
        $commentPerPage = max(1, min(50, (int) ($query['comment_per_page'] ?? 10)));

        $profile = $this->users->publicProfile($person, $blogPage, $blogPerPage, $commentPage, $commentPerPage);
        if ($profile === null) {
            return ResponseHelper::error(404, 'User not found');
        }

        return ResponseHelper::success($profile);
    }

    /**
     * Updates public-facing profile fields (bio, avatar).
     */
    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $body = $request->getParsedBody();
            $payload = array_merge(is_array($body) ? $body : [], $request->getUploadedFiles());
            $updated = $this->users->updateProfile($userId, $payload);
            if ($updated === null) {
                return ResponseHelper::error(404, 'User not found');
            }

            return ResponseHelper::success($updated);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        }
    }

    /**
     * Retrieves paginated notifications for the authenticated user.
     */
    public function notifications(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));

        return ResponseHelper::success(
            $this->users->notifications($userId, $page, $perPage),
            ['page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * Batch marks all unread notifications as read.
     */
    public function markNotificationsRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $this->users->markNotificationsRead($userId);
        return ResponseHelper::success(['updated' => true]);
    }

    /**
     * Lists other users currently followed by the authenticated user.
     */
    public function followedUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));

        return ResponseHelper::success(
            $this->users->followedUsers($userId, $page, $perPage),
            ['page' => $page, 'per_page' => $perPage]
        );
    }

    /**
     * Establishes a follow relationship with another user.
     *
     * @param array $args Must contain 'person'.
     */
    public function follow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $person = (string) $args['person'];
            $this->users->followUser($userId, $person);
            return ResponseHelper::success(['followed' => true]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    /**
     * Retracts a follow relationship with another user.
     *
     * @param array $args Must contain 'person'.
     */
    public function unfollow(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $person = (string) $args['person'];
            $this->users->unfollowUser($userId, $person);
            return ResponseHelper::success(['followed' => false]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function wallet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->wallet($userId));
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function walletTransactions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            $query = $request->getQueryParams();
            $page = max(1, (int) ($query['page'] ?? 1));
            $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
            $result = $this->wallets->transactions($userId, $page, $perPage);
            return ResponseHelper::success($result['items'], $result['meta']);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(404, $exception->getMessage());
        }
    }

    public function seriesUnlocks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
        $result = $this->wallets->seriesUnlocks($userId, $page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function chapterUnlocks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
        $result = $this->wallets->chapterUnlocks($userId, $page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function featureStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->wallets->featureStatus($userId));
    }

    public function featureEntitlements(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($query['per_page'] ?? 20)));
        $result = $this->wallets->featureEntitlements($userId, $page, $perPage);
        return ResponseHelper::success($result['items'], $result['meta']);
    }

    public function purchaseAdFree(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            return ResponseHelper::success($this->wallets->purchaseAdFree($userId));
        } catch (\DomainException $exception) {
            return ResponseHelper::error(402, $exception->getMessage());
        }
    }
}
