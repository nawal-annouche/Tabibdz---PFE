-- 2026-03-28 002: auth hardening

ALTER TABLE users
  ADD COLUMN email_verified_at DATETIME NULL AFTER email,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER email_verified_at;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_prt_user (user_id),
  INDEX idx_prt_exp (expires_at)
);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_evt_user (user_id),
  INDEX idx_evt_exp (expires_at)
);

CREATE TABLE IF NOT EXISTS rate_limit_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  action_key VARCHAR(120) NOT NULL,
  subject_key VARCHAR(191) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rle_action_subject_time (action_key, subject_key, created_at),
  INDEX idx_rle_created (created_at)
);

UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL;
