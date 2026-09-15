<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Queue\Handlers\NotifyNewChapterHandler;
use App\Services\Queue\JobHandlerInterface;
use PDO;
use Psr\Container\ContainerInterface;

final class QueueService
{
    private const LEASE_SECONDS = 300;

    /**
     * Default registry of job types mapped to their handler classes.
     *
     * @var array<string, class-string<JobHandlerInterface>>
     */
    private const DEFAULT_HANDLERS = [
        'notify_new_chapter' => NotifyNewChapterHandler::class,
    ];

    /**
     * @var array<string, class-string<JobHandlerInterface>>
     */
    private array $handlers;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?ContainerInterface $container = null,
        array $customHandlers = []
    ) {
        $this->handlers = array_merge(self::DEFAULT_HANDLERS, $customHandlers);
    }

    /**
     * Registers or overrides a job handler for a specific job type.
     *
     * @param string $type
     * @param class-string<JobHandlerInterface> $handlerClass
     */
    public function registerHandler(string $type, string $handlerClass): void
    {
        $this->handlers[trim($type)] = $handlerClass;
    }

    /**
     * Checks whether a handler is registered for a given job type.
     */
    public function hasHandler(string $type): bool
    {
        return isset($this->handlers[trim($type)]);
    }

    /**
     * Returns the registered handlers map.
     *
     * @return array<string, class-string<JobHandlerInterface>>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function enqueue(
        string $type,
        array $payload,
        ?string $dedupeKey = null,
        int $priority = 0,
        int $maxAttempts = 3,
        int $delaySeconds = 0
    ): int
    {
        $type = trim($type);
        if ($type === '' || strlen($type) > 64) {
            throw new \InvalidArgumentException('A valid job type is required.');
        }
        $dedupeKey = $dedupeKey !== null ? trim($dedupeKey) : null;
        if ($dedupeKey === '') $dedupeKey = null;
        if ($dedupeKey !== null && strlen($dedupeKey) > 128) {
            throw new \InvalidArgumentException('dedupeKey must be at most 128 characters.');
        }
        $maxAttempts = max(1, min(20, $maxAttempts));
        $delaySeconds = max(0, min(86400, $delaySeconds));

        if ($dedupeKey !== null) {
            $existing = $this->pdo->prepare('SELECT id FROM system_jobs WHERE dedupe_key = :dedupe_key LIMIT 1');
            $existing->execute(['dedupe_key' => $dedupeKey]);
            $existingId = $existing->fetchColumn();
            if ($existingId !== false) return (int) $existingId;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO system_jobs
                (job_type, payload, status, dedupe_key, priority, max_attempts, attempts, available_at, created_at, updated_at)
             VALUES
                (:job_type, :payload, :status, :dedupe_key, :priority, :max_attempts, 0,
                 :available_at, NOW(), NOW())'
        );
        $availableAt = (new \DateTimeImmutable('now'))
            ->modify('+' . $delaySeconds . ' seconds')
            ->format('Y-m-d H:i:s');
        try {
            $stmt->execute([
                'job_type' => $type,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'status' => 'pending',
                'dedupe_key' => $dedupeKey,
                'priority' => $priority,
                'max_attempts' => $maxAttempts,
                'available_at' => $availableAt,
            ]);
        } catch (\PDOException $exception) {
            // A concurrent enqueue can win the unique dedupe key between the
            // lookup above and INSERT. Return that job instead of duplicating it.
            if ($dedupeKey === null || !str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                throw $exception;
            }
            $existing = $this->pdo->prepare('SELECT id FROM system_jobs WHERE dedupe_key = :dedupe_key LIMIT 1');
            $existing->execute(['dedupe_key' => $dedupeKey]);
            $existingId = $existing->fetchColumn();
            if ($existingId === false) throw $exception;
            return (int) $existingId;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function runOnce(int $limit = 10, ?string $jobType = null, ?string $workerId = null): array
    {
        $limit = max(1, min(100, $limit));
        $jobType = $jobType !== null ? trim($jobType) : null;
        if ($jobType === '') $jobType = null;
        $workerId = $workerId !== null && trim($workerId) !== ''
            ? substr(trim($workerId), 0, 80)
            : sprintf('%s:%d:%s', php_uname('n'), getmypid(), bin2hex(random_bytes(6)));

        $recovered = $this->recoverExpiredJobs();
        $processed = 0;
        $failed = 0;

        $where = "status = 'pending' AND available_at <= NOW() AND attempts < max_attempts";
        $params = [];
        if ($jobType !== null) {
            $where .= ' AND job_type = :job_type';
            $params['job_type'] = $jobType;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, job_type, payload, attempts, max_attempts
             FROM system_jobs
             WHERE ' . $where . '
             ORDER BY priority DESC, id ASC
             LIMIT :limit'
        );
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();

        foreach ($jobs as $job) {
            $id = (int) ($job['id'] ?? 0);
            $type = (string) ($job['job_type'] ?? ($job['type'] ?? ''));
            $payload = json_decode((string) ($job['payload'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            // Atomically claim the job to prevent duplicate execution across workers
            $leaseUntil = (new \DateTimeImmutable('now'))
                ->modify('+' . self::LEASE_SECONDS . ' seconds')
                ->format('Y-m-d H:i:s');
            $claim = $this->pdo->prepare(
                "UPDATE system_jobs
                 SET status = 'processing', locked_by = :worker_id, locked_at = NOW(),
                     locked_until = :locked_until, started_at = COALESCE(started_at, NOW()),
                     attempts = attempts + 1, updated_at = NOW()
                 WHERE id = :id AND status = 'pending' AND available_at <= NOW() AND attempts < max_attempts"
            );
            $claim->execute(['id' => $id, 'worker_id' => $workerId, 'locked_until' => $leaseUntil]);
            if ($claim->rowCount() === 0) {
                continue;
            }

            try {
                $this->process($type, $payload);
                $done = $this->pdo->prepare(
                    'UPDATE system_jobs
                     SET status = :status, result = :result, completed_at = NOW(),
                         locked_by = NULL, locked_at = NULL, locked_until = NULL, updated_at = NOW()
                     WHERE id = :id AND status = "processing" AND locked_by = :worker_id'
                );
                $done->execute([
                    'status' => 'done',
                    'result' => json_encode(['completed_at' => gmdate('c')], JSON_UNESCAPED_SLASHES),
                    'id' => $id,
                    'worker_id' => $workerId,
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $fail = $this->pdo->prepare(
                    'UPDATE system_jobs
                     SET status = :status, last_error = :last_error, failed_at = NOW(),
                         locked_by = NULL, locked_at = NULL, locked_until = NULL, updated_at = NOW()
                     WHERE id = :id AND status = "processing" AND locked_by = :worker_id'
                );
                $fail->execute([
                    'status' => 'failed',
                    'last_error' => substr($e->getMessage(), 0, 500),
                    'id' => $id,
                    'worker_id' => $workerId,
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'scanned' => count($jobs),
            'recovered' => $recovered,
        ];
    }

    private function recoverExpiredJobs(): int
    {
        $requeue = $this->pdo->exec(
            "UPDATE system_jobs
             SET status = 'pending', available_at = NOW(), locked_by = NULL,
                 locked_at = NULL, locked_until = NULL, failed_at = NULL,
                 last_error = 'Worker lease expired', updated_at = NOW()
             WHERE status = 'processing' AND locked_until IS NOT NULL AND locked_until <= NOW()
               AND attempts < max_attempts"
        );
        $this->pdo->exec(
            "UPDATE system_jobs
             SET status = 'failed', failed_at = NOW(), last_error = COALESCE(last_error, 'Worker lease expired'),
                 locked_by = NULL, locked_at = NULL, locked_until = NULL, updated_at = NOW()
             WHERE status = 'processing' AND locked_until IS NOT NULL AND locked_until <= NOW()
               AND attempts >= max_attempts"
        );

        return max(0, (int) $requeue);
    }

    private function process(string $type, array $payload): void
    {
        $handlerClass = $this->handlers[$type] ?? null;

        if ($handlerClass === null) {
            throw new \RuntimeException('Unknown job type: ' . $type);
        }

        $handler = $this->resolveHandler($handlerClass);
        $handler->handle($payload);
    }

    private function resolveHandler(string $handlerClass): JobHandlerInterface
    {
        if ($this->container !== null && $this->container->has($handlerClass)) {
            $instance = $this->container->get($handlerClass);
        } else {
            // Direct instantiation fallback (passing PDO to constructor if accepted)
            $instance = new $handlerClass($this->pdo);
        }

        if (!$instance instanceof JobHandlerInterface) {
            throw new \RuntimeException(sprintf(
                'Job handler %s must implement %s',
                $handlerClass,
                JobHandlerInterface::class
            ));
        }

        return $instance;
    }
}
