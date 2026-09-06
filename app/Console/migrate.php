#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Config;
use Dotenv\Dotenv;

$baseDir = dirname(__DIR__, 2);
require $baseDir . '/vendor/autoload.php';

if (is_file($baseDir . '/.env')) {
    Dotenv::createUnsafeImmutable($baseDir)->load();
}

$settings = Config::getSettings();
$db = $settings['database'];
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']),
        $db['username'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $exception) {
    fwrite(STDERR, 'Database connection failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$migrationFiles = glob($baseDir . '/app/database/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);
$statusOnly = in_array('--status', $argv, true);

if ($statusOnly) {
    try {
        $appliedRows = $pdo->query('SELECT version, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $exception) {
        // SQLSTATE 42S02: table does not exist. Status must remain read-only.
        if ((string) $exception->getCode() !== '42S02') throw $exception;
        $appliedRows = [];
    }
    foreach ($migrationFiles as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        echo sprintf("[%s] %s\n", array_key_exists($version, $appliedRows) ? 'applied' : 'pending', $version);
    }
    exit(0);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(191) NOT NULL PRIMARY KEY,
        checksum CHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$locked = (int) $pdo->query("SELECT GET_LOCK('nm_reader_schema_migrations', 10)")->fetchColumn() === 1;
if (!$locked) {
    fwrite(STDERR, "Could not acquire migration lock.\n");
    exit(1);
}

try {
    // Reload after acquiring the lock so two migration processes cannot apply
    // the same version from a stale pre-lock snapshot.
    $appliedRows = $pdo->query('SELECT version, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($migrationFiles as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        $sql = file_get_contents($file);
        if ($sql === false) throw new RuntimeException("Cannot read migration: {$version}");
        $checksum = hash('sha256', $sql);

        if (isset($appliedRows[$version])) {
            $recorded = (string) $appliedRows[$version];
            if ($recorded !== 'installed_from_schema' && !hash_equals($recorded, $checksum)) {
                throw new RuntimeException("Applied migration checksum changed: {$version}");
            }
            echo "Already applied: {$version}\n";
            continue;
        }

        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (:version, :checksum)');
        $stmt->execute(['version' => $version, 'checksum' => $checksum]);
        echo "Applied: {$version}\n";
    }
} finally {
    $pdo->query("SELECT RELEASE_LOCK('nm_reader_schema_migrations')");
}
