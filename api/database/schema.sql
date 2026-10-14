CREATE DATABASE IF NOT EXISTS tabibdz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tabibdz;

CREATE TABLE IF NOT EXISTS wilayas (
  id INT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL
);

CREATE TABLE IF NOT EXISTS communes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wilaya_id INT NOT NULL,
  nom VARCHAR(120) NOT NULL,
  FOREIGN KEY (wilaya_id) REFERENCES wilayas(id)
);

CREATE TABLE IF NOT EXISTS specialites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon_name VARCHAR(40) NOT NULL DEFAULT 'activity',
  featured TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('patient','medecin') NOT NULL,
  prenom VARCHAR(80) NOT NULL,
  nom VARCHAR(80) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  telephone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  photo_url VARCHAR(255) NULL,
  date_naissance DATE NULL,
  sexe ENUM('Homme','Femme') NULL,
  wilaya_id INT NULL,
  commune VARCHAR(120) NULL,
  groupe_sanguin VARCHAR(8) NULL,
  num_assurance VARCHAR(80) NULL,
  allergies TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (wilaya_id) REFERENCES wilayas(id)
);

CREATE TABLE IF NOT EXISTS medecin_profiles (
  user_id INT PRIMARY KEY,
  specialite_id INT NOT NULL,
  tarif DECIMAL(10,2) NOT NULL DEFAULT 0,
  cnas TINYINT(1) NOT NULL DEFAULT 0,
  casnos TINYINT(1) NOT NULL DEFAULT 0,
  note DECIMAL(3,2) NOT NULL DEFAULT 0,
  avis_count INT NOT NULL DEFAULT 0,
  adresse VARCHAR(255) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (specialite_id) REFERENCES specialites(id)
);

CREATE TABLE IF NOT EXISTS creneaux (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  medecin_id INT NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_medecin_start (medecin_id, start_at),
  FOREIGN KEY (medecin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  medecin_id INT NOT NULL,
  creneau_id BIGINT NULL,
  date_heure DATETIME NOT NULL,
  duree_min INT NOT NULL DEFAULT 30,
  motif TEXT NULL,
  type_visite ENUM('new','suivi') NOT NULL DEFAULT 'new',
  consultation_mode ENUM('cabinet','teleconsultation') NOT NULL DEFAULT 'cabinet',
  patient_target_type ENUM('self','family') NOT NULL DEFAULT 'self',
  family_member_id BIGINT NULL,
  patient_prenom VARCHAR(80) NULL,
  patient_nom VARCHAR(80) NULL,
  patient_date_naissance DATE NULL,
  patient_telephone VARCHAR(30) NULL,
  patient_email VARCHAR(180) NULL,
  patient_relation VARCHAR(80) NULL,
  teleconsultation_notes TEXT NULL,
  statut ENUM('confirme','termine','annule') NOT NULL DEFAULT 'confirme',
  confirmation_code VARCHAR(30) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES users(id),
  FOREIGN KEY (medecin_id) REFERENCES users(id),
  FOREIGN KEY (creneau_id) REFERENCES creneaux(id) ON DELETE SET NULL
);

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
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointment_documents (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT NOT NULL,
  document_type VARCHAR(40) NOT NULL DEFAULT 'patient_document',
  uploaded_by_role ENUM('patient','medecin') NOT NULL DEFAULT 'patient',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

ALTER TABLE appointments
  ADD CONSTRAINT fk_appointments_family_member
  FOREIGN KEY (family_member_id) REFERENCES patient_family_members(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS patient_favoris (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  medecin_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fav (patient_id, medecin_id),
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (medecin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notification_preferences (
  patient_id INT PRIMARY KEY,
  sms_24h TINYINT(1) NOT NULL DEFAULT 1,
  sms_2h TINYINT(1) NOT NULL DEFAULT 0,
  email_24h TINYINT(1) NOT NULL DEFAULT 1,
  email_2h TINYINT(1) NOT NULL DEFAULT 1,
  sms_annulation TINYINT(1) NOT NULL DEFAULT 1,
  email_annulation TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

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

CREATE TABLE IF NOT EXISTS message_attachments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT NOT NULL,
  kind ENUM('image','file','audio') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_message_attachments_message (message_id),
  CONSTRAINT fk_message_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointment_notification_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT NOT NULL,
  patient_id INT NOT NULL,
  event_key VARCHAR(64) NOT NULL,
  notification_id BIGINT NULL,
  email_sent_at DATETIME NULL,
  email_error TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_appointment_notification_event (appointment_id, event_key),
  KEY idx_appointment_notification_patient (patient_id, created_at),
  CONSTRAINT fk_appointment_notification_events_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_notification_events_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_notification_events_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS auth_sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  jti VARCHAR(64) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  user_agent VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_auth_user (user_id),
  INDEX idx_auth_exp (expires_at)
);

INSERT INTO wilayas (id, nom) VALUES
(1,'Adrar'),
(2,'Chlef'),
(3,'Laghouat'),
(4,'Oum El Bouaghi'),
(5,'Batna'),
(6,'Bejaia'),
(7,'Biskra'),
(8,'Bechar'),
(9,'Blida'),
(10,'Bouira'),
(11,'Tamanrasset'),
(12,'Tebessa'),
(13,'Tlemcen'),
(14,'Tiaret'),
(15,'Tizi Ouzou'),
(16,'Alger'),
(17,'Djelfa'),
(18,'Jijel'),
(19,'Setif'),
(20,'Saida'),
(21,'Skikda'),
(22,'Sidi Bel Abbes'),
(23,'Annaba'),
(24,'Guelma'),
(25,'Constantine'),
(26,'Medea'),
(27,'Mostaganem'),
(28,'Msila'),
(29,'Mascara'),
(30,'Ouargla'),
(31,'Oran'),
(32,'El Bayadh'),
(33,'Illizi'),
(34,'Bordj Bou Arreridj'),
(35,'Boumerdes'),
(36,'El Tarf'),
(37,'Tindouf'),
(38,'Tissemsilt'),
(39,'El Oued'),
(40,'Khenchela'),
(41,'Souk Ahras'),
(42,'Tipaza'),
(43,'Mila'),
(44,'Ain Defla'),
(45,'Naama'),
(46,'Ain Temouchent'),
(47,'Ghardaia'),
(48,'Relizane'),
(49,'Timimoun'),
(50,'Bordj Badji Mokhtar'),
(51,'Ouled Djellal'),
(52,'Beni Abbes'),
(53,'In Salah'),
(54,'In Guezzam'),
(55,'Touggourt'),
(56,'Djanet'),
(57,'El M Ghair'),
(58,'El Meniaa'),
(59,'Aflou'),
(60,'El Abiodh Sidi Cheikh'),
(61,'El Kantara'),
(62,'Bir El Ater'),
(63,'El Aricha'),
(64,'Ksar Chellala'),
(65,'Ain Oussara'),
(66,'Messaad'),
(67,'Ksar El Boukhari'),
(68,'Boussaada'),
(69,'Barika')
ON DUPLICATE KEY UPDATE nom=VALUES(nom);

INSERT INTO communes (wilaya_id, nom) VALUES
(16,'Sidi M''hamed'),(16,'Bab Ezzouar'),(16,'Hussein Dey'),
(31,'Es Senia'),(31,'Bir El Djir'),
(25,'El Khroub'),(19,'El Eulma'),(9,'Boufarik');

INSERT INTO specialites (id, nom, slug, icon_name, featured) VALUES
(1,'Cardiologie','cardiologie','heart',1),
(2,'Dermatologie','dermatologie','user',1),
(3,'Ophtalmologie','ophtalmologie','eye',1),
(4,'Pediatrie','pediatrie','smile',1),
(5,'Medecine generale','medecine-generale','activity',1),
(6,'Gynecologie','gynecologie','female',1),
(7,'Dentisterie','dentisterie','tooth',1),
(8,'Orthopedie','orthopedie','bone',1)
ON DUPLICATE KEY UPDATE nom=VALUES(nom), slug=VALUES(slug), icon_name=VALUES(icon_name), featured=VALUES(featured);

INSERT INTO users (id, role, prenom, nom, email, telephone, password_hash, photo_url, date_naissance, sexe, wilaya_id, commune, groupe_sanguin, num_assurance, allergies) VALUES
(1, 'patient', 'Karim', 'Benali', 'karim.benali@example.com', '+213550123456', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq', NULL, '1991-06-12', 'Homme', 16, 'Bab Ezzouar', 'O+', 'CNAS-00215987', 'Aucune allergie connue'),
(2, 'medecin', 'Amira', 'Benali', 'amira.benali@example.com', '+213560111111', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq', NULL, NULL, NULL, 16, 'Sidi M''hamed', NULL, NULL, NULL),
(3, 'medecin', 'Nadia', 'Yahia', 'nadia.yahia@example.com', '+213560222222', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq', NULL, NULL, NULL, 31, 'Bir El Djir', NULL, NULL, NULL),
(4, 'medecin', 'Samir', 'Zerrouki', 'samir.z@example.com', '+213560333333', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq', NULL, NULL, NULL, 25, 'El Khroub', NULL, NULL, NULL),
(5, 'medecin', 'Lina', 'Bensaid', 'lina.bensaid@example.com', '+213560444444', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq', NULL, NULL, NULL, 19, 'El Eulma', NULL, NULL, NULL),
(6, 'medecin', 'Yacine', 'Mansouri', 'yacine.m@example.com', '+213560555555', '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq', NULL, NULL, NULL, 9, 'Boufarik', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE prenom=VALUES(prenom), nom=VALUES(nom), email=VALUES(email), telephone=VALUES(telephone), wilaya_id=VALUES(wilaya_id), commune=VALUES(commune);

INSERT INTO medecin_profiles (user_id, specialite_id, tarif, cnas, casnos, note, avis_count, adresse) VALUES
(2,1,2500,1,1,4.8,124,'16 Rue Didouche Mourad, Alger'),
(3,2,3000,1,0,4.7,96,'Centre medical El Bahia, Oran'),
(4,3,2800,0,1,4.6,71,'Clinique El Wifak, Constantine'),
(5,4,2200,1,1,4.9,143,'Cabinet Sante Enfant, Setif'),
(6,5,1800,1,1,4.5,58,'Polyclinique El Amal, Blida')
ON DUPLICATE KEY UPDATE specialite_id=VALUES(specialite_id), tarif=VALUES(tarif), cnas=VALUES(cnas), casnos=VALUES(casnos), note=VALUES(note), avis_count=VALUES(avis_count), adresse=VALUES(adresse);

INSERT IGNORE INTO patient_favoris (patient_id, medecin_id) VALUES (1,2),(1,3),(1,5);

INSERT INTO patient_family_members (patient_id, prenom, nom, relation, date_naissance, sexe, telephone, email, notes_medicales) VALUES
(1, 'Yasmine', 'Benali', 'Fille', '2016-04-09', 'Femme', '+213550123456', NULL, 'Suivi pediatrique regulier'),
(1, 'Nora', 'Benali', 'Mere', '1964-11-18', 'Femme', '+213550998877', 'nora.benali@example.com', 'Hypertension traitee')
ON DUPLICATE KEY UPDATE relation=VALUES(relation), telephone=VALUES(telephone), email=VALUES(email), notes_medicales=VALUES(notes_medicales);

INSERT INTO notification_preferences (patient_id, sms_24h, sms_2h, email_24h, email_2h, sms_annulation, email_annulation)
VALUES (1,1,0,1,1,1,1)
ON DUPLICATE KEY UPDATE sms_24h=VALUES(sms_24h), sms_2h=VALUES(sms_2h), email_24h=VALUES(email_24h), email_2h=VALUES(email_2h), sms_annulation=VALUES(sms_annulation), email_annulation=VALUES(email_annulation);

INSERT INTO notifications (patient_id, title, body, is_read) VALUES
(1, 'Rappel rendez-vous', 'Votre rendez-vous avec Dr. Amira Benali est demain a 09:15.', 0),
(1, 'Profil mis a jour', 'Vos informations de profil ont ete enregistrees avec succes.', 1),
(1, 'Nouveau creneau disponible', 'Un nouveau creneau est disponible cette semaine.', 0);

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
ON DUPLICATE KEY UPDATE end_at=VALUES(end_at), is_available=VALUES(is_available);

INSERT INTO appointments (
  patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
  consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance,
  patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code
) VALUES
(1,2,NULL,'2026-04-28 09:15:00',30,'Controle annuel','suivi','cabinet','self','Karim','Benali','1991-06-12','+213550123456','karim.benali@example.com','Moi-meme',NULL,'confirme','TBZ-2026-3412'),
(1,3,NULL,'2026-04-29 14:00:00',30,'Consultation dermatologie','new','teleconsultation','self','Karim','Benali','1991-06-12','+213550123456','karim.benali@example.com','Moi-meme','Photos et bilan deja transmises','confirme','TBZ-2026-3518'),
(1,4,NULL,'2026-05-02 11:30:00',30,'Controle de la vue','new','cabinet','self','Karim','Benali','1991-06-12','+213550123456','karim.benali@example.com','Moi-meme',NULL,'confirme','TBZ-2026-3624'),
(1,5,NULL,'2026-05-04 16:00:00',30,'Suivi pediatrique','suivi','cabinet','family','Yasmine','Benali','2016-04-09','+213550123456',NULL,'Fille',NULL,'confirme','TBZ-2026-3740'),
(1,6,NULL,'2026-05-06 10:30:00',30,'Renouvellement ordonnance','suivi','teleconsultation','family','Nora','Benali','1964-11-18','+213550998877','nora.benali@example.com','Mere','Ordonnance et derniers bilans a revoir','confirme','TBZ-2026-3856'),
(1,3,NULL,'2026-04-18 14:00:00',30,'Consultation dermatologie','new','teleconsultation','self','Karim','Benali','1991-06-12','+213550123456','karim.benali@example.com','Moi-meme','Photos et bilan deja transmis','termine','TBZ-2026-1120'),
(1,5,NULL,'2026-04-10 11:00:00',30,'Suivi pediatrique','suivi','cabinet','family','Yasmine','Benali','2016-04-09','+213550123456',NULL,'Fille',NULL,'annule','TBZ-2026-0974'),
(1,2,NULL,'2026-03-22 10:00:00',30,'Suivi tension arterielle','suivi','cabinet','family','Nora','Benali','1964-11-18','+213550998877','nora.benali@example.com','Mere',NULL,'termine','TBZ-2026-0833'),
(1,4,NULL,'2026-03-15 09:30:00',30,'Consultation ophtalmologie','new','teleconsultation','self','Karim','Benali','1991-06-12','+213550123456','karim.benali@example.com','Moi-meme','Baisse de vision sur oeil droit','termine','TBZ-2026-0714'),
(1,6,NULL,'2026-03-05 08:45:00',30,'Suivi medecine generale','suivi','cabinet','self','Karim','Benali','1991-06-12','+213550123456','karim.benali@example.com','Moi-meme',NULL,'annule','TBZ-2026-0541')
ON DUPLICATE KEY UPDATE
  date_heure=VALUES(date_heure),
  motif=VALUES(motif),
  type_visite=VALUES(type_visite),
  consultation_mode=VALUES(consultation_mode),
  patient_target_type=VALUES(patient_target_type),
  patient_prenom=VALUES(patient_prenom),
  patient_nom=VALUES(patient_nom),
  patient_date_naissance=VALUES(patient_date_naissance),
  patient_telephone=VALUES(patient_telephone),
  patient_email=VALUES(patient_email),
  patient_relation=VALUES(patient_relation),
  teleconsultation_notes=VALUES(teleconsultation_notes),
  statut=VALUES(statut);

UPDATE appointments a
INNER JOIN patient_family_members pf
  ON pf.patient_id = a.patient_id
 AND pf.prenom = a.patient_prenom
 AND pf.nom = a.patient_nom
 AND pf.relation = a.patient_relation
SET a.family_member_id = pf.id
WHERE a.patient_target_type = 'family' AND a.family_member_id IS NULL;
