<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Service to manage and dispatch webhooks for platform events (chapter published, blog approved, etc.).
 */
final class WebhookService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function listWebhooks(): array
    {
        $stmt = $this->pdo->query('SELECT id, platform, event, webhook_url, is_active, created_at, updated_at FROM webhook_configs ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function getWebhook(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, platform, event, webhook_url, is_active, created_at, updated_at FROM webhook_configs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createWebhook(array $payload): array
    {
        $platform = strtolower(trim((string) ($payload['platform'] ?? 'discord')));
        if (!in_array($platform, ['discord', 'telegram', 'custom'], true)) {
            throw new \InvalidArgumentException('Invalid webhook platform');
        }

        $event = strtolower(trim((string) ($payload['event'] ?? 'chapter_published')));
        if (!in_array($event, ['chapter_published', 'blog_approved', 'series_created'], true)) {
            throw new \InvalidArgumentException('Invalid webhook event');
        }

        $url = trim((string) ($payload['webhook_url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Valid webhook_url is required');
        }

        $isActive = isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : 1;

        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_configs (platform, event, webhook_url, is_active, created_at)
             VALUES (:platform, :event, :url, :is_active, NOW())'
        );
        $stmt->execute([
            'platform' => $platform,
            'event' => $event,
            'url' => $url,
            'is_active' => $isActive,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return [
            'id' => $id,
            'platform' => $platform,
            'event' => $event,
            'webhook_url' => $url,
            'is_active' => (bool) $isActive,
        ];
    }

    public function updateWebhook(int $id, array $payload): void
    {
        $current = $this->getWebhook($id);
        if (!$current) {
            throw new \DomainException('Webhook not found');
        }

        $platform = isset($payload['platform']) ? strtolower(trim((string) $payload['platform'])) : $current['platform'];
        $event = isset($payload['event']) ? strtolower(trim((string) $payload['event'])) : $current['event'];
        $url = isset($payload['webhook_url']) ? trim((string) $payload['webhook_url']) : $current['webhook_url'];
        $isActive = isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : (int) $current['is_active'];

        $stmt = $this->pdo->prepare(
            'UPDATE webhook_configs
             SET platform = :platform, event = :event, webhook_url = :url, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'platform' => $platform,
            'event' => $event,
            'url' => $url,
            'is_active' => $isActive,
            'id' => $id,
        ]);
    }

    public function deleteWebhook(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhook_configs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new \DomainException('Webhook not found');
        }
    }

    public function trigger(string $event, array $data): void
    {
        $stmt = $this->pdo->prepare('SELECT id, platform, webhook_url FROM webhook_configs WHERE event = :event AND is_active = 1');
        $stmt->execute(['event' => $event]);
        $webhooks = $stmt->fetchAll();

        foreach ($webhooks as $hook) {
            $this->dispatchPayload($hook['platform'], $hook['webhook_url'], $event, $data);
        }
    }

    public function testWebhook(int $id): array
    {
        $hook = $this->getWebhook($id);
        if (!$hook) {
            throw new \DomainException('Webhook not found');
        }

        $sampleData = [
            'series_title' => 'Test Series (Solo Leveling)',
            'chapter_number' => '100',
            'title' => 'The Final Battle',
            'url' => 'https://example.com/manga/solo-leveling/chapter/100',
            'cover_image' => 'https://example.com/cover.jpg',
            'is_test' => true,
        ];

        $success = $this->dispatchPayload($hook['platform'], $hook['webhook_url'], (string) $hook['event'], $sampleData);

        return [
            'success' => $success,
            'message' => $success ? 'Webhook test signal sent successfully!' : 'Failed to send webhook request to destination endpoint.',
        ];
    }

    private function dispatchPayload(string $platform, string $url, string $event, array $data): bool
    {
        $body = match ($platform) {
            'discord' => $this->formatDiscordPayload($event, $data),
            'telegram' => $this->formatTelegramPayload($event, $data),
            default => json_encode(['event' => $event, 'data' => $data, 'timestamp' => time()]),
        };

        if (!$body) {
            return false;
        }

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return false;
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: NM-Reader-Bot/1.0'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatDiscordPayload(string $event, array $data): string
    {
        $title = match ($event) {
            'chapter_published' => "📖 Yeni Bölüm Yayınlandı!",
            'blog_approved' => "✍️ Yeni Blog Yazısı Yayında!",
            'series_created' => "✨ Yeni Seri Eklendi!",
            default => "📢 NM Reader Bildirimi",
        };

        $desc = match ($event) {
            'chapter_published' => sprintf("**%s** — Bölüm %s\n%s", $data['series_title'] ?? 'Seri', $data['chapter_number'] ?? '', $data['title'] ?? ''),
            'blog_approved' => sprintf("**%s**\nYazar: %s", $data['title'] ?? 'Blog', $data['author'] ?? 'Anonim'),
            'series_created' => sprintf("**%s**\nTür: %s", $data['title'] ?? 'Yeni Seri', $data['type'] ?? 'Manga'),
            default => json_encode($data),
        };

        $embed = [
            'title' => $title,
            'description' => $desc,
            'color' => 5814783,
            'footer' => ['text' => 'NM Reader Otomatik Bildirim'],
            'timestamp' => date('c'),
        ];

        if (!empty($data['cover_image'])) {
            $embed['thumbnail'] = ['url' => $data['cover_image']];
        }

        if (!empty($data['url'])) {
            $embed['url'] = $data['url'];
        }

        return json_encode([
            'username' => 'NM Reader Bot',
            'embeds' => [$embed],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function formatTelegramPayload(string $event, array $data): string
    {
        $text = match ($event) {
            'chapter_published' => sprintf("📖 *Yeni Bölüm:* *%s* - Bölüm %s\n%s", $data['series_title'] ?? 'Seri', $data['chapter_number'] ?? '', $data['url'] ?? ''),
            'blog_approved' => sprintf("✍️ *Yeni Blog:* *%s*\n%s", $data['title'] ?? 'Blog', $data['url'] ?? ''),
            'series_created' => sprintf("✨ *Yeni Seri:* *%s*\n%s", $data['title'] ?? 'Yeni Seri', $data['url'] ?? ''),
            default => json_encode($data),
        };

        return json_encode([
            'text' => $text,
            'parse_mode' => 'Markdown',
        ], JSON_UNESCAPED_UNICODE);
    }
}
