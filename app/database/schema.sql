/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

-- --------------------------------------------------------
-- Core Tables
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` char(8) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `roles` varchar(255) DEFAULT '4',
  `api_token` varchar(64) DEFAULT NULL COMMENT 'SHA-256 hex digest; raw token is never stored',
  `api_token_expires_at` datetime DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uniq_api_token` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `series`;
CREATE TABLE `series` (
  `id` char(6) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `type` enum('manga','manhua','manhwa','webtoon','novel','light_novel','web_novel','light-novel','web-novel') NOT NULL,
  `is_adult` tinyint(1) NOT NULL DEFAULT 0,
  `is_members_only` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ongoing','completed','hiatus','dropped') NOT NULL DEFAULT 'ongoing',
  `lifecycle_status` enum('draft','scheduled','published','archived') NOT NULL DEFAULT 'published',
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `accent_color` varchar(7) DEFAULT '#2a2a2a',
  `description` text DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `artist` varchar(100) DEFAULT NULL,
  `alternative_titles` varchar(255) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `release_year` varchar(4) DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `chapter_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_series_deleted` (`deleted_at`),
  KEY `idx_series_lifecycle_schedule` (`lifecycle_status`,`scheduled_at`),
  FULLTEXT KEY `ft_series_search` (`title`,`slug`,`description`,`author`,`artist`,`alternative_titles`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `series_revisions`;
CREATE TABLE `series_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` char(6) NOT NULL,
  `moderator_user_id` char(8) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_series_revisions_series_created` (`series_id`,`created_at`),
  KEY `idx_series_revisions_moderator` (`moderator_user_id`),
  CONSTRAINT `fk_series_revisions_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_revisions_moderator` FOREIGN KEY (`moderator_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Unified Taxonomy & Legacy Taxonomies
-- --------------------------------------------------------

DROP TABLE IF EXISTS `taxonomies`;
CREATE TABLE `taxonomies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('genre','tag','theme','demographic') NOT NULL DEFAULT 'genre',
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `ui_config` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_taxonomy_type_slug` (`type`,`slug`),
  KEY `idx_taxonomy_type` (`type`),
  KEY `idx_taxonomy_type_order` (`type`,`sort_order`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `series_taxonomy_map`;
CREATE TABLE `series_taxonomy_map` (
  `content_id` char(6) NOT NULL,
  `taxonomy_id` int(11) NOT NULL,
  PRIMARY KEY (`content_id`,`taxonomy_id`),
  KEY `idx_taxonomy_map_tax` (`taxonomy_id`),
  CONSTRAINT `fk_tax_map_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tax_map_tax` FOREIGN KEY (`taxonomy_id`) REFERENCES `taxonomies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `taxonomies` (`type`, `name`, `slug`, `ui_config`) VALUES
('genre', 'Action', 'action', '{"icon": "bi-fire"}'),
('genre', 'Adventure', 'adventure', '{"icon": "bi-compass"}'),
('genre', 'Fantasy', 'fantasy', '{"icon": "bi-stars"}'),
('genre', 'Urban Fantasy', 'urban-fantasy', '{"icon": "bi-building"}'),
('genre', 'Sci-Fi', 'sci-fi', '{"icon": "bi-cpu"}'),
('genre', 'Cyberpunk', 'cyberpunk', '{"icon": "bi-cpu-fill"}'),
('genre', 'Romance', 'romance', '{"icon": "bi-heart"}'),
('genre', 'Drama', 'drama', '{"icon": "bi-mask"}'),
('genre', 'Comedy', 'comedy', '{"icon": "bi-emoji-laughing"}'),
('genre', 'Slice of Life', 'slice-of-life', '{"icon": "bi-cup"}'),
('genre', 'Mystery', 'mystery', '{"icon": "bi-search"}'),
('genre', 'Thriller', 'thriller', '{"icon": "bi-alarm"}'),
('genre', 'Horror', 'horror', '{"icon": "bi-bug"}'),
('genre', 'Psychological', 'psychological', '{"icon": "bi-brain"}'),
('genre', 'Supernatural', 'supernatural', '{"icon": "bi-ghost"}'),
('genre', 'Historical', 'historical', '{"icon": "bi-journal"}'),
('genre', 'Martial Arts', 'martial-arts', '{"icon": "bi-person"}'),
('genre', 'Sports', 'sports', '{"icon": "bi-trophy"}'),
('genre', 'Mecha', 'mecha', '{"icon": "bi-gear"}'),
('genre', 'Military', 'military', '{"icon": "bi-shield"}'),
('genre', 'School', 'school', '{"icon": "bi-mortarboard"}'),
('genre', 'Ecchi', 'ecchi', '{"icon": "bi-eye"}'),
('genre', 'Harem', 'harem', '{"icon": "bi-people"}'),
('genre', 'Reverse Harem', 'reverse-harem', '{"icon": "bi-people-fill"}'),
('genre', 'Josei', 'josei', '{"icon": "bi-person-heart"}'),
('genre', 'Seinen', 'seinen', '{"icon": "bi-person-vcard"}'),
('genre', 'Shounen', 'shounen', '{"icon": "bi-lightning"}'),
('genre', 'Shoujo', 'shoujo', '{"icon": "bi-flower1"}'),
('tag', 'Game System', 'game-system', '{"color": "primary"}'),
('tag', 'Leveling', 'leveling', '{"color": "success"}'),
('tag', 'Skill System', 'skill-system', '{"color": "info"}'),
('tag', 'Status Window', 'status-window', '{"color": "secondary"}'),
('tag', 'Dungeon', 'dungeon', '{"color": "dark"}'),
('tag', 'Tower', 'tower', '{"color": "warning"}'),
('tag', 'Raid', 'raid', '{"color": "danger"}'),
('tag', 'OP Protagonist', 'op-protagonist', '{"color": "danger"}'),
('tag', 'Genius MC', 'genius-mc', '{"color": "dark"}'),
('tag', 'Weak to Strong', 'weak-to-strong', '{"color": "success"}'),
('tag', 'Regression', 'regression', '{"color": "warning"}'),
('tag', 'Reincarnation', 'reincarnation', '{"color": "success"}'),
('tag', 'Time Travel', 'time-travel', '{"color": "info"}'),
('tag', 'Second Chance', 'second-chance', '{"color": "primary"}'),
('tag', 'Transmigration', 'transmigration', '{"color": "secondary"}'),
('tag', 'Isekai', 'isekai', '{"color": "success"}'),
('tag', 'Cultivation', 'cultivation', '{"color": "warning"}'),
('tag', 'Murim', 'murim', '{"color": "dark"}'),
('tag', 'Swordsmanship', 'swordsmanship', '{"color": "dark"}'),
('tag', 'Necromancer', 'necromancer', '{"color": "dark"}'),
('tag', 'Assassin', 'assassin', '{"color": "danger"}'),
('tag', 'Magic Academy', 'magic-academy', '{"color": "primary"}'),
('tag', 'Summoner', 'summoner', '{"color": "info"}'),
('tag', 'Tamer', 'tamer', '{"color": "secondary"}'),
('tag', 'Alchemy', 'alchemy', '{"color": "warning"}'),
('tag', 'Blacksmith', 'blacksmith', '{"color": "dark"}'),
('tag', 'Demons', 'demons', '{"color": "danger"}'),
('tag', 'Angels', 'angels', '{"color": "info"}'),
('tag', 'Gods', 'gods', '{"color": "warning"}'),
('tag', 'Dragons', 'dragons', '{"color": "danger"}'),
('tag', 'Monsters', 'monsters', '{"color": "dark"}'),
('tag', 'Vampires', 'vampires', '{"color": "danger"}'),
('tag', 'Werewolves', 'werewolves', '{"color": "secondary"}'),
('tag', 'Survival', 'survival', '{"color": "warning"}'),
('tag', 'Revenge', 'revenge', '{"color": "danger"}'),
('tag', 'Betrayal', 'betrayal', '{"color": "dark"}'),
('tag', 'Political', 'political', '{"color": "secondary"}'),
('tag', 'Kingdom Building', 'kingdom-building', '{"color": "primary"}'),
('tag', 'Empire', 'empire', '{"color": "dark"}'),
('tag', 'War', 'war', '{"color": "danger"}'),
('tag', 'Post-Apocalyptic', 'post-apocalyptic', '{"color": "dark"}'),
('tag', 'Magic', 'magic', '{"color": "info"}'),
('tag', 'Elemental Powers', 'elemental-powers', '{"color": "primary"}'),
('tag', 'Superpowers', 'superpowers', '{"color": "info"}'),
('tag', 'System Admin', 'system-admin', '{"color": "dark"}'),
('tag', 'Virtual Reality', 'virtual-reality', '{"color": "secondary"}'),
('tag', 'VRMMO', 'vrmmo', '{"color": "primary"}'),
('tag', 'Love Triangle', 'love-triangle', '{"color": "warning"}'),
('tag', 'Slow Burn', 'slow-burn', '{"color": "secondary"}'),
('tag', 'Enemies to Lovers', 'enemies-to-lovers', '{"color": "danger"}'),
('tag', 'Childhood Friends', 'childhood-friends', '{"color": "primary"}'),
('tag', 'Contract Marriage', 'contract-marriage', '{"color": "dark"}'),
('tag', 'Marriage of Convenience', 'marriage-of-convenience', '{"color": "dark"}'),
('tag', 'Harem', 'harem', '{"color": "warning"}'),
('tag', 'Reverse Harem', 'reverse-harem', '{"color": "info"}'),
('tag', 'Dark Fantasy', 'dark-fantasy', '{"color": "dark"}'),
('tag', 'Tragedy', 'tragedy', '{"color": "danger"}'),
('tag', 'Psychological Trauma', 'psychological-trauma', '{"color": "dark"}'),
('tag', 'Mind Games', 'mind-games', '{"color": "secondary"}'),
('tag', 'Antihero', 'antihero', '{"color": "dark"}'),
('tag', 'Villain Protagonist', 'villain-protagonist', '{"color": "danger"}'),
('tag', 'School Life', 'school-life', '{"color": "primary"}'),
('tag', 'Academy', 'academy', '{"color": "info"}'),
('tag', 'Office Romance', 'office-romance', '{"color": "secondary"}'),
('tag', 'Age Gap', 'age-gap', '{"color": "warning"}'),
('tag', 'Gender Bender', 'gender-bender', '{"color": "secondary"}'),
('tag', 'Crossdressing', 'crossdressing', '{"color": "dark"}'),
('tag', 'Mecha Combat', 'mecha-combat', '{"color": "primary"}'),
('tag', 'Military Strategy', 'military-strategy', '{"color": "dark"}'),
('tag', 'Space Opera', 'space-opera', '{"color": "info"}'),
('tag', 'Cyberpunk Elements', 'cyberpunk-elements', '{"color": "secondary"}'),
('tag', 'AI', 'ai', '{"color": "primary"}'),
('tag', 'Robots', 'robots', '{"color": "info"}'),
('tag', 'Cooking', 'cooking', '{"color": "success"}'),
('tag', 'Crafting', 'crafting', '{"color": "secondary"}'),
('tag', 'Farming', 'farming', '{"color": "success"}'),
('tag', 'Merchant', 'merchant', '{"color": "warning"}'),
('tag', 'Healer', 'healer', '{"color": "success"}');

-- --------------------------------------------------------
-- Content Elements
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chapters`;
CREATE TABLE `chapters` (
  `id` char(6) NOT NULL,
  `content_id` char(6) NOT NULL,
  `number` decimal(8,2) NOT NULL DEFAULT 0.00,
  `chapter_number` varchar(10) NOT NULL DEFAULT '',
  `title` varchar(200) DEFAULT NULL,
  `type` enum('text','image') NOT NULL DEFAULT 'image',
  `data` json NOT NULL,
  `is_members_only` tinyint(1) NOT NULL DEFAULT 0,
  `price_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `price_last_update` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `is_free_after` datetime DEFAULT NULL,
  `created_by` char(8) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chapters_content_number` (`content_id`,`number`),
  KEY `idx_chapters_content` (`content_id`),
  KEY `idx_chapters_number` (`number`),
  KEY `idx_chapters_published` (`published_at`),
  KEY `idx_chapters_creator` (`created_by`),
  KEY `idx_chapters_deleted` (`deleted_at`),
  CONSTRAINT `fk_chapters_content` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chapters_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_reading_history`;
CREATE TABLE `user_reading_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `content_id` char(6) NOT NULL,
  `chapter_id` char(6) NOT NULL,
  `chapter_number` decimal(8,2) NOT NULL DEFAULT 0.00,
  `progress_pct` tinyint unsigned NOT NULL DEFAULT 0,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `read_count` smallint unsigned NOT NULL DEFAULT 1,
  `last_read_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_chapter` (`user_id`,`chapter_id`),
  KEY `idx_history_user_content` (`user_id`,`content_id`,`chapter_number`),
  KEY `idx_history_user_recent` (`user_id`,`last_read_at`),
  CONSTRAINT `fk_reading_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reading_history_content` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reading_history_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_reading_progress`;
CREATE TABLE `user_reading_progress` (
  `user_id` char(8) NOT NULL,
  `series_id` char(6) NOT NULL,
  `last_chapter_id` char(6) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`series_id`),
  CONSTRAINT `fk_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_chapter` FOREIGN KEY (`last_chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_chapters_reads`;
CREATE TABLE `user_chapters_reads` (
  `user_id` char(8) NOT NULL,
  `chapter_id` char(6) NOT NULL,
  `content_id` char(6) DEFAULT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`chapter_id`),
  KEY `idx_user_reads_content` (`user_id`,`content_id`),
  KEY `idx_user_reads_read_at` (`read_at`),
  CONSTRAINT `fk_user_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_reads_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `blogs`;
CREATE TABLE `blogs` (
  `id` char(6) NOT NULL,
  `user_id` char(8) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending','published','rejected','hidden') NOT NULL DEFAULT 'pending',
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `approver_user_id` char(8) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_blogs_status` (`status`),
  CONSTRAINT `fk_blogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blogs_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ratings`;
CREATE TABLE `ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `content_id` char(6) NOT NULL,
  `score` tinyint(4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_content_rating` (`user_id`,`content_id`),
  CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ratings_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `target_type` enum('series','chapter','blog') NOT NULL DEFAULT 'series',
  `target_id` varchar(32) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `upvote_count` int(11) DEFAULT 0,
  `downvote_count` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_target` (`target_type`,`target_id`,`created_at`),
  KEY `idx_comments_user` (`user_id`,`created_at`),
  KEY `idx_comments_parent` (`parent_id`),
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `votes`;
CREATE TABLE `votes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `target_type` enum('blog','comment') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `vote` tinyint(4) NOT NULL DEFAULT 1, -- 1 for upvote, -1 for downvote
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_target_vote` (`user_id`,`target_type`,`target_id`),
  KEY `idx_votes_target` (`target_type`,`target_id`),
  KEY `idx_votes_user` (`user_id`),
  CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `target_type` enum('series','chapter','blog','comment') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `reason` varchar(64) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewing','resolved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` char(8) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_target_reason` (`user_id`,`target_type`,`target_id`,`reason`),
  KEY `idx_reports_status_created` (`status`,`created_at`),
  KEY `idx_reports_target` (`target_type`,`target_id`),
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- User Interactions & Logs
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_activity`;
CREATE TABLE `user_activity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `tab_id` varchar(32) NOT NULL,
  `chapter_id` char(6) DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `duration_seconds` int(11) NOT NULL DEFAULT 0,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_tab` (`user_id`,`tab_id`),
  KEY `idx_activity_last_seen` (`last_seen_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_events`;
CREATE TABLE `notification_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` char(8) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `target_type` varchar(30) DEFAULT NULL,
  `target_id` varchar(32) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `data` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_events_type_created` (`type`,`created_at`),
  KEY `idx_events_actor` (`actor_user_id`),
  CONSTRAINT `fk_notification_events_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` char(8) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT '',
  `target_type` varchar(30) DEFAULT NULL,
  `target_id` varchar(32) DEFAULT NULL,
  `title` varchar(120) NOT NULL DEFAULT '',
  `body` text DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_event` (`event_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_event` FOREIGN KEY (`event_id`) REFERENCES `notification_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `session_key` char(32) NOT NULL,
  `user_id` char(8) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_key`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_refresh_tokens`;
DROP TABLE IF EXISTS `user_tokens`;
CREATE TABLE `user_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `session_key` char(32) DEFAULT NULL,
  `type` enum('refresh','password_reset','email_verification') NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_tokens_lookup` (`token_hash`,`type`,`expires_at`,`used_at`),
  KEY `idx_tokens_user` (`user_id`,`type`),
  KEY `idx_tokens_session` (`session_key`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_login_logs`;
CREATE TABLE `user_login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `failure_reason` varchar(50) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempted` (`attempted_at`),
  KEY `idx_login_user_attempted` (`user_id`,`attempted_at`),
  KEY `idx_login_email_attempted` (`email`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_series_follows`;
CREATE TABLE `user_series_follows` (
  `user_id` char(8) NOT NULL,
  `content_id` char(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`content_id`),
  CONSTRAINT `fk_follows_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_follows`;
CREATE TABLE `user_follows` (
  `follower_id` char(8) NOT NULL,
  `followed_id` char(8) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`follower_id`,`followed_id`),
  KEY `idx_user_follows_followed` (`followed_id`),
  CONSTRAINT `fk_user_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_follows_followed` FOREIGN KEY (`followed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_wallets`;
CREATE TABLE `user_wallets` (
  `user_id` char(8) NOT NULL,
  `balance_coin` int(10) unsigned NOT NULL DEFAULT 0,
  `total_coin_purchased` int(10) unsigned NOT NULL DEFAULT 0,
  `total_coin_spent` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `type` enum('manual_credit','manual_debit','package_credit','chapter_unlock','series_unlock','feature_unlock','refund','adjustment') NOT NULL,
  `coin_delta` int(11) NOT NULL,
  `balance_after` int(10) unsigned NOT NULL,
  `reference_type` varchar(32) DEFAULT NULL,
  `reference_id` varchar(32) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `created_by` char(8) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wallet_transactions_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_wallet_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wallet_transactions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `coin_catalog`;
CREATE TABLE `coin_catalog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `catalog_type` enum('coin_package','feature_pass','series_bundle') NOT NULL,
  `item_key` varchar(32) NOT NULL,
  `name` varchar(120) NOT NULL,
  `coin_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `bonus_coin` int(10) unsigned NOT NULL DEFAULT 0,
  `coin_price` int(10) unsigned NOT NULL DEFAULT 0,
  `fiat_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'TRY',
  `duration_days` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_catalog_item` (`item_key`),
  KEY `idx_catalog_type_active` (`catalog_type`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_unlocks`;
CREATE TABLE `user_unlocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `unlock_type` enum('chapter','series','feature') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `content_id` char(6) DEFAULT NULL,
  `price_coin` int(10) unsigned NOT NULL DEFAULT 0,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `unlocked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_unlock` (`user_id`,`unlock_type`,`target_id`),
  KEY `idx_unlock_lookup` (`user_id`,`content_id`,`unlock_type`),
  KEY `idx_unlock_active` (`user_id`,`unlock_type`,`expires_at`),
  CONSTRAINT `fk_user_unlocks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_unlocks_tx` FOREIGN KEY (`transaction_id`) REFERENCES `wallet_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Audit & Analytics
-- --------------------------------------------------------

DROP TABLE IF EXISTS `admin_actions`;
CREATE TABLE `admin_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `moderator_user_id` char(8) DEFAULT NULL,
  `target_type` enum('comment','blog','content','user','system','role','series','chapter','security') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `action` enum('hide','delete','ban','warn','approve','trigger','grant_permission','revoke_permission','role_change','unban','update','create','update_taxonomy','revoke_session','wallet_credit','wallet_debit','wallet_package_credit','refund','series_unlock','chapter_unlock','feature_unlock','package_create','package_update','pricing_update','feature_update','auth_fail','permission_denied','create_genre','create_tag','env_update') NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_audit_logs`;
CREATE TABLE `system_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `method` varchar(10) NOT NULL,
  `path` varchar(255) NOT NULL,
  `status_code` int(11) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `duration_ms` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_status_created` (`status_code`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_uploads`;
CREATE TABLE `system_uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `image_id` varchar(32) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_upload_user` (`user_id`),
  CONSTRAINT `fk_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_users_votes`;
CREATE TABLE `analytics_users_votes` (
  `user_id` varchar(64) NOT NULL,
  `votes_cast` int(11) NOT NULL DEFAULT 0,
  `upvotes_received` int(11) NOT NULL DEFAULT 0,
  `downvotes_received` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_series_daily`;
CREATE TABLE `analytics_series_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_content_daily` (`content_id`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_series_views`;
CREATE TABLE `analytics_series_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_id` char(6) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_series_views_content` (`content_id`,`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_chapters_views`;
CREATE TABLE `analytics_chapters_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` char(6) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_chapters_views_chapter` (`chapter_id`,`viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_chapters_daily`;
CREATE TABLE `analytics_chapters_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_chapter_daily` (`chapter_id`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_events`;
CREATE TABLE `analytics_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(64) NOT NULL,
  `user_id` char(8) DEFAULT NULL,
  `entity_type` varchar(32) DEFAULT NULL,
  `entity_id` varchar(32) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `ip_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_events_type_date` (`event_type`,`created_at`),
  KEY `idx_analytics_events_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_search_logs`;
CREATE TABLE `analytics_search_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `query` varchar(255) NOT NULL,
  `result_count` int(11) NOT NULL DEFAULT 0,
  `ip_hash` char(64) NOT NULL,
  `searched_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_search_logs_date` (`searched_at`),
  KEY `idx_search_logs_query` (`query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_daily_metrics`;
CREATE TABLE `analytics_daily_metrics` (
  `stat_date` date NOT NULL,
  `metric_category` enum('content','chapter','search','community','auth','system','finance','funnel','hourly') NOT NULL,
  `metric_key` varchar(64) NOT NULL,
  `entity_type` varchar(24) NOT NULL DEFAULT '',
  `entity_id` varchar(32) NOT NULL DEFAULT '',
  `metric_value` bigint(20) NOT NULL DEFAULT 0,
  `metadata` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`stat_date`,`metric_key`,`entity_id`),
  KEY `idx_metrics_lookup` (`metric_key`,`stat_date`),
  KEY `idx_entity_history` (`entity_type`,`entity_id`,`stat_date`),
  KEY `idx_category_date` (`metric_category`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_daily`;
CREATE TABLE `analytics_snapshots_daily` (
  `stat_date` date NOT NULL,
  `metric_name` varchar(120) NOT NULL,
  `metric_value` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`,`metric_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_hourly`;
CREATE TABLE `analytics_snapshots_hourly` (
  `bucket_start` datetime NOT NULL,
  `metric_name` varchar(120) NOT NULL,
  `metric_value` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket_start`,`metric_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_series_top`;
CREATE TABLE `analytics_snapshots_series_top` (
  `content_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`content_id`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_chapters_top`;
CREATE TABLE `analytics_snapshots_chapters_top` (
  `chapter_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`chapter_id`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_search`;
CREATE TABLE `analytics_snapshots_search` (
  `stat_date` date NOT NULL,
  `query` varchar(255) NOT NULL,
  `search_count` int(11) NOT NULL DEFAULT 0,
  `zero_result_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`,`query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_auth`;
CREATE TABLE `analytics_snapshots_auth` (
  `stat_date` date NOT NULL,
  `failed_login_count` int(11) NOT NULL DEFAULT 0,
  `rate_limited_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analytics_snapshots_health`;
CREATE TABLE `analytics_snapshots_health` (
  `stat_date` date NOT NULL,
  `request_total_24h` int(11) NOT NULL DEFAULT 0,
  `server_error_total_24h` int(11) NOT NULL DEFAULT 0,
  `p95_duration_ms_24h` int(11) NOT NULL DEFAULT 0,
  `suspicious_login_ips_24h` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `series_team_assignments`;
CREATE TABLE `series_team_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` char(6) NOT NULL,
  `user_id` char(8) NOT NULL,
  `role` enum('lead','translator','proofreader','cleaner','typesetter','uploader') NOT NULL DEFAULT 'translator',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_series_user_role` (`series_id`,`user_id`,`role`),
  KEY `idx_team_user` (`user_id`),
  CONSTRAINT `fk_team_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `webhook_configs`;
CREATE TABLE `webhook_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `platform` enum('discord','telegram','custom') NOT NULL DEFAULT 'discord',
  `event` enum('chapter_published','blog_approved','series_created') NOT NULL,
  `webhook_url` varchar(500) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_event_active` (`event`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `group` varchar(32) NOT NULL DEFAULT 'general',
  `key` varchar(64) NOT NULL,
  `type` enum('string','int','bool','json') NOT NULL DEFAULT 'string',
  `value` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`),
  KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rbac_role_permission_overrides`;
CREATE TABLE `rbac_role_permission_overrides` (
  `role_slug` varchar(32) NOT NULL,
  `permission_code` varchar(96) NOT NULL,
  `effect` enum('grant','revoke') NOT NULL,
  `updated_by` char(8) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`role_slug`,`permission_code`),
  KEY `idx_rbac_overrides_updated_by` (`updated_by`),
  CONSTRAINT `fk_rbac_overrides_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`group`, `key`, `type`, `value`) VALUES
('general', 'site_name', 'string', 'NM Reader'),
('general', 'site_slogan', 'string', 'En İyi Çevrimiçi Manga ve Novel Okuyucusu'),
('general', 'site_abbreviation', 'string', 'NMR'),
('general', 'site_description', 'string', 'Read manga, manhwa, webtoon and novels.'),
('general', 'site_address', 'string', ''),
('general', 'default_language', 'string', 'tr'),
('general', 'footer_text', 'string', '© 2026 NM Reader. Tüm hakları saklıdır.'),
('appearance', 'default_theme', 'string', 'dark'),
('appearance', 'site_logo', 'string', '/assets/img/logo.svg'),
('appearance', 'logo_url', 'string', '/assets/img/logo.svg'),
('appearance', 'favicon_url', 'string', '/favicon.ico'),
('appearance', 'default_profile_image', 'string', '/assets/img/default-profile.png'),
('appearance', 'default_content_cover_image', 'string', '/assets/img/covers/placeholder.svg'),
('security', 'maintenance_mode', 'bool', 'false'),
('security', 'maintenance_whitelist_ips', 'json', '["127.0.0.1", "::1"]'),
('security', 'enforce_https', 'bool', 'false'),
('mail', 'mail_enabled', 'bool', 'true'),
('mail', 'mail_send_on_register', 'bool', 'true'),
('mail', 'email_verification_required', 'bool', 'false'),
('mail', 'mail_from_name', 'string', 'NM Reader'),
('mail', 'mail_from_address', 'string', 'noreply@nmreader.com'),
('mail', 'password_reset_subject', 'string', 'Şifre Sıfırlama Talebi - {{site_name}}'),
('mail', 'password_reset_body', 'string', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">Şifre Sıfırlama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} hesabınız için bir şifre sıfırlama talebi aldık. Şifrenizi yenilemek için aşağıdaki butona tıklayabilirsiniz:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">Şifremi Sıfırla</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir. Talebi siz yapmadıysanız bu e-postayı güvenle silebilirsiniz.</p></div>'),
('mail', 'email_verification_subject', 'string', 'E-posta Adresinizi Doğrulayın - {{site_name}}'),
('mail', 'email_verification_body', 'string', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; background-color: #18181b; color: #f4f4f5; border-radius: 12px;"><h2 style="color: #ffffff; margin-bottom: 16px;">E-posta Doğrulama</h2><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Merhaba <strong>{{username}}</strong>,</p><p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">{{site_name}} ailesine hoş geldiniz! Hesabınızı doğrulamak ve güvenliğinizi sağlamak için lütfen aşağıdaki butona tıklayın:</p><div style="text-align: center; margin: 28px 0;"><a href="{{action_url}}" style="background-color: #e11d48; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">E-postamı Doğrula</a></div><p style="color: #71717a; font-size: 12px; line-height: 1.5;">Bu bağlantı <strong>{{expires_in}}</strong> boyunca geçerlidir.</p></div>')
ON DUPLICATE KEY UPDATE `group`=VALUES(`group`), `type`=VALUES(`type`), `value`=VALUES(`value`);

DROP TABLE IF EXISTS `system_jobs`;
CREATE TABLE `system_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_type` varchar(64) NOT NULL,
  `payload` longtext DEFAULT NULL,
  `status` enum('pending','processing','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_system_jobs_status_available` (`status`,`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` varchar(191) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`version`, `checksum`)
VALUES
('001_baseline', 'installed_from_schema'),
('002_reading_history_progress_defaults', 'installed_from_schema'),
('003_hash_existing_api_tokens', 'installed_from_schema'),
('004_rbac_role_permission_overrides', 'installed_from_schema'),
('005_series_lifecycle_and_revisions', 'installed_from_schema'),
('006_taxonomy_order', 'installed_from_schema'),
('007_queue_cancellation', 'installed_from_schema');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
