-- Additive users + per-user columns. Do not DROP applications.
-- Prefer App/Auth::ensureSchema() which is idempotent.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL DEFAULT '',
  username VARCHAR(80) NOT NULL,
  email VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_settings (
  user_id INT UNSIGNED NOT NULL,
  `key` VARCHAR(64) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (user_id, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
