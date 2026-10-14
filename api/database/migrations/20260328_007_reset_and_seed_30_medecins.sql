-- Reset all existing doctors and reseed 30 complete profiles

DELETE a
FROM appointments a
INNER JOIN users u ON u.id = a.medecin_id
WHERE u.role = 'medecin';

DELETE pf
FROM patient_favoris pf
INNER JOIN users u ON u.id = pf.medecin_id
WHERE u.role = 'medecin';

DELETE c
FROM creneaux c
INNER JOIN users u ON u.id = c.medecin_id
WHERE u.role = 'medecin';

DELETE r
FROM medecin_reviews r
INNER JOIN users u ON u.id = r.medecin_id
WHERE u.role = 'medecin';

DELETE mp
FROM medecin_profiles mp
INNER JOIN users u ON u.id = mp.user_id
WHERE u.role = 'medecin';

DELETE FROM users WHERE role = 'medecin';

INSERT INTO users
  (id, role, prenom, nom, email, telephone, password_hash, email_verified_at, is_active, wilaya_id, commune)
VALUES
  (2001,'medecin','Amine','Boukhalfa','amine.boukhalfa@tabibdz.local','+213550200001','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,16,'Bab Ezzouar'),
  (2002,'medecin','Nadia','Yahia','nadia.yahia@tabibdz.local','+213550200002','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,31,'Bir El Djir'),
  (2003,'medecin','Lina','Bensaid','lina.bensaid@tabibdz.local','+213550200003','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,19,'El Eulma'),
  (2004,'medecin','Karim','Meziane','karim.meziane@tabibdz.local','+213550200004','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,25,'El Khroub'),
  (2005,'medecin','Sonia','Cherif','sonia.cherif@tabibdz.local','+213550200005','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,6,'Bejaia Centre'),
  (2006,'medecin','Samir','Ait Ali','samir.aitali@tabibdz.local','+213550200006','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,15,'Tizi Ouzou Centre'),
  (2007,'medecin','Yasmine','Benhamou','yasmine.benhamou@tabibdz.local','+213550200007','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,9,'Boufarik'),
  (2008,'medecin','Nabil','Mansouri','nabil.mansouri@tabibdz.local','+213550200008','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,34,'Bordj Bou Arreridj'),
  (2009,'medecin','Farah','Zerrouki','farah.zerrouki@tabibdz.local','+213550200009','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,16,'Hussein Dey'),
  (2010,'medecin','Ilyes','Rahmani','ilyes.rahmani@tabibdz.local','+213550200010','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,31,'Es Senia'),
  (2011,'medecin','Meriem','Tebib','meriem.tebib@tabibdz.local','+213550200011','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,19,'Setif Centre'),
  (2012,'medecin','Walid','Kaci','walid.kaci@tabibdz.local','+213550200012','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,6,'Akbou'),
  (2013,'medecin','Rania','Belaid','rania.belaid@tabibdz.local','+213550200013','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,25,'Constantine Centre'),
  (2014,'medecin','Yacine','Haddad','yacine.haddad@tabibdz.local','+213550200014','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,34,'Ras El Oued'),
  (2015,'medecin','Nour','Bouzid','nour.bouzid@tabibdz.local','+213550200015','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,9,'Blida Centre'),
  (2016,'medecin','Hakim','Saadi','hakim.saadi@tabibdz.local','+213550200016','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,16,'Sidi Mhamed'),
  (2017,'medecin','Imene','Mokrane','imene.mokrane@tabibdz.local','+213550200017','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,19,'Ain Oulmene'),
  (2018,'medecin','Tarek','Guerfi','tarek.guerfi@tabibdz.local','+213550200018','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,31,'Oran Centre'),
  (2019,'medecin','Sarra','Khaldi','sarra.khaldi@tabibdz.local','+213550200019','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,6,'Tazmalt'),
  (2020,'medecin','Anis','Benali','anis.benali@tabibdz.local','+213550200020','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,25,'Didouche Mourad'),
  (2021,'medecin','Lamia','Bouras','lamia.bouras@tabibdz.local','+213550200021','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,16,'Kouba'),
  (2022,'medecin','Reda','Chaalal','reda.chaalal@tabibdz.local','+213550200022','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,34,'BBA Centre'),
  (2023,'medecin','Amina','Hamdi','amina.hamdi@tabibdz.local','+213550200023','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,9,'Mouzaia'),
  (2024,'medecin','Sofiane','Belkacem','sofiane.belkacem@tabibdz.local','+213550200024','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,31,'Arzew'),
  (2025,'medecin','Maya','Ait Ouali','maya.aitouali@tabibdz.local','+213550200025','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,15,'Draa Ben Khedda'),
  (2026,'medecin','Rachid','Messaoud','rachid.messaoud@tabibdz.local','+213550200026','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,19,'El Eulma'),
  (2027,'medecin','Ines','Bouafia','ines.bouafia@tabibdz.local','+213550200027','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,25,'Ain Smara'),
  (2028,'medecin','Adel','Bourai','adel.bourai@tabibdz.local','+213550200028','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,6,'Amizour'),
  (2029,'medecin','Nesrine','Kadri','nesrine.kadri@tabibdz.local','+213550200029','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,34,'Mansourah'),
  (2030,'medecin','Bilal','Aouadi','bilal.aouadi@tabibdz.local','+213550200030','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',NOW(),1,16,'Cheraga');

INSERT INTO medecin_profiles
  (user_id, specialite_id, tarif, cnas, casnos, note, avis_count, adresse, langues, secteur, experience_years, agrement_no, bio, formations_text, hopitaux_text, mutuelle_private)
VALUES
  (2001,1,2800,1,1,0,0,'Clinique El Amal, Bab Ezzouar, Alger','Arabe,Francais','prive',12,'ALG-CARD-2001','Cardiologue specialise en prevention et suivi cardiovasculaire.','Doctorat medecine USTHB|Specialite cardiologie CHU Alger','CHU Mustapha Bacha|Clinique El Amal',1),
  (2002,2,3000,1,0,0,0,'Centre medical El Bahia, Bir El Djir, Oran','Arabe,Francais','prive',15,'ORN-DERM-2002','Dermatologue orientee diagnostic clinique et suivi des maladies chroniques.','Doctorat medecine Oran|Specialite dermatologie EHU Oran','EHU Oran|Centre El Bahia',1),
  (2003,4,2200,1,1,0,0,'Cabinet Sante Enfant, El Eulma, Setif','Arabe,Francais','prive',10,'SET-PED-2003','Pediatre pour nourrissons, enfants et suivi vaccinal.','Doctorat medecine Setif|Specialite pediatrie CHU Setif','CHU Setif|Polyclinique El Eulma',1),
  (2004,1,2500,0,1,0,0,'Cabinet Cardio Plus, El Khroub, Constantine','Arabe,Francais','mixte',9,'CST-CARD-2004','Cardiologue avec expertise ECG et bilan cardiovasculaire.','Doctorat medecine Constantine|Specialite cardiologie CHU Constantine','CHU Constantine|Cardio Plus',0),
  (2005,5,1800,1,1,0,0,'Polyclinique Les Jasmins, Bejaia Centre','Arabe,Francais,Tamazight','public',8,'BGY-MG-2005','Medecin generaliste de proximite.','Doctorat medecine Bejaia','CHU Bejaia|Polyclinique Les Jasmins',1),
  (2006,6,2600,1,1,0,0,'Centre Femme & Sante, Tizi Ouzou','Arabe,Francais,Tamazight','prive',14,'TZO-GYN-2006','Gynecologue-obstetricienne, suivi grossesse et consultation gynecologique.','Doctorat medecine Tizi Ouzou|Specialite gynecologie','CHU Nedir Mohamed|Centre Femme & Sante',1),
  (2007,5,1700,1,0,0,0,'Cabinet Medical Boufarik, Blida','Arabe,Francais','public',7,'BLD-MG-2007','Medecin generaliste orientee medecine familiale.','Doctorat medecine Blida','CHU Blida|Cabinet Boufarik',0),
  (2008,8,2400,1,1,0,0,'Clinique Ortho Vital, Bordj Bou Arreridj','Arabe,Francais,Tamazight','prive',11,'BBA-ORTH-2008','Orthopediste, traumatologie et douleurs articulaires.','Doctorat medecine Setif|Specialite orthopedie','CHU Setif|Ortho Vital',1),
  (2009,3,2900,1,0,0,0,'Vision Care, Hussein Dey, Alger','Arabe,Francais','prive',13,'ALG-OPHT-2009','Ophtalmologue pour bilans visuels et suivi complet.','Doctorat medecine Alger|Specialite ophtalmologie','CHU Beni Messous|Vision Care',1),
  (2010,7,2000,1,1,0,0,'Cabinet Dentaire Smile, Es Senia, Oran','Arabe,Francais','prive',6,'ORN-DENT-2010','Dentiste omnipraticien, soins conservateurs et prevention.','Doctorat chirurgie dentaire Oran','Faculte dentaire Oran|Smile Clinic',1),
  (2011,2,2700,1,1,0,0,'Dermacare Setif, Setif Centre','Arabe,Francais','prive',12,'SET-DERM-2011','Dermatologue specialisee pathologies cutanees et laser.','Doctorat medecine Setif|Specialite dermatologie','CHU Setif|Dermacare',1),
  (2012,4,2100,1,1,0,0,'Pediatrie Akbou, Akbou, Bejaia','Arabe,Francais,Tamazight','mixte',9,'BGY-PED-2012','Pediatre oriente suivi croissance et nutrition.','Doctorat medecine Bejaia|Specialite pediatrie','CHU Bejaia|Pediatrie Akbou',1),
  (2013,6,2500,1,0,0,0,'Centre Gyne Plus, Constantine Centre','Arabe,Francais','public',10,'CST-GYN-2013','Gynecologue pour depistage, suivi et prevention.','Doctorat medecine Constantine|Specialite gynecologie','CHU Constantine|Gyne Plus',0),
  (2014,8,2300,1,1,0,0,'Cabinet Ortho Ras El Oued, BBA','Arabe,Francais','prive',8,'BBA-ORTH-2014','Orthopediste specialise traumatologie sportive.','Doctorat medecine Setif|DESC orthopedie','CHU Setif|Ortho Ras El Oued',1),
  (2015,5,1600,1,1,0,0,'Cabinet Medical Blida Centre','Arabe,Francais','public',5,'BLD-MG-2015','Medecin generaliste, prevention et suivi maladies chroniques.','Doctorat medecine Blida','CHU Blida|Cabinet Blida Centre',0),
  (2016,1,3000,1,1,0,0,'Cardio Sidi Mhamed, Alger','Arabe,Francais','prive',18,'ALG-CARD-2016','Cardiologue interventionnel, consultations et suivi post-op.','Doctorat medecine Alger|Specialite cardiologie','CHU Mustapha|Cardio Sidi Mhamed',1),
  (2017,7,1900,1,0,0,0,'Dental Ain Oulmene, Setif','Arabe,Francais','mixte',7,'SET-DENT-2017','Dentiste pour soins, detartrage et esthetique dentaire.','Doctorat chirurgie dentaire Setif','CHU Setif|Dental Ain Oulmene',0),
  (2018,3,2800,1,1,0,0,'Vision Oran Centre, Oran','Arabe,Francais','prive',12,'ORN-OPHT-2018','Ophtalmologue specialisee cataracte et retine.','Doctorat medecine Oran|Specialite ophtalmologie','EHU Oran|Vision Oran',1),
  (2019,2,2600,1,1,0,0,'Derma Tazmalt, Bejaia','Arabe,Francais,Tamazight','prive',9,'BGY-DERM-2019','Dermatologue orientee acne, eczema, suivi dermatoses.','Doctorat medecine Bejaia|Specialite dermatologie','CHU Bejaia|Derma Tazmalt',1),
  (2020,5,1700,1,1,0,0,'Cabinet Didouche, Constantine','Arabe,Francais','public',6,'CST-MG-2020','Medecin generaliste pour consultation adulte et enfant.','Doctorat medecine Constantine','CHU Constantine|Cabinet Didouche',0),
  (2021,4,2300,1,1,0,0,'Pediatrie Kouba, Alger','Arabe,Francais','prive',11,'ALG-PED-2021','Pediatre specialisee suivi des nourrissons.','Doctorat medecine Alger|Specialite pediatrie','CHU Beni Messous|Pediatrie Kouba',1),
  (2022,8,2400,1,0,0,0,'Ortho BBA Centre','Arabe,Francais,Tamazight','mixte',10,'BBA-ORTH-2022','Orthopediste, reeducation et prise en charge traumatique.','Doctorat medecine Setif|Specialite orthopedie','CHU Setif|Ortho BBA Centre',0),
  (2023,6,2550,1,1,0,0,'Centre Gyne Mouzaia, Blida','Arabe,Francais','prive',12,'BLD-GYN-2023','Gynecologue, suivi grossesse et echographie clinique.','Doctorat medecine Blida|Specialite gynecologie','CHU Blida|Centre Gyne Mouzaia',1),
  (2024,1,2750,1,0,0,0,'Cardio Arzew, Oran','Arabe,Francais','public',9,'ORN-CARD-2024','Cardiologue clinique, suivi HTA et insuffisance cardiaque.','Doctorat medecine Oran|Specialite cardiologie','EHU Oran|Cardio Arzew',0),
  (2025,2,2650,1,1,0,0,'Derma Draa Ben Khedda, Tizi Ouzou','Arabe,Francais,Tamazight','prive',8,'TZO-DERM-2025','Dermatologue clinique et esthetique.','Doctorat medecine Tizi Ouzou|Specialite dermatologie','CHU Tizi Ouzou|Derma DBK',1),
  (2026,3,2850,1,1,0,0,'Vision El Eulma, Setif','Arabe,Francais','prive',13,'SET-OPHT-2026','Ophtalmologue oriente depistage et correction visuelle.','Doctorat medecine Setif|Specialite ophtalmologie','CHU Setif|Vision El Eulma',1),
  (2027,7,1950,1,1,0,0,'Dental Ain Smara, Constantine','Arabe,Francais','mixte',7,'CST-DENT-2027','Dentiste omnipraticienne, soins conservateurs et urgences.','Doctorat chirurgie dentaire Constantine','CHU Constantine|Dental Ain Smara',1),
  (2028,5,1650,1,1,0,0,'Cabinet Medical Amizour, Bejaia','Arabe,Francais,Tamazight','public',6,'BGY-MG-2028','Medecin generaliste, consultation de premiere ligne.','Doctorat medecine Bejaia','CHU Bejaia|Cabinet Amizour',0),
  (2029,4,2250,1,0,0,0,'Pediatrie Mansourah, BBA','Arabe,Francais,Tamazight','mixte',9,'BBA-PED-2029','Pediatre, suivi neonatal et pediatrie generale.','Doctorat medecine Setif|Specialite pediatrie','CHU Setif|Pediatrie Mansourah',0),
  (2030,6,2600,1,1,0,0,'Centre Gyne Cheraga, Alger','Arabe,Francais','prive',14,'ALG-GYN-2030','Gynecologue-obstetricien, consultation et suivi complet.','Doctorat medecine Alger|Specialite gynecologie','CHU Mustapha|Centre Gyne Cheraga',1);

-- Seed upcoming slots for all doctors (6 slots each)
INSERT INTO creneaux (medecin_id, start_at, end_at, is_available)
SELECT u.id,
       TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL s.day_offset DAY), SEC_TO_TIME(s.minute_of_day * 60)),
       DATE_ADD(TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL s.day_offset DAY), SEC_TO_TIME(s.minute_of_day * 60)), INTERVAL 30 MINUTE),
       1
FROM users u
JOIN (
  SELECT 1 AS day_offset, 540 AS minute_of_day UNION ALL
  SELECT 1, 570 UNION ALL
  SELECT 2, 840 UNION ALL
  SELECT 3, 540 UNION ALL
  SELECT 3, 870 UNION ALL
  SELECT 4, 600
) s
WHERE u.role = 'medecin';

-- Seed two verified reviews per doctor
INSERT INTO medecin_reviews (medecin_id, patient_id, patient_name, rating, comment, is_verified, created_at)
SELECT u.id, NULL, CONCAT('Patient ', SUBSTRING(u.prenom, 1, 1), '.'),
       5,
       CONCAT('Tres bonne consultation avec Dr. ', u.prenom, ' ', u.nom, '. Prise en charge professionnelle.'),
       1,
       DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM users u
WHERE u.role = 'medecin';

INSERT INTO medecin_reviews (medecin_id, patient_id, patient_name, rating, comment, is_verified, created_at)
SELECT u.id, NULL, CONCAT('Patient ', SUBSTRING(u.nom, 1, 1), '.'),
       4,
       CONCAT('Consultation claire et ponctuelle avec Dr. ', u.prenom, '.'),
       1,
       DATE_SUB(NOW(), INTERVAL 4 DAY)
FROM users u
WHERE u.role = 'medecin';

UPDATE medecin_profiles mp
JOIN (
  SELECT medecin_id, ROUND(AVG(rating), 1) AS avg_note, COUNT(*) AS cnt
  FROM medecin_reviews
  GROUP BY medecin_id
) r ON r.medecin_id = mp.user_id
SET mp.note = r.avg_note,
    mp.avis_count = r.cnt;


