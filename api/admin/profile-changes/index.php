<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

requireAuth('admin');
assertMethod('GET');

$status = trim((string) ($_GET['status'] ?? 'pending'));
$search = trim((string) ($_GET['search'] ?? ''));

$sql = "SELECT r.id,
               r.medecin_id,
               r.title,
               r.description,
               r.status,
               r.admin_note,
               r.submitted_at,
               r.processed_at,
               u.prenom,
               u.nom,
               u.email,
               u.telephone,
               u.wilaya_id,
               u.commune,
               s.nom AS specialite_nom
        FROM medecin_profile_change_requests r
        INNER JOIN users u ON u.id = r.medecin_id
        LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
        LEFT JOIN specialites s ON s.id = mp.specialite_id
        WHERE u.role = 'medecin'";
$params = [];

if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
    $sql .= " AND r.status = :status";
    $params['status'] = $status;
}

if ($search !== '') {
    $sql .= " AND (
        u.prenom LIKE :search_prenom
        OR u.nom LIKE :search_nom
        OR u.email LIKE :search_email
        OR r.title LIKE :search_title
    )";
    $like = '%' . $search . '%';
    $params['search_prenom'] = $like;
    $params['search_nom'] = $like;
    $params['search_email'] = $like;
    $params['search_title'] = $like;
}

$sql .= " ORDER BY
            CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
            r.submitted_at DESC,
            r.id DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);

jsonResponse(['data' => $stmt->fetchAll()]);
