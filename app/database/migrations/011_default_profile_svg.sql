-- Use the bundled SVG avatar when an installation still points to the old
-- missing PNG placeholder. Custom profile image settings remain unchanged.
UPDATE system_settings
SET value = '/assets/img/default-profile.svg', updated_at = NOW()
WHERE `key` = 'default_profile_image'
  AND value = '/assets/img/default-profile.png';
