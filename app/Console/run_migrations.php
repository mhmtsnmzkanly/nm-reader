#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use PDO;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
date_default_timezone_set((string) ($settings['app']['timezone'] ?? 'UTC'));
$container = require __DIR__ . '/../dependencies.php';

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$migrationDir = __DIR__ . '/../database/migrations';
$defaultFiles = [
    '2026_03_18_analytics_search_logs.sql',
    '2026_03_18_hot_path_indexes.sql',
    '2026_03_18_search_fulltext.sql',
];

$args = array_slice($argv, 1);
$only = null;
$runAll = false;
foreach ($args as $arg) {
    if ($arg === '--all') {
        $runAll = true;
    }
    if (str_starts_with($arg, '--only=')) {
        $only = array_filter(array_map('trim', explode(',', substr($arg, 7))));
    }
}

if ($runAll) {
    $files = array_values(array_filter(scandir($migrationDir) ?: [], static fn (string $f): bool => str_ends_with($f, '.sql')));
    sort($files);
} elseif (is_array($only) && $only !== []) {
    $files = $only;
} else {
    $files = $defaultFiles;
}

$results = [];
foreach ($files as $file) {
    $path = $migrationDir . '/' . $file;
    if (!is_file($path)) {
        $results[] = ['file' => $file, 'status' => 'missing'];
        continue;
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        $results[] = ['file' => $file, 'status' => 'read_failed'];
        continue;
    }

    $statements = [];
    foreach (explode("\n", $sql) as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--')) {
            continue;
        }
        $statements[] = $line;
    }

    $flatSql = implode("\n", $statements);
    $chunks = array_filter(array_map('trim', explode(';', $flatSql)));

    $applied = 0;
    $pdo->beginTransaction();
    try {
        foreach ($chunks as $chunk) {
            $pdo->exec($chunk);
            $applied++;
        }
        $pdo->commit();
        $results[] = ['file' => $file, 'status' => 'ok', 'statements' => $applied];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $results[] = ['file' => $file, 'status' => 'failed', 'error' => $e->getMessage()];
    }
}

fwrite(STDOUT, json_encode(['ran' => $files, 'results' => $results], JSON_UNESCAPED_SLASHES) . PHP_EOL);
