<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/db.php';
require_once __DIR__ . '/../_core/helpers.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'id invalide';
    exit;
}

$patientId = currentPatientId();

$stmt = db()->prepare(
    "SELECT a.date_heure, a.duree_min, a.motif, a.confirmation_code,
            a.consultation_mode, a.patient_prenom, a.patient_nom,
            m.prenom AS medecin_prenom, m.nom AS medecin_nom, mp.adresse
     FROM appointments a
     INNER JOIN users m ON m.id = a.medecin_id
     INNER JOIN medecin_profiles mp ON mp.user_id = m.id
     WHERE a.id = :id AND a.patient_id = :pid"
);
$stmt->execute(['id' => $id, 'pid' => $patientId]);
$rdv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rdv) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Rendez-vous introuvable';
    exit;
}

$start = gmdate('Ymd\\THis\\Z', strtotime((string) $rdv['date_heure']));
$end = gmdate('Ymd\\THis\\Z', strtotime((string) $rdv['date_heure'] . ' +' . ((int) $rdv['duree_min']) . ' minutes'));
$summary = 'RDV Dr. ' . $rdv['medecin_prenom'] . ' ' . $rdv['medecin_nom'];
$patientName = trim(((string) ($rdv['patient_prenom'] ?? '')) . ' ' . ((string) ($rdv['patient_nom'] ?? '')));
$description = 'Motif: ' . ($rdv['motif'] ?: 'Consultation')
    . ' | Patient: ' . ($patientName !== '' ? $patientName : 'Patient')
    . ' | Ref: ' . $rdv['confirmation_code'];
$location = ($rdv['consultation_mode'] ?? 'cabinet') === 'teleconsultation'
    ? 'Teleconsultation TabibDZ'
    : ($rdv['adresse'] ?: 'Cabinet medical');

$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//TabibDZ//RDV//FR\r\nBEGIN:VEVENT\r\nDTSTART:$start\r\nDTEND:$end\r\nSUMMARY:$summary\r\nDESCRIPTION:$description\r\nLOCATION:$location\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="rdv-tabibdz-' . $id . '.ics"');
echo $ics;
