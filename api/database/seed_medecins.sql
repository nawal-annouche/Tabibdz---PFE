USE tabibdz;

INSERT INTO users (role, prenom, nom, email, telephone, password_hash, wilaya_id, commune)
VALUES
('medecin','Sofia','Khaldi','sofia.khaldi@example.com','+213560666666','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',16,'Hussein Dey'),
('medecin','Rania','Mebarki','rania.mebarki@example.com','+213560777777','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',31,'Es Senia'),
('medecin','Nabil','Ait Ali','nabil.aitali@example.com','+213560888888','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',6,'Bejaia Centre'),
('medecin','Imane','Bouzid','imane.bouzid@example.com','+213560999999','$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq',25,'El Khroub')
ON DUPLICATE KEY UPDATE
prenom=VALUES(prenom), nom=VALUES(nom), telephone=VALUES(telephone), wilaya_id=VALUES(wilaya_id), commune=VALUES(commune);

INSERT INTO medecin_profiles (user_id, specialite_id, tarif, cnas, casnos, note, avis_count, adresse)
SELECT u.id, t.specialite_id, t.tarif, t.cnas, t.casnos, t.note, t.avis_count, t.adresse
FROM users u
JOIN (
    SELECT 'sofia.khaldi@example.com' AS email, 6 AS specialite_id, 3200 AS tarif, 1 AS cnas, 1 AS casnos, 4.7 AS note, 83 AS avis_count, 'Clinique El Fajr, Alger' AS adresse
    UNION ALL SELECT 'rania.mebarki@example.com', 7, 2600, 1, 0, 4.6, 66, 'Cabinet Dentaire El Bahia, Oran'
    UNION ALL SELECT 'nabil.aitali@example.com', 8, 3400, 0, 1, 4.8, 54, 'Centre Ortho Soummam, Bejaia'
    UNION ALL SELECT 'imane.bouzid@example.com', 2, 2900, 1, 1, 4.5, 42, 'Cabinet Derma Nova, Constantine'
) t ON t.email = u.email
ON DUPLICATE KEY UPDATE
specialite_id=VALUES(specialite_id), tarif=VALUES(tarif), cnas=VALUES(cnas), casnos=VALUES(casnos), note=VALUES(note), avis_count=VALUES(avis_count), adresse=VALUES(adresse);

INSERT INTO creneaux (medecin_id, start_at, end_at, is_available)
SELECT u.id,
       TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL d.day_offset DAY), t.start_time) AS start_at,
       TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL d.day_offset DAY), t.end_time)   AS end_at,
       1
FROM users u
JOIN (
    SELECT 1 AS day_offset
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
) d
JOIN (
    SELECT '09:00:00' AS start_time, '09:30:00' AS end_time
    UNION ALL SELECT '09:30:00','10:00:00'
    UNION ALL SELECT '10:00:00','10:30:00'
    UNION ALL SELECT '14:00:00','14:30:00'
    UNION ALL SELECT '14:30:00','15:00:00'
    UNION ALL SELECT '15:00:00','15:30:00'
) t
WHERE u.role='medecin'
ON DUPLICATE KEY UPDATE end_at=VALUES(end_at), is_available=VALUES(is_available);
