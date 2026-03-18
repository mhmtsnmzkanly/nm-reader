ALTER TABLE chapters
  ADD COLUMN price_amount int(10) unsigned NOT NULL DEFAULT 0,
  ADD COLUMN price_last_update datetime DEFAULT NULL;

UPDATE chapters ch
INNER JOIN chapter_access_products cap ON cap.chapter_id = ch.id
SET ch.price_amount = CASE WHEN cap.is_active = 1 THEN cap.price_coin ELSE 0 END,
    ch.price_last_update = cap.updated_at;

DROP TABLE IF EXISTS chapter_access_products;
