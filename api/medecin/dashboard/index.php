<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('GET');

$doctor = requireAuth('medecin');
$pdo = db();
$doctorId = (int) $doctor['id'];
$range = (string) ($_GET['range'] ?? 'today');
$allowedRanges = ['today', 'tomorrow', 'week'];
if (!in_array($range, $allowedRanges, true)) {
    $range = 'today';
}

$today = new DateTimeImmutable('today');
$tomorrow = $today->modify('+1 day');
$nextWeek = $today->modify('+7 day');
$monthStart = $today->modify('first day of this month');
$monthEnd = $monthStart->modify('+1 month');

$doctorStmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.commune, w.nom AS wilaya_nom,
            mp.note, mp.avis_count, s.nom AS specialite_nom,
            CASE
                WHEN COALESCE(mp.approval_status, 'approved') = 'pending' AND mp.submitted_at IS NULL THEN 'draft'
                ELSE COALESCE(mp.approval_status, 'approved')
            END AS approval_status,
            mp.submitted_at, mp.approved_at, mp.review_note
     FROM users u
     LEFT JOIN wilayas w ON w.id = u.wilaya_id
     LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
     LEFT JOIN specialites s ON s.id = mp.specialite_id
     WHERE u.id = :id AND u.role = 'medecin'
     LIMIT 1"
);
$doctorStmt->execute(['id' => $doctorId]);
$doctorRow = firstOrNull($doctorStmt);
if (!$doctorRow) {
    jsonResponse(['error' => 'Medecin introuvable'], 404);
}

if (($doctorRow['approval_status'] ?? 'approved') !== 'approved') {
    jsonResponse([
        'error' => 'Profil medecin non approuve',
        'approval_status' => (string) $doctorRow['approval_status'],
        'submitted_at' => $doctorRow['submitted_at'] ? date(DATE_ATOM, strtotime((string) $doctorRow['submitted_at'])) : null,
        'approved_at' => $doctorRow['approved_at'] ? date(DATE_ATOM, strtotime((string) $doctorRow['approved_at'])) : null,
        'review_note' => $doctorRow['review_note'] ?: null,
    ], 403);
}

$countBetween = static function (PDO $pdo, int $doctorId, string $from, string $to, ?string $status = null): int {
    $sql = 'SELECT COUNT(*) FROM appointments WHERE medecin_id = :id AND date_heure >= :from_at AND date_heure < :to_at';
    $params = ['id' => $doctorId, 'from_at' => $from, 'to_at' => $to];
    if ($status !== null) {
        $sql .= ' AND statut = :status';
        $params['status'] = $status;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

$todayTotal = $countBetween($pdo, $doctorId, $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s'));
$todayConfirmed = $countBetween($pdo, $doctorId, $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s'), 'confirme');
$todayCancelled = $countBetween($pdo, $doctorId, $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s'), 'annule');
$weekAppointments = $countBetween($pdo, $doctorId, $today->format('Y-m-d H:i:s'), $nextWeek->format('Y-m-d H:i:s'));
$monthTotal = $countBetween($pdo, $doctorId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'));
$monthCancelled = $countBetween($pdo, $doctorId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s'), 'annule');

$teleTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE medecin_id = :id AND consultation_mode = 'teleconsultation' AND date_heure >= :from_at AND date_heure < :to_at");
$teleTodayStmt->execute([
    'id' => $doctorId,
    'from_at' => $today->format('Y-m-d H:i:s'),
    'to_at' => $tomorrow->format('Y-m-d H:i:s'),
]);
$teleTodayCount = (int) $teleTodayStmt->fetchColumn();

$teleLiveStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE medecin_id = :id AND consultation_mode = 'teleconsultation' AND statut = 'confirme' AND COALESCE(teleconsultation_status, 'planned') IN ('room_open','in_progress')");
$teleLiveStmt->execute(['id' => $doctorId]);
$teleLiveCount = (int) $teleLiveStmt->fetchColumn();

$weekSlotsStmt = $pdo->prepare('SELECT COUNT(*) FROM creneaux WHERE medecin_id = :id AND start_at >= :from_at AND start_at < :to_at');
$weekSlotsStmt->execute([
    'id' => $doctorId,
    'from_at' => $today->format('Y-m-d H:i:s'),
    'to_at' => $nextWeek->format('Y-m-d H:i:s'),
]);
$weekSlots = (int) $weekSlotsStmt->fetchColumn();
$fillRate = $weekSlots > 0 ? (int) round(($weekAppointments / $weekSlots) * 100) : 0;
$cancelRate = $monthTotal > 0 ? (int) round(($monthCancelled / $monthTotal) * 100) : 0;

$rangeFrom = match ($range) {
    'tomorrow' => $tomorrow,
    'week' => $today,
    default => $today,
};
$rangeTo = match ($range) {
    'tomorrow' => $tomorrow->modify('+1 day'),
    'week' => $nextWeek,
    default => $tomorrow,
};

$appointmentsStmt = $pdo->prepare(
    "SELECT a.id, a.date_heure, a.motif, a.type_visite, a.consultation_mode, a.statut,
            COALESCE(NULLIF(a.patient_prenom, ''), u.prenom) AS patient_prenom,
            COALESCE(NULLIF(a.patient_nom, ''), u.nom) AS patient_nom,
            COALESCE(a.patient_date_naissance, u.date_naissance) AS patient_date_naissance,
            COALESCE(NULLIF(a.patient_relation, ''), 'Patient') AS patient_relation,
            u.commune
     FROM appointments a
     INNER JOIN users u ON u.id = a.patient_id
     WHERE a.medecin_id = :id AND a.date_heure >= :from_at AND a.date_heure < :to_at
     ORDER BY a.date_heure ASC
     LIMIT 20"
);
$appointmentsStmt->execute([
    'id' => $doctorId,
    'from_at' => $rangeFrom->format('Y-m-d H:i:s'),
    'to_at' => $rangeTo->format('Y-m-d H:i:s'),
]);
$appointments = [];
while ($row = $appointmentsStmt->fetch()) {
    $age = null;
    if (!empty($row['patient_date_naissance'])) {
        $dob = new DateTimeImmutable((string) $row['patient_date_naissance']);
        $age = $dob->diff(new DateTimeImmutable('today'))->y;
    }
    $appointments[] = [
        'id' => (int) $row['id'],
        'date_heure' => date(DATE_ATOM, strtotime((string) $row['date_heure'])),
        'motif' => $row['motif'] ?: 'Consultation',
        'type_visite' => $row['type_visite'],
        'consultation_mode' => $row['consultation_mode'] ?: 'cabinet',
        'statut' => $row['statut'],
        'patient' => [
            'prenom' => $row['patient_prenom'],
            'nom' => $row['patient_nom'],
            'commune' => $row['commune'],
            'age' => $age,
            'relation' => $row['patient_relation'],
        ],
    ];
}

$upcomingStmt = $pdo->prepare(
    "SELECT a.id, a.date_heure, a.motif, a.type_visite, a.consultation_mode, a.statut,
            COALESCE(NULLIF(a.patient_prenom, ''), u.prenom) AS patient_prenom,
            COALESCE(NULLIF(a.patient_nom, ''), u.nom) AS patient_nom,
            COALESCE(NULLIF(a.patient_relation, ''), 'Patient') AS patient_relation,
            u.commune
     FROM appointments a
     INNER JOIN users u ON u.id = a.patient_id
     WHERE a.medecin_id = :id AND a.date_heure >= NOW()
     ORDER BY a.date_heure ASC
     LIMIT 8"
);
$upcomingStmt->execute(['id' => $doctorId]);
$upcomingAppointments = [];
while ($row = $upcomingStmt->fetch()) {
    $upcomingAppointments[] = [
        'id' => (int) $row['id'],
        'date_heure' => date(DATE_ATOM, strtotime((string) $row['date_heure'])),
        'motif' => $row['motif'] ?: 'Consultation',
        'type_visite' => $row['type_visite'],
        'consultation_mode' => $row['consultation_mode'] ?: 'cabinet',
        'statut' => $row['statut'],
        'patient' => [
            'prenom' => $row['patient_prenom'],
            'nom' => $row['patient_nom'],
            'commune' => $row['commune'],
            'relation' => $row['patient_relation'],
        ],
    ];
}

$teleStmt = $pdo->prepare(
    "SELECT a.id, a.date_heure, a.motif, a.type_visite, a.consultation_mode, a.statut,
            a.teleconsultation_status, a.teleconsultation_notes, a.teleconsultation_started_at, a.teleconsultation_ended_at,
            COALESCE(NULLIF(a.patient_prenom, ''), u.prenom) AS patient_prenom,
            COALESCE(NULLIF(a.patient_nom, ''), u.nom) AS patient_nom,
            COALESCE(NULLIF(a.patient_relation, ''), 'Patient') AS patient_relation
     FROM appointments a
     INNER JOIN users u ON u.id = a.patient_id
     WHERE a.medecin_id = :id AND a.consultation_mode = 'teleconsultation'
     ORDER BY a.date_heure DESC
     LIMIT 6"
);
$teleStmt->execute(['id' => $doctorId]);
$teleconsultations = [];
while ($row = $teleStmt->fetch()) {
    $dateTs = strtotime((string) $row['date_heure']);
    $derivedStatus = (string) ($row['teleconsultation_status'] ?? '');
    if ($derivedStatus === '') {
        if ($row['statut'] === 'annule') {
            $derivedStatus = 'cancelled';
        } elseif ($row['statut'] === 'termine') {
            $derivedStatus = 'completed';
        } elseif (!empty($row['teleconsultation_started_at'])) {
            $derivedStatus = 'in_progress';
        } elseif ($dateTs !== false && time() >= ($dateTs - 600) && time() <= ($dateTs + 5400)) {
            $derivedStatus = 'room_open';
        } else {
            $derivedStatus = 'planned';
        }
    }
    $teleconsultations[] = [
        'id' => (int) $row['id'],
        'date_heure' => date(DATE_ATOM, $dateTs ?: time()),
        'motif' => $row['motif'] ?: 'Téléconsultation',
        'type_visite' => $row['type_visite'],
        'consultation_mode' => 'teleconsultation',
        'statut' => $row['statut'],
        'teleconsultation_status' => $derivedStatus,
        'teleconsultation_notes' => $row['teleconsultation_notes'] ?: null,
        'teleconsultation_started_at' => !empty($row['teleconsultation_started_at']) ? date(DATE_ATOM, strtotime((string) $row['teleconsultation_started_at'])) : null,
        'teleconsultation_ended_at' => !empty($row['teleconsultation_ended_at']) ? date(DATE_ATOM, strtotime((string) $row['teleconsultation_ended_at'])) : null,
        'patient' => [
            'prenom' => $row['patient_prenom'],
            'nom' => $row['patient_nom'],
            'relation' => $row['patient_relation'],
        ],
    ];
}

$recentPatientsStmt = $pdo->prepare(
    "SELECT MAX(a.id) AS appointment_id,
            COALESCE(NULLIF(a.patient_prenom, ''), u.prenom) AS patient_prenom,
            COALESCE(NULLIF(a.patient_nom, ''), u.nom) AS patient_nom,
            COALESCE(a.patient_date_naissance, u.date_naissance) AS patient_date_naissance,
            COALESCE(NULLIF(a.patient_relation, ''), 'Patient') AS patient_relation,
            u.commune,
            MAX(a.date_heure) AS last_appointment_at
     FROM appointments a
     INNER JOIN users u ON u.id = a.patient_id
     WHERE a.medecin_id = :id
      GROUP BY
        COALESCE(NULLIF(a.patient_prenom, ''), u.prenom),
        COALESCE(NULLIF(a.patient_nom, ''), u.nom),
        COALESCE(a.patient_date_naissance, u.date_naissance),
        COALESCE(NULLIF(a.patient_relation, ''), 'Patient'),
        u.commune
      ORDER BY last_appointment_at DESC
      LIMIT 5"
);
$recentPatientsStmt->execute(['id' => $doctorId]);
$recentPatients = [];
while ($row = $recentPatientsStmt->fetch()) {
    $age = null;
    if (!empty($row['patient_date_naissance'])) {
        $dob = new DateTimeImmutable((string) $row['patient_date_naissance']);
        $age = $dob->diff(new DateTimeImmutable('today'))->y;
    }
    $recentPatients[] = [
        'id' => (int) ($row['appointment_id'] ?? 0),
        'prenom' => $row['patient_prenom'],
        'nom' => $row['patient_nom'],
        'commune' => $row['commune'],
        'age' => $age,
        'relation' => $row['patient_relation'],
        'last_appointment_at' => date(DATE_ATOM, strtotime((string) $row['last_appointment_at'])),
    ];
}

$chart = [];
for ($i = 6; $i >= 0; $i -= 1) {
    $from = $today->modify("-{$i} day");
    $to = $from->modify('+1 day');
    $count = $countBetween($pdo, $doctorId, $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'), 'confirme');
    $chart[] = [
        'label' => $from->format('D'),
        'date' => $from->format('Y-m-d'),
        'count' => $count,
    ];
}

$slotFrom = $today;
$slotTo = $today->modify('+7 day');
$slotsStmt = $pdo->prepare(
    "SELECT c.id, c.start_at, c.end_at, c.is_available,
            EXISTS(
              SELECT 1 FROM appointments a
              WHERE a.creneau_id = c.id AND a.statut = 'confirme'
            ) AS is_booked
     FROM creneaux c
     WHERE c.medecin_id = :id AND c.start_at >= :from_at AND c.start_at < :to_at
     ORDER BY c.start_at ASC"
);
$slotsStmt->execute([
    'id' => $doctorId,
    'from_at' => $slotFrom->format('Y-m-d H:i:s'),
    'to_at' => $slotTo->format('Y-m-d H:i:s'),
]);
$slotsByDay = [];
while ($row = $slotsStmt->fetch()) {
    $dayKey = date('Y-m-d', strtotime((string) $row['start_at']));
    $slotsByDay[$dayKey] ??= [];
    $slotsByDay[$dayKey][] = [
        'id' => (int) $row['id'],
        'start_at' => date(DATE_ATOM, strtotime((string) $row['start_at'])),
        'end_at' => date(DATE_ATOM, strtotime((string) $row['end_at'])),
        'is_available' => (int) $row['is_available'] === 1,
        'is_booked' => (int) $row['is_booked'] === 1,
    ];
}

jsonResponse([
    'doctor' => [
        'id' => $doctorId,
        'prenom' => $doctorRow['prenom'],
        'nom' => $doctorRow['nom'],
        'specialite' => $doctorRow['specialite_nom'],
        'commune' => $doctorRow['commune'],
        'wilaya' => $doctorRow['wilaya_nom'],
        'note' => (float) ($doctorRow['note'] ?? 0),
        'avis_count' => (int) ($doctorRow['avis_count'] ?? 0),
        'approval_status' => $doctorRow['approval_status'] ?? 'approved',
        'submitted_at' => $doctorRow['submitted_at'] ? date(DATE_ATOM, strtotime((string) $doctorRow['submitted_at'])) : null,
        'approved_at' => $doctorRow['approved_at'] ? date(DATE_ATOM, strtotime((string) $doctorRow['approved_at'])) : null,
        'review_note' => $doctorRow['review_note'] ?: null,
    ],
    'stats' => [
        'today_total' => $todayTotal,
        'today_confirmed' => $todayConfirmed,
        'today_cancelled' => $todayCancelled,
        'tele_today' => $teleTodayCount,
        'tele_live' => $teleLiveCount,
        'week_total' => $weekAppointments,
        'week_slots' => $weekSlots,
        'fill_rate' => $fillRate,
        'month_total' => $monthTotal,
        'cancel_rate' => $cancelRate,
        'month_cancelled' => $monthCancelled,
        'note' => (float) ($doctorRow['note'] ?? 0),
        'avis_count' => (int) ($doctorRow['avis_count'] ?? 0),
    ],
    'appointments' => $appointments,
    'upcoming_appointments' => $upcomingAppointments,
    'teleconsultations' => $teleconsultations,
    'recent_patients' => $recentPatients,
    'chart' => $chart,
    'slots_by_day' => $slotsByDay,
    'selected_range' => $range,
    'generated_at' => date(DATE_ATOM),
]);
