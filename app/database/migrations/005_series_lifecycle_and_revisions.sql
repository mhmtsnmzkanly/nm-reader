ALTER TABLE series
  ADD COLUMN IF NOT EXISTS lifecycle_status enum('draft','scheduled','published','archived') NOT NULL DEFAULT 'published' AFTER status,
  ADD COLUMN IF NOT EXISTS scheduled_at datetime DEFAULT NULL AFTER lifecycle_status,
  ADD COLUMN IF NOT EXISTS published_at datetime DEFAULT NULL AFTER scheduled_at,
  ADD COLUMN IF NOT EXISTS archived_at datetime DEFAULT NULL AFTER published_at,
  ADD KEY IF NOT EXISTS idx_series_lifecycle_schedule (lifecycle_status, scheduled_at);

UPDATE series SET published_at = COALESCE(published_at, created_at) WHERE lifecycle_status = 'published';

CREATE TABLE IF NOT EXISTS series_revisions (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  series_id char(6) NOT NULL,
  moderator_user_id char(8) DEFAULT NULL,
  action varchar(32) NOT NULL,
  snapshot_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(snapshot_json)),
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_series_revisions_series_created (series_id, created_at),
  KEY idx_series_revisions_moderator (moderator_user_id),
  CONSTRAINT fk_series_revisions_series FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE,
  CONSTRAINT fk_series_revisions_moderator FOREIGN KEY (moderator_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
