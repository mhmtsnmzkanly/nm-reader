<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Log\LoggerInterface;

final class AnalyticsService
{
    private bool $disabled = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?LoggerInterface $logger = null
    )
    {
    }

    public function track(
        string $eventType,
        ?string $userId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        ?string $ip = null
    ): void {
        if ($this->disabled) {
            return;
        }

        $payloadJson = $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metadata !== [] && $payloadJson === false) {
            $payloadJson = null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO analytics_events (event_type, user_id, entity_type, entity_id, metadata, ip_hash, created_at)
                 VALUES (:event_type, :user_id, :entity_type, :entity_id, :metadata, :ip_hash, NOW())'
            );
            $stmt->execute([
                'event_type' => strtolower(trim($eventType)),
                'user_id' => $userId,
                'entity_type' => $entityType === null ? null : strtolower(trim($entityType)),
                'entity_id' => $entityId,
                'metadata' => $payloadJson,
                'ip_hash' => hash('sha256', $ip ?? 'unknown'),
            ]);
        } catch (\Throwable $e) {
            // If analytics schema is missing, do not break domain flows.
            $code = (string) $e->getCode();
            if ($code === '42S02' || str_contains(strtolower($e->getMessage()), 'analytics_events')) {
                $this->disabled = true;
            }

            if ($this->logger !== null) {
                $this->logger->warning('analytics.track_failed', [
                    'event_type' => $eventType,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
