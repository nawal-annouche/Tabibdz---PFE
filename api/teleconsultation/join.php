<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';

assertMethod('POST');

$pdo = db();
$user = requireAuth();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

$role = (string) ($user['role'] ?? '');
if (!in_array($role, ['patient', 'medecin'], true)) {
    jsonResponse(['error' => 'Acces interdit'], 403);
}

$sql = 'SELECT a.id, a.patient_id, a.medecin_id, a.date_heure, a.statut, a.consultation_mode, a.confirmation_code,
               a.patient_prenom, a.patient_nom, u.prenom AS medecin_prenom, u.nom AS medecin_nom
        FROM appointments a
        INNER JOIN users u ON u.id = a.medecin_id
        WHERE a.id = :id';
$params = ['id' => $id];
if ($role === 'patient') {
    $sql .= ' AND a.patient_id = :owner_id';
    $params['owner_id'] = (int) $user['id'];
} else {
    $sql .= ' AND a.medecin_id = :owner_id';
    $params['owner_id'] = (int) $user['id'];
}
$sql .= ' LIMIT 1';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointment = firstOrNull($stmt);
if (!$appointment) {
    jsonResponse(['error' => 'Rendez-vous introuvable'], 404);
}

if (($appointment['consultation_mode'] ?? 'cabinet') !== 'teleconsultation') {
    jsonResponse(['error' => 'Ce rendez-vous n est pas une teleconsultation'], 422);
}

$status = (string) ($appointment['statut'] ?? '');
if (in_array($status, ['annule', 'termine'], true)) {
    jsonResponse(['error' => 'Cette consultation n est plus accessible'], 409);
}

$appointmentDate = strtotime((string) $appointment['date_heure']);
if ($appointmentDate === false) {
    jsonResponse(['error' => 'Date de rendez-vous invalide'], 500);
}

$joinOpenAtTs = $appointmentDate - (10 * 60);
$lateLimitTs = $appointmentDate + (90 * 60);
$nowTs = time();

if ($nowTs < $joinOpenAtTs) {
    jsonResponse([
        'error' => 'La salle ouvrira 10 minutes avant le rendez-vous.',
        'opens_at' => date(DATE_ATOM, $joinOpenAtTs),
    ], 409);
}

if ($nowTs > $lateLimitTs) {
    jsonResponse([
        'error' => 'La fenetre de consultation est depassee.',
    ], 409);
}

$updateStmt = $pdo->prepare(
    "UPDATE appointments
     SET teleconsultation_status = 'in_progress',
         teleconsultation_started_at = COALESCE(teleconsultation_started_at, NOW()),
         updated_at = NOW()
     WHERE id = :id AND consultation_mode = 'teleconsultation'"
);
$updateStmt->execute(['id' => $id]);

$code = slugify((string) ($appointment['confirmation_code'] ?? ''));
if ($code === '') {
    $code = 'rdv';
}
$roomName = sprintf('tabibdz-rdv-%d-%s', $id, $code);

jsonResponse([
    'room_name' => $roomName,
    'join_url' => '/teleconsultation-room/?id=' . $id,
    'viewer_role' => $role,
    'back_url' => $role === 'medecin' ? '/espace-medecin/?tab=teleconsultations' : '/teleconsultation/?id=' . $id,
    'opens_at' => date(DATE_ATOM, $joinOpenAtTs),
    'appointment_at' => date(DATE_ATOM, $appointmentDate),
    'patient_display_name' => trim(((string) ($appointment['patient_prenom'] ?? '')) . ' ' . ((string) ($appointment['patient_nom'] ?? ''))),
    'doctor_display_name' => trim('Dr. ' . ((string) ($appointment['medecin_prenom'] ?? '')) . ' ' . ((string) ($appointment['medecin_nom'] ?? ''))),
]);
