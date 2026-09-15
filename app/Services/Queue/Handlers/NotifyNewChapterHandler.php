<?php

declare(strict_types=1);

namespace App\Services\Queue\Handlers;

use App\Services\Queue\JobHandlerInterface;
use PDO;
use RuntimeException;

/**
 * Handles the 'notify_new_chapter' background job.
 *
 * Broadcasts in-app notification events to all users following a series
 * when a new chapter is released.
 */
final class NotifyNewChapterHandler implements JobHandlerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function handle(array $payload): void
    {
        $contentId = (string) ($payload['content_id'] ?? '');
        $chapterId = (string) ($payload['chapter_id'] ?? '');
        $chapterNumber = (string) ($payload['chapter_number'] ?? '');
        $seriesTitle = (string) ($payload['series_title'] ?? 'Series');

        if ($contentId === '' || $chapterId === '') {
            throw new RuntimeException('Invalid notify_new_chapter payload: content_id and chapter_id are required.');
        }

        $eventTitle = 'Yeni bolum yayinlandi';
        $eventBody = sprintf('%s icin yeni bolum (%s) yayinda.', $seriesTitle, $chapterNumber);
        $eventData = json_encode([
            'source' => 'new_chapter',
            'content_id' => $contentId,
            'chapter_id' => $chapterId,
            'chapter_number' => $chapterNumber,
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        $eventId = null;
        try {
            $stmtEvent = $this->pdo->prepare(
                'INSERT INTO notification_events (actor_user_id, type, target_type, target_id, title, body, `data`, created_at)
                 VALUES (NULL, :type, "chapter", :chapter_id, :title, :body, :data, NOW())'
            );
            $stmtEvent->execute([
                'type' => 'new_chapter',
                'chapter_id' => $chapterId,
                'title' => $eventTitle,
                'body' => $eventBody,
                'data' => $eventData,
            ]);
            $eventId = (int) $this->pdo->lastInsertId();
        } catch (\Throwable) {
            // Event insertion failure should not prevent dispatching user notifications
        }

        $sql = 'INSERT INTO user_notifications (user_id, event_id, actor_user_id, type, title, body, `data`, created_at)
                SELECT
                    f.user_id,
                    :event_id,
                    NULL,
                    :type,
                    :title,
                    :body,
                    :data,
                    NOW()
                FROM user_series_follows f
                WHERE f.content_id = :content_id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'event_id' => $eventId,
            'type' => 'new_chapter',
            'title' => $eventTitle,
            'body' => $eventBody,
            'data' => $eventData,
            'content_id' => $contentId,
        ]);
    }

    #[\Override]
    public function requiredPermission(): ?string
    {
        return 'admin.chapter.create';
    }
}
