CREATE TABLE IF NOT EXISTS appointment_notification_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT NOT NULL,
  patient_id INT NOT NULL,
  event_key VARCHAR(64) NOT NULL,
  notification_id BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_appointment_notification_event (appointment_id, event_key),
  KEY idx_appointment_notification_patient (patient_id, created_at),
  CONSTRAINT fk_appointment_notification_appointment
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_notification_patient
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_notification_notification
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
);
