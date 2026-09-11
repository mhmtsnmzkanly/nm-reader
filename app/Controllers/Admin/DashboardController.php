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
            $userId = trim((string) $request->getAttribute('user_id'));
            return ResponseHelper::success($this->dashboardService->overview($userId !== '' ? $userId : null));
        }

    /**
     * Returns the complete dashboard payload in one request. The legacy
     * endpoint above remains available for consumers that only need KPIs.
     */
    public function dashboardData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $query = $request->getQueryParams();
            $days = max(1, min(90, (int) ($query['days'] ?? 30)));
            $limit = max(1, min(30, (int) ($query['limit'] ?? 10)));

            return ResponseHelper::success([
                'overview' => $this->dashboardService->overview((string) $request->getAttribute('user_id')),
                'insights' => [
                    'views' => $this->dashboardService->viewStats($days, $limit),
                    'blogs' => $this->dashboardService->blogStats($days, $limit),
                    'visits' => $this->dashboardService->siteVisits(),
                    'reputation' => $this->dashboardService->userReputation($limit),
                ],
                'monetization' => $this->metricsService->monetizationAnalytics($days),
                'search' => $this->metricsService->searchInsights($days, $limit),
                'meta' => [
                    'period_days' => $days,
                    'limit' => $limit,
                    'generated_at' => gmdate('c'),
                ],
            ]);
        }
    
    public function reauthenticate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $payload = (array)$request->getParsedBody();
            $userId = (string)$request->getAttribute('user_id');
            $hash = $this->users->passwordHashForId($userId);
            if ($hash === null || !password_verify((string)($payload['password'] ?? ''), $hash)) {
                $this->dashboardService->createModerationAction($userId, 'security', $userId, 'auth_fail', 'Critical action reauthentication failed');
                return ResponseHelper::error(401, 'Parola doğrulanamadı.');
            }
            $_SESSION['admin_reauthenticated_at'] = time();
            $_SESSION['admin_reauthenticated_user_id'] = $userId;
            $this->dashboardService->createModerationAction($userId, 'security', $userId, 'update', 'Critical action reauthentication succeeded');
            return ResponseHelper::success(['valid_for_seconds' => 300]);
        }
    
    public function siteVisits(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            return ResponseHelper::success($this->dashboardService->siteVisits());
        }
    
    public function viewStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->dashboardService->viewStats((int)($q['days'] ?? 30), (int)($q['limit'] ?? 10)));
        }
    
    public function blogStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->dashboardService->blogStats((int)($q['days'] ?? 30), (int)($q['limit'] ?? 10)));
        }
    
    public function userReputation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            return ResponseHelper::success($this->dashboardService->userReputation((int)($q['limit'] ?? 10)));
        }
    
    public function metricsSnapshot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $userId = trim((string) $request->getAttribute('user_id'));
            return ResponseHelper::success($this->dashboardService->overview($userId !== '' ? $userId : null));
        }
    
    public function metricsInsights(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
        {
            $q = $request->getQueryParams();
            $days = (int) ($q['days'] ?? 30);
            $limit = (int) ($q['limit'] ?? 10);
    
            return ResponseHelper::success([
                'views' => $this->dashboardService->viewStats($days, $limit),
                'blogs' => $this->dashboardService->blogStats($days, $limit),
                'visits' => $this->dashboardService->siteVisits(),
                'reputation' => $this->dashboardService->userReputation($limit),
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
