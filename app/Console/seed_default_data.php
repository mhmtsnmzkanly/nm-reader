#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Seed default Genres and Tags.
 * Usage: php app/Console/seed_default_data.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Config;
use Dotenv\Dotenv;

// 1. Load .env manually if it exists to override defaults
$basePath = dirname(__DIR__, 2);
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->load();
}

$settings = Config::getSettings();
$db = $settings['database'];

try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
    $pdo = new \PDO($dsn, $db['username'], $db['password'], [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    ]);

    echo "🌱 Seeding default Taxonomy data...\n";

    // 1. Genres
    $genres = [
        ['Action', 'action', '{"icon": "bi-fire"}'],
        ['Adventure', 'adventure', '{"icon": "bi-compass"}'],
        ['Fantasy', 'fantasy', '{"icon": "bi-stars"}'],
        ['Urban Fantasy', 'urban-fantasy', '{"icon": "bi-building"}'],
        ['Sci-Fi', 'sci-fi', '{"icon": "bi-cpu"}'],
        ['Cyberpunk', 'cyberpunk', '{"icon": "bi-cpu-fill"}'],
        ['Romance', 'romance', '{"icon": "bi-heart"}'],
        ['Drama', 'drama', '{"icon": "bi-mask"}'],
        ['Comedy', 'comedy', '{"icon": "bi-emoji-laughing"}'],
        ['Slice of Life', 'slice-of-life', '{"icon": "bi-cup"}'],
        ['Mystery', 'mystery', '{"icon": "bi-search"}'],
        ['Thriller', 'thriller', '{"icon": "bi-alarm"}'],
        ['Horror', 'horror', '{"icon": "bi-bug"}'],
        ['Psychological', 'psychological', '{"icon": "bi-brain"}'],
        ['Supernatural', 'supernatural', '{"icon": "bi-ghost"}'],
        ['Historical', 'historical', '{"icon": "bi-journal"}'],
        ['Martial Arts', 'martial-arts', '{"icon": "bi-person"}'],
        ['Sports', 'sports', '{"icon": "bi-trophy"}'],
        ['Mecha', 'mecha', '{"icon": "bi-gear"}'],
        ['Military', 'military', '{"icon": "bi-shield"}'],
        ['School', 'school', '{"icon": "bi-mortarboard"}'],
        ['Ecchi', 'ecchi', '{"icon": "bi-eye"}'],
        ['Harem', 'harem', '{"icon": "bi-people"}'],
        ['Reverse Harem', 'reverse-harem', '{"icon": "bi-people-fill"}'],
        ['Josei', 'josei', '{"icon": "bi-person-heart"}'],
        ['Seinen', 'seinen', '{"icon": "bi-person-vcard"}'],
        ['Shounen', 'shounen', '{"icon": "bi-lightning"}'],
        ['Shoujo', 'shoujo', '{"icon": "bi-flower1"}']
    ];

    $stmtTaxonomy = $pdo->prepare("INSERT INTO taxonomies (type, name, slug, ui_config) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), ui_config=VALUES(ui_config)");
    foreach ($genres as $g) {
        $stmtTaxonomy->execute(['genre', $g[0], $g[1], $g[2]]);
    }
    echo "✅ " . count($genres) . " Genres seeded into taxonomies.\n";

    foreach ($tags as $t) {
        $stmtTaxonomy->execute(['tag', $t[0], $t[1], $t[2]]);
    }
    echo "✅ " . count($tags) . " Tags seeded into taxonomies.\n";

    // 3. Seed Default Coin Catalog Packages & Feature Passes
    $packages = [
        ['coin_package', 'pkg_starter', 'Starter Coin Pack', 100, 10, 19.99, 'TRY', 1, 1],
        ['coin_package', 'pkg_popular', 'Popular Coin Pack', 500, 75, 89.99, 'TRY', 1, 2],
        ['coin_package', 'pkg_vip', 'VIP Ultra Pack', 1200, 250, 199.99, 'TRY', 1, 3],
        ['feature_pass', 'ad_free', 'Ad-Free Pass (30 Days)', 0, 0, 0, 'TRY', 1, 4],
        ['feature_pass', 'early_access', 'VIP Early Access (30 Days)', 0, 0, 0, 'TRY', 1, 5],
    ];

    $stmtCatalog = $pdo->prepare(
        "INSERT INTO coin_catalog (catalog_type, item_key, name, coin_amount, bonus_coin, fiat_price, currency, is_active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name=VALUES(name), coin_amount=VALUES(coin_amount), bonus_coin=VALUES(bonus_coin), fiat_price=VALUES(fiat_price), is_active=VALUES(is_active), sort_order=VALUES(sort_order)"
    );
    foreach ($packages as $pkg) {
        $stmtCatalog->execute($pkg);
    }
    // 4. Seed Default System Settings
    $settingsData = [
        ['site_name', 'NM Reader', 'general'],
        ['site_slogan', 'En İyi Çevrimiçi Manga ve Novel Okuyucusu', 'general'],
        ['default_theme', 'dark', 'appearance'],
        ['logo_url', '', 'appearance'],
        ['favicon_url', '', 'appearance'],
        ['footer_text', '© 2026 NM Reader. Tüm hakları saklıdır.', 'general'],
        ['maintenance_mode', 'false', 'security'],
        ['maintenance_whitelist_ips', '["127.0.0.1", "::1"]', 'security']
    ];

    $stmtSettings = $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, setting_group)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_group=VALUES(setting_group)"
    );
    foreach ($settingsData as $s) {
        $stmtSettings->execute($s);
    }
    echo "✅ Default system settings seeded.\n";

    echo "\n🎉 Database seeding completed successfully!\n";

} catch (\Throwable $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
