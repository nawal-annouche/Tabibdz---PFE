<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../_core/db.php';

$pdo = db();
$passwordHash = '$2y$10$DwRY82E2Wtvv08mWENfWb.6gv7jgYn3fjQnDKn2/KNzKvAN4tYQWq';

$doctorFirstNames = [
    'Amine','Nadia','Lina','Karim','Sonia','Samir','Yasmine','Nabil','Farah','Ilyes',
    'Meriem','Walid','Rania','Yacine','Nour','Hakim','Imene','Tarek','Sarra','Anis',
    'Lamia','Reda','Amina','Sofiane','Maya','Rachid','Ines','Adel','Nesrine','Bilal',
];
$patientFirstNames = [
    'Youssef','Kenza','Rafik','Nassima','Alaa','Houda','Mehdi','Amel','Omar','Siham',
    'Nassim','Loubna','Hichem','Dalila','Saber','Nadjah','Salim','Myriam','Aymen','Sabrina',
    'Mohamed','Imane','Hamza','Yasmina','Ismail','Khadidja','Samy','Fella','Zinedine','Rym',
];
$lastNames = [
    'Benali','Yahia','Bensaid','Meziane','Cherif','Ait Ali','Benhamou','Mansouri','Zerrouki','Rahmani',
    'Tebib','Kaci','Belaid','Haddad','Bouzid','Saadi','Mokrane','Guerfi','Khaldi','Aouadi',
    'Bouras','Chaalal','Hamdi','Belkacem','Ait Ouali','Messaoud','Bouafia','Bourai','Kadri','Benkhelifa',
];
$specialities = [
    1 => ['nom' => 'Cardiologie', 'slug' => 'cardiologie', 'tarif' => 2900, 'tele_fee' => 3200, 'sector' => 'prive', 'bio' => 'Cardiologue orienté prévention, HTA et suivi cardiovasculaire.'],
    2 => ['nom' => 'Dermatologie', 'slug' => 'dermatologie', 'tarif' => 2700, 'tele_fee' => 2900, 'sector' => 'prive', 'bio' => 'Dermatologue pour pathologies cutanées, suivi et conseils personnalisés.'],
    3 => ['nom' => 'Ophtalmologie', 'slug' => 'ophtalmologie', 'tarif' => 2850, 'tele_fee' => 3000, 'sector' => 'prive', 'bio' => 'Ophtalmologue pour bilans visuels, dépistage et suivi ophtalmologique.'],
    4 => ['nom' => 'Pediatrie', 'slug' => 'pediatrie', 'tarif' => 2300, 'tele_fee' => 2500, 'sector' => 'mixte', 'bio' => 'Pédiatre pour nourrissons, enfants, vaccination et suivi de croissance.'],
    5 => ['nom' => 'Medecine generale', 'slug' => 'medecine-generale', 'tarif' => 1800, 'tele_fee' => 2100, 'sector' => 'public', 'bio' => 'Médecin généraliste de proximité pour consultations du quotidien et suivi médical.'],
    6 => ['nom' => 'Gynecologie', 'slug' => 'gynecologie', 'tarif' => 2600, 'tele_fee' => 2800, 'sector' => 'prive', 'bio' => 'Gynécologue pour suivi, dépistage, grossesse et consultation spécialisée.'],
    7 => ['nom' => 'Dentisterie', 'slug' => 'dentisterie', 'tarif' => 2100, 'tele_fee' => 0, 'sector' => 'mixte', 'bio' => 'Dentiste omnipraticien pour soins conservateurs, contrôle et urgence dentaire.'],
    8 => ['nom' => 'Orthopedie', 'slug' => 'orthopedie', 'tarif' => 2450, 'tele_fee' => 2700, 'sector' => 'mixte', 'bio' => 'Orthopédiste pour douleurs articulaires, traumatologie et rééducation.'],
];
$locationPool = [
    ['wilaya_id' => 16, 'wilaya' => 'Alger', 'commune' => 'Bab Ezzouar'],
    ['wilaya_id' => 16, 'wilaya' => 'Alger', 'commune' => 'Kouba'],
    ['wilaya_id' => 16, 'wilaya' => 'Alger', 'commune' => 'Cheraga'],
    ['wilaya_id' => 31, 'wilaya' => 'Oran', 'commune' => 'Bir El Djir'],
    ['wilaya_id' => 31, 'wilaya' => 'Oran', 'commune' => 'Es Senia'],
    ['wilaya_id' => 25, 'wilaya' => 'Constantine', 'commune' => 'El Khroub'],
    ['wilaya_id' => 25, 'wilaya' => 'Constantine', 'commune' => 'Didouche Mourad'],
    ['wilaya_id' => 19, 'wilaya' => 'Setif', 'commune' => 'El Eulma'],
    ['wilaya_id' => 19, 'wilaya' => 'Setif', 'commune' => 'Setif Centre'],
    ['wilaya_id' => 6, 'wilaya' => 'Bejaia', 'commune' => 'Bejaia Centre'],
    ['wilaya_id' => 6, 'wilaya' => 'Bejaia', 'commune' => 'Akbou'],
    ['wilaya_id' => 9, 'wilaya' => 'Blida', 'commune' => 'Blida Centre'],
    ['wilaya_id' => 15, 'wilaya' => 'Tizi Ouzou', 'commune' => 'Tizi Ouzou Centre'],
    ['wilaya_id' => 34, 'wilaya' => 'Bordj Bou Arreridj', 'commune' => 'BBA Centre'],
];
$doctorReviewTemplates = [
    1 => [
        'Suivi très rassurant, diagnostic clair et explications utiles.',
        'Très bon cardiologue, ponctuel et à l’écoute pendant toute la consultation.',
        'Bilan bien expliqué, prise en charge sérieuse et conseils précis.',
    ],
    2 => [
        'Consultation dermatologique efficace, traitement bien expliqué.',
        'Médecin attentive, diagnostic clair et suivi rassurant.',
        'Très bonne expérience, explications simples et résultat satisfaisant.',
    ],
    3 => [
        'Bilan visuel complet, médecin professionnelle et rassurante.',
        'Très bon suivi ophtalmologique, explications claires sur le traitement.',
        'Consultation sérieuse, prise en charge rapide et rassurante.',
    ],
    4 => [
        'Pédiatre douce et à l’écoute, très bon contact avec l’enfant.',
        'Consultation claire, conseils utiles et suivi rassurant.',
        'Très bonne prise en charge, explications simples pour les parents.',
    ],
    5 => [
        'Consultation générale fluide, médecin à l’écoute et efficace.',
        'Très bon accueil, diagnostic clair et conseils adaptés.',
        'Explications simples, consultation sérieuse et rassurante.',
    ],
    6 => [
        'Consultation respectueuse, claire et très professionnelle.',
        'Très bon suivi, explications rassurantes et écoute attentive.',
        'Prise en charge soignée, consultation organisée et ponctuelle.',
    ],
    7 => [
        'Soin dentaire réalisé avec douceur, explications très claires.',
        'Très bon dentiste, rassurant et précis pendant le soin.',
        'Consultation propre, rapide et professionnelle.',
    ],
    8 => [
        'Très bon examen orthopédique, conseils précis pour la suite.',
        'Consultation sérieuse, médecin à l’écoute de la douleur.',
        'Explications claires, prise en charge efficace et rassurante.',
    ],
];

function initialsLabel(string $prenom, string $nom): string
{
    $initial = mb_strtoupper(mb_substr($nom, 0, 1));
    return trim($prenom . ' ' . $initial . '.');
}

function patientGender(string $prenom): string
{
    $female = ['Kenza','Nassima','Houda','Amel','Siham','Loubna','Dalila','Nadjah','Myriam','Sabrina','Imane','Yasmina','Khadidja','Fella','Rym'];
    return in_array($prenom, $female, true) ? 'Femme' : 'Homme';
}

function doctorLanguages(int $specialiteId, int $index): string
{
    $base = ['Arabe', 'Francais'];
    if (in_array($specialiteId, [4, 5, 8], true) || $index % 5 === 0) {
        $base[] = 'Tamazight';
    }
    return implode(',', array_unique($base));
}

function buildAddress(array $location, string $specialityName, int $index): string
{
    $label = match ($specialityName) {
        'Cardiologie' => 'Centre Cardio',
        'Dermatologie' => 'Cabinet Derma',
        'Ophtalmologie' => 'Vision Care',
        'Pediatrie' => 'Cabinet Enfant',
        'Medecine generale' => 'Cabinet Médical',
        'Gynecologie' => 'Centre Femme',
        'Dentisterie' => 'Cabinet Dentaire',
        'Orthopedie' => 'Clinique Ortho',
        default => 'Cabinet',
    };
    return sprintf('%s %02d, %s, %s', $label, $index + 1, $location['commune'], $location['wilaya']);
}

$pdo->beginTransaction();

try {
    $insertUser = $pdo->prepare(
        'INSERT INTO users
            (role, prenom, nom, email, email_verified_at, is_active, telephone, password_hash, date_naissance, sexe, wilaya_id, commune, groupe_sanguin, num_assurance, allergies)
         VALUES
            (:role, :prenom, :nom, :email, NOW(), 1, :telephone, :password_hash, :date_naissance, :sexe, :wilaya_id, :commune, :groupe_sanguin, :num_assurance, :allergies)'
    );
    $selectUserId = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $insertProfile = $pdo->prepare(
        'INSERT INTO medecin_profiles
            (user_id, specialite_id, tarif, cnas, casnos, note, avis_count, adresse, teleconsultation_enabled, teleconsultation_fee, teleconsultation_notes, experience_years, agrement_no, bio, formations_text, hopitaux_text, map_url, mutuelle_private, langues, secteur, approval_status, submitted_at, approved_at)
         VALUES
            (:user_id, :specialite_id, :tarif, :cnas, :casnos, 0, 0, :adresse, :tele_enabled, :tele_fee, :tele_notes, :experience_years, :agrement_no, :bio, :formations_text, :hopitaux_text, :map_url, :mutuelle_private, :langues, :secteur, "approved", NOW(), NOW())'
    );
    $insertPrefs = $pdo->prepare(
        'INSERT INTO notification_preferences
            (patient_id, sms_24h, sms_2h, email_24h, email_2h, sms_annulation, email_annulation)
         VALUES
            (:patient_id, 1, 0, 1, 1, 1, 1)'
    );
    $slotExists = $pdo->prepare('SELECT 1 FROM creneaux WHERE medecin_id = :medecin_id AND start_at = :start_at LIMIT 1');
    $insertSlot = $pdo->prepare(
        'INSERT INTO creneaux (medecin_id, start_at, end_at, is_available)
         VALUES (:medecin_id, :start_at, :end_at, 1)'
    );
    $apptExists = $pdo->prepare('SELECT 1 FROM appointments WHERE confirmation_code = :code LIMIT 1');
    $insertAppt = $pdo->prepare(
        'INSERT INTO appointments
            (patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite, consultation_mode, patient_target_type, patient_prenom, patient_nom, patient_date_naissance, patient_telephone, patient_email, patient_relation, teleconsultation_notes, statut, confirmation_code)
         VALUES
            (:patient_id, :medecin_id, NULL, :date_heure, 30, :motif, :type_visite, :consultation_mode, "self", :patient_prenom, :patient_nom, :patient_date_naissance, :patient_telephone, :patient_email, "Moi-même", :teleconsultation_notes, "termine", :confirmation_code)'
    );
    $reviewExists = $pdo->prepare('SELECT 1 FROM medecin_reviews WHERE medecin_id = :medecin_id AND patient_id = :patient_id LIMIT 1');
    $insertReview = $pdo->prepare(
        'INSERT INTO medecin_reviews
            (medecin_id, patient_id, patient_name, rating, comment, is_verified, created_at)
         VALUES
            (:medecin_id, :patient_id, :patient_name, :rating, :comment, 1, :created_at)'
    );

    $doctorIds = [];
    $patientIds = [];
    $insertedDoctors = 0;
    $insertedPatients = 0;
    $insertedProfiles = 0;
    $insertedSlots = 0;
    $insertedAppointments = 0;
    $insertedReviews = 0;

    for ($i = 0; $i < 100; $i++) {
        $prenom = $doctorFirstNames[$i % count($doctorFirstNames)];
        $nom = $lastNames[(int) floor($i / count($doctorFirstNames)) % count($lastNames)];
        $email = strtolower(sprintf('%s.%s.med%03d@tabibdz.local', $prenom, $nom, $i + 1));
        $location = $locationPool[$i % count($locationPool)];
        $telephone = '+21355' . str_pad((string) (600000 + $i), 6, '0', STR_PAD_LEFT);
        $birthYear = 1970 + ($i % 18);
        $sexe = in_array($prenom, ['Nadia','Lina','Sonia','Yasmine','Farah','Meriem','Rania','Nour','Imene','Sarra','Lamia','Amina','Maya','Ines','Nesrine'], true) ? 'Femme' : 'Homme';

        $selectUserId->execute(['email' => $email]);
        $userId = (int) $selectUserId->fetchColumn();
        if ($userId < 1) {
            $insertUser->execute([
                'role' => 'medecin',
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'telephone' => $telephone,
                'password_hash' => $passwordHash,
                'date_naissance' => sprintf('%04d-%02d-%02d', $birthYear, (($i % 12) + 1), (($i % 27) + 1)),
                'sexe' => $sexe,
                'wilaya_id' => $location['wilaya_id'],
                'commune' => $location['commune'],
                'groupe_sanguin' => null,
                'num_assurance' => null,
                'allergies' => null,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $insertedDoctors++;
        }
        $doctorIds[] = $userId;

        $specialiteId = ($i % 8) + 1;
        $speciality = $specialities[$specialiteId];
        $profileExists = (bool) $pdo->prepare('SELECT 1 FROM medecin_profiles WHERE user_id = :id LIMIT 1')
            ->execute(['id' => $userId]);
        $checkProfile = $pdo->prepare('SELECT 1 FROM medecin_profiles WHERE user_id = :id LIMIT 1');
        $checkProfile->execute(['id' => $userId]);
        if (!$checkProfile->fetchColumn()) {
            $teleEnabled = in_array($specialiteId, [1, 2, 3, 4, 5, 6], true) ? 1 : (($i % 3) === 0 ? 1 : 0);
            $teleFee = $teleEnabled ? ($speciality['tele_fee'] ?: $speciality['tarif']) : null;
            $experienceYears = 6 + ($i % 16);
            $address = buildAddress($location, $speciality['nom'], $i);
            $insertProfile->execute([
                'user_id' => $userId,
                'specialite_id' => $specialiteId,
                'tarif' => $speciality['tarif'] + (($i % 5) * 100),
                'cnas' => ($i % 4) !== 0 ? 1 : 0,
                'casnos' => ($i % 5) !== 0 ? 1 : 0,
                'adresse' => $address,
                'tele_enabled' => $teleEnabled,
                'tele_fee' => $teleFee,
                'tele_notes' => $teleEnabled ? 'Téléconsultation disponible sur rendez-vous confirmé.' : null,
                'experience_years' => $experienceYears,
                'agrement_no' => strtoupper(substr($location['wilaya'], 0, 3)) . '-' . strtoupper(substr($speciality['slug'], 0, 4)) . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'bio' => $speciality['bio'],
                'formations_text' => 'Doctorat medecine|Specialite ' . $speciality['nom'] . '|Formation continue TabibDZ',
                'hopitaux_text' => 'CHU ' . $location['wilaya'] . '|Cabinet principal ' . $location['commune'],
                'map_url' => 'https://maps.google.com/?q=' . rawurlencode($address),
                'mutuelle_private' => ($i % 2),
                'langues' => doctorLanguages($specialiteId, $i),
                'secteur' => $speciality['sector'],
            ]);
            $insertedProfiles++;
        }
    }

    for ($i = 0; $i < 100; $i++) {
        $prenom = $patientFirstNames[$i % count($patientFirstNames)];
        $nom = $lastNames[(($i * 3) + 7) % count($lastNames)];
        $email = strtolower(sprintf('%s.%s.patient%03d@tabibdz.local', $prenom, $nom, $i + 1));
        $location = $locationPool[($i * 2) % count($locationPool)];
        $telephone = '+21366' . str_pad((string) (700000 + $i), 6, '0', STR_PAD_LEFT);

        $selectUserId->execute(['email' => $email]);
        $userId = (int) $selectUserId->fetchColumn();
        if ($userId < 1) {
            $insertUser->execute([
                'role' => 'patient',
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'telephone' => $telephone,
                'password_hash' => $passwordHash,
                'date_naissance' => sprintf('%04d-%02d-%02d', 1985 + ($i % 20), (($i % 12) + 1), (($i % 27) + 1)),
                'sexe' => patientGender($prenom),
                'wilaya_id' => $location['wilaya_id'],
                'commune' => $location['commune'],
                'groupe_sanguin' => ['O+','A+','B+','AB+','O-','A-'][$i % 6],
                'num_assurance' => 'CNAS-' . str_pad((string) (50000000 + $i), 8, '0', STR_PAD_LEFT),
                'allergies' => ($i % 4 === 0) ? 'Aucune allergie connue' : null,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $insertedPatients++;
        }
        $patientIds[] = $userId;

        $checkPrefs = $pdo->prepare('SELECT 1 FROM notification_preferences WHERE patient_id = :id LIMIT 1');
        $checkPrefs->execute(['id' => $userId]);
        if (!$checkPrefs->fetchColumn()) {
            $insertPrefs->execute(['patient_id' => $userId]);
        }
    }

    $slotPatterns = [
        [2, '09:00:00'], [2, '09:30:00'], [3, '10:00:00'],
        [4, '14:00:00'], [5, '14:30:00'], [6, '15:00:00'],
    ];
    foreach ($doctorIds as $index => $doctorId) {
        foreach ($slotPatterns as [$dayOffset, $time]) {
            $startAt = date('Y-m-d H:i:s', strtotime('+' . $dayOffset . ' day ' . $time));
            $endAt = date('Y-m-d H:i:s', strtotime($startAt . ' +30 minutes'));
            $slotExists->execute(['medecin_id' => $doctorId, 'start_at' => $startAt]);
            if (!$slotExists->fetchColumn()) {
                $insertSlot->execute([
                    'medecin_id' => $doctorId,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ]);
                $insertedSlots++;
            }
        }
    }

    foreach ($doctorIds as $doctorIndex => $doctorId) {
        $specialiteId = ($doctorIndex % 8) + 1;
        $reviewComments = $doctorReviewTemplates[$specialiteId];
        for ($reviewIndex = 0; $reviewIndex < 3; $reviewIndex++) {
            $patientIndex = ($doctorIndex * 3 + $reviewIndex) % count($patientIds);
            $patientId = $patientIds[$patientIndex];

            $patientStmt = $pdo->prepare('SELECT prenom, nom, email, telephone, date_naissance FROM users WHERE id = :id LIMIT 1');
            $patientStmt->execute(['id' => $patientId]);
            $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                continue;
            }

            $confirmationCode = sprintf('TBZ-SEED-%03d-%02d', $doctorIndex + 1, $reviewIndex + 1);
            $appointmentAt = date('Y-m-d H:i:s', strtotime('-' . (18 + $doctorIndex + ($reviewIndex * 3)) . ' days 10:00:00'));
            $motif = match ($specialiteId) {
                1 => 'Bilan cardiovasculaire',
                2 => 'Consultation dermatologique',
                3 => 'Bilan visuel',
                4 => 'Suivi pédiatrique',
                5 => 'Consultation générale',
                6 => 'Consultation gynécologique',
                7 => 'Soin dentaire',
                8 => 'Douleur articulaire',
                default => 'Consultation',
            };
            $consultationMode = in_array($specialiteId, [1,2,3,4,5,6], true) && $reviewIndex === 2 ? 'teleconsultation' : 'cabinet';

            $apptExists->execute(['code' => $confirmationCode]);
            if (!$apptExists->fetchColumn()) {
                $insertAppt->execute([
                    'patient_id' => $patientId,
                    'medecin_id' => $doctorId,
                    'date_heure' => $appointmentAt,
                    'motif' => $motif,
                    'type_visite' => $reviewIndex === 0 ? 'new' : 'suivi',
                    'consultation_mode' => $consultationMode,
                    'patient_prenom' => $patient['prenom'],
                    'patient_nom' => $patient['nom'],
                    'patient_date_naissance' => $patient['date_naissance'],
                    'patient_telephone' => $patient['telephone'],
                    'patient_email' => $patient['email'],
                    'teleconsultation_notes' => $consultationMode === 'teleconsultation' ? 'Téléconsultation réalisée avec succès.' : null,
                    'confirmation_code' => $confirmationCode,
                ]);
                $insertedAppointments++;
            }

            $reviewExists->execute(['medecin_id' => $doctorId, 'patient_id' => $patientId]);
            if (!$reviewExists->fetchColumn()) {
                $insertReview->execute([
                    'medecin_id' => $doctorId,
                    'patient_id' => $patientId,
                    'patient_name' => initialsLabel((string) $patient['prenom'], (string) $patient['nom']),
                    'rating' => 5 - ($reviewIndex % 2),
                    'comment' => $reviewComments[$reviewIndex],
                    'created_at' => $appointmentAt,
                ]);
                $insertedReviews++;
            }
        }
    }

    $pdo->exec(
        'UPDATE medecin_profiles mp
         LEFT JOIN (
            SELECT medecin_id, ROUND(AVG(rating), 2) AS avg_note, COUNT(*) AS cnt
            FROM medecin_reviews
            GROUP BY medecin_id
         ) r ON r.medecin_id = mp.user_id
         SET mp.note = COALESCE(r.avg_note, 0),
             mp.avis_count = COALESCE(r.cnt, 0)'
    );

    $pdo->commit();

    echo "Doctors added: {$insertedDoctors}\n";
    echo "Patients added: {$insertedPatients}\n";
    echo "Profiles added: {$insertedProfiles}\n";
    echo "Slots added: {$insertedSlots}\n";
    echo "Appointments added: {$insertedAppointments}\n";
    echo "Reviews added: {$insertedReviews}\n";
    echo 'New doctor total in seed namespace: ' . (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'medecin' AND email LIKE '%@tabibdz.local' AND email LIKE '%.med%'")->fetchColumn() . "\n";
    echo 'New patient total in seed namespace: ' . (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient' AND email LIKE '%@tabibdz.local' AND email LIKE '%.patient%'")->fetchColumn() . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
