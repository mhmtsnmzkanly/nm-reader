<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\AuthorizationService;
use App\Services\SiteConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SiteConfigService $siteConfig,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path === '/health' || $path === '/health/live') {
            return $handler->handle($request);
        }

        if (!$this->siteConfig->isMaintenanceMode()) {
            return $handler->handle($request);
        }

        // 1. Static assets and media files must always be accessible (for CSS/JS/images)
        if (str_starts_with($path, '/assets/')
            || str_starts_with($path, '/media/')
            || str_starts_with($path, '/api/v1/media/')
            || $path === '/favicon.ico'
            || $path === '/robots.txt'
        ) {
            return $handler->handle($request);
        }

        // 2. Authentication & Admin Panel routes must remain accessible
        // So administrators can reach the login screen, authenticate, and manage the system
        if ($path === '/login'
            || $path === '/panel'
            || str_starts_with($path, '/panel/')
            || str_starts_with($path, '/api/v1/admin')
            || str_starts_with($path, '/api/v1/auth/login')
            || str_starts_with($path, '/api/v1/auth/logout')
            || str_starts_with($path, '/api/v1/auth/refresh')
            || str_starts_with($path, '/api/v1/auth/sessions')
            || str_starts_with($path, '/api/v1/user/profile')
            || str_starts_with($path, '/api/v1/user/preferences')
        ) {
            return $handler->handle($request);
        }

        // 3. Authenticated Admin / Staff Check
        // If the user has an active session with admin access, allow full site access
        if ($this->isAdminUser($request)) {
            return $handler->handle($request);
        }

        // 4. Check IP whitelist (with proxy / Cloudflare support)
        $clientIp = $this->resolveClientIp($request);
        $whitelist = $this->siteConfig->maintenanceWhitelistIps();

        if (in_array($clientIp, $whitelist, true)) {
            return $handler->handle($request);
        }

        // 5. Return 503 Service Unavailable for API requests
        $accept = (string) $request->getHeaderLine('Accept');
        if (str_starts_with($path, '/api/') || str_contains($accept, 'application/json')) {
            return ResponseHelper::error(
                503,
                'System is currently undergoing scheduled maintenance. Please check back shortly.',
                'maintenance_mode'
            );
        }

        // 6. Return 503 HTML for web requests (rendered via storage/views/maintenance.php)
        $response = new \Slim\Psr7\Response(503);
        $html = $this->renderMaintenancePage();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withHeader('Retry-After', '300');
    }

    private function renderMaintenancePage(): string
    {
        $viewPath = dirname(__DIR__, 2) . '/storage/views/maintenance.php';
        $siteName = $this->siteConfig->siteName();
        $siteLogo = $this->siteConfig->siteLogo();
        $panelUrl = '/panel';

        if (is_file($viewPath)) {
            ob_start();
            (static function () use ($viewPath, $siteName, $siteLogo, $panelUrl): void {
                include $viewPath;
            })();
            return (string) ob_get_clean();
        }

        $siteNameEscaped = htmlspecialchars($siteName);
        return '<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><title>Bakım Modu — ' . $siteNameEscaped . '</title></head>
<body style="background:#0b0f19;color:#fff;text-align:center;padding:50px;font-family:sans-serif;">
    <h1>🛠️ Sistem Bakımda</h1>
    <p>' . $siteNameEscaped . ' geçici olarak bakım modundadır.</p>
    <p><a href="/panel" style="color:#38bdf8;">Yönetici Girişi</a></p>
</body>
</html>';
    }

    private function isAdminUser(ServerRequestInterface $request): bool
    {
        // 1. Session check
        if (!empty($_SESSION['is_admin'])) {
            return true;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $roles = is_array($_SESSION['roles'] ?? null) ? $_SESSION['roles'] : [];
        $permissions = is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];

        if ($userId && $this->authorization !== null) {
            $effective = $this->authorization->resolveEffectivePermissions(
                $roles,
                $permissions,
                (string) $userId
            );
            if (in_array('admin.panel.access', $effective, true)) {
                return true;
            }
        }

        // 2. Request attribute check (if Bearer token was resolved by ApiAuthMiddleware)
        if ($request->getAttribute('is_admin') === true) {
            return true;
        }

        return false;
    }

    private function resolveClientIp(ServerRequestInterface $request): string
    {
        $cfIp = trim($request->getHeaderLine('CF-Connecting-IP'));
        if ($cfIp !== '') {
            return $cfIp;
        }

        $xff = trim($request->getHeaderLine('X-Forwarded-For'));
        if ($xff !== '') {
            $parts = explode(',', $xff);
            return trim($parts[0]);
        }

        $serverParams = $request->getServerParams();
        if (!empty($serverParams['HTTP_CF_CONNECTING_IP'])) {
            return trim((string) $serverParams['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }

        return (string) ($serverParams['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
