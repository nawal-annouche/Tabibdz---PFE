<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

$pdo = db();
$medecinsCount = (int) $pdo->query("SELECT COUNT(*) FROM users u INNER JOIN medecin_profiles mp ON mp.user_id = u.id WHERE u.role='medecin' AND COALESCE(mp.approval_status, 'approved') = 'approved'")->fetchColumn();
$wilayasCount = (int) $pdo->query('SELECT COUNT(*) FROM wilayas')->fetchColumn();
$rdvCount = (int) $pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
$note = (float) $pdo->query('SELECT COALESCE(AVG(note),0) FROM medecin_profiles')->fetchColumn();

jsonResponse([
    'medecins_count' => $medecinsCount,
    'wilayas_count' => $wilayasCount,
    'rdv_count' => $rdvCount,
    'note_moyenne' => round($note, 1),
]);
