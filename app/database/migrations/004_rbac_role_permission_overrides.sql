CREATE TABLE IF NOT EXISTS rbac_role_permission_overrides (
  role_slug varchar(32) NOT NULL,
  permission_code varchar(96) NOT NULL,
  effect enum('grant','revoke') NOT NULL,
  updated_by char(8) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (role_slug, permission_code),
  KEY idx_rbac_overrides_updated_by (updated_by),
  CONSTRAINT fk_rbac_overrides_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
