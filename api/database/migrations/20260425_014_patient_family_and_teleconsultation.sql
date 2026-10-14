ALTER TABLE appointments
  ADD COLUMN consultation_mode ENUM('cabinet','teleconsultation') NOT NULL DEFAULT 'cabinet' AFTER type_visite,
  ADD COLUMN patient_target_type ENUM('self','family') NOT NULL DEFAULT 'self' AFTER consultation_mode,
  ADD COLUMN family_member_id BIGINT NULL AFTER patient_target_type,
  ADD COLUMN patient_prenom VARCHAR(80) NULL AFTER family_member_id,
  ADD COLUMN patient_nom VARCHAR(80) NULL AFTER patient_prenom,
  ADD COLUMN patient_date_naissance DATE NULL AFTER patient_nom,
  ADD COLUMN patient_telephone VARCHAR(30) NULL AFTER patient_date_naissance,
  ADD COLUMN patient_email VARCHAR(180) NULL AFTER patient_telephone,
  ADD COLUMN patient_relation VARCHAR(80) NULL AFTER patient_email,
  ADD COLUMN teleconsultation_notes TEXT NULL AFTER patient_relation;

CREATE TABLE IF NOT EXISTS patient_family_members (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  prenom VARCHAR(80) NOT NULL,
  nom VARCHAR(80) NOT NULL,
  relation VARCHAR(80) NOT NULL,
  date_naissance DATE NULL,
  sexe ENUM('Homme','Femme') NULL,
  telephone VARCHAR(30) NULL,
  email VARCHAR(180) NULL,
  notes_medicales TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_patient_family_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointment_documents (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_documents_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

ALTER TABLE appointments
  ADD CONSTRAINT fk_appointments_family_member
  FOREIGN KEY (family_member_id) REFERENCES patient_family_members(id) ON DELETE SET NULL;

INSERT INTO patient_family_members (patient_id, prenom, nom, relation, date_naissance, sexe, telephone, email, notes_medicales)
SELECT 1, 'Yasmine', 'Benali', 'Fille', '2016-04-09', 'Femme', '+213550123456', NULL, 'Suivi pediatrique regulier'
WHERE NOT EXISTS (
  SELECT 1 FROM patient_family_members
  WHERE patient_id = 1 AND prenom = 'Yasmine' AND nom = 'Benali' AND relation = 'Fille'
);

INSERT INTO patient_family_members (patient_id, prenom, nom, relation, date_naissance, sexe, telephone, email, notes_medicales)
SELECT 1, 'Nora', 'Benali', 'Mere', '1964-11-18', 'Femme', '+213550998877', 'nora.benali@example.com', 'Hypertension traitee'
WHERE NOT EXISTS (
  SELECT 1 FROM patient_family_members
  WHERE patient_id = 1 AND prenom = 'Nora' AND nom = 'Benali' AND relation = 'Mere'
);

UPDATE appointments a
INNER JOIN users u ON u.id = a.patient_id
SET
  a.patient_prenom = COALESCE(a.patient_prenom, u.prenom),
  a.patient_nom = COALESCE(a.patient_nom, u.nom),
  a.patient_date_naissance = COALESCE(a.patient_date_naissance, u.date_naissance),
  a.patient_telephone = COALESCE(a.patient_telephone, u.telephone),
  a.patient_email = COALESCE(a.patient_email, u.email),
  a.patient_relation = COALESCE(a.patient_relation, 'Moi-meme')
WHERE a.patient_prenom IS NULL OR a.patient_nom IS NULL;

INSERT INTO creneaux (medecin_id, start_at, end_at, is_available) VALUES
(2, '2026-04-28 09:15:00', '2026-04-28 09:45:00', 1),
(2, '2026-04-28 10:00:00', '2026-04-28 10:30:00', 1),
(2, '2026-05-01 09:00:00', '2026-05-01 09:30:00', 1),
(3, '2026-04-29 14:00:00', '2026-04-29 14:30:00', 1),
(3, '2026-05-03 15:00:00', '2026-05-03 15:30:00', 1),
(4, '2026-05-02 11:30:00', '2026-05-02 12:00:00', 1),
(4, '2026-05-05 09:30:00', '2026-05-05 10:00:00', 1),
(5, '2026-05-04 16:00:00', '2026-05-04 16:30:00', 1),
(5, '2026-05-07 16:30:00', '2026-05-07 17:00:00', 1),
(6, '2026-05-06 10:30:00', '2026-05-06 11:00:00', 1),
(6, '2026-05-08 09:30:00', '2026-05-08 10:00:00', 1)
ON DUPLICATE KEY UPDATE end_at = VALUES(end_at), is_available = VALUES(is_available);

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 2, NULL, '2026-04-28 09:15:00', 30, 'Controle annuel', 'suivi',
       'cabinet', 'self', 'Karim', 'Benali', '1991-06-12',
       '+213550123456', 'karim.benali@example.com', 'Moi-meme', NULL, 'confirme', 'TBZ-2026-3412'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-3412');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 3, NULL, '2026-04-29 14:00:00', 30, 'Consultation dermatologie', 'new',
       'teleconsultation', 'self', 'Karim', 'Benali', '1991-06-12',
       '+213550123456', 'karim.benali@example.com', 'Moi-meme', 'Photos et bilan deja transmises', 'confirme', 'TBZ-2026-3518'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-3518');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 4, NULL, '2026-05-02 11:30:00', 30, 'Controle de la vue', 'new',
       'cabinet', 'self', 'Karim', 'Benali', '1991-06-12',
       '+213550123456', 'karim.benali@example.com', 'Moi-meme', NULL, 'confirme', 'TBZ-2026-3624'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-3624');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 5, NULL, '2026-05-04 16:00:00', 30, 'Suivi pediatrique', 'suivi',
       'cabinet', 'family', 'Yasmine', 'Benali', '2016-04-09',
       '+213550123456', NULL, 'Fille', NULL, 'confirme', 'TBZ-2026-3740'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-3740');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 6, NULL, '2026-05-06 10:30:00', 30, 'Renouvellement ordonnance', 'suivi',
       'teleconsultation', 'family', 'Nora', 'Benali', '1964-11-18',
       '+213550998877', 'nora.benali@example.com', 'Mere', 'Ordonnance et derniers bilans a revoir', 'confirme', 'TBZ-2026-3856'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-3856');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 3, NULL, '2026-04-18 14:00:00', 30, 'Consultation dermatologie', 'new',
       'teleconsultation', 'self', 'Karim', 'Benali', '1991-06-12',
       '+213550123456', 'karim.benali@example.com', 'Moi-meme', 'Photos et bilan deja transmis', 'termine', 'TBZ-2026-1120'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-1120');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 5, NULL, '2026-04-10 11:00:00', 30, 'Suivi pediatrique', 'suivi',
       'cabinet', 'family', 'Yasmine', 'Benali', '2016-04-09',
       '+213550123456', NULL, 'Fille', NULL, 'annule', 'TBZ-2026-0974'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-0974');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 2, NULL, '2026-03-22 10:00:00', 30, 'Suivi tension arterielle', 'suivi',
       'cabinet', 'family', 'Nora', 'Benali', '1964-11-18',
       '+213550998877', 'nora.benali@example.com', 'Mere', NULL, 'termine', 'TBZ-2026-0833'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-0833');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 4, NULL, '2026-03-15 09:30:00', 30, 'Consultation ophtalmologie', 'new',
       'teleconsultation', 'self', 'Karim', 'Benali', '1991-06-12',
       '+213550123456', 'karim.benali@example.com', 'Moi-meme', 'Baisse de vision sur oeil droit', 'termine', 'TBZ-2026-0714'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-0714');

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
)
SELECT 1, 6, NULL, '2026-03-05 08:45:00', 30, 'Suivi medecine generale', 'suivi',
       'cabinet', 'self', 'Karim', 'Benali', '1991-06-12',
       '+213550123456', 'karim.benali@example.com', 'Moi-meme', NULL, 'annule', 'TBZ-2026-0541'
WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE confirmation_code = 'TBZ-2026-0541');

UPDATE appointments a
INNER JOIN patient_family_members pf
  ON pf.patient_id = a.patient_id
 AND pf.prenom = a.patient_prenom
 AND pf.nom = a.patient_nom
 AND pf.relation = a.patient_relation
SET a.family_member_id = pf.id
WHERE a.patient_target_type = 'family' AND a.family_member_id IS NULL;
