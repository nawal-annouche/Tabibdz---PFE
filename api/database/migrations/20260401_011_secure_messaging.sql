CREATE TABLE IF NOT EXISTS message_threads (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  medecin_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_message_at DATETIME NULL,
  UNIQUE KEY uq_message_thread_pair (patient_id, medecin_id),
  INDEX idx_message_threads_patient (patient_id, last_message_at),
  INDEX idx_message_threads_medecin (medecin_id, last_message_at),
  CONSTRAINT fk_message_threads_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_threads_medecin FOREIGN KEY (medecin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT NOT NULL,
  sender_user_id INT NOT NULL,
  sender_role ENUM('patient','medecin') NOT NULL,
  body TEXT NOT NULL,
  read_at_patient DATETIME NULL,
  read_at_medecin DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_thread_created (thread_id, created_at),
  INDEX idx_messages_sender (sender_user_id),
  CONSTRAINT fk_messages_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
);
