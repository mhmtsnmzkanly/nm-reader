-- Hot-path indexes for comments, notifications, auth logs, and audit logs.

ALTER TABLE social_comments
  ADD INDEX idx_comments_content_created (content_id, created_at),
  ADD INDEX idx_comments_chapter_created (chapter_id, created_at),
  ADD INDEX idx_comments_blog_created (blog_id, created_at),
  ADD INDEX idx_comments_user_created (user_id, created_at),
  ADD INDEX idx_comments_parent (parent_id);

ALTER TABLE user_notifications
  ADD INDEX idx_notifications_user_created (user_id, created_at),
  ADD INDEX idx_notifications_user_read (user_id, is_read, created_at);

ALTER TABLE user_login_logs
  ADD INDEX idx_login_attempted (attempted_at),
  ADD INDEX idx_login_user_attempted (user_id, attempted_at),
  ADD INDEX idx_login_email_attempted (email, attempted_at);

ALTER TABLE system_audit_logs
  ADD INDEX idx_audit_created (created_at),
  ADD INDEX idx_audit_status_created (status_code, created_at);
