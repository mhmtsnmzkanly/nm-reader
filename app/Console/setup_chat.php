#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Setup Global Chat Blog Entry.
 * Usage: php app/Console/setup_chat.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Config;
use Dotenv\Dotenv;
use App\Repositories\BlogRepository;
use App\Repositories\AdminConsoleRepository;
use App\Services\EntityIdService;

$basePath = dirname(__DIR__, 2);
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$container = require __DIR__ . '/../dependencies.php';
/** @var BlogRepository $blogs */
$blogs = $container->get(BlogRepository::class);
/** @var AdminConsoleRepository $adminRepo */
$adminRepo = $container->get(AdminConsoleRepository::class);
/** @var EntityIdService $ids */
$ids = $container->get(EntityIdService::class);

try {
    echo "🔍 Checking for 'global-chat' blog...\n";
    
    if ($blogs->existsBySlug('global-chat')) {
        echo "✅ Chat blog already exists.\n";
        exit(0);
    }

    echo "⚙️ Creating 'global-chat' blog entry...\n";

    // Find an admin user to own the blog
    $users = $adminRepo->listAllUsersForSelect();
    $ownerId = $users[0]['id'] ?? null;

    if (!$ownerId) {
        echo "❌ No users found in database. Please create a user first.\n";
        exit(1);
    }

    $blogId = $ids->generate(6);
    $blogs->create(
        $blogId,
        $ownerId,
        'Global Chat',
        'global-chat',
        'Welcome to the community chat! Feel free to discuss anything here.'
    );

    // Auto-approve it
    $blogs->approve($blogId, $ownerId);

    echo "🎉 Success! Global Chat blog created (ID: $blogId) owned by User ID: $ownerId.\n";

} catch (\Throwable $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
