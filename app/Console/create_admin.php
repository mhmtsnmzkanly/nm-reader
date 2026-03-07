#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Create a new Admin User.
 * Usage: php app/Console/create_admin.php
 */

use App\Services\AuthService;
use App\Services\AdminConsoleService;

require __DIR__ . '/../../vendor/autoload.php';

$basePath = dirname(__DIR__, 2);

// Load Environment
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = \App\Config::getSettings();
$container = require __DIR__ . '/../dependencies.php';

/** @var AuthService $auth */
$auth = $container->get(AuthService::class);
/** @var AdminConsoleService $admin */
$admin = $container->get(AdminConsoleService::class);

echo "--- Admin Creation Tool ---
";

$username = readline("Username (3-30 chars): ");
$email = readline("Email: ");
$password = readline("Password (8+ chars): ");

try {
    // 1. Register User
    $user = $auth->register([
        'username' => $username,
        'email' => $email,
        'password' => $password
    ]);

    // 2. Assign Admin Role
    $admin->assignRoleToUser([
        'user_id' => $user['id'],
        'role_slug' => 'admin'
    ]);

    echo "SUCCESS: Admin '{$username}' created with ID: {$user['id']}
";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "
";
}
