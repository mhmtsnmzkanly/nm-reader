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
    $cacheFile = (string) ($settings['cache']['path'] ?? ($basePath . '/storage/cache')) . '/fastroute_cache.php';
    $app->getRouteCollector()->setCacheFile($cacheFile);
}

require __DIR__ . '/middleware.php';

// Register all routes from the unified Config class
Config::registerRoutes($app);

return $app;
