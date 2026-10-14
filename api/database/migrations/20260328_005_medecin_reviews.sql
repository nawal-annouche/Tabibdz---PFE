CREATE TABLE IF NOT EXISTS medecin_reviews (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  medecin_id INT NOT NULL,
  patient_id INT NULL,
  patient_name VARCHAR(120) NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_medecin FOREIGN KEY (medecin_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_reviews_medecin_date (medecin_id, created_at),
  INDEX idx_reviews_medecin_rating (medecin_id, rating)
);

INSERT INTO medecin_reviews (medecin_id, patient_id, patient_name, rating, comment, is_verified, created_at)
SELECT 2, 1, 'Karim B.', 5, 'Médecin attentive, explications claires et prise en charge efficace.', 1, '2026-03-15 10:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM medecin_reviews WHERE medecin_id = 2 AND patient_name = 'Karim B.' AND created_at = '2026-03-15 10:00:00'
);

INSERT INTO medecin_reviews (medecin_id, patient_id, patient_name, rating, comment, is_verified, created_at)
SELECT 3, NULL, 'Nassima B.', 4, 'Consultation sérieuse, bon diagnostic, délai d''attente raisonnable.', 1, '2026-03-02 09:30:00'
WHERE NOT EXISTS (
  SELECT 1 FROM medecin_reviews WHERE medecin_id = 3 AND patient_name = 'Nassima B.' AND created_at = '2026-03-02 09:30:00'
);

INSERT INTO medecin_reviews (medecin_id, patient_id, patient_name, rating, comment, is_verified, created_at)
SELECT 5, NULL, 'Mohamed H.', 5, 'Réservation en ligne rapide et consultation très professionnelle.', 1, '2026-02-18 11:20:00'
WHERE NOT EXISTS (
  SELECT 1 FROM medecin_reviews WHERE medecin_id = 5 AND patient_name = 'Mohamed H.' AND created_at = '2026-02-18 11:20:00'
);
