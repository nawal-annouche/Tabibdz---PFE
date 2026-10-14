-- 2026-03-28 001: production foundation upgrade

CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  migration_name VARCHAR(191) NOT NULL UNIQUE,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users
  MODIFY COLUMN role ENUM('admin','patient','medecin') NOT NULL;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id VARCHAR(80) NULL,
  payload_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_actor (actor_user_id),
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at),
  FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(180) NOT NULL,
  phone VARCHAR(40) NULL,
  subject VARCHAR(180) NULL,
  message TEXT NOT NULL,
  status ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contact_status (status),
  INDEX idx_contact_created (created_at)
);

INSERT INTO users (role, prenom, nom, email, telephone, password_hash)
SELECT 'admin', 'Admin', 'TabibDZ', 'admin@tabibdz.local', '+213000000000', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq'
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE email = 'admin@tabibdz.local'
);
