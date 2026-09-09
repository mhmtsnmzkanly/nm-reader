#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Pre-generate sitemap.xml.
 * Usage: php app/Console/generate_sitemap.php
 */

use App\Services\SitemapService;
use App\Config;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

Config::loadEnvironment($basePath);

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
