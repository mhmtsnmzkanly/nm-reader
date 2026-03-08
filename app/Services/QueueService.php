<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class QueueService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enqueue(string $type, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO system_jobs (job_type, payload, status, attempts, available_at, created_at, updated_at)
             VALUES (:job_type, :payload, :status, 0, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            'job_type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
            'status' => 'pending',
        ]);
    }

    public function runOnce(int $limit = 10): array
    {
        $processed = 0;
        $failed = 0;

        $stmt = $this->pdo->prepare(
            'SELECT id, job_type, payload
             FROM system_jobs
             WHERE status = :status
               AND available_at <= NOW()
             ORDER BY id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();

        foreach ($jobs as $job) {
            $id = (int) $job['id'];
            $type = (string) $job['job_type'];
            $payload = json_decode((string) ($job['payload'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            try {
                $this->process($type, $payload);
                $done = $this->pdo->prepare(
                    'UPDATE system_jobs
                     SET status = :status, updated_at = NOW()
                     WHERE id = :id'
                );
                $done->execute([
                    'status' => 'done',
                    'id' => $id,
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $fail = $this->pdo->prepare(
                    'UPDATE system_jobs
                     SET status = :status, attempts = attempts + 1, last_error = :last_error, updated_at = NOW()
                     WHERE id = :id'
                );
                $fail->execute([
                    'status' => 'failed',
                    'last_error' => substr($e->getMessage(), 0, 500),
                    'id' => $id,
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'scanned' => count($jobs),
        ];
    }

    private function process(string $type, array $payload): void
    {
        if ($type === 'notify_new_chapter') {
            $contentId = (string) ($payload['content_id'] ?? '');
            $chapterId = (string) ($payload['chapter_id'] ?? '');
            $chapterNumber = (string) ($payload['chapter_number'] ?? '');
            $seriesTitle = (string) ($payload['series_title'] ?? 'Series');
            if ($contentId === '' || $chapterId === '') {
                throw new \RuntimeException('Invalid notify_new_chapter payload');
            }

            $sql = 'INSERT INTO user_notifications (user_id, actor_user_id, type, title, body, `data`, is_read, created_at)
                    SELECT
                        f.user_id,
                        NULL,
                        :type,
                        :title,
                        :body,
                        :data,
                        0,
                        NOW()
                    FROM user_series_follows f
                    WHERE f.content_id = :content_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'type' => 'new_chapter',
                'title' => 'Yeni bolum yayinlandi',
                'body' => sprintf('%s icin yeni bolum (%s) yayinda.', $seriesTitle, $chapterNumber),
                'data' => json_encode([
                    'source' => 'new_chapter',
                    'content_id' => $contentId,
                    'chapter_id' => $chapterId,
                    'chapter_number' => $chapterNumber,
                ], JSON_UNESCAPED_UNICODE) ?: '{}',
                'content_id' => $contentId,
            ]);
            return;
        }

        throw new \RuntimeException('Unknown job type: ' . $type);
    }
}

