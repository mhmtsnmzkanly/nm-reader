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
  `password_hash` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `roles` varchar(255) DEFAULT '4',
  `api_token` varchar(64) DEFAULT NULL,
  `api_token_expires_at` datetime DEFAULT NULL,
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
  `type` enum('manga','manhua','manhwa','webtoon','light-novel','web-novel','novel') NOT NULL,
  `status` enum('ongoing','completed','hiatus','dropped') NOT NULL DEFAULT 'ongoing',
  `cover_image` varchar(255) DEFAULT NULL,
  `accent_color` varchar(7) DEFAULT '#2a2a2a',
  `description` text DEFAULT NULL,
  `rating_avg` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `chapter_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `series_metadata`;
CREATE TABLE `series_metadata` (
  `content_id` char(6) NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `artist` varchar(100) DEFAULT NULL,
  `alternative_titles` varchar(255) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `release_year` varchar(4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`content_id`),
  CONSTRAINT `fk_metadata_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Taxonomy (Genres & Tags with initial data)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `series_genres`;
CREATE TABLE `series_genres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `ui_config` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `series_genres` (`id`, `name`, `slug`, `ui_config`) VALUES
(1,'Dungeon','dungeon', '{"icon": "bi-grid"}'), (2,'Leveling','leveling', '{"icon": "bi-graph-up"}'),
(3,'Regression','regression', '{"icon": "bi-arrow-left"}'), (4,'Game System','game-system', '{"icon": "bi-controller"}'),
(5,'World Building','world-building', '{"icon": "bi-globe"}'), (6,'Tower','tower', '{"icon": "bi-building"}'),
(7,'Pirates','pirates', '{"icon": "bi-water"}'), (8,'Magic','magic', '{"icon": "bi-magic"}'),
(9,'Reincarnation','reincarnation', '{"icon": "bi-recycle"}'), (10,'Op Protagonist','op-protagonist', '{"icon": "bi-lightning"}'),
(11,'Action','action', '{"icon": "bi-fire"}'), (12,'SciFi','scifi', '{"icon": "bi-cpu"}'),
(13,'Adventure','adventure', '{"icon": "bi-compass"}'), (14,'Fantasy','fantasy', '{"icon": "bi-stars"}'),
(15,'Drama','drama', '{"icon": "bi-mask"}'), (16,'Romance','romance', '{"icon": "bi-heart"}'),
(17,'Comedy','comedy', '{"icon": "bi-emoji-laughing"}'), (18,'Mystery','mystery', '{"icon": "bi-search"}'),
(19,'Supernatural','supernatural', '{"icon": "bi-ghost"}'), (20,'Horror','horror', '{"icon": "bi-bug"}'),
(21,'Thriller','thriller', '{"icon": "bi-alarm"}'), (22,'Martial Arts','martial-arts', '{"icon": "bi-person"}'),
(23,'School Life','school-life', '{"icon": "bi-mortarboard"}'), (24,'Slice of Life','slice-of-life', '{"icon": "bi-cup"}'),
(25,'Historical','historical', '{"icon": "bi-journal"}'), (26,'Psychological','psychological', '{"icon": "bi-brain"}'),
(27,'Seinen','seinen', '{"icon": "bi-person-vcard"}'), (28,'Shounen','shounen', '{"icon": "bi-person"}'),
(29,'Shoujo','shoujo', '{"icon": "bi-person-heart"}'), (30,'Isekai','isekai', '{"icon": "bi-door-open"}');

DROP TABLE IF EXISTS `series_tags`;
CREATE TABLE `series_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `ui_config` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `series_tags` (`id`, `name`, `slug`, `ui_config`) VALUES
(1,'Action','action', '{"color": "primary"}'), (2,'Adventure','adventure', '{"color": "success"}'),
(3,'Fantasy','fantasy', '{"color": "info"}'), (4,'Drama','drama', '{"color": "warning"}'),
(5,'Mystery','mystery', '{"color": "dark"}'), (6,'Supernatural','supernatural', '{"color": "secondary"}'),
(7,'Martial Arts','martial-arts', '{"color": "danger"}'), (8,'Comedy','comedy', '{"color": "info"}'),
(9,'Shounen','shounen', '{"color": "primary"}'), (10,'Isekai','isekai', '{"color": "success"}');

DROP TABLE IF EXISTS `series_genre_map`;
CREATE TABLE `series_genre_map` (
  `content_id` char(6) NOT NULL,
  `genre_id` int(11) NOT NULL,
  PRIMARY KEY (`content_id`,`genre_id`),
  CONSTRAINT `fk_genre_map_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_genre_map_genre` FOREIGN KEY (`genre_id`) REFERENCES `series_genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `series_tag_map`;
CREATE TABLE `series_tag_map` (
  `content_id` char(6) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`content_id`,`tag_id`),
  CONSTRAINT `fk_tag_map_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tag_map_tag` FOREIGN KEY (`tag_id`) REFERENCES `series_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Content Elements
-- --------------------------------------------------------

DROP TABLE IF EXISTS `chapters`;
CREATE TABLE `chapters` (
  `id` char(6) NOT NULL,
  `content_id` char(6) NOT NULL,
  `chapter_number` varchar(10) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `data` longtext NOT NULL,
  `type` enum('text','image') NOT NULL DEFAULT 'image',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_chapter` (`content_id`,`chapter_number`),
  CONSTRAINT `fk_chapters_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `blogs`;
CREATE TABLE `blogs` (
  `id` char(6) NOT NULL,
  `user_id` char(8) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `approver_user_id` char(8) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  CONSTRAINT `fk_blogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blogs_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `blog_votes`;
CREATE TABLE `blog_votes` (
  `blog_id` char(6) NOT NULL,
  `user_id` char(8) NOT NULL,
  `vote` tinyint(4) NOT NULL, -- 1 for upvote, -1 for downvote
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`blog_id`,`user_id`),
  CONSTRAINT `fk_blog_votes_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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

DROP TABLE IF EXISTS `comment_votes`;
CREATE TABLE `comment_votes` (
  `comment_id` bigint(20) unsigned NOT NULL,
  `user_id` char(8) NOT NULL,
  `vote` tinyint(4) NOT NULL, -- 1 for upvote, -1 for downvote
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`comment_id`,`user_id`),
  CONSTRAINT `fk_comment_votes_comment` FOREIGN KEY (`comment_id`) REFERENCES `social_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `social_comments`;
CREATE TABLE `social_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `content_id` char(6) DEFAULT NULL,
  `chapter_id` char(6) DEFAULT NULL,
  `blog_id` char(6) DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `upvote_count` int(11) DEFAULT 0,
  `downvote_count` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- User Interactions & Logs
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_activity`;
CREATE TABLE `user_activity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `chapter_id` char(6) NOT NULL,
  `tab_id` varchar(32) NOT NULL,
  `duration_seconds` int(11) NOT NULL DEFAULT 0,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_tab` (`user_id`,`tab_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `actor_user_id` char(8) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `target_type` varchar(30) DEFAULT NULL,
  `target_id` varchar(32) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `data` longtext DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_refresh_tokens`;
CREATE TABLE `user_refresh_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_key` char(32) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  CONSTRAINT `fk_tokens_session` FOREIGN KEY (`session_key`) REFERENCES `user_sessions` (`session_key`) ON DELETE CASCADE
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
  PRIMARY KEY (`id`)
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

-- --------------------------------------------------------
-- Audit & Analytics
-- --------------------------------------------------------

DROP TABLE IF EXISTS `admin_actions`;
CREATE TABLE `admin_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `moderator_user_id` char(8) DEFAULT NULL,
  `target_type` enum('comment','blog','content','user','system','role') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `action` enum('hide','delete','ban','warn','approve','trigger','grant_permission','revoke_permission','role_change','unban','update') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
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
  PRIMARY KEY (`id`)
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

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
