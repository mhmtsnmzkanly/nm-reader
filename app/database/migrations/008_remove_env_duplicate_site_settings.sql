-- SITE_ADDRESS and ENFORCE_HTTPS are environment-only settings. Remove old
-- database rows left by pre-installer schemas so the admin site-config API
-- cannot expose two competing sources of truth.
DELETE FROM system_settings WHERE `key` IN ('site_address', 'enforce_https');
