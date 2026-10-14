CREATE TABLE IF NOT EXISTS medecin_profile_change_requests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  medecin_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  payload_json LONGTEXT NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  admin_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_change_medecin FOREIGN KEY (medecin_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_change_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_profile_change_medecin_status (medecin_id, status),
  INDEX idx_profile_change_status_submitted (status, submitted_at)
);
