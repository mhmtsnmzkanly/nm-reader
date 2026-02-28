#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\AnalyticsAggregationService;
use Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
date_default_timezone_set((string) ($settings['app']['timezone'] ?? 'UTC'));
$container = require __DIR__ . '/../dependencies.php';

$days = 30;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = (int) substr($arg, 7);
    }
}
$days = max(1, min(365, $days));

/** @var AnalyticsAggregationService $aggregation */
$aggregation = $container->get(AnalyticsAggregationService::class);
$result = $aggregation->aggregateAll($days);

fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);

