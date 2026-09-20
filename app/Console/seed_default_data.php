#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Seed default Genres and Tags.
 * Usage: php app/Console/seed_default_data.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Config;

$basePath = dirname(__DIR__, 2);
Config::loadEnvironment($basePath);

$settings = Config::getSettings();
$db = $settings['database'];
$pdo = null;

try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
    $pdo = new \PDO($dsn, $db['username'], $db['password'], [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    ]);
    $pdo->beginTransaction();

    echo "🌱 Seeding default Taxonomy data...\n";

    // 1. Genres
    $genres = [
        ['Action', 'action', '{"icon":"flame","color":"#ef4444"}'],
        ['Adventure', 'adventure', '{"icon":"compass","color":"#f59e0b"}'],
        ['Fantasy', 'fantasy', '{"icon":"sparkles","color":"#8b5cf6"}'],
        ['Urban Fantasy', 'urban-fantasy', '{"icon":"building","color":"#6366f1"}'],
        ['Sci-Fi', 'sci-fi', '{"icon":"atom","color":"#06b6d4"}'],
        ['Cyberpunk', 'cyberpunk', '{"icon":"cpu","color":"#a855f7"}'],
        ['Romance', 'romance', '{"icon":"heart","color":"#ec4899"}'],
        ['Drama', 'drama', '{"icon":"drama","color":"#f97316"}'],
        ['Comedy', 'comedy', '{"icon":"laugh","color":"#eab308"}'],
        ['Slice of Life', 'slice-of-life', '{"icon":"leaf","color":"#22c55e"}'],
        ['Mystery', 'mystery', '{"icon":"search","color":"#64748b"}'],
        ['Thriller', 'thriller', '{"icon":"alarm","color":"#dc2626"}'],
        ['Horror', 'horror', '{"icon":"skull","color":"#7f1d1d"}'],
        ['Psychological', 'psychological', '{"icon":"brain","color":"#7c3aed"}'],
        ['Supernatural', 'supernatural', '{"icon":"ghost","color":"#4f46e5"}'],
        ['Historical', 'historical', '{"icon":"landmark","color":"#a16207"}'],
        ['Martial Arts', 'martial-arts', '{"icon":"swords","color":"#b91c1c"}'],
        ['Sports', 'sports', '{"icon":"trophy","color":"#16a34a"}'],
        ['Mecha', 'mecha', '{"icon":"bot","color":"#475569"}'],
        ['Military', 'military', '{"icon":"shield","color":"#4d7c0f"}'],
        ['School', 'school', '{"icon":"graduation","color":"#2563eb"}'],
        ['Ecchi', 'ecchi', '{"icon":"eye","color":"#db2777"}'],
        ['Harem', 'harem', '{"icon":"users","color":"#d946ef"}'],
        ['Reverse Harem', 'reverse-harem', '{"icon":"user-heart","color":"#c026d3"}'],
        ['Josei', 'josei', '{"icon":"flower","color":"#be185d"}'],
        ['Seinen', 'seinen', '{"icon":"badge","color":"#334155"}'],
        ['Shounen', 'shounen', '{"icon":"zap","color":"#ea580c"}'],
        ['Shoujo', 'shoujo', '{"icon":"flower","color":"#f472b6"}']
    ];

    $tags = [
        ['Overpowered MC', 'overpowered-mc', '{"icon":"crown","color":"#dc2626"}'],
        ['Reincarnation', 'reincarnation', '{"icon":"repeat","color":"#10b981"}'],
        ['System', 'system', '{"icon":"cpu","color":"#475569"}'],
        ['Level Up', 'level-up', '{"icon":"trending","color":"#22c55e"}'],
        ['Dungeon', 'dungeon', '{"icon":"door","color":"#475569"}'],
        ['Monsters', 'monsters', '{"icon":"axe","color":"#4b5563"}'],
        ['Magic', 'magic', '{"icon":"wand","color":"#7c3aed"}'],
        ['Cultivation', 'cultivation', '{"icon":"sprout","color":"#ca8a04"}'],
        ['Martial Arts', 'martial-arts-tag', '{"icon":"swords","color":"#991b1b"}'],
        ['Time Travel', 'time-travel', '{"icon":"hourglass","color":"#06b6d4"}'],
        ['Revenge', 'revenge', '{"icon":"swords","color":"#e11d48"}'],
        ['Survival', 'survival', '{"icon":"shield","color":"#65a30d"}'],
        ['Video Games', 'video-games', '{"icon":"gamepad","color":"#6366f1"}'],
        ['Virtual Reality', 'virtual-reality', '{"icon":"eye","color":"#7c3aed"}']
    ];

    $stmtTaxonomy = $pdo->prepare("INSERT INTO taxonomies (type, name, slug, ui_config) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), ui_config=JSON_MERGE_PATCH(COALESCE(taxonomies.ui_config, JSON_OBJECT()), VALUES(ui_config))");
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
        ['general', 'site_name', 'string', 'NM Reader'],
        ['general', 'site_slogan', 'string', 'En İyi Çevrimiçi Manga ve Novel Okuyucusu'],
        ['general', 'site_abbreviation', 'string', 'NMR'],
        ['general', 'site_description', 'string', 'Read manga, manhwa, webtoon and novels.'],
        ['general', 'default_language', 'string', 'tr'],
        ['general', 'footer_text', 'string', '© 2026 NM Reader. Tüm hakları saklıdır.'],
        ['appearance', 'default_theme', 'string', 'dark'],
        ['appearance', 'site_logo', 'string', '/assets/img/logo-header.svg'],
        ['appearance', 'logo_url', 'string', '/assets/img/logo-footer.svg'],
        ['appearance', 'favicon_url', 'string', '/favicon.ico'],
        ['appearance', 'default_profile_image', 'string', '/assets/img/default-profile.svg'],
        ['appearance', 'default_content_cover_image', 'string', '/assets/img/covers/placeholder.svg'],
        ['security', 'maintenance_mode', 'bool', 'false'],
        ['security', 'maintenance_whitelist_ips', 'json', '["127.0.0.1", "::1"]'],
        ['mail', 'mail_enabled', 'bool', 'true'],
        ['mail', 'mail_send_on_register', 'bool', 'true'],
        ['mail', 'email_verification_required', 'bool', 'false'],
        ['mail', 'mail_from_name', 'string', 'NM Reader'],
        ['mail', 'mail_from_address', 'string', 'noreply@nmreader.com'],
        ['mail', 'password_reset_subject', 'string', 'Şifre Sıfırlama Talebi - {{site_name}}'],
        ['mail', 'password_reset_body', 'string', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">Şifre Sıfırlama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} hesabınız için bir şifre sıfırlama talebi aldık. Şifrenizi yenilemek için aşağıdaki butona tıklayabilirsiniz:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">Şifremi Sıfırla</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir. Talebi siz yapmadıysanız bu e-postayı güvenle silebilirsiniz.</p></div>'],
        ['mail', 'email_verification_subject', 'string', 'E-posta Adresinizi Doğrulayın - {{site_name}}'],
        ['mail', 'email_verification_body', 'string', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">E-posta Doğrulama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} ailesine hoş geldiniz! Hesabınızı doğrulamak ve güvenliğinizi sağlamak için lütfen aşağıdaki butona tıklayın:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #e11d48; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">E-postamı Doğrula</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir.</p></div>']
    ];

    $stmtSettings = $pdo->prepare(
        "INSERT INTO system_settings (`group`, `key`, `type`, `value`)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE `group`=VALUES(`group`), `type`=VALUES(`type`), `value`=VALUES(`value`)"
    );
    foreach ($settingsData as $s) {
        $stmtSettings->execute($s);
    }
    echo "✅ Default system settings seeded.\n";

    $pdo->commit();

    echo "\n🎉 Database seeding completed successfully!\n";

} catch (\Throwable $e) {
    if ($pdo instanceof \PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
