<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class RetentionService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?CacheService $cache = null
    ) {
    }

    public function cleanup(int $days = 30): array
    {
        $days = max(1, $days);
        $result = [
            'days' => $days,
            'audit_logs_deleted' => 0,
            'auth_login_events_deleted' => 0,
            'auth_sessions_deleted' => 0,
            'auth_refresh_tokens_deleted' => 0,
            'job_queue_done_deleted' => 0,
            'job_queue_failed_deleted' => 0,
            'cache_expired_deleted' => 0,
            'cache_locks_deleted' => 0,
        ];

        // Cache files are outside the database transaction, but all related
        // database cleanup must succeed as one unit. Otherwise an exception in
        // the middle of retention would silently leave a misleading partial
        // result in the admin panel.
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $result['audit_logs_deleted'] = $this->delete(
                'DELETE FROM system_audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
                $days
            );
            $result['auth_login_events_deleted'] = $this->delete(
                'DELETE FROM user_login_logs WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
                $days
            );
            $result['auth_refresh_tokens_deleted'] = $this->delete(
                'DELETE FROM user_tokens
                 WHERE (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL :used_days DAY))
                    OR expires_at < DATE_SUB(NOW(), INTERVAL :expires_days DAY)',
                $days
            );
            $result['auth_sessions_deleted'] = $this->delete(
                'DELETE FROM user_sessions
                 WHERE (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL :revoked_days DAY))
                    OR expires_at < DATE_SUB(NOW(), INTERVAL :expires_days DAY)',
                $days
            );
            $result['job_queue_done_deleted'] = $this->delete(
                "DELETE FROM system_jobs
                 WHERE status = 'done' AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
                $days
            );
            $result['job_queue_failed_deleted'] = $this->delete(
                "DELETE FROM system_jobs
                 WHERE status = 'failed' AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
                $days
            );
            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        if ($this->cache !== null) {
            $cachePrune = $this->cache->prune();
            $result['cache_expired_deleted'] = $cachePrune['expired_deleted'];
            $result['cache_locks_deleted'] = $cachePrune['stale_locks_deleted'];
        }

        $result['total_deleted'] =
            $result['audit_logs_deleted']
            + $result['auth_login_events_deleted']
            + $result['auth_sessions_deleted']
            + $result['auth_refresh_tokens_deleted']
            + $result['job_queue_done_deleted']
            + $result['job_queue_failed_deleted']
            + $result['cache_expired_deleted']
            + $result['cache_locks_deleted'];

        return $result;
    }

    private function delete(string $sql, int $days): int
    {
        $stmt = $this->pdo->prepare($sql);
        foreach (['days', 'used_days', 'expires_days', 'revoked_days'] as $parameter) {
            if (str_contains($sql, ':' . $parameter)) {
                $stmt->bindValue(':' . $parameter, $days, PDO::PARAM_INT);
            }
        }
        $stmt->execute();
        return $stmt->rowCount();
    }
}
