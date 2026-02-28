#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Warm up application caches.
 * Usage: php app/Console/cache_warmer.php
 */

use App\Services\SeriesService;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
$container = require __DIR__ . '/../dependencies.php';

/** @var SeriesService $series */
$series = $container->get(SeriesService::class);

echo "--- Cache Warmer ---
";

try {
    echo "[1/2] Warming Homepage data...
";
    $series->home(1, 20);
    echo "SUCCESS: Homepage warmed.
";

    echo "[2/2] Warming Type listings...
";
    $types = ['manga', 'novel', 'webtoon', 'manhwa', 'manhua', 'light-novel', 'web-novel'];
    foreach ($types as $type) {
        $series->byType($type, 1, 20);
        echo " - $type: OK
";
    }

    echo "--- Cache Warmup Completed ---
";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "
";
}
