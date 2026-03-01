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

    $stmtGenre = $pdo->prepare("INSERT INTO series_genres (name, slug, ui_config) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), ui_config=VALUES(ui_config)");
    foreach ($genres as $g) {
        $stmtGenre->execute($g);
    }
    echo "✅ " . count($genres) . " Genres seeded.\n";

    // 2. Tags
    $tags = [
        ['Game System', 'game-system', '{"color": "primary"}'],
        ['Leveling', 'leveling', '{"color": "success"}'],
        ['Skill System', 'skill-system', '{"color": "info"}'],
        ['Status Window', 'status-window', '{"color": "secondary"}'],
        ['Dungeon', 'dungeon', '{"color": "dark"}'],
        ['Tower', 'tower', '{"color": "warning"}'],
        ['Raid', 'raid', '{"color": "danger"}'],
        ['OP Protagonist', 'op-protagonist', '{"color": "danger"}'],
        ['Genius MC', 'genius-mc', '{"color": "dark"}'],
        ['Weak to Strong', 'weak-to-strong', '{"color": "success"}'],
        ['Regression', 'regression', '{"color": "warning"}'],
        ['Reincarnation', 'reincarnation', '{"color": "success"}'],
        ['Time Travel', 'time-travel', '{"color": "info"}'],
        ['Second Chance', 'second-chance', '{"color": "primary"}'],
        ['Transmigration', 'transmigration', '{"color": "secondary"}'],
        ['Isekai', 'isekai', '{"color": "success"}'],
        ['Cultivation', 'cultivation', '{"color": "warning"}'],
        ['Murim', 'murim', '{"color": "dark"}'],
        ['Swordsmanship', 'swordsmanship', '{"color": "dark"}'],
        ['Necromancer', 'necromancer', '{"color": "dark"}'],
        ['Assassin', 'assassin', '{"color": "danger"}'],
        ['Magic Academy', 'magic-academy', '{"color": "primary"}'],
        ['Summoner', 'summoner', '{"color": "info"}'],
        ['Tamer', 'tamer', '{"color": "secondary"}'],
        ['Alchemy', 'alchemy', '{"color": "warning"}'],
        ['Blacksmith', 'blacksmith', '{"color": "dark"}'],
        ['Demons', 'demons', '{"color": "danger"}'],
        ['Angels', 'angels', '{"color": "info"}'],
        ['Gods', 'gods', '{"color": "warning"}'],
        ['Dragons', 'dragons', '{"color": "danger"}'],
        ['Monsters', 'monsters', '{"color": "dark"}'],
        ['Vampires', 'vampires', '{"color": "danger"}'],
        ['Werewolves', 'werewolves', '{"color": "secondary"}'],
        ['Survival', 'survival', '{"color": "warning"}'],
        ['Revenge', 'revenge', '{"color": "danger"}'],
        ['Betrayal', 'betrayal', '{"color": "dark"}'],
        ['Political', 'political', '{"color": "secondary"}'],
        ['Kingdom Building', 'kingdom-building', '{"color": "primary"}'],
        ['Empire', 'empire', '{"color": "dark"}'],
        ['War', 'war', '{"color": "danger"}'],
        ['Post-Apocalyptic', 'post-apocalyptic', '{"color": "dark"}'],
        ['Magic', 'magic', '{"color": "info"}'],
        ['Elemental Powers', 'elemental-powers', '{"color": "primary"}'],
        ['Superpowers', 'superpowers', '{"color": "info"}'],
        ['System Admin', 'system-admin', '{"color": "dark"}'],
        ['Virtual Reality', 'virtual-reality', '{"color": "secondary"}'],
        ['VRMMO', 'vrmmo', '{"color": "primary"}'],
        ['Love Triangle', 'love-triangle', '{"color": "warning"}'],
        ['Slow Burn', 'slow-burn', '{"color": "secondary"}'],
        ['Enemies to Lovers', 'enemies-to-lovers', '{"color": "danger"}'],
        ['Childhood Friends', 'childhood-friends', '{"color": "primary"}'],
        ['Contract Marriage', 'contract-marriage', '{"color": "dark"}'],
        ['Marriage of Convenience', 'marriage-of-convenience', '{"color": "dark"}'],
        ['Harem', 'harem', '{"color": "warning"}'],
        ['Reverse Harem', 'reverse-harem', '{"color": "info"}'],
        ['Dark Fantasy', 'dark-fantasy', '{"color": "dark"}'],
        ['Tragedy', 'tragedy', '{"color": "danger"}'],
        ['Psychological Trauma', 'psychological-trauma', '{"color": "dark"}'],
        ['Mind Games', 'mind-games', '{"color": "secondary"}'],
        ['Antihero', 'antihero', '{"color": "dark"}'],
        ['Villain Protagonist', 'villain-protagonist', '{"color": "danger"}'],
        ['School Life', 'school-life', '{"color": "primary"}'],
        ['Academy', 'academy', '{"color": "info"}'],
        ['Office Romance', 'office-romance', '{"color": "secondary"}'],
        ['Age Gap', 'age-gap', '{"color": "warning"}'],
        ['Gender Bender', 'gender-bender', '{"color": "secondary"}'],
        ['Crossdressing', 'crossdressing', '{"color": "dark"}'],
        ['Mecha Combat', 'mecha-combat', '{"color": "primary"}'],
        ['Military Strategy', 'military-strategy', '{"color": "dark"}'],
        ['Space Opera', 'space-opera', '{"color": "info"}'],
        ['Cyberpunk Elements', 'cyberpunk-elements', '{"color": "secondary"}'],
        ['AI', 'ai', '{"color": "primary"}'],
        ['Robots', 'robots', '{"color": "info"}'],
        ['Cooking', 'cooking', '{"color": "success"}'],
        ['Crafting', 'crafting', '{"color": "secondary"}'],
        ['Farming', 'farming', '{"color": "success"}'],
        ['Merchant', 'merchant', '{"color": "warning"}'],
        ['Healer', 'healer', '{"color": "success"}']
    ];

    $stmtTag = $pdo->prepare("INSERT INTO series_tags (name, slug, ui_config) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), ui_config=VALUES(ui_config)");
    foreach ($tags as $t) {
        $stmtTag->execute($t);
    }
    echo "✅ " . count($tags) . " Tags seeded.\n";

    echo "\n🎉 Database seeding completed successfully!\n";

} catch (\Throwable $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
