<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

requireAuth('admin');
assertMethod('GET');

$search = trim((string) ($_GET['search'] ?? ''));

$sql = "SELECT u.id,
               u.prenom,
               u.nom,
               u.email,
               u.telephone,
               u.date_naissance,
               u.sexe,
               u.wilaya_id,
               w.nom AS wilaya_nom,
               u.commune,
               u.is_active,
               u.created_at,
               COUNT(DISTINCT a.id) AS appointments_count,
               MAX(a.date_heure) AS last_appointment_at,
               COUNT(DISTINCT pf.id) AS family_count
        FROM users u
        LEFT JOIN wilayas w ON w.id = u.wilaya_id
        LEFT JOIN appointments a ON a.patient_id = u.id
        LEFT JOIN patient_family_members pf ON pf.patient_id = u.id
        WHERE u.role = 'patient'";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.prenom LIKE :search OR u.nom LIKE :search OR u.email LIKE :search OR COALESCE(u.telephone, '') LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute($params);

jsonResponse(['data' => $stmt->fetchAll()]);
