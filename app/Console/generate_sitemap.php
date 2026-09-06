#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Pre-generate sitemap.xml.
 * Usage: php app/Console/generate_sitemap.php
 */

use App\Services\SitemapService;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
$container = require __DIR__ . '/../dependencies.php';

/** @var SitemapService $sitemapService */
$sitemapService = $container->get(SitemapService::class);

echo "--- Sitemap Generator ---\n";

try {
    $result = $sitemapService->generateAndSave();
    foreach ($result['output'] ?? [] as $line) {
        echo $line . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
