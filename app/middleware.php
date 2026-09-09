<?php

declare(strict_types=1);

use App\Helpers\ResponseHelper;
use App\Helpers\RequestSecurity;
use App\Config;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;

if (!isset($app, $settings)) {
    throw new RuntimeException('App or settings missing for middleware bootstrap.');
}

// 1. Dependency Access
$container = $app->getContainer();

// 2. Identify Install Route to skip DB-dependent logic
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$isInstallRoute = is_string($requestPath)
    && preg_match('#^' . preg_quote(Config::INSTALL_PATH, '#') . '(?:/|$)#', $requestPath) === 1;

// Maintenance Mode Middleware (Runs inside Session & Auth scope so admin sessions are recognized)
if (!$isInstallRoute) {
    $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container): ResponseInterface {
        return $container->get(\App\Middleware\MaintenanceMiddleware::class)->process($request, $handler);
    });
}

// Session and Auth Middleware
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container, $settings, $isInstallRoute): ResponseInterface {
    $path = (string) $request->getUri()->getPath();
    $isStaticPublicRoute = str_starts_with($path, '/media/public/')
        || str_starts_with($path, '/api/v1/media/public/')
        || $path === '/health'
        || $path === '/health/live'
        || $path === '/robots.txt'
        || $path === '/sitemap.xml'
        || $request->getMethod() === 'OPTIONS';

    // If pure static/public asset or health check, skip session initiation completely for maximum throughput
    if ($isStaticPublicRoute) {
        return $handler->handle($request);
    }

    $sessionPath = (string) $settings['app']['session_path'];
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (is_dir($sessionPath)) @chmod($sessionPath, 0700);

    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $sessionLifetime = (int) ($settings['app']['session_lifetime_seconds'] ?? 3600);
        ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

        session_name((string) $settings['app']['session_name']);
        session_save_path($sessionPath);
        
        $trustedProxies = (array) ($settings['app']['trusted_proxies'] ?? []);
        $isHttps = RequestSecurity::isSecure($request, $trustedProxies);
        // Never downgrade a cookie on a secure request, even when an old
        // configuration omitted SESSION_COOKIE_SECURE.
        $sessionSecure = (bool) ($settings['app']['session_cookie_secure'] ?? false) || $isHttps;
        $sessionSameSite = (string) ($settings['app']['session_same_site'] ?? 'Lax');
        $sessionLifetimeCookie = (int) ($settings['app']['session_cookie_lifetime'] ?? 0);

        session_set_cookie_params([
            'lifetime' => $sessionLifetimeCookie,
            'path' => '/',
            'domain' => '',
            'secure' => $sessionSecure,
            'httponly' => true,
            'samesite' => $sessionSameSite,
        ]);

        @session_start();
    }

    $newRefreshToken = null;
    $invalidToken = false;
    
    // Auth logic depends on DB - SKIP if installing
    if (!$isInstallRoute && !isset($_SESSION['user_id']) && !empty($request->getCookieParams()['nm_remember'])) {
        try {
            $authService = $container->get(\App\Services\AuthService::class);
            $refreshToken = $request->getCookieParams()['nm_remember'];
            $ip = RequestSecurity::clientIp($request, (array) ($settings['app']['trusted_proxies'] ?? []));
            $ua = (string) ($request->getHeaderLine('User-Agent') ?: 'unknown');
            
            $user = $authService->refresh($refreshToken, $ip, $ua);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['roles'] = $user['roles'];
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['is_admin'] = in_array('admin.panel.access', $user['permissions'], true);
            $_SESSION['session_key'] = $user['session_key'];
            $newRefreshToken = $user['refresh_token'];
        } catch (\Throwable) {
            $invalidToken = true;
        }
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }

    $response = $handler->handle($request);

    if (isset($_SESSION['csrf_token'])) {
        $response = $response->withHeader('X-CSRF-Token', (string) $_SESSION['csrf_token']);
        $isHttps = RequestSecurity::isSecure($request, (array) ($settings['app']['trusted_proxies'] ?? []));
        $csrfCookie = sprintf(
            'csrf_token=%s; Path=/; SameSite=Lax%s',
            urlencode((string) $_SESSION['csrf_token']),
            $isHttps ? '; Secure' : ''
        );
        $response = $response->withAddedHeader('Set-Cookie', $csrfCookie);
    }
    
    if ($newRefreshToken) {
        $rememberDays = (int) ($settings['app']['refresh_token_days'] ?? 30);
        $expires = time() + ($rememberDays * 24 * 60 * 60);
        $rememberSameSite = (string) ($settings['app']['remember_cookie_same_site'] ?? 'Lax');
        $rememberSecure = (bool) ($settings['app']['remember_cookie_secure'] ?? false)
            || RequestSecurity::isSecure($request, (array) ($settings['app']['trusted_proxies'] ?? []));

        $cookie = sprintf(
            'nm_remember=%s; Expires=%s; Path=/; HttpOnly; SameSite=%s%s',
            $newRefreshToken,
            gmdate('D, d M Y H:i:s T', $expires),
            $rememberSameSite,
            $rememberSecure ? '; Secure' : ''
        );

        $response = $response->withAddedHeader('Set-Cookie', $cookie);
    } elseif ($invalidToken) {
        $rememberSameSite = (string) ($settings['app']['remember_cookie_same_site'] ?? 'Lax');
        $rememberSecure = (bool) ($settings['app']['remember_cookie_secure'] ?? false)
            || RequestSecurity::isSecure($request, (array) ($settings['app']['trusted_proxies'] ?? []));
        $cookie = sprintf(
            'nm_remember=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=%s%s',
            $rememberSameSite,
            $rememberSecure ? '; Secure' : ''
        );

        $response = $response->withAddedHeader('Set-Cookie', $cookie);
    }
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    return $response;
});

// HTTPS Enforcement
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($settings, $isInstallRoute): ResponseInterface {
    if ($isInstallRoute) return $handler->handle($request);

    $path = (string) $request->getUri()->getPath();
    if ($path === '/health' || $path === '/health/live') {
        return $handler->handle($request);
    }
    
    if (!(bool) ($settings['app']['enforce_https'] ?? false)) {
        return $handler->handle($request);
    }

    $isSecure = RequestSecurity::isSecure($request, (array) ($settings['app']['trusted_proxies'] ?? []));
    if ($isSecure) {
        return $handler->handle($request);
    }

    $uri = $request->getUri()->withScheme('https');
    $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
    $response = $responseFactory->createResponse(308);
    return $response->withHeader('Location', (string) $uri);
});

$app->addBodyParsingMiddleware();

// I18n Middleware (Depends on DB) - SKIP if installing
if (!$isInstallRoute) {
    $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container): ResponseInterface {
        $path = (string) $request->getUri()->getPath();
        if ($path === '/health' || $path === '/health/live') {
            return $handler->handle($request);
        }

        return $container->get(App\Middleware\I18nMiddleware::class)->process($request, $handler);
    });
}

$app->addRoutingMiddleware();

// Access and Audit Logging (Depends on DB for audit) - SKIP if installing
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container, $isInstallRoute, $settings): ResponseInterface {
    $start = microtime(true);
    $response = $handler->handle($request);

    try {
        $accessLogger = $container->get('logger.access');
        $requestId = (string) ($request->getAttribute('request_id') ?? '');
        $userAgent = substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255);
        $accessLogger->info('request', [
            'request_id' => $requestId !== '' ? $requestId : null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'method' => $request->getMethod(),
            'path' => (string) $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'ip_hash' => hash('sha256', RequestSecurity::clientIp($request, (array) ($settings['app']['trusted_proxies'] ?? []))),
            'user_agent' => $userAgent,
            'context' => ['query' => (function () use ($request): string {
                $query = $request->getQueryParams();
                if (array_key_exists('install_token', $query)) $query['install_token'] = '[redacted]';
                return http_build_query($query);
            })()],
        ]);
    } catch (\Throwable) {}

    return $response;
});

$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container, $isInstallRoute, $settings): ResponseInterface {
    $startedAt = microtime(true);
    $response = $handler->handle($request);
    
    if ($isInstallRoute) return $response;

    $path = (string) $request->getUri()->getPath();
    if (str_starts_with($path, '/media/public/') || str_starts_with($path, '/api/v1/media/public/') || $path === '/health' || $path === '/health/live' || $path === '/robots.txt') {
        return $response;
    }

    try {
        $auditLogger = $container->get('logger.audit');
        $pdo = $container->get(\PDO::class);
        $duration = (int) round((microtime(true) - $startedAt) * 1000);
        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
        $method = $request->getMethod();
        $path = (string) $request->getUri()->getPath();
        $status = $response->getStatusCode();
        $outcome = $status >= 400 ? 'failure' : 'success';
        $normalizedAction = preg_replace('/\/[a-z0-9]{6,}(?=\/|$)/i', '/:id', $path);
        $action = strtoupper($method) . ' ' . ($normalizedAction !== false ? $normalizedAction : $path);
        $ipHash = hash('sha256', RequestSecurity::clientIp($request, (array) ($settings['app']['trusted_proxies'] ?? [])));
        $userAgent = substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255);
        $query = $request->getQueryParams();
        if (array_key_exists('install_token', $query)) $query['install_token'] = '[redacted]';

        $auditLogger->info('audit', [
            'user_id' => $userId, 'method' => $method, 'path' => $path,
            'status_code' => $status, 'ip_hash' => $ipHash, 'user_agent' => $userAgent, 'duration_ms' => $duration,
        ]);

        // Selectively write to database system_audit_logs: mutations, sensitive security/finance routes, or error responses
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $isSensitive = str_contains($path, '/admin/')
            || str_contains($path, '/auth/')
            || str_contains($path, '/wallet/')
            || str_contains($path, '/reports')
            || str_contains($path, '/user/');
        $isFailure = $status >= 400;

        if ($isMutation || $isSensitive || $isFailure) {
            $stmt = $pdo->prepare(
                'INSERT INTO system_audit_logs
                    (request_id, user_id, method, path, action, outcome, status_code, ip_hash, user_agent, duration_ms, context_json, created_at)
                 VALUES (:request_id, :user_id, :method, :path, :action, :outcome, :status_code, :ip_hash, :user_agent, :duration_ms, :context_json, NOW())'
            );
            $stmt->execute([
                'request_id' => $request->getAttribute('request_id') ?: null,
                'user_id' => $userId, 'method' => $method, 'path' => $path,
                'action' => substr($action, 0, 100), 'outcome' => $outcome,
                'status_code' => $status, 'ip_hash' => $ipHash, 'user_agent' => $userAgent, 'duration_ms' => $duration,
                'context_json' => json_encode(['query' => $query], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    } catch (\Throwable) {}

    return $response;
});

// Utility Middlewares
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $path = $request->getUri()->getPath();
    if ($path !== '/' && str_ends_with($path, '/')) {
        $normalizedPath = rtrim($path, '/');
        $query = $request->getUri()->getQuery();
        $location = $normalizedPath . ($query !== '' ? '?' . $query : '');

        $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
        $response = $responseFactory->createResponse(308);
        return $response->withHeader('Location', $location);
    }

    return $handler->handle($request);
});

// Bearer Token & Request ID
if (!$isInstallRoute) {
    $app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container): ResponseInterface {
        $path = (string) $request->getUri()->getPath();
        if ($path === '/health' || $path === '/health/live') {
            return $handler->handle($request);
        }

        return $container->get(\App\Middleware\ApiAuthMiddleware::class)->process($request, $handler);
    });
}
$app->add(\App\Middleware\RequestIdMiddleware::class);

// Error Handling
$errorMiddleware = $app->addErrorMiddleware((bool) ($settings['app']['debug'] ?? false), true, true);
$errorMiddleware->setDefaultErrorHandler(
    function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) use ($container, $settings): ResponseInterface {
        $statusCode = 500;
        $message = 'Internal server error';

        if ($exception instanceof HttpException) {
            $statusCode = (int) $exception->getCode();
            $message = $statusCode === 404 ? 'Not found' : $exception->getMessage();
        }

        if ($statusCode >= 500) {
            try {
                $errorLogger = $container->get('logger.error');
                $errorLogger->error($exception->getMessage(), [
                    'request_id' => $request->getAttribute('request_id'),
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'method' => $request->getMethod(),
                    'path' => (string) $request->getUri()->getPath(),
                    'status' => $statusCode,
                    'duration_ms' => 0,
                    'ip_hash' => hash('sha256', RequestSecurity::clientIp($request, (array) ($settings['app']['trusted_proxies'] ?? []))),
                    'user_agent' => substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255),
                    'context' => ['trace' => $exception->getTraceAsString()],
                ]);
            } catch (\Throwable) {}
        }

        $path = $request->getUri()->getPath();
        $accept = $request->getHeaderLine('Accept');

        if (str_starts_with($path, '/api/') || str_contains($accept, 'application/json')) {
            return ResponseHelper::error($statusCode, $displayErrorDetails ? $exception->getMessage() : $message);
        }

        try {
            $webController = $container->get(\App\Controllers\SystemPageController::class);
            $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
            $response = $responseFactory->createResponse($statusCode);
            return $webController->renderError($request, $response, $statusCode, $message);
        } catch (\Throwable) {
            return ResponseHelper::error($statusCode, $message);
        }
    }
);

// Slim executes the last-added middleware first. Keep security/CORS outside the
// error handler so preflights short-circuit and error responses retain headers.
$app->add(\App\Middleware\SecurityHeadersMiddleware::class);
$app->add(\App\Middleware\CorsMiddleware::class);
