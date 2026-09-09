<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Domain controller extracted from the legacy admin panel controller. */
final class DashboardController extends AdminController
{
    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->overview());
        }
    
    public function reauthenticate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array)$request->getParsedBody();
            $userId = (string)$request->getAttribute('user_id');
            $hash = $this->users->passwordHashForId($userId);
            if ($hash === null || !password_verify((string)($payload['password'] ?? ''), $hash)) {
                $this->console->createModerationAction($userId, 'security', $userId, 'auth_fail', 'Critical action reauthentication failed');
                return ResponseHelper::error(401, 'Parola doğrulanamadı.');
            }
            $_SESSION['admin_reauthenticated_at'] = time();
            $_SESSION['admin_reauthenticated_user_id'] = $userId;
            $this->console->createModerationAction($userId, 'security', $userId, 'update', 'Critical action reauthentication succeeded');
            return ResponseHelper::success(['valid_for_seconds' => 300]);
        }
    
    public function siteVisits(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->siteVisits());
        }
    
    public function viewStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->console->viewStats((int)($q['days'] ?? 30), (int)($q['limit'] ?? 10)));
        }
    
    public function blogStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->console->blogStats((int)($q['days'] ?? 30), (int)($q['limit'] ?? 10)));
        }
    
    public function userReputation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->console->userReputation((int)($q['limit'] ?? 10)));
        }
    
    public function metricsSnapshot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->console->overview());
        }
    
    public function metricsInsights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            $days = (int) ($q['days'] ?? 30);
            $limit = (int) ($q['limit'] ?? 10);
    
            return ResponseHelper::success([
                'views' => $this->console->viewStats($days, $limit),
                'blogs' => $this->console->blogStats($days, $limit),
                'visits' => $this->console->siteVisits(),
                'reputation' => $this->console->userReputation($limit),
            ]);
        }
    
    public function monetizationAnalytics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            $days = (int) ($q['days'] ?? 30);
            return ResponseHelper::success($this->metricsService->monetizationAnalytics($days));
        }
    
    public function searchInsights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            $days = (int) ($q['days'] ?? 30);
            $limit = (int) ($q['limit'] ?? 20);
            return ResponseHelper::success($this->metricsService->searchInsights($days, $limit));
        }
    
    public function seriesReadingFunnel(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
        {
            return ResponseHelper::success($this->metricsService->seriesReadingFunnel((string) $args['id']));
        }
}
