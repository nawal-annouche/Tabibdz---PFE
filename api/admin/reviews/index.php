<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

requireAuth('admin');
assertMethod('GET');

$search = trim((string) ($_GET['search'] ?? ''));
$sql = "SELECT r.id,
               r.medecin_id,
               r.patient_id,
               r.patient_name,
               r.rating,
               r.comment,
               r.is_verified,
               r.created_at,
               mu.prenom AS medecin_prenom,
               mu.nom AS medecin_nom,
               s.nom AS specialite_nom
        FROM medecin_reviews r
        INNER JOIN users mu ON mu.id = r.medecin_id
        LEFT JOIN medecin_profiles mp ON mp.user_id = mu.id
        LEFT JOIN specialites s ON s.id = mp.specialite_id
        WHERE 1 = 1";
$params = [];
if ($search !== '') {
    $sql .= " AND (r.patient_name LIKE :search OR r.comment LIKE :search OR mu.prenom LIKE :search OR mu.nom LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
$sql .= " ORDER BY r.created_at DESC LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute($params);
jsonResponse(['data' => $stmt->fetchAll()]);
