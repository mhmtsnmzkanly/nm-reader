-- Add scoped disciplinary levels without replacing existing ban history.
-- Warning/removal entries are audit-only; temporary/permanent entries are
-- enforced by RestrictedActionMiddleware for their matching scope.

SET @nmr_schema := DATABASE();
SET @nmr_has_ban_level := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @nmr_schema
      AND TABLE_NAME = 'bans'
      AND COLUMN_NAME = 'level'
);
SET @nmr_sql := IF(
    @nmr_has_ban_level = 0,
    'ALTER TABLE `bans` ADD COLUMN `level` ENUM(''warning'',''removal'',''temporary'',''permanent'') NOT NULL DEFAULT ''temporary'' AFTER `type`',
    'SELECT 1'
);
PREPARE nmr_stmt FROM @nmr_sql;
EXECUTE nmr_stmt;
DEALLOCATE PREPARE nmr_stmt;

CREATE TABLE IF NOT EXISTS `moderation_violations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` char(8) NOT NULL,
  `target_type` enum('series','chapter','blog','comment','system') NOT NULL,
  `target_id` varchar(32) NOT NULL,
  `scope` enum('general','comment','blog') NOT NULL DEFAULT 'general',
  `level` enum('warning','removal','temporary','permanent') NOT NULL DEFAULT 'warning',
  `action` enum('warn','remove','restrict') NOT NULL DEFAULT 'warn',
  `reason` text NOT NULL,
  `ban_id` bigint unsigned DEFAULT NULL,
  `moderator_user_id` char(8) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_violations_user_created` (`user_id`,`created_at`),
  KEY `idx_violations_target` (`target_type`,`target_id`,`created_at`),
  KEY `idx_violations_scope_level` (`scope`,`level`,`created_at`),
  KEY `idx_violations_ban` (`ban_id`),
  CONSTRAINT `fk_violations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_violations_ban` FOREIGN KEY (`ban_id`) REFERENCES `bans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_violations_moderator` FOREIGN KEY (`moderator_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
