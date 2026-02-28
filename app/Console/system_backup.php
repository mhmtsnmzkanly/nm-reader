#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Script for System Backups (Database & Media).
 * 
 * This script:
 * 1. Performs a MySQL dump using credentials from .env.
 * 2. Compresses the 'public/uploads' directory.
 * 3. Stores everything in 'storage/backups' with a timestamp.
 * 4. Deletes backups older than 7 days.
 */

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
$db = $settings['database'];
$backupDir = $basePath . '/storage/backups';
$timestamp = date('Y-m-d_H-i-s');

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

echo "--- Starting System Backup [$timestamp] ---\n";

// 1. Database Backup
$dbFile = "$backupDir/db_$timestamp.sql";
echo "[1/3] Exporting database...\n";

// Use environment variables for mysqldump
$passArg = $db['password'] !== '' ? "-p" . escapeshellarg($db['password']) : "";
$cmd = sprintf(
    'mysqldump -h %s -P %d -u %s %s %s > %s',
    escapeshellarg($db['host']),
    $db['port'],
    escapeshellarg($db['username']),
    $passArg,
    escapeshellarg($db['database']),
    escapeshellarg($dbFile)
);

exec($cmd, $output, $returnVar);

if ($returnVar !== 0) {
    echo "ERROR: Database export failed!\n";
} else {
    // Compress SQL
    exec("gzip " . escapeshellarg($dbFile));
    echo "SUCCESS: Database exported and compressed.\n";
}

// 2. Media Backup (Uploads)
echo "[2/3] Archiving media uploads...\n";
$mediaFile = "$backupDir/media_$timestamp.tar.gz";
$mediaPath = $basePath . '/public/uploads';

if (is_dir($mediaPath)) {
    $cmd = sprintf('tar -czf %s -C %s .', escapeshellarg($mediaFile), escapeshellarg($mediaPath));
    exec($cmd, $output, $returnVar);
    
    if ($returnVar === 0) {
        echo "SUCCESS: Media uploads archived.\n";
    } else {
        echo "ERROR: Media archive failed!\n";
    }
} else {
    echo "SKIP: Media path not found ($mediaPath).\n";
}

// 3. Cleanup Old Backups (Retention: 7 Days)
echo "[3/3] Cleaning up old backups...
";
$files = glob("$backupDir/*");
$now = time();
$deletedCount = 0;

foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file) >= 7 * 86400)) {
        unlink($file);
        $deletedCount++;
    }
}

echo "CLEANUP: Removed $deletedCount old backup files.
";
echo "--- Backup Process Completed ---
";
