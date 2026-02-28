<?php

declare(strict_types=1);

use App\Helpers\ResponseHelper;
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

// CORS must be early in the stack to handle preflight OPTIONS
$app->add(\App\Middleware\CorsMiddleware::class);

// Session and Auth Middleware
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container, $settings): ResponseInterface {
    $sessionPath = (string) $settings['app']['session_path'];
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }

    if (session_status() === PHP_SESSION_NONE) {
        $sessionLifetime = (int) ($settings['app']['session_lifetime_seconds'] ?? 3600);
        ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

        session_name((string) $settings['app']['session_name']);
        session_save_path($sessionPath);
        
        $isHttps = ($request->getUri()->getScheme() === 'https');
        $sessionSecure = (bool) ($settings['app']['session_cookie_secure'] ?? $isHttps);
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
    
    // Auth logic depends on DB
    if (!isset($_SESSION['user_id']) && !empty($request->getCookieParams()['nm_remember'])) {
        try {
            $authService = $container->get(\App\Services\AuthService::class);
            $refreshToken = $request->getCookieParams()['nm_remember'];
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
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
            // DB might be down or token invalid
            $invalidToken = true;
        }
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }

    $response = $handler->handle($request);

    if (isset($_SESSION['csrf_token'])) {
        $response = $response->withHeader('X-CSRF-Token', (string) $_SESSION['csrf_token']);
    }
    
    if ($newRefreshToken) {
        $rememberDays = (int) ($settings['app']['refresh_token_days'] ?? 30);
        $expires = time() + ($rememberDays * 24 * 60 * 60);
        $rememberSameSite = (string) ($settings['app']['remember_cookie_same_site'] ?? 'Lax');
        $rememberSecure = (bool) ($settings['app']['remember_cookie_secure'] ?? ($request->getUri()->getScheme() === 'https'));

        $cookie = sprintf(
            'nm_remember=%s; Expires=%s; Path=/; HttpOnly; SameSite=%s%s',
            $newRefreshToken,
            gmdate('D, d M Y H:i:s T', $expires),
            $rememberSameSite,
            $rememberSecure ? '; Secure' : ''
        );

        $response = $response->withHeader('Set-Cookie', $cookie);
    } elseif ($invalidToken) {
        $rememberSameSite = (string) ($settings['app']['remember_cookie_same_site'] ?? 'Lax');
        $rememberSecure = (bool) ($settings['app']['remember_cookie_secure'] ?? ($request->getUri()->getScheme() === 'https'));
        $cookie = sprintf(
            'nm_remember=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=%s%s',
            $rememberSameSite,
            $rememberSecure ? '; Secure' : ''
        );

        $response = $response->withHeader('Set-Cookie', $cookie);
    }
    
    return $response;
});

// HTTPS Enforcement
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container): ResponseInterface {
    try {
        $siteConfig = $container->get(\App\Services\SiteConfigService::class);
        if (!$siteConfig->enforceHttps()) {
            return $handler->handle($request);
        }

        $protoHeader = strtolower(trim($request->getHeaderLine('X-Forwarded-Proto')));
        $isSecure = $request->getUri()->getScheme() === 'https' || $protoHeader === 'https';
        if ($isSecure) {
            return $handler->handle($request);
        }

        $uri = $request->getUri()->withScheme('https');
        $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
        $response = $responseFactory->createResponse(308);
        return $response->withHeader('Location', (string) $uri);
    } catch (\Throwable) {
        // If DB config fails, skip HTTPS enforcement
        return $handler->handle($request);
    }
});

// Security Headers
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $response = $handler->handle($request);

    return $response
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-Frame-Options', 'DENY')
        ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->withHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data: https:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
});

$app->addBodyParsingMiddleware();
$app->add(App\Middleware\I18nMiddleware::class);
$app->addRoutingMiddleware();

// Access and Audit Logging
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container): ResponseInterface {
    $accessLogger = $container->get('logger.access');
    $start = microtime(true);
    $response = $handler->handle($request);

    $requestId = (string) ($request->getAttribute('request_id') ?? '');
    $userAgent = substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255);
    $accessLogger->info('request', [
        'request_id' => $requestId !== '' ? $requestId : null,
        'user_id' => $_SESSION['user_id'] ?? null,
        'method' => $request->getMethod(),
        'path' => (string) $request->getUri()->getPath(),
        'status' => $response->getStatusCode(),
        'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        'ip_hash' => hash('sha256', (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')),
        'user_agent' => $userAgent,
        'context' => [
            'query' => $request->getUri()->getQuery(),
        ],
    ]);

    return $response;
});

$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($container): ResponseInterface {
    $auditLogger = $container->get('logger.audit');
    $startedAt = microtime(true);
    $response = $handler->handle($request);
    $duration = (int) round((microtime(true) - $startedAt) * 1000);
    $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
    $method = $request->getMethod();
    $path = (string) $request->getUri()->getPath();
    $status = $response->getStatusCode();
    $ipHash = hash('sha256', (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'));
    $userAgent = substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255);

    // 1. Permanent File Logging
    $auditLogger->info('audit', [
        'user_id' => $userId,
        'method' => $method,
        'path' => $path,
        'status_code' => $status,
        'ip_hash' => $ipHash,
        'user_agent' => $userAgent,
        'duration_ms' => $duration,
    ]);

    // 2. Short-term Database Logging (Optional)
    try {
        $pdo = $container->get(\PDO::class);
        $stmt = $pdo->prepare(
            'INSERT INTO system_audit_logs (user_id, method, path, status_code, ip_hash, user_agent, duration_ms, created_at)
             VALUES (:user_id, :method, :path, :status_code, :ip_hash, :user_agent, :duration_ms, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'method' => $method,
            'path' => $path,
            'status_code' => $status,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
            'duration_ms' => $duration,
        ]);
    } catch (\Throwable) {
        // DB logging failure is non-blocking
    }

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

$app->add(\App\Middleware\ApiAuthMiddleware::class);
$app->add(\App\Middleware\RequestIdMiddleware::class);

// Error Handling
$errorLogger = $container->get('logger.error');
$errorMiddleware = $app->addErrorMiddleware((bool) ($settings['app']['debug'] ?? false), true, true);
$errorMiddleware->setDefaultErrorHandler(
    function (
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) use ($errorLogger, $container): ResponseInterface {
        $statusCode = 500;
        $message = 'Internal server error';

        if ($exception instanceof HttpException) {
            $statusCode = (int) $exception->getCode();
            $message = $statusCode === 404 ? 'Not found' : $exception->getMessage();
        }

        if ($statusCode >= 500) {
            try {
                $errorLogger->error($exception->getMessage(), [
                    'request_id' => $request->getAttribute('request_id'),
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'method' => $request->getMethod(),
                    'path' => (string) $request->getUri()->getPath(),
                    'status' => $statusCode,
                    'duration_ms' => 0,
                    'ip_hash' => hash('sha256', (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')),
                    'user_agent' => substr((string) ($request->getHeaderLine('User-Agent') ?: ''), 0, 255),
                    'context' => ['trace' => $exception->getTraceAsString()],
                ]);
            } catch (\Throwable) {
                // Logger might fail if disk is full or permissions are wrong
            }
        }

        $path = $request->getUri()->getPath();
        $accept = $request->getHeaderLine('Accept');

        if (str_starts_with($path, '/api/') || str_contains($accept, 'application/json')) {
            return ResponseHelper::error($statusCode, $displayErrorDetails ? $exception->getMessage() : $message);
        }

        try {
            $webController = $container->get(\App\Controllers\WebController::class);
            $responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
            $response = $responseFactory->createResponse($statusCode);
            return $webController->renderError($request, $response, $statusCode, $message);
        } catch (\Throwable) {
            return ResponseHelper::error($statusCode, $message);
        }
    }
);
