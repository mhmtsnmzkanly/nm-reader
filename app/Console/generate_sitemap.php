#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Pre-generate sitemap.xml.
 * Usage: php app/Console/generate_sitemap.php
 */

use App\Controllers\WebController;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
$container = require __DIR__ . '/../dependencies.php';

/** @var WebController $web */
$web = $container->get(WebController::class);

echo "--- Sitemap Generator ---
";

try {
    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest('GET', '/sitemap.xml');
    
    $responseFactory = new ResponseFactory();
    $response = $responseFactory->createResponse();

    $response = $web->sitemapXml($request, $response);
    
    $xmlContent = (string) $response->getBody();
    $filePath = $basePath . '/public/sitemap.xml';
    
    file_put_contents($filePath, $xmlContent);
    
    echo "SUCCESS: Sitemap saved to $filePath
";
    echo "Size: " . number_format(strlen($xmlContent) / 1024, 2) . " KB
";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "
";
}
