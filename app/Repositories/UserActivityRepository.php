<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repository for tracking granular User Activity.
 *
 * Handles database operations for monitoring user session duration, 
 * tab-based activity tracking, and device metadata (IP/UA) logging.
 *
 * @package App\Repositories
 */
final class UserActivityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Atomically records or increments active time for a user's browser tab.
     *
     * Uses a tab_id to distinguish between multiple open windows.
     * Updates the total duration_seconds and refreshes the last_seen timestamp.
     *
     * @param string $userId
     * @param string $tabId Unique identifier for the client-side session/tab.
     * @param int $deltaSeconds Number of seconds to add to the total active time.
     * @param string|null $ipHash
     * @param string|null $userAgent
     */
    public function upsertActivity(string $userId, string $tabId, int $deltaSeconds, ?string $ipHash, ?string $userAgent): void
    {
        $deltaSeconds = max(0, min(300, $deltaSeconds));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_activity (user_id, tab_id, started_at, last_seen_at, duration_seconds, ip_hash, user_agent)
             VALUES (:ins_user_id, :ins_tab_id, :ins_started_at, :ins_last_seen_at, :ins_duration, :ins_ip_hash, :ins_user_agent)
             ON DUPLICATE KEY UPDATE
                duration_seconds = LEAST(duration_seconds + :upd_duration, 86400 * 90),
                last_seen_at = :upd_last_seen_at,
                ip_hash = COALESCE(:upd_ip_hash, ip_hash),
                user_agent = COALESCE(:upd_user_agent, user_agent)'
        );

        $stmt->execute([
            'ins_user_id' => $userId,
            'ins_tab_id' => $tabId,
            'ins_started_at' => $now,
            'ins_last_seen_at' => $now,
            'ins_duration' => $deltaSeconds,
            'ins_ip_hash' => $ipHash,
            'ins_user_agent' => $userAgent,
            'upd_duration' => $deltaSeconds,
            'upd_last_seen_at' => $now,
            'upd_ip_hash' => $ipHash,
            'upd_user_agent' => $userAgent,
        ]);
    }

    /**
     * Checks when a specific user tab was last active.
     */
    public function fetchLastSeen(string $userId, string $tabId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT last_seen_at FROM user_activity WHERE user_id = :user_id AND tab_id = :tab_id LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'tab_id' => $tabId,
        ]);

        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    /**
     * Calculates the total cumulative time (in seconds) a user has spent on the site.
     */
    public function totalDurationSeconds(string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(duration_seconds), 0) FROM user_activity WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
