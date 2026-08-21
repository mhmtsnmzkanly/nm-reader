#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Worker for processing the Job Queue.
 * 
 * Run this script via CLI to process background tasks (e.g., email notifications).
 * Usage: php app/Console/queue_worker.php [--sleep=5] [--limit=100]
 */

use App\Services\QueueService;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
$container = require __DIR__ . '/../dependencies.php';

$sleep = 5;
$limit = 50;
$once = false;

// Parse arguments
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--sleep=')) $sleep = (int) substr($arg, 8);
    if (str_starts_with($arg, '--limit=')) $limit = (int) substr($arg, 8);
    if ($arg === '--once') $once = true;
}

/** @var QueueService $queue */
$queue = $container->get(QueueService::class);

echo "--- Job Queue Worker Started (PID: " . getmypid() . ") ---" . PHP_EOL;
echo "Config: mode=" . ($once ? 'single-run' : 'daemon') . ", sleep=$sleep seconds, batch_limit=$limit jobs" . PHP_EOL;

// Graceful stop handling (SIGTERM / SIGINT)
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function() use (&$running) { $running = false; });
    pcntl_signal(SIGINT, function() use (&$running) { $running = false; });
}

while ($running) {
    try {
        $results = $queue->runOnce($limit);
        
        if ($results['processed'] > 0 || $once) {
            echo "[" . date('Y-m-d H:i:s') . "] Processed: " . $results['processed'] . " | Failed: " . $results['failed'] . " | Scanned: " . $results['scanned'] . PHP_EOL;
        }

        if ($once) {
            break;
        }
        
        // Wait before next check
        sleep($sleep);
    } catch (\Throwable $e) {
        echo "CRITICAL ERROR: " . $e->getMessage() . PHP_EOL;
        if ($once) {
            break;
        }
        sleep(10); // Wait longer on crash
    }
}

echo "--- Worker Stopped Gracefully ---
";
