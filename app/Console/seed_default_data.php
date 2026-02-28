#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Tool to Seed default Genres and Tags.
 * Usage: php app/Console/seed_default_data.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Config;
use PDO;

$settings = Config::getSettings();
$db = $settings['database'];

try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "🌱 Seeding default Taxonomy data...
";

    // 1. Genres
    $genres = [
        [1,'Dungeon','dungeon', '{"icon": "bi-grid"}'], [2,'Leveling','leveling', '{"icon": "bi-graph-up"}'],
        [3,'Regression','regression', '{"icon": "bi-arrow-left"}'], [4,'Game System','game-system', '{"icon": "bi-controller"}'],
        [5,'World Building','world-building', '{"icon": "bi-globe"}'], [6,'Tower','tower', '{"icon": "bi-building"}'],
        [7,'Pirates','pirates', '{"icon": "bi-water"}'], [8,'Magic','magic', '{"icon": "bi-magic"}'],
        [9,'Reincarnation','reincarnation', '{"icon": "bi-recycle"}'], [10,'Op Protagonist','op-protagonist', '{"icon": "bi-lightning"}'],
        [11,'Action','action', '{"icon": "bi-fire"}'], [12,'SciFi','scifi', '{"icon": "bi-cpu"}'],
        [13,'Adventure','adventure', '{"icon": "bi-compass"}'], [14,'Fantasy','fantasy', '{"icon": "bi-stars"}'],
        [15,'Drama','drama', '{"icon": "bi-mask"}'], [16,'Romance','romance', '{"icon": "bi-heart"}'],
        [17,'Comedy','comedy', '{"icon": "bi-emoji-laughing"}'], [18,'Mystery','mystery', '{"icon": "bi-search"}'],
        [19,'Supernatural','supernatural', '{"icon": "bi-ghost"}'], [20,'Horror','horror', '{"icon": "bi-bug"}'],
        [21,'Thriller','thriller', '{"icon": "bi-alarm"}'], [22,'Martial Arts','martial-arts', '{"icon": "bi-person"}'],
        [23,'School Life','school-life', '{"icon": "bi-mortarboard"}'], [24,'Slice of Life','slice-of-life', '{"icon": "bi-cup"}'],
        [25,'Historical','historical', '{"icon": "bi-journal"}'], [26,'Psychological','psychological', '{"icon": "bi-brain"}'],
        [27,'Seinen','seinen', '{"icon": "bi-person-vcard"}'], [28,'Shounen','shounen', '{"icon": "bi-person"}'],
        [29,'Shoujo','shoujo', '{"icon": "bi-person-heart"}'], [30,'Isekai','isekai', '{"icon": "bi-door-open"}']
    ];

    $stmtGenre = $pdo->prepare("INSERT INTO series_genres (id, name, slug, ui_config) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), ui_config=VALUES(ui_config)");
    foreach ($genres as $g) {
        $stmtGenre->execute($g);
    }
    echo "✅ " . count($genres) . " Genres seeded.
";

    // 2. Tags
    $tags = [
        [1,'Action','action', '{"color": "primary"}'], [2,'Adventure','adventure', '{"color": "success"}'],
        [3,'Fantasy','fantasy', '{"color": "info"}'], [4,'Drama','drama', '{"color": "warning"}'],
        [5,'Mystery','mystery', '{"color": "dark"}'], [6,'Supernatural','supernatural', '{"color": "secondary"}'],
        [7,'Martial Arts','martial-arts', '{"color": "danger"}'], [8,'Comedy','comedy', '{"color": "info"}'],
        [9,'Shounen','shounen', '{"color": "primary"}'], [10,'Isekai','isekai', '{"color": "success"}']
    ];

    $stmtTag = $pdo->prepare("INSERT INTO series_tags (id, name, slug, ui_config) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), ui_config=VALUES(ui_config)");
    foreach ($tags as $t) {
        $stmtTag->execute($t);
    }
    echo "✅ " . count($tags) . " Tags seeded.
";

    echo "
🎉 Database seeding completed successfully!
";

} catch (Throwable $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "
";
    exit(1);
}
