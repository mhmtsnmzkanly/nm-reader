-- Add analytics_search_logs table for search analytics persistence.

CREATE TABLE IF NOT EXISTS analytics_search_logs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id char(8) DEFAULT NULL,
  query varchar(255) NOT NULL,
  result_count int(11) NOT NULL DEFAULT 0,
  ip_hash char(64) NOT NULL,
  searched_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_search_logs_date (searched_at),
  KEY idx_search_logs_query (query)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
