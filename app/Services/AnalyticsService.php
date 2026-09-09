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

        $sessionHash = null;
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $sessionHash = hash('sha256', session_id());
        }
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : null;
        $page = $requestUri !== null ? substr((string) parse_url($requestUri, PHP_URL_PATH), 0, 255) : null;
        $route = $entityType !== null ? strtolower(trim($entityType)) : null;
        $source = isset($metadata['source']) && is_scalar($metadata['source'])
            ? substr((string) $metadata['source'], 0, 64)
            : (isset($_SERVER['HTTP_X_ANALYTICS_SOURCE']) ? substr((string) $_SERVER['HTTP_X_ANALYTICS_SOURCE'], 0, 64) : null);
        $searchQuery = isset($metadata['search_query']) && is_scalar($metadata['search_query'])
            ? substr(trim((string) $metadata['search_query']), 0, 255)
            : (isset($_GET['q']) ? substr(trim((string) $_GET['q']), 0, 255) : null);
        $referrer = isset($_SERVER['HTTP_REFERER']) ? substr((string) $_SERVER['HTTP_REFERER'], 0, 500) : null;
        $durationMs = isset($metadata['duration_ms']) && is_numeric($metadata['duration_ms'])
            ? max(0, min(86400000, (int) $metadata['duration_ms']))
            : null;

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO analytics_events
                    (event_type, user_id, session_hash, entity_type, entity_id, page, route, source, search_query, referrer, duration_ms, metadata, ip_hash, created_at)
                 VALUES
                    (:event_type, :user_id, :session_hash, :entity_type, :entity_id, :page, :route, :source, :search_query, :referrer, :duration_ms, :metadata, :ip_hash, NOW())'
            );
            $stmt->execute([
                'event_type' => strtolower(trim($eventType)),
                'user_id' => $userId,
                'session_hash' => $sessionHash,
                'entity_type' => $entityType === null ? null : strtolower(trim($entityType)),
                'entity_id' => $entityId,
                'page' => $page,
                'route' => $route,
                'source' => $source,
                'search_query' => $searchQuery,
                'referrer' => $referrer,
                'duration_ms' => $durationMs,
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
