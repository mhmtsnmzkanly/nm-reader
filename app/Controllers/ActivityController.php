<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTO\UserActivityDto;
use App\Helpers\ResponseHelper;
use App\Services\UserActivityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for tracking User Activity and active session time.
 */
final class ActivityController
{
    public function __construct(
        private readonly UserActivityService $service
    ) {
    }

    /**
     * Records active time spent by a user in a specific tab.
     * Usually called via navigator.sendBeacon when a tab is hidden or closed.
     */
    public function track(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            return ResponseHelper::error(401, 'Unauthorized');
        }

        $body = (array) $request->getParsedBody();
        $tabId = (string) ($body['tab_id'] ?? '');
        $duration = (int) ($body['duration'] ?? 0);

        if ($tabId === '' || $duration <= 0) {
            return ResponseHelper::error(400, 'Invalid activity data');
        }

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $ua = $request->getServerParams()['HTTP_USER_AGENT'] ?? null;
        $ipHash = hash('sha256', $ip);

        try {
            $dto = new UserActivityDto(
                (string) $userId,
                $tabId,
                $duration,
                $ipHash,
                $ua
            );

            $this->service->logActivity($dto);
            return ResponseHelper::success(['tracked' => true]);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error(400, $e->getMessage());
        } catch (\Throwable $e) {
            return ResponseHelper::error(500, 'Failed to log activity: ' . $e->getMessage());
        }
    }
}
