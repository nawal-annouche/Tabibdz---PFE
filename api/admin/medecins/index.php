<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $search = trim((string) ($_GET['search'] ?? ''));

    $sql = "SELECT u.id,
                   u.prenom,
                   u.nom,
                   u.email,
                   u.telephone,
                   u.photo_url,
                   u.wilaya_id,
                   u.commune,
                   u.created_at,
                   mp.specialite_id,
                   s.nom AS specialite_nom,
                   mp.tarif,
                   mp.cnas,
                   mp.casnos,
                   mp.note,
                   mp.avis_count,
                   mp.adresse,
                   mp.experience_years,
                   mp.agrement_no,
                   mp.bio,
                   mp.formations_text,
                   mp.hopitaux_text,
                   COALESCE(mp.approval_status, 'pending') AS approval_status,
                   CASE
                       WHEN COALESCE(mp.approval_status, 'pending') = 'pending' AND mp.submitted_at IS NULL THEN 'draft'
                       ELSE COALESCE(mp.approval_status, 'pending')
                   END AS workflow_status,
                   mp.submitted_at,
                   mp.approved_at,
                   mp.review_note
            FROM users u
            INNER JOIN medecin_profiles mp ON mp.user_id = u.id
            LEFT JOIN specialites s ON s.id = mp.specialite_id
            WHERE u.role = 'medecin'";
    $params = [];

    if ($status === 'draft') {
        $sql .= " AND COALESCE(mp.approval_status, 'pending') = 'pending' AND mp.submitted_at IS NULL";
    } elseif ($status === 'pending') {
        $sql .= " AND COALESCE(mp.approval_status, 'pending') = 'pending' AND mp.submitted_at IS NOT NULL";
    } elseif (in_array($status, ['approved', 'rejected'], true)) {
        $sql .= " AND COALESCE(mp.approval_status, 'pending') = :status";
        $params['status'] = $status;
    }

    if ($search !== '') {
        $sql .= " AND (
            u.prenom LIKE :search_prenom
            OR u.nom LIKE :search_nom
            OR u.email LIKE :search_email
            OR COALESCE(s.nom, '') LIKE :search_specialite
            OR COALESCE(mp.agrement_no, '') LIKE :search_agrement
        )";
        $searchLike = '%' . $search . '%';
        $params['search_prenom'] = $searchLike;
        $params['search_nom'] = $searchLike;
        $params['search_email'] = $searchLike;
        $params['search_specialite'] = $searchLike;
        $params['search_agrement'] = $searchLike;
    }

    $sql .= " ORDER BY
                CASE
                    WHEN COALESCE(mp.approval_status, 'pending') = 'pending' AND mp.submitted_at IS NOT NULL THEN 0
                    WHEN COALESCE(mp.approval_status, 'pending') = 'rejected' THEN 1
                    WHEN COALESCE(mp.approval_status, 'pending') = 'pending' AND mp.submitted_at IS NULL THEN 2
                    ELSE 3
                END,
                COALESCE(mp.submitted_at, u.created_at) ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
