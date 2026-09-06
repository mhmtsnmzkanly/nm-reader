-- Opening a chapter must not imply that it was completed.
ALTER TABLE user_reading_history
    MODIFY progress_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
    MODIFY is_completed TINYINT(1) NOT NULL DEFAULT 0;
