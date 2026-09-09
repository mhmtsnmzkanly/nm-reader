-- Keep search snapshots self-contained so analytics APIs do not need to read
-- the raw search log for grouped counts, averages, or the last search time.
ALTER TABLE analytics_snapshots_search
    ADD COLUMN IF NOT EXISTS result_total BIGINT NOT NULL DEFAULT 0 AFTER zero_result_count,
    ADD COLUMN IF NOT EXISTS last_searched_at DATETIME DEFAULT NULL AFTER result_total;
