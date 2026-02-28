/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-12.2.2-MariaDB, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: nm-reader
-- ------------------------------------------------------
-- Server version	12.2.2-MariaDB

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

--
-- Table structure for table `admin_actions`
--

DROP TABLE IF EXISTS `admin_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `moderator_user_id` char(8) DEFAULT NULL,
  `target_type` enum('comment','blog','content','user','system','role') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `action` enum('hide','delete','ban','warn','approve','trigger','grant_permission','revoke_permission','role_change','unban','update') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_moderation_actions_type_date` (`target_type`,`created_at`),
  KEY `idx_moderation_actions_mod_date` (`moderator_user_id`,`created_at`),
  CONSTRAINT `fk_moderation_actions_mod` FOREIGN KEY (`moderator_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_actions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `admin_actions` WRITE;
/*!40000 ALTER TABLE `admin_actions` DISABLE KEYS */;
INSERT INTO `admin_actions` VALUES
(1,'qm303gs0','user','qm303gs0','role_change','{\"diff\":{\"roles\":{\"before\":\"\",\"after\":\"1\"}}}','2026-02-28 02:31:59');
/*!40000 ALTER TABLE `admin_actions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_chapters_daily`
--

DROP TABLE IF EXISTS `analytics_chapters_daily`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_chapters_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_chapter_daily` (`chapter_id`,`stat_date`),
  KEY `idx_daily_chapter_date` (`stat_date`),
  CONSTRAINT `1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_chapters_daily`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_chapters_daily` WRITE;
/*!40000 ALTER TABLE `analytics_chapters_daily` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_chapters_daily` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_chapters_views`
--

DROP TABLE IF EXISTS `analytics_chapters_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_chapters_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` char(6) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chapter_views_chapter_date` (`chapter_id`,`viewed_at`),
  CONSTRAINT `1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_chapters_views`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_chapters_views` WRITE;
/*!40000 ALTER TABLE `analytics_chapters_views` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_chapters_views` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_events`
--

DROP TABLE IF EXISTS `analytics_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `user_id` char(8) DEFAULT NULL,
  `entity_type` varchar(30) DEFAULT NULL,
  `entity_id` varchar(32) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_type_date` (`event_type`,`created_at`),
  KEY `idx_analytics_entity` (`entity_type`,`entity_id`),
  KEY `idx_analytics_date` (`created_at`),
  KEY `idx_analytics_entity_date` (`entity_type`,`entity_id`,`created_at`),
  KEY `idx_analytics_created_user` (`created_at`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_events`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_events` WRITE;
/*!40000 ALTER TABLE `analytics_events` DISABLE KEYS */;
INSERT INTO `analytics_events` VALUES
(1,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:02:55'),
(2,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:22:36'),
(3,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:44:39'),
(4,'auth_login_success','qm303gs0','auth','qm303gs0','{\"failure_reason\":null,\"email_hash\":\"2770a50bdf7f3b61d22a1d6febbff8de560039ace1400b404ce4f1739beae228\"}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:45:20'),
(5,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:45:21'),
(6,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:45:24'),
(7,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:45:28'),
(8,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:45:35'),
(9,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-27 20:45:38'),
(10,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 00:58:42'),
(11,'auth_login_success','qm303gs0','auth','qm303gs0','{\"failure_reason\":null,\"email_hash\":\"2770a50bdf7f3b61d22a1d6febbff8de560039ace1400b404ce4f1739beae228\"}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 00:58:54'),
(12,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 00:58:55'),
(13,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 00:59:03'),
(14,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 00:59:05'),
(15,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 01:18:40'),
(16,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 01:23:05'),
(17,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 01:32:45'),
(18,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 01:41:22'),
(19,'auth_login_success','qm303gs0','auth','qm303gs0','{\"failure_reason\":null,\"email_hash\":\"2770a50bdf7f3b61d22a1d6febbff8de560039ace1400b404ce4f1739beae228\"}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 01:41:26'),
(20,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 01:41:28'),
(21,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:20:57'),
(22,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:21:00'),
(23,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:24:19'),
(24,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:24:21'),
(25,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:24:28'),
(26,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:36:39'),
(27,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:38:53'),
(28,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:45:42'),
(29,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:45:44'),
(30,'home_view',NULL,'home',NULL,'{\"page\":1,\"per_page\":20}','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','2026-02-28 02:45:57');
/*!40000 ALTER TABLE `analytics_events` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_search_logs`
--

DROP TABLE IF EXISTS `analytics_search_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_search_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `query` varchar(120) NOT NULL,
  `result_count` int(11) NOT NULL DEFAULT 0,
  `ip_hash` char(64) NOT NULL,
  `searched_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_search_logs_query_date` (`query`,`searched_at`),
  KEY `idx_search_logs_user_date` (`user_id`,`searched_at`),
  CONSTRAINT `fk_search_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_search_logs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_search_logs` WRITE;
/*!40000 ALTER TABLE `analytics_search_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_search_logs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_series_daily`
--

DROP TABLE IF EXISTS `analytics_series_daily`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_series_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_content_daily` (`content_id`,`stat_date`),
  KEY `idx_daily_content_date` (`stat_date`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_series_daily`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_series_daily` WRITE;
/*!40000 ALTER TABLE `analytics_series_daily` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_series_daily` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_series_views`
--

DROP TABLE IF EXISTS `analytics_series_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_series_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_id` char(6) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_content_views_content_date` (`content_id`,`viewed_at`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_series_views`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_series_views` WRITE;
/*!40000 ALTER TABLE `analytics_series_views` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_series_views` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_auth`
--

DROP TABLE IF EXISTS `analytics_snapshots_auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_auth` (
  `stat_date` date NOT NULL,
  `failed_login_count` int(11) NOT NULL DEFAULT 0,
  `rate_limited_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_auth`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_auth` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_auth` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_auth` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_chapters_top`
--

DROP TABLE IF EXISTS `analytics_snapshots_chapters_top`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_chapters_top` (
  `chapter_id` char(6) NOT NULL,
  `stat_date` date NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`chapter_id`,`stat_date`),
  KEY `idx_top_chapter_stat_views` (`stat_date`,`view_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_chapters_top`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_chapters_top` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_chapters_top` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_chapters_top` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_daily`
--

DROP TABLE IF EXISTS `analytics_snapshots_daily`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_daily` (
  `stat_date` date NOT NULL,
  `metric_name` varchar(50) NOT NULL,
  `metric_value` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`,`metric_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_daily`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_daily` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_daily` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_daily` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_health`
--

DROP TABLE IF EXISTS `analytics_snapshots_health`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_health` (
  `stat_date` date NOT NULL,
  `request_total_24h` int(11) NOT NULL DEFAULT 0,
  `server_error_total_24h` int(11) NOT NULL DEFAULT 0,
  `p95_duration_ms_24h` int(11) NOT NULL DEFAULT 0,
  `suspicious_login_ips_24h` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_health`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_health` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_health` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_health` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_hourly`
--

DROP TABLE IF EXISTS `analytics_snapshots_hourly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_hourly` (
  `bucket_start` datetime NOT NULL,
  `metric_name` varchar(50) NOT NULL,
  `metric_value` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket_start`,`metric_name`),
  KEY `idx_hourly_metric_bucket` (`metric_name`,`bucket_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_hourly`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_hourly` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_hourly` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_hourly` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_search`
--

DROP TABLE IF EXISTS `analytics_snapshots_search`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_search` (
  `stat_date` date NOT NULL,
  `query` varchar(120) NOT NULL,
  `search_count` int(11) NOT NULL DEFAULT 0,
  `zero_result_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_date`,`query`),
  KEY `idx_search_stats_date_count` (`stat_date`,`search_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_search`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_search` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_search` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_search` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_snapshots_series_top`
--

DROP TABLE IF EXISTS `analytics_snapshots_series_top`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_snapshots_series_top` (
  `content_id` char(6) NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `stat_date` date NOT NULL,
  PRIMARY KEY (`content_id`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_snapshots_series_top`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_snapshots_series_top` WRITE;
/*!40000 ALTER TABLE `analytics_snapshots_series_top` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_snapshots_series_top` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `analytics_users_votes`
--

DROP TABLE IF EXISTS `analytics_users_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytics_users_votes` (
  `user_id` char(8) NOT NULL,
  `votes_cast` int(11) NOT NULL DEFAULT 0,
  `upvotes_received` int(11) NOT NULL DEFAULT 0,
  `downvotes_received` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_users_votes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `analytics_users_votes` WRITE;
/*!40000 ALTER TABLE `analytics_users_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_users_votes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `blog_votes`
--

DROP TABLE IF EXISTS `blog_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_votes` (
  `user_id` char(8) NOT NULL,
  `blog_id` char(6) NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`blog_id`),
  KEY `idx_blog_votes_blog` (`blog_id`,`vote`),
  CONSTRAINT `fk_blog_votes_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_blog_vote` CHECK (`vote` in (-1,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_votes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `blog_votes` WRITE;
/*!40000 ALTER TABLE `blog_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_votes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `blogs`
--

DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blogs` (
  `id` char(6) NOT NULL,
  `user_id` char(8) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `approver_user_id` char(8) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_blogs_approved_created` (`approved`,`created_at`),
  KEY `idx_blogs_user_created` (`user_id`,`created_at`),
  KEY `idx_blogs_approver` (`approver_user_id`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blogs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `blogs` WRITE;
/*!40000 ALTER TABLE `blogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `blogs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `chapters`
--

DROP TABLE IF EXISTS `chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chapters` (
  `id` char(6) NOT NULL,
  `content_id` char(6) NOT NULL,
  `chapter_number` decimal(6,2) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` enum('text','image') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `data` longtext DEFAULT NULL,
  `created_by` char(8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_content_chapter` (`content_id`,`chapter_number`),
  KEY `idx_chapters_content` (`content_id`),
  KEY `idx_chapters_number` (`chapter_number`),
  KEY `fk_chapters_created_by` (`created_by`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chapters_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chapters`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `chapters` WRITE;
/*!40000 ALTER TABLE `chapters` DISABLE KEYS */;
/*!40000 ALTER TABLE `chapters` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `comment_votes`
--

DROP TABLE IF EXISTS `comment_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comment_votes` (
  `user_id` char(8) NOT NULL,
  `comment_id` bigint(20) unsigned NOT NULL,
  `vote` tinyint(4) NOT NULL CHECK (`vote` in (-1,1)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`comment_id`),
  KEY `idx_comment_votes_comment` (`comment_id`,`vote`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`comment_id`) REFERENCES `social_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comment_votes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `comment_votes` WRITE;
/*!40000 ALTER TABLE `comment_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `comment_votes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series`
--

DROP TABLE IF EXISTS `series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series` (
  `id` char(6) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('light_novel','web_novel','novel','manga','manhua','manhwa','webtoon') NOT NULL,
  `status` enum('ongoing','completed','hiatus') NOT NULL DEFAULT 'ongoing',
  `cover_image` varchar(255) DEFAULT NULL,
  `rating_avg` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rating_count` int(11) NOT NULL DEFAULT 0,
  `chapter_count` int(11) NOT NULL DEFAULT 0,
  `comment_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_contents_type` (`type`),
  KEY `idx_contents_created` (`created_at`),
  KEY `fk_contents_created_by` (`created_by`),
  CONSTRAINT `fk_contents_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series` WRITE;
/*!40000 ALTER TABLE `series` DISABLE KEYS */;
/*!40000 ALTER TABLE `series` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series_genre_map`
--

DROP TABLE IF EXISTS `series_genre_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_genre_map` (
  `content_id` char(6) NOT NULL,
  `genre_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`content_id`,`genre_id`),
  KEY `idx_content_genres_genre_content` (`genre_id`,`content_id`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`genre_id`) REFERENCES `series_genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series_genre_map`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series_genre_map` WRITE;
/*!40000 ALTER TABLE `series_genre_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `series_genre_map` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series_genres`
--

DROP TABLE IF EXISTS `series_genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_genres` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `ui_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ui_config`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series_genres`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series_genres` WRITE;
/*!40000 ALTER TABLE `series_genres` DISABLE KEYS */;
INSERT INTO `series_genres` VALUES
(1,'Dungeon','dungeon','{\"icon\": \"bi-grid\"}'),
(2,'Leveling','leveling','{\"icon\": \"bi-graph-up\"}'),
(3,'Regression','regression','{\"icon\": \"bi-arrow-left\"}'),
(4,'Game System','game-system','{\"icon\": \"bi-controller\"}'),
(5,'World Building','world-building','{\"icon\": \"bi-globe\"}'),
(6,'Tower','tower','{\"icon\": \"bi-building\"}'),
(7,'Pirates','pirates','{\"icon\": \"bi-water\"}'),
(8,'Magic','magic','{\"icon\": \"bi-magic\"}'),
(9,'Reincarnation','reincarnation','{\"icon\": \"bi-recycle\"}'),
(10,'Op Protagonist','op-protagonist','{\"icon\": \"bi-lightning\"}'),
(11,'Action','action','{\"icon\": \"bi-fire\"}'),
(12,'SciFi','scifi','{\"icon\": \"bi-cpu\"}'),
(13,'Adventure','adventure','{\"icon\": \"bi-compass\"}'),
(14,'Fantasy','fantasy','{\"icon\": \"bi-stars\"}'),
(15,'Drama','drama','{\"icon\": \"bi-mask\"}'),
(16,'Romance','romance','{\"icon\": \"bi-heart\"}'),
(17,'Comedy','comedy','{\"icon\": \"bi-emoji-laughing\"}'),
(18,'Mystery','mystery','{\"icon\": \"bi-search\"}'),
(19,'Supernatural','supernatural','{\"icon\": \"bi-ghost\"}'),
(20,'Horror','horror','{\"icon\": \"bi-bug\"}'),
(21,'Thriller','thriller','{\"icon\": \"bi-alarm\"}'),
(22,'Martial Arts','martial-arts','{\"icon\": \"bi-person\"}'),
(23,'School Life','school-life','{\"icon\": \"bi-mortarboard\"}'),
(24,'Slice of Life','slice-of-life','{\"icon\": \"bi-cup\"}'),
(25,'Historical','historical','{\"icon\": \"bi-journal\"}'),
(26,'Psychological','psychological','{\"icon\": \"bi-brain\"}'),
(27,'Seinen','seinen','{\"icon\": \"bi-person-vcard\"}'),
(28,'Shounen','shounen','{\"icon\": \"bi-person\"}'),
(29,'Shoujo','shoujo','{\"icon\": \"bi-person-heart\"}'),
(30,'Isekai','isekai','{\"icon\": \"bi-door-open\"}');
/*!40000 ALTER TABLE `series_genres` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series_metadata`
--

DROP TABLE IF EXISTS `series_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_metadata` (
  `content_id` char(6) NOT NULL,
  `author` varchar(120) DEFAULT NULL,
  `artist` varchar(120) DEFAULT NULL,
  `country` varchar(64) DEFAULT NULL,
  `release_year` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`content_id`),
  CONSTRAINT `fk_series_metadata_series` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series_metadata`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series_metadata` WRITE;
/*!40000 ALTER TABLE `series_metadata` DISABLE KEYS */;
/*!40000 ALTER TABLE `series_metadata` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series_ratings`
--

DROP TABLE IF EXISTS `series_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `content_id` char(6) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rating` (`user_id`,`content_id`),
  KEY `idx_ratings_content` (`content_id`),
  KEY `idx_ratings_updated_at` (`updated_at`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series_ratings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series_ratings` WRITE;
/*!40000 ALTER TABLE `series_ratings` DISABLE KEYS */;
/*!40000 ALTER TABLE `series_ratings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series_tag_map`
--

DROP TABLE IF EXISTS `series_tag_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_tag_map` (
  `content_id` char(6) NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`content_id`,`tag_id`),
  KEY `idx_content_tags_tag_content` (`tag_id`,`content_id`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`tag_id`) REFERENCES `series_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series_tag_map`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series_tag_map` WRITE;
/*!40000 ALTER TABLE `series_tag_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `series_tag_map` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `series_tags`
--

DROP TABLE IF EXISTS `series_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `ui_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ui_config`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `series_tags`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `series_tags` WRITE;
/*!40000 ALTER TABLE `series_tags` DISABLE KEYS */;
INSERT INTO `series_tags` VALUES
(1,'Action','action','{\"color\": \"primary\"}'),
(2,'Adventure','adventure','{\"color\": \"success\"}'),
(3,'Fantasy','fantasy','{\"color\": \"info\"}'),
(4,'Drama','drama','{\"color\": \"warning\"}'),
(5,'Mystery','mystery','{\"color\": \"dark\"}'),
(6,'Supernatural','supernatural','{\"color\": \"secondary\"}'),
(7,'Martial Arts','martial-arts','{\"color\": \"danger\"}'),
(8,'Comedy','comedy','{\"color\": \"info\"}'),
(9,'Shounen','shounen','{\"color\": \"primary\"}'),
(10,'Isekai','isekai','{\"color\": \"success\"}');
/*!40000 ALTER TABLE `series_tags` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `social_comments`
--

DROP TABLE IF EXISTS `social_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `content_id` char(6) DEFAULT NULL,
  `chapter_id` char(6) DEFAULT NULL,
  `blog_id` char(6) DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `upvote_count` int(11) NOT NULL DEFAULT 0,
  `downvote_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_comments_content_created` (`content_id`,`created_at`),
  KEY `idx_comments_chapter_created` (`chapter_id`,`created_at`),
  KEY `idx_comments_parent` (`parent_id`),
  KEY `idx_comments_blog_created` (`blog_id`,`created_at`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE,
  CONSTRAINT `3` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `4` FOREIGN KEY (`parent_id`) REFERENCES `social_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_comments`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `social_comments` WRITE;
/*!40000 ALTER TABLE `social_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `social_comments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `system_audit_logs`
--

DROP TABLE IF EXISTS `system_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `method` varchar(10) NOT NULL,
  `path` varchar(255) NOT NULL,
  `status_code` smallint(5) unsigned NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `duration_ms` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_path_created` (`path`,`created_at`),
  KEY `idx_audit_logs_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=263 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_audit_logs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `system_audit_logs` WRITE;
/*!40000 ALTER TABLE `system_audit_logs` DISABLE KEYS */;
INSERT INTO `system_audit_logs` VALUES
(1,NULL,'GET','/',302,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',13,'2026-02-27 20:02:55'),
(2,NULL,'GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',16,'2026-02-27 20:02:55'),
(3,NULL,'GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-27 20:02:55'),
(4,NULL,'GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',29,'2026-02-27 20:02:55'),
(5,NULL,'GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-27 20:02:55'),
(6,NULL,'GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',9,'2026-02-27 20:02:55'),
(7,NULL,'GET','/install-63e4qq3',302,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',10,'2026-02-27 20:22:36'),
(8,NULL,'GET','/',302,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-27 20:22:36'),
(9,NULL,'GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',20,'2026-02-27 20:22:36'),
(10,NULL,'GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-27 20:22:36'),
(11,NULL,'GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',35,'2026-02-27 20:22:36'),
(12,NULL,'GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:22:36'),
(13,NULL,'GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',70,'2026-02-27 20:44:39'),
(14,NULL,'GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-27 20:44:39'),
(15,NULL,'GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',9,'2026-02-27 20:44:39'),
(16,NULL,'GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',31,'2026-02-27 20:44:39'),
(17,NULL,'POST','/api/v1/auth/register',201,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',353,'2026-02-27 20:45:17'),
(18,'qm303gs0','POST','/api/v1/auth/login',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',339,'2026-02-27 20:45:20'),
(19,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',10,'2026-02-27 20:45:21'),
(20,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-27 20:45:21'),
(21,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:21'),
(22,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-27 20:45:21'),
(23,'qm303gs0','GET','/tr/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',9,'2026-02-27 20:45:24'),
(24,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-27 20:45:24'),
(25,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:24'),
(26,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-27 20:45:24'),
(27,'qm303gs0','GET','/api/v1/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',11,'2026-02-27 20:45:24'),
(28,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-27 20:45:28'),
(29,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:28'),
(30,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:28'),
(31,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-27 20:45:28'),
(32,NULL,'GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:32'),
(33,'qm303gs0','GET','/tr/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-27 20:45:34'),
(34,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:35'),
(35,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-27 20:45:35'),
(36,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-27 20:45:35'),
(37,'qm303gs0','GET','/api/v1/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-27 20:45:35'),
(38,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-27 20:45:38'),
(39,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:38'),
(40,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-27 20:45:38'),
(41,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-27 20:45:38'),
(42,NULL,'GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',40,'2026-02-28 00:58:42'),
(43,NULL,'GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 00:58:42'),
(44,NULL,'GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',10,'2026-02-28 00:58:42'),
(45,NULL,'GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',34,'2026-02-28 00:58:42'),
(46,'qm303gs0','POST','/api/v1/auth/login',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',220,'2026-02-28 00:58:54'),
(47,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-28 00:58:55'),
(48,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 00:58:55'),
(49,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 00:58:55'),
(50,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 00:58:55'),
(51,'qm303gs0','PUT','/api/v1/user/preferences',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 00:59:02'),
(52,'qm303gs0','PUT','/api/v1/user/preferences',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 00:59:02'),
(53,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 00:59:03'),
(54,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 00:59:03'),
(55,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 00:59:03'),
(56,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-28 00:59:03'),
(57,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 00:59:05'),
(58,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 00:59:05'),
(59,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 00:59:05'),
(60,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 00:59:05'),
(61,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',16,'2026-02-28 01:18:40'),
(62,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 01:18:40'),
(63,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 01:18:40'),
(64,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',15,'2026-02-28 01:18:40'),
(65,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',46,'2026-02-28 01:23:05'),
(66,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:23:05'),
(67,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:23:05'),
(68,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',15,'2026-02-28 01:23:05'),
(69,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',19,'2026-02-28 01:32:44'),
(70,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:32:45'),
(71,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:32:45'),
(72,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',15,'2026-02-28 01:32:45'),
(73,NULL,'GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',15,'2026-02-28 01:41:21'),
(74,NULL,'GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 01:41:22'),
(75,NULL,'GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:41:22'),
(76,NULL,'GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',14,'2026-02-28 01:41:22'),
(77,'qm303gs0','POST','/api/v1/auth/login',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',356,'2026-02-28 01:41:26'),
(78,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 01:41:27'),
(79,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 01:41:28'),
(80,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 01:41:28'),
(81,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 01:41:28'),
(82,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 01:41:31'),
(83,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',57,'2026-02-28 01:41:32'),
(84,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 01:41:32'),
(85,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 01:41:32'),
(86,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 01:41:32'),
(87,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:41:32'),
(88,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 01:41:32'),
(89,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',13,'2026-02-28 01:41:32'),
(90,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 01:41:32'),
(91,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 01:49:59'),
(92,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:50:00'),
(93,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 01:50:00'),
(94,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 01:56:15'),
(95,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:56:15'),
(96,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 01:56:15'),
(97,'qm303gs0','GET','/tr/admin/rbac',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',1,'2026-02-28 01:56:17'),
(98,'qm303gs0','GET','/tr/admin/rbac',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 01:58:11'),
(99,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:01:50'),
(100,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:01:50'),
(101,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:01:50'),
(102,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:01:51'),
(103,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',1,'2026-02-28 02:01:51'),
(104,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',1,'2026-02-28 02:01:51'),
(105,'qm303gs0','GET','/tr/admin/content',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:01:54'),
(106,'qm303gs0','GET','/tr/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:01:57'),
(107,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:02:01'),
(108,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:02:02'),
(109,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:02:02'),
(110,'qm303gs0','GET','/tr/admin/logs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:02:04'),
(111,'qm303gs0','GET','/tr/admin/ops',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:02:06'),
(112,'qm303gs0','GET','/api/v1/admin/settings',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:02:06'),
(113,'qm303gs0','GET','/tr/admin/ops',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:02:39'),
(114,'qm303gs0','GET','/api/v1/admin/settings',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:02:40'),
(115,'qm303gs0','GET','/tr/admin/ops',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:10:18'),
(116,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:10:34'),
(117,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:10:34'),
(118,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:10:34'),
(119,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:14:41'),
(120,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',55,'2026-02-28 02:14:41'),
(121,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-28 02:14:41'),
(122,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-28 02:14:41'),
(123,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:14:41'),
(124,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:14:41'),
(125,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:14:41'),
(126,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:14:41'),
(127,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:16:26'),
(128,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',28,'2026-02-28 02:16:26'),
(129,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:16:26'),
(130,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:16:26'),
(131,'qm303gs0','GET','/api/v1/admin/series',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:16:26'),
(132,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:16:26'),
(133,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:16:26'),
(134,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:16:26'),
(135,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',20,'2026-02-28 02:16:26'),
(136,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:16:27'),
(137,'qm303gs0','GET','/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:20:13'),
(138,'qm303gs0','GET','/',302,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:20:57'),
(139,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:20:57'),
(140,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:20:57'),
(141,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 02:20:57'),
(142,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',21,'2026-02-28 02:20:57'),
(143,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:20:59'),
(144,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:21:00'),
(145,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:21:00'),
(146,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:21:00'),
(147,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',16,'2026-02-28 02:24:19'),
(148,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:19'),
(149,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',16,'2026-02-28 02:24:19'),
(150,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:24:19'),
(151,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:24:19'),
(152,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:24:19'),
(153,'qm303gs0','GET','/tr/genre/game-system',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:24:21'),
(154,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:21'),
(155,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:21'),
(156,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:21'),
(157,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:24:21'),
(158,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:21'),
(159,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:24:28'),
(160,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:24:28'),
(161,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:24:28'),
(162,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:28'),
(163,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:28'),
(164,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:24:28'),
(165,'qm303gs0','GET','/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:24:30'),
(166,'qm303gs0','GET','/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:27:46'),
(167,'qm303gs0','GET','/api/v1/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',15,'2026-02-28 02:27:47'),
(168,'qm303gs0','GET','/api/v1/admin/rbac/roles',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:27:47'),
(169,'qm303gs0','GET','/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:31:55'),
(170,'qm303gs0','GET','/api/v1/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:31:55'),
(171,'qm303gs0','GET','/api/v1/admin/rbac/roles',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:31:55'),
(172,'qm303gs0','PUT','/api/v1/admin/users/qm303gs0',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',12,'2026-02-28 02:31:59'),
(173,'qm303gs0','GET','/api/v1/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:31:59'),
(174,'qm303gs0','GET','/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:32:01'),
(175,'qm303gs0','GET','/api/v1/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:32:01'),
(176,'qm303gs0','GET','/api/v1/admin/rbac/roles',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:32:01'),
(177,'qm303gs0','GET','/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:34:18'),
(178,'qm303gs0','GET','/api/v1/admin/users',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:34:18'),
(179,'qm303gs0','GET','/api/v1/admin/rbac/roles',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:34:18'),
(180,'qm303gs0','GET','/tr/admin/comments',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:34:22'),
(181,'qm303gs0','GET','/tr/admin/comments',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:07'),
(182,'qm303gs0','GET','/api/v1/admin/comments',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 02:36:07'),
(183,'qm303gs0','GET','/tr/admin/comments',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:08'),
(184,'qm303gs0','GET','/api/v1/admin/comments',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:36:09'),
(185,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:09'),
(186,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 02:36:10'),
(187,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:36:10'),
(188,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:10'),
(189,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:36:10'),
(190,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:36:10'),
(191,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',18,'2026-02-28 02:36:10'),
(192,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:10'),
(193,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:36:39'),
(194,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:39'),
(195,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',13,'2026-02-28 02:36:39'),
(196,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:36:39'),
(197,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:36:39'),
(198,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:36:39'),
(199,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:37:36'),
(200,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 02:37:36'),
(201,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:37:36'),
(202,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:37:36'),
(203,'qm303gs0','GET','/api/v1/admin/series',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:37:36'),
(204,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:37:36'),
(205,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:37:36'),
(206,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:37:36'),
(207,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',13,'2026-02-28 02:37:36'),
(208,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:37:36'),
(209,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:38:53'),
(210,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:38:53'),
(211,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:38:53'),
(212,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:38:53'),
(213,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',13,'2026-02-28 02:38:53'),
(214,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:38:53'),
(215,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:40:29'),
(216,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',10,'2026-02-28 02:40:29'),
(217,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',8,'2026-02-28 02:40:29'),
(218,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:40:29'),
(219,'qm303gs0','GET','/api/v1/admin/series',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:40:29'),
(220,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:40:29'),
(221,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:40:29'),
(222,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:40:29'),
(223,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',25,'2026-02-28 02:40:29'),
(224,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:40:29'),
(225,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:40:32'),
(226,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:40:33'),
(227,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:40:33'),
(228,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:40:33'),
(229,'qm303gs0','GET','/api/v1/admin/series',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:40:33'),
(230,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:40:33'),
(231,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:40:33'),
(232,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:40:33'),
(233,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',20,'2026-02-28 02:40:33'),
(234,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:40:33'),
(235,'qm303gs0','GET','/tr/admin',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:45:35'),
(236,'qm303gs0','GET','/api/v1/admin/overview',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',10,'2026-02-28 02:45:35'),
(237,'qm303gs0','GET','/api/v1/admin/stats/views',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 02:45:35'),
(238,'qm303gs0','GET','/api/v1/admin/stats/blogs',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',7,'2026-02-28 02:45:35'),
(239,'qm303gs0','GET','/api/v1/admin/series',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:45:35'),
(240,'qm303gs0','GET','/api/v1/series_genres',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:45:35'),
(241,'qm303gs0','GET','/api/v1/admin/stats/visits',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:45:35'),
(242,'qm303gs0','GET','/api/v1/admin/stats/reputation',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:45:35'),
(243,'qm303gs0','GET','/api/v1/admin/metrics',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',26,'2026-02-28 02:45:35'),
(244,'qm303gs0','GET','/api/v1/series_tags',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:45:35'),
(245,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:45:42'),
(246,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:42'),
(247,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:42'),
(248,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:45:42'),
(249,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',14,'2026-02-28 02:45:42'),
(250,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:42'),
(251,'qm303gs0','GET','/tr/light-novel',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',4,'2026-02-28 02:45:44'),
(252,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:45:44'),
(253,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:45:44'),
(254,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:44'),
(255,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:45:44'),
(256,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:44'),
(257,'qm303gs0','GET','/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',5,'2026-02-28 02:45:57'),
(258,'qm303gs0','GET','/api/v1/i18n/tr',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:45:57'),
(259,'qm303gs0','GET','/api/v1/user/profile',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',2,'2026-02-28 02:45:57'),
(260,'qm303gs0','GET','/api/v1/latest-chapters',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:57'),
(261,'qm303gs0','GET','/api/v1/home',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',6,'2026-02-28 02:45:57'),
(262,'qm303gs0','GET','/api/v1/user/notifications',200,'12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',3,'2026-02-28 02:45:57');
/*!40000 ALTER TABLE `system_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `system_jobs`
--

DROP TABLE IF EXISTS `system_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_type` varchar(80) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `status` enum('pending','done','failed') NOT NULL DEFAULT 'pending',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_job_queue_status_available` (`status`,`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_jobs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `system_jobs` WRITE;
/*!40000 ALTER TABLE `system_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `system_uploads`
--

DROP TABLE IF EXISTS `system_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `image_id` char(32) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` char(8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `image_id` (`image_id`),
  KEY `fk_image_user` (`user_id`),
  CONSTRAINT `fk_image_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_uploads`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `system_uploads` WRITE;
/*!40000 ALTER TABLE `system_uploads` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_uploads` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_activity`
--

DROP TABLE IF EXISTS `user_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `tab_id` char(16) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `duration_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_tab` (`user_id`,`tab_id`),
  KEY `idx_user_last_seen` (`user_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_activity`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_activity` WRITE;
/*!40000 ALTER TABLE `user_activity` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_activity` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_chapters_reads`
--

DROP TABLE IF EXISTS `user_chapters_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_chapters_reads` (
  `user_id` char(8) NOT NULL,
  `chapter_id` char(6) NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`chapter_id`),
  KEY `chapter_id` (`chapter_id`),
  KEY `idx_user_chapter_reads_read_at` (`read_at`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_chapters_reads`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_chapters_reads` WRITE;
/*!40000 ALTER TABLE `user_chapters_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_chapters_reads` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_follows`
--

DROP TABLE IF EXISTS `user_follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_follows` (
  `follower_id` char(8) NOT NULL,
  `followed_id` char(8) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`follower_id`,`followed_id`),
  KEY `following_id` (`followed_id`),
  KEY `idx_user_follows_created_at` (`created_at`),
  CONSTRAINT `1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`followed_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_follows`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_follows` WRITE;
/*!40000 ALTER TABLE `user_follows` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_follows` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_login_logs`
--

DROP TABLE IF EXISTS `user_login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `failure_reason` varchar(80) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auth_login_events_user_attempted` (`user_id`,`attempted_at`),
  KEY `idx_auth_login_events_email_attempted` (`email`,`attempted_at`),
  KEY `idx_auth_login_events_success_attempted` (`success`,`attempted_at`),
  CONSTRAINT `fk_auth_login_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_login_logs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_login_logs` WRITE;
/*!40000 ALTER TABLE `user_login_logs` DISABLE KEYS */;
INSERT INTO `user_login_logs` VALUES
(1,'qm303gs0','memo@novastrum.xyz','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',1,NULL,'2026-02-27 20:45:20'),
(2,'qm303gs0','memo@novastrum.xyz','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',1,NULL,'2026-02-28 00:58:54'),
(3,'qm303gs0','memo@novastrum.xyz','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0',1,NULL,'2026-02-28 01:41:26');
/*!40000 ALTER TABLE `user_login_logs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `actor_user_id` char(8) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(120) NOT NULL,
  `body` varchar(255) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_preferences` (
  `user_id` char(8) NOT NULL,
  `theme` enum('default','dark','royal','bootstrap','material','apple','glass') NOT NULL DEFAULT 'default',
  `reader_layout` enum('vertical','single','double') NOT NULL DEFAULT 'vertical',
  `reader_font_size` smallint(5) unsigned NOT NULL DEFAULT 18,
  `reader_font_family` varchar(50) NOT NULL DEFAULT 'var(--font-sans)',
  `reader_line_height` decimal(2,1) NOT NULL DEFAULT 1.8,
  `reader_font_weight` smallint(5) unsigned NOT NULL DEFAULT 400,
  `reader_reading_direction` enum('ltr','rtl') NOT NULL DEFAULT 'ltr',
  `reader_image_fit` enum('width','height','original') NOT NULL DEFAULT 'width',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lang` varchar(10) NOT NULL DEFAULT 'tr',
  PRIMARY KEY (`user_id`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES
('qm303gs0','glass','vertical',18,'var(--font-sans)',1.8,400,'ltr','width','2026-02-28 00:59:02','2026-02-28 00:59:02','tr');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_refresh_tokens`
--

DROP TABLE IF EXISTS `user_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_refresh_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_key` char(32) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_auth_refresh_tokens_session_active` (`session_key`,`revoked_at`,`expires_at`),
  CONSTRAINT `1` FOREIGN KEY (`session_key`) REFERENCES `user_sessions` (`session_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_refresh_tokens`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_refresh_tokens` WRITE;
/*!40000 ALTER TABLE `user_refresh_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_refresh_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_series_follows`
--

DROP TABLE IF EXISTS `user_series_follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_series_follows` (
  `user_id` char(8) NOT NULL,
  `content_id` char(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`content_id`),
  KEY `idx_user_content_follows_content_user` (`content_id`,`user_id`),
  KEY `idx_user_content_follows_created_at` (`created_at`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`content_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_series_follows`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_series_follows` WRITE;
/*!40000 ALTER TABLE `user_series_follows` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_series_follows` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `session_key` char(32) NOT NULL,
  `user_id` char(8) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_key`),
  KEY `idx_auth_sessions_user_active` (`user_id`,`revoked_at`,`expires_at`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES
('081717d0cc26f0846456379fbebcaf00','qm303gs0','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0','2026-02-27 20:45:20','2026-02-27 19:45:20',NULL,'2026-02-27 20:45:20'),
('3207fe0071fa225497eeaaeef5716e75','qm303gs0','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0','2026-02-28 00:58:54','2026-02-27 23:58:54',NULL,'2026-02-28 00:58:54'),
('8da78bb3ea2d351dcb8aaf7693ca4e74','qm303gs0','12ca17b49af2289436f303e0166030a21e525d266e209267433801a8fd4071a0','Mozilla/5.0 (X11; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0','2026-02-28 01:41:26','2026-02-28 00:41:26',NULL,'2026-02-28 01:41:26');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` char(8) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `roles` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`id` regexp '^[a-z0-9]{8}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
('qm303gs0','memo','memo@novastrum.xyz','$2y$12$12/jmf5tNlAvshAkJy.XQ.OTudHNN/3hhLbdWX1.cs96vtGgIM/ie',NULL,NULL,NULL,'2026-02-27 20:45:17','1');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-02-28  2:47:42
