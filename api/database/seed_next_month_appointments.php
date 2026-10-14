<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../_core/db.php';

$pdo = db();
$appointmentsPerDoctorPerWeek = 2;
$slotMinutes = 30;

$appointmentColumns = array_fill_keys(
    $pdo->query('SHOW COLUMNS FROM appointments')->fetchAll(PDO::FETCH_COLUMN),
    true
);

$doctorStmt = $pdo->query(
    'SELECT u.id, u.prenom, u.nom, COALESCE(s.nom, "Medecine generale") AS specialite
     FROM users u
     LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
     LEFT JOIN specialites s ON s.id = mp.specialite_id
     WHERE u.role = "medecin"
     ORDER BY u.id ASC'
);
$doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);

$patients = $pdo->query(
    'SELECT id, prenom, nom, email, telephone, date_naissance
     FROM users
     WHERE role = "patient"
     ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

if (!$doctors) {
    fwrite(STDERR, "No doctors found. Run the doctor seed first.\n");
    exit(1);
}

if (!$patients) {
    fwrite(STDERR, "No patients found. Run the demo accounts seed first or create a patient.\n");
    exit(1);
}

$motifs = [
    'Cardiologie' => ['Controle tension', 'Bilan cardiovasculaire'],
    'Dermatologie' => ['Controle dermatologique', 'Suivi traitement peau'],
    'Ophtalmologie' => ['Bilan visuel', 'Controle vision'],
    'Pediatrie' => ['Suivi pediatrique', 'Controle croissance'],
    'Gynecologie' => ['Controle gynecologique', 'Suivi regulier'],
    'Dentisterie' => ['Controle dentaire', 'Consultation bucco-dentaire'],
    'Orthopedie' => ['Suivi orthopedique', 'Douleur articulaire'],
    'Medecine generale' => ['Consultation generale', 'Suivi medical'],
    'default' => ['Consultation generale', 'Suivi medical'],
];

$startDate = new DateTimeImmutable('tomorrow 00:00:00');
$lastDayNextMonth = (new DateTimeImmutable('first day of next month 00:00:00'))->modify('last day of this month');
$times = ['09:00:00', '10:00:00', '11:00:00', '14:00:00', '15:00:00', '16:00:00'];

$slotSelect = $pdo->prepare(
    'SELECT id FROM creneaux WHERE medecin_id = :medecin_id AND start_at = :start_at LIMIT 1'
);
$slotInsert = $pdo->prepare(
    'INSERT INTO creneaux (medecin_id, start_at, end_at, is_available)
     VALUES (:medecin_id, :start_at, :end_at, 0)
     ON DUPLICATE KEY UPDATE end_at = VALUES(end_at), is_available = 0'
);
$existingAppointment = $pdo->prepare(
    'SELECT id FROM appointments WHERE medecin_id = :medecin_id AND date_heure = :date_heure LIMIT 1'
);
$codeExists = $pdo->prepare('SELECT 1 FROM appointments WHERE confirmation_code = :code LIMIT 1');

function appointmentDateUntilNextMonth(DateTimeImmutable $startDate, DateTimeImmutable $lastDay, int $weekIndex, int $slotIndex, int $doctorIndex, array $times): ?DateTimeImmutable
{
    $weekdayOffsets = [0, 2, 4, 1, 3];
    $dayOffset = ($weekIndex * 7) + $weekdayOffsets[($slotIndex + $doctorIndex) % count($weekdayOffsets)];
    $date = $startDate->modify('+' . $dayOffset . ' days');

    while (in_array($date->format('N'), ['6', '7'], true)) {
        $date = $date->modify('+1 day');
        if ($date > $lastDay) {
            return null;
        }
    }

    if ($date > $lastDay) {
        return null;
    }

    $time = $times[($doctorIndex + $weekIndex + $slotIndex) % count($times)];
    return new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time);
}

function makeNextMonthCode(PDOStatement $codeExists, int $doctorId, int $index): string
{
    do {
        $code = sprintf('TBZ-NEXT-%d-%02d-%04d', $doctorId, $index + 1, random_int(1000, 9999));
        $codeExists->execute(['code' => $code]);
    } while ($codeExists->fetchColumn());

    return $code;
}

$columns = [
    'patient_id',
    'medecin_id',
    'creneau_id',
    'date_heure',
    'duree_min',
    'motif',
    'type_visite',
    'consultation_mode',
    'patient_target_type',
    'patient_prenom',
    'patient_nom',
    'patient_date_naissance',
    'patient_telephone',
    'patient_email',
    'patient_relation',
    'teleconsultation_notes',
    'statut',
    'confirmation_code',
];

if (isset($appointmentColumns['beneficiary_type'])) {
    array_splice($columns, 3, 0, 'beneficiary_type');
}

$placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
$insertAppointment = $pdo->prepare(
    'INSERT INTO appointments (' . implode(', ', $columns) . ')
     VALUES (' . implode(', ', $placeholders) . ')'
);

$inserted = 0;
$skipped = 0;
$patientCursor = 0;

$pdo->beginTransaction();

try {
    foreach ($doctors as $doctorIndex => $doctor) {
        for ($weekIndex = 0; ; $weekIndex++) {
            $weekHasDate = false;

            for ($i = 0; $i < $appointmentsPerDoctorPerWeek; $i++) {
                $date = appointmentDateUntilNextMonth($startDate, $lastDayNextMonth, $weekIndex, $i, $doctorIndex, $times);
                if ($date === null) {
                    continue;
                }

                $weekHasDate = true;
                $globalIndex = ($doctorIndex * 1000) + ($weekIndex * $appointmentsPerDoctorPerWeek) + $i;
            $dateSql = $date->format('Y-m-d H:i:s');

            $existingAppointment->execute([
                'medecin_id' => (int) $doctor['id'],
                'date_heure' => $dateSql,
            ]);
            if ($existingAppointment->fetchColumn()) {
                $skipped++;
                continue;
            }

            $endSql = $date->modify('+' . $slotMinutes . ' minutes')->format('Y-m-d H:i:s');
            $slotInsert->execute([
                'medecin_id' => (int) $doctor['id'],
                'start_at' => $dateSql,
                'end_at' => $endSql,
            ]);
            $slotSelect->execute([
                'medecin_id' => (int) $doctor['id'],
                'start_at' => $dateSql,
            ]);
            $slotId = (int) $slotSelect->fetchColumn();

            $patient = $patients[$patientCursor % count($patients)];
            $patientCursor++;

            $speciality = (string) ($doctor['specialite'] ?: 'default');
            $motifPool = $motifs[$speciality] ?? $motifs['default'];
            $mode = ($globalIndex % 4 === 0) ? 'teleconsultation' : 'cabinet';

            $data = [
                'patient_id' => (int) $patient['id'],
                'medecin_id' => (int) $doctor['id'],
                'creneau_id' => $slotId ?: null,
                'date_heure' => $dateSql,
                'duree_min' => $slotMinutes,
                'motif' => $motifPool[($weekIndex + $i) % count($motifPool)],
                'type_visite' => ($globalIndex % 2 === 0) ? 'new' : 'suivi',
                'consultation_mode' => $mode,
                'patient_target_type' => 'self',
                'patient_prenom' => (string) $patient['prenom'],
                'patient_nom' => (string) $patient['nom'],
                'patient_date_naissance' => $patient['date_naissance'] ?: null,
                'patient_telephone' => $patient['telephone'] ?: null,
                'patient_email' => $patient['email'] ?: null,
                'patient_relation' => 'Moi-meme',
                'teleconsultation_notes' => $mode === 'teleconsultation' ? 'Rendez-vous teleconsultation ajoute pour le mois prochain.' : null,
                'statut' => 'confirme',
                'confirmation_code' => makeNextMonthCode($codeExists, (int) $doctor['id'], $i),
            ];

            if (isset($appointmentColumns['beneficiary_type'])) {
                $data['beneficiary_type'] = 'self';
            }

            $insertAppointment->execute(array_intersect_key($data, array_flip($columns)));
            $inserted++;
            }

            if (!$weekHasDate) {
                break;
            }
        }
    }

    $pdo->commit();

    echo 'Range: ' . $startDate->format('Y-m-d') . ' -> ' . $lastDayNextMonth->format('Y-m-d') . PHP_EOL;
    echo 'Doctors scanned: ' . count($doctors) . PHP_EOL;
    echo 'Appointments inserted: ' . $inserted . PHP_EOL;
    echo 'Appointments skipped: ' . $skipped . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
