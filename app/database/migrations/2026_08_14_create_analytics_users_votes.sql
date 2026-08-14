CREATE TABLE IF NOT EXISTS `analytics_users_votes` (
  `user_id` varchar(64) NOT NULL,
  `votes_cast` int(11) NOT NULL DEFAULT 0,
  `upvotes_received` int(11) NOT NULL DEFAULT 0,
  `downvotes_received` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
