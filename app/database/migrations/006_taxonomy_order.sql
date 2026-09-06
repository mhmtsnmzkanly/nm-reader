ALTER TABLE taxonomies
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER ui_config,
    ADD INDEX IF NOT EXISTS idx_taxonomy_type_order (type, sort_order, name);
