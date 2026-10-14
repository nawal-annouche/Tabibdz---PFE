<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('GET');

$doctor = requireAuth('medecin');
$pdo = db();
$doctorId = (int) $doctor['id'];
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$allowed = ['all', 'upcoming', 'live', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'all';
}

$sql = "SELECT a.id, a.date_heure, a.duree_min, a.motif, a.type_visite, a.consultation_mode,
               a.teleconsultation_notes, a.teleconsultation_reason, a.teleconsultation_contact,
               a.statut, a.teleconsultation_status, a.teleconsultation_started_at, a.teleconsultation_ended_at,
               a.teleconsultation_summary, a.teleconsultation_ordonnance_text, a.teleconsultation_certificat_text,
               a.confirmation_code,
               COALESCE(NULLIF(a.patient_prenom, ''), u.prenom) AS patient_prenom,
               COALESCE(NULLIF(a.patient_nom, ''), u.nom) AS patient_nom,
               COALESCE(a.patient_date_naissance, u.date_naissance) AS patient_date_naissance,
               COALESCE(NULLIF(a.patient_telephone, ''), u.telephone) AS patient_telephone,
               COALESCE(NULLIF(a.patient_email, ''), u.email) AS patient_email,
               COALESCE(NULLIF(a.patient_relation, ''), 'Patient') AS patient_relation,
               COUNT(d.id) AS documents_count
        FROM appointments a
        INNER JOIN users u ON u.id = a.patient_id
        LEFT JOIN appointment_documents d ON d.appointment_id = a.id
        WHERE a.medecin_id = :doctor_id AND a.consultation_mode = 'teleconsultation'";
$params = ['doctor_id' => $doctorId];

if ($statusFilter === 'upcoming') {
    $sql .= " AND a.statut = 'confirme' AND a.date_heure >= NOW() AND COALESCE(a.teleconsultation_status, 'planned') NOT IN ('completed','cancelled')";
} elseif ($statusFilter === 'live') {
    $sql .= " AND a.statut = 'confirme' AND (COALESCE(a.teleconsultation_status, 'planned') IN ('room_open','in_progress') OR (a.date_heure <= DATE_ADD(NOW(), INTERVAL 90 MINUTE) AND a.date_heure >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)))";
} elseif ($statusFilter === 'completed') {
    $sql .= " AND (a.statut = 'termine' OR COALESCE(a.teleconsultation_status, '') = 'completed')";
} elseif ($statusFilter === 'cancelled') {
    $sql .= " AND (a.statut = 'annule' OR COALESCE(a.teleconsultation_status, '') = 'cancelled')";
}

$sql .= " GROUP BY a.id ORDER BY a.date_heure DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = [];
while ($row = $stmt->fetch()) {
    $date = strtotime((string) $row['date_heure']);
    $joinOpenAt = $date !== false ? $date - 600 : time();
    $endedAt = !empty($row['teleconsultation_ended_at']) ? strtotime((string) $row['teleconsultation_ended_at']) : false;
    $startedAt = !empty($row['teleconsultation_started_at']) ? strtotime((string) $row['teleconsultation_started_at']) : false;
    $derivedStatus = (string) ($row['teleconsultation_status'] ?? '');
    if ($derivedStatus === '') {
        if ($row['statut'] === 'annule') {
            $derivedStatus = 'cancelled';
        } elseif ($row['statut'] === 'termine') {
            $derivedStatus = 'completed';
        } elseif ($startedAt) {
            $derivedStatus = 'in_progress';
        } elseif (time() >= $joinOpenAt && time() <= ($date + 5400)) {
            $derivedStatus = 'room_open';
        } elseif ($date !== false && time() > ($date + 5400)) {
            $derivedStatus = 'missed';
        } else {
            $derivedStatus = 'planned';
        }
    } elseif ($derivedStatus === 'planned' && $date !== false && time() > ($date + 5400) && $row['statut'] === 'confirme') {
        $derivedStatus = 'missed';
    }

    $patientName = trim((string) $row['patient_prenom'] . ' ' . (string) $row['patient_nom']);
    $rows[] = [
        'id' => (int) $row['id'],
        'date_heure' => date(DATE_ATOM, $date ?: time()),
        'duree_min' => (int) ($row['duree_min'] ?? 0),
        'motif' => $row['motif'] ?: 'T�l�consultation',
        'type_visite' => $row['type_visite'] ?: null,
        'consultation_mode' => 'teleconsultation',
        'statut' => $row['statut'],
        'teleconsultation_status' => $derivedStatus,
        'teleconsultation_reason' => $row['teleconsultation_reason'] ?: null,
        'teleconsultation_notes' => $row['teleconsultation_notes'] ?: null,
        'teleconsultation_contact' => $row['teleconsultation_contact'] ?: 'video',
        'teleconsultation_started_at' => $startedAt ? date(DATE_ATOM, $startedAt) : null,
        'teleconsultation_ended_at' => $endedAt ? date(DATE_ATOM, $endedAt) : null,
        'teleconsultation_summary' => $row['teleconsultation_summary'] ?: null,
        'teleconsultation_ordonnance_text' => $row['teleconsultation_ordonnance_text'] ?: null,
        'teleconsultation_certificat_text' => $row['teleconsultation_certificat_text'] ?: null,
        'confirmation_code' => $row['confirmation_code'] ?: null,
        'join_open_at' => date(DATE_ATOM, $joinOpenAt),
        'documents_count' => (int) ($row['documents_count'] ?? 0),
        'patient' => [
            'display_name' => $patientName !== '' ? $patientName : 'Patient',
            'prenom' => $row['patient_prenom'] ?: null,
            'nom' => $row['patient_nom'] ?: null,
            'telephone' => $row['patient_telephone'] ?: null,
            'email' => $row['patient_email'] ?: null,
            'relation' => $row['patient_relation'] ?: 'Patient',
            'date_naissance' => !empty($row['patient_date_naissance']) ? date('Y-m-d', strtotime((string) $row['patient_date_naissance'])) : null,
        ],
    ];
}

jsonResponse(['data' => $rows]);
