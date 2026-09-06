ALTER TABLE system_jobs
    MODIFY COLUMN status ENUM('pending','processing','done','failed','cancelled') NOT NULL DEFAULT 'pending';
