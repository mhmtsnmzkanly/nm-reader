<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\SiteConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SiteConfigService $siteConfig
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

        // Always allow admin panel and admin API access during maintenance
        if (str_starts_with($path, '/panel')
            || str_starts_with($path, '/api/v1/admin')
            || str_starts_with($path, '/api/v1/auth/login')
        ) {
            return $handler->handle($request);
        }

        // Check IP whitelist
        $serverParams = $request->getServerParams();
        $clientIp = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
        $whitelist = $this->siteConfig->maintenanceWhitelistIps();

        if (in_array($clientIp, $whitelist, true)) {
            return $handler->handle($request);
        }

        // Return 503 Service Unavailable
        if (str_starts_with($path, '/api/')) {
            return ResponseHelper::error(503, 'System is currently undergoing scheduled maintenance. Please check back shortly.');
        }

        $response = new \Slim\Psr7\Response(503);
        $html = '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Modu — ' . htmlspecialchars($this->siteConfig->siteName()) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
        .card { max-width: 500px; padding: 40px; background: #1e293b; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border: 1px solid #334155; }
        h1 { font-size: 28px; margin-bottom: 12px; color: #38bdf8; }
        p { font-size: 16px; color: #94a3b8; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🛠️ Sistem Bakımda</h1>
        <p>' . htmlspecialchars($this->siteConfig->siteName()) . ' şu anda planlı bakım ve altyapı güncellemesi nedeniyle geçici olarak hizmet dışıdır. Lütfen kısa süre sonra tekrar deneyiniz.</p>
    </div>
</body>
</html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withHeader('Retry-After', '300');
    }
}
