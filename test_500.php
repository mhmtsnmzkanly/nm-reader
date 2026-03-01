<?php
require 'vendor/autoload.php';

// Mocking/Bootstrapping minimal environment to test the repo
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbName = $_ENV['DB_DATABASE'] ?? 'nm-reader';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $repo = new App\Repositories\AdminConsoleRepository($pdo);
    echo "Testing AdminConsoleRepository::listContents(1, 20)...
";
    $result = $repo->listContents(1, 20);
    echo "Success! Found " . count($result['items'] ?? []) . " items.
";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "
";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "
";
}
