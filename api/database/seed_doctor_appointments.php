<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../_core/db.php';

$pdo = db();
$minAppointmentsPerDoctor = 3;

$doctorStmt = $pdo->query(
    "SELECT u.id, u.prenom, u.nom,
            COUNT(a.id) AS appointment_count
     FROM users u
     LEFT JOIN appointments a ON a.medecin_id = u.id
     WHERE u.role = 'medecin'
     GROUP BY u.id, u.prenom, u.nom
     ORDER BY u.id ASC"
);
$doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);

$patientStmt = $pdo->query(
    "SELECT id, prenom, nom, email, telephone, date_naissance
     FROM users
     WHERE role = 'patient'
     ORDER BY id ASC"
);
$patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$doctors || !$patients) {
    fwrite(STDERR, "Missing doctors or patients.\n");
    exit(1);
}

$motifs = [
    'Cardiologie' => ['Controle tension', 'Suivi palpitations', 'Bilan cardiovasculaire'],
    'Dermatologie' => ['Controle dermatologique', 'Suivi traitement peau', 'Avis lesion cutanee'],
    'Ophtalmologie' => ['Bilan visuel', 'Controle vision', 'Suivi irritation oculaire'],
    'Pediatrie' => ['Suivi pediatrique', 'Controle croissance', 'Consultation enfant'],
    'Gynecologie' => ['Controle gynecologique', 'Suivi regulier', 'Consultation preventive'],
    'Dentisterie' => ['Controle dentaire', 'Suivi douleur dentaire', 'Consultation bucco-dentaire'],
    'Orthopedie' => ['Douleur articulaire', 'Suivi orthopedique', 'Controle traumatique'],
    'Medecine generale' => ['Consultation generale', 'Suivi medical', 'Controle annuel'],
    'default' => ['Consultation generale', 'Suivi medical', 'Controle annuel'],
];

$doctorSpecialityStmt = $pdo->prepare(
    'SELECT s.nom
     FROM medecin_profiles mp
     LEFT JOIN specialites s ON s.id = mp.specialite_id
     WHERE mp.user_id = :user_id
     LIMIT 1'
);
$codeExistsStmt = $pdo->prepare('SELECT 1 FROM appointments WHERE confirmation_code = :code LIMIT 1');
$insertStmt = $pdo->prepare(
    'INSERT INTO appointments
        (patient_id, beneficiary_type, family_member_id, patient_prenom, patient_nom, patient_date_naissance, patient_telephone, patient_email, patient_relation,
         medecin_id, creneau_id, date_heure, duree_min, motif, type_visite, consultation_mode, patient_target_type, teleconsultation_notes, statut, confirmation_code)
     VALUES
        (:patient_id, "self", NULL, :patient_prenom, :patient_nom, :patient_date_naissance, :patient_telephone, :patient_email, "Moi-meme",
         :medecin_id, NULL, :date_heure, 30, :motif, :type_visite, :consultation_mode, "self", :teleconsultation_notes, :statut, :confirmation_code)'
);

function makeConfirmationCode(PDOStatement $codeExistsStmt, int $doctorId, int $index): string
{
    do {
        $code = sprintf('TBZ-AUTO-%d-%02d-%04d', $doctorId, $index + 1, random_int(1000, 9999));
        $codeExistsStmt->execute(['code' => $code]);
    } while ($codeExistsStmt->fetchColumn());

    return $code;
}

function appointmentDateForFill(int $doctorId, int $slotIndex): array
{
    if ($slotIndex === 0) {
        $dayOffset = - (5 + ($doctorId % 12));
        $time = ['09:00:00', '10:00:00', '14:00:00'][$doctorId % 3];
        return [
            'date_heure' => date('Y-m-d H:i:s', strtotime($dayOffset . ' day ' . $time)),
            'statut' => 'termine',
        ];
    }

    $futureTimes = ['09:00:00', '09:30:00', '10:00:00', '14:00:00', '14:30:00', '15:00:00'];
    $dayOffset = 2 + (($doctorId + $slotIndex) % 9);
    $time = $futureTimes[($doctorId + $slotIndex) % count($futureTimes)];

    return [
        'date_heure' => date('Y-m-d H:i:s', strtotime('+' . $dayOffset . ' day ' . $time)),
        'statut' => 'confirme',
    ];
}

$inserted = 0;
$touchedDoctors = 0;
$patientCursor = 0;

$pdo->beginTransaction();

try {
    foreach ($doctors as $doctorIndex => $doctor) {
        $existingCount = (int) ($doctor['appointment_count'] ?? 0);
        if ($existingCount >= $minAppointmentsPerDoctor) {
            continue;
        }

        $doctorSpecialityStmt->execute(['user_id' => (int) $doctor['id']]);
        $specialityName = (string) ($doctorSpecialityStmt->fetchColumn() ?: 'default');
        $motifPool = $motifs[$specialityName] ?? $motifs['default'];
        $needed = $minAppointmentsPerDoctor - $existingCount;
        $touchedDoctors++;

        for ($i = 0; $i < $needed; $i++) {
            $patient = $patients[$patientCursor % count($patients)];
            $patientCursor++;

            $timing = appointmentDateForFill((int) $doctor['id'], $existingCount + $i);
            $mode = (($doctorIndex + $i) % 4 === 0) ? 'teleconsultation' : 'cabinet';
            $motif = $motifPool[($doctorIndex + $i) % count($motifPool)];
            $typeVisite = (($doctorIndex + $i) % 2 === 0) ? 'new' : 'suivi';
            $code = makeConfirmationCode($codeExistsStmt, (int) $doctor['id'], $i);

            $insertStmt->execute([
                'patient_id' => (int) $patient['id'],
                'patient_prenom' => (string) $patient['prenom'],
                'patient_nom' => (string) $patient['nom'],
                'patient_date_naissance' => $patient['date_naissance'] ?: null,
                'patient_telephone' => $patient['telephone'] ?: null,
                'patient_email' => $patient['email'] ?: null,
                'medecin_id' => (int) $doctor['id'],
                'date_heure' => $timing['date_heure'],
                'motif' => $motif,
                'type_visite' => $typeVisite,
                'consultation_mode' => $mode,
                'teleconsultation_notes' => $mode === 'teleconsultation' ? 'Rendez-vous teleconsultation ajoute automatiquement pour demo.' : null,
                'statut' => $timing['statut'],
                'confirmation_code' => $code,
            ]);

            $inserted++;
        }
    }

    $pdo->commit();

    echo 'Doctors touched: ' . $touchedDoctors . PHP_EOL;
    echo 'Appointments inserted: ' . $inserted . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
