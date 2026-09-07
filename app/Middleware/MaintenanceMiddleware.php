<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\AuthorizationService;
use App\Services\SiteConfigService;
use App\Services\HtmlTemplateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SiteConfigService $siteConfig,
        private readonly HtmlTemplateService $templates,
        private readonly ?AuthorizationService $authorization = null,
        private readonly array $trustedProxies = []
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

        // 6. Return 503 HTML for web requests (read from the public HTML shell)
        $response = new \Slim\Psr7\Response(503);
        $html = $this->renderMaintenancePage();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withHeader('Retry-After', '300');
    }

    private function renderMaintenancePage(): string
    {
        $siteName = $this->siteConfig->siteName();
        $html = $this->templates->render('maintenance.html', [
            'site_name' => htmlspecialchars($siteName ?: 'NM Reader', ENT_QUOTES, 'UTF-8'),
            'panel_url' => htmlspecialchars('/panel', ENT_QUOTES, 'UTF-8'),
            'year' => (string) date('Y'),
        ]);
        if ($html !== null) {
            return $html;
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
        $serverParams = $request->getServerParams();
        $remoteAddress = trim((string) ($serverParams['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '') {
            $remoteAddress = '127.0.0.1';
        }

        // Forwarded headers are only trustworthy when the direct peer is a
        // configured reverse proxy. Otherwise use the socket peer address.
        if (!$this->isTrustedProxy($remoteAddress)) {
            return $remoteAddress;
        }

        $cfIp = trim($request->getHeaderLine('CF-Connecting-IP'));
        if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP) !== false) {
            return $cfIp;
        }

        $xff = trim($request->getHeaderLine('X-Forwarded-For'));
        if ($xff !== '') {
            $parts = explode(',', $xff);
            $forwardedIp = trim((string) ($parts[0] ?? ''));
            if (filter_var($forwardedIp, FILTER_VALIDATE_IP) !== false) {
                return $forwardedIp;
            }
        }

        return $remoteAddress;
    }

    private function isTrustedProxy(string $address): bool
    {
        foreach ($this->trustedProxies as $entry) {
            $entry = trim((string) $entry);
            if ($entry === $address) {
                return true;
            }

            if (!str_contains($entry, '/')) {
                continue;
            }

            [$network, $prefix] = array_pad(explode('/', $entry, 2), 2, null);
            $addressBytes = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false
                ? inet_pton($address)
                : false;
            $networkBytes = is_string($network)
                ? inet_pton($network)
                : false;
            $prefixLength = is_numeric($prefix) ? (int) $prefix : -1;
            $maxPrefix = is_string($networkBytes) ? strlen($networkBytes) * 8 : -1;

            if ($addressBytes === false || $networkBytes === false
                || strlen($addressBytes) !== strlen($networkBytes)
                || $prefixLength < 0 || $prefixLength > $maxPrefix) {
                continue;
            }

            $fullBytes = intdiv($prefixLength, 8);
            $remainingBits = $prefixLength % 8;
            if ($fullBytes > 0 && substr($addressBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
                continue;
            }
            if ($remainingBits > 0) {
                $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
                if ((ord($addressBytes[$fullBytes]) & $mask) !== (ord($networkBytes[$fullBytes]) & $mask)) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }
}
