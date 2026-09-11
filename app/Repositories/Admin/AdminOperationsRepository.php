<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use PDO;

use App\Repositories\Admin\AdminRepositoryBase;

/** Domain repository extracted from AdminConsoleRepository. */
final class AdminOperationsRepository extends AdminRepositoryBase
{
    public function listQueueJobs(int $page, int $perPage, ?string $status = null, string $query = '', ?string $jobType = null): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'processing', 'done', 'failed', 'cancelled'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($query !== '') {
            $where[] = '(job_type LIKE :query_job_type OR last_error LIKE :query_last_error)';
            $queryValue = '%' . $query . '%';
            $params['query_job_type'] = $queryValue;
            $params['query_last_error'] = $queryValue;
        }
        if ($jobType !== null && trim($jobType) !== '') {
            $where[] = 'job_type = :job_type';
            $params['job_type'] = trim($jobType);
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM system_jobs' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
    
        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                job_type,
                status,
                dedupe_key,
                priority,
                max_attempts,
                attempts,
                last_error,
                available_at,
                locked_by,
                locked_until,
                started_at,
                completed_at,
                failed_at,
                created_at,
                updated_at
             FROM system_jobs' . $whereSql . '
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function retryQueueJob(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE system_jobs
             SET status = "pending", attempts = 0, last_error = NULL, result = NULL,
                 failed_at = NULL, completed_at = NULL, started_at = NULL,
                 locked_by = NULL, locked_at = NULL,
                 locked_until = NULL, available_at = NOW(), updated_at = NOW()
             WHERE id = :id AND status IN ("failed", "cancelled")'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function cancelQueueJob(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE system_jobs SET status = "cancelled", updated_at = NOW() WHERE id = :id AND status = "pending"');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function systemHealthSnapshot(): array
    {
        $queue = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'cancelled' => 0];
        try {
            foreach ($this->pdo->query('SELECT status, COUNT(*) AS total FROM system_jobs GROUP BY status')->fetchAll() as $row) {
                $queue[(string)$row['status']] = (int)$row['total'];
            }
        } catch (\Throwable) {
            // Keep the rest of the health payload available while a fresh
            // installation is still applying its schema.
        }
        try {
            $latestMigration = $this->pdo->query('SELECT version, applied_at FROM schema_migrations ORDER BY applied_at DESC, version DESC LIMIT 1')->fetch() ?: null;
        } catch (\Throwable) {
            $latestMigration = null;
        }
        try {
            $databaseOk = $this->pdo->query('SELECT 1')->fetchColumn() !== false;
        } catch (\Throwable) {
            $databaseOk = false;
        }
        return [
            'database' => ['ok' => $databaseOk, 'version' => (string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION)],
            'queue' => $queue,
            'latest_migration' => $latestMigration,
        ];
    }

    public function queueOperationalMetrics(): array
    {
        $row = $this->pdo->query(
            "SELECT
                MIN(CASE WHEN status = 'pending' THEN created_at END) AS oldest_pending_at,
                SUM(CASE WHEN status = 'processing' AND locked_until IS NOT NULL AND locked_until < NOW() THEN 1 ELSE 0 END) AS stale_processing,
                SUM(CASE WHEN status = 'pending' AND available_at > NOW() THEN 1 ELSE 0 END) AS delayed
             FROM system_jobs"
        )->fetch() ?: [];
        return [
            'oldest_pending_at' => $row['oldest_pending_at'] ?? null,
            'stale_processing' => (int) ($row['stale_processing'] ?? 0),
            'delayed' => (int) ($row['delayed'] ?? 0),
        ];
    }
}
