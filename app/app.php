<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use App\Config;

$basePath = dirname(__DIR__);

// Load environment variables
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createUnsafeImmutable($basePath);
    $dotenv->load();
}

$settings = Config::getSettings();
date_default_timezone_set((string) $settings['app']['timezone']);

$container = require __DIR__ . '/dependencies.php';
AppFactory::setContainer($container);

$app = AppFactory::create();

// Enable FastRoute precompiled route caching in production for instant dispatching
if (!(bool) ($settings['app']['debug'] ?? false)) {
    $cachePath = (string) ($settings['cache']['path'] ?? ($basePath . '/storage/cache'));
    $routeSources = [__DIR__ . '/Config.php', $basePath . '/public/index.php'];
    $routeSignature = hash_init('sha256');
    foreach ($routeSources as $routeSource) {
        hash_update_file($routeSignature, $routeSource);
    }
    $routeHash = substr(hash_final($routeSignature), 0, 16);
    $cacheFile = $cachePath . '/fastroute_cache_' . $routeHash . '.php';

    // Route cache is generated and recoverable. Remove obsolete signatures so
    // deployments cannot keep serving a stale route table indefinitely.
    foreach (glob($cachePath . '/fastroute_cache*.php') ?: [] as $oldCacheFile) {
        if ($oldCacheFile !== $cacheFile && is_file($oldCacheFile)) {
            @unlink($oldCacheFile);
        }
    }
    $app->getRouteCollector()->setCacheFile($cacheFile);
}

require __DIR__ . '/middleware.php';

// Register all routes from the unified Config class
Config::registerRoutes($app);

return $app;
