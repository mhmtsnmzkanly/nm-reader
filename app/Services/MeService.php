<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\CursorPagination;

/**
 * Aggregates the authenticated user's startup data into one response.
 *
 * This is intentionally limited to the four datasets needed by the global
 * application providers. More expensive, paginated user resources remain on
 * their dedicated endpoints.
 */
final class MeService
{
    public function __construct(
        private readonly UserService $users,
        private readonly WalletService $wallets,
    ) {
    }

    /**
     * @return array{profile:array<string,mixed>, preferences:array<string,mixed>, wallet:array<string,mixed>, notifications:array{items:list<array<string,mixed>>, meta:array<string,mixed>}}
     */
    public function snapshot(string $userId, int $notificationPage = 1, int $notificationPerPage = 20): array
    {
        $profile = $this->users->profile($userId);
        if (!is_array($profile)) {
            throw new \DomainException('User not found');
        }
        $profile['is_guest'] = false;

        $preferences = $this->users->preferences($userId);
        $wallet = $this->wallets->wallet($userId);
        $notifications = $this->users->notifications($userId, $notificationPage, $notificationPerPage);

        $nextCursor = null;
        if (count($notifications) >= $notificationPerPage) {
            $last = $notifications[array_key_last($notifications)] ?? null;
            if (is_array($last)) {
                $nextCursor = CursorPagination::encode($last['created_at'] ?? null, $last['id'] ?? null);
            }
        }

        return [
            'profile' => $profile,
            'preferences' => $preferences,
            'wallet' => $wallet,
            'notifications' => [
                'items' => array_values($notifications),
                'meta' => [
                    'page' => $notificationPage,
                    'per_page' => $notificationPerPage,
                    'next_cursor' => $nextCursor,
                ],
            ],
        ];
    }
}
