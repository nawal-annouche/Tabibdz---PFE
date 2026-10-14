<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

requireAuth('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $mode = trim((string) ($_GET['mode'] ?? ''));
    $search = trim((string) ($_GET['search'] ?? ''));

    $sql = "SELECT a.id,
                   a.patient_id,
                   a.medecin_id,
                   a.date_heure,
                   a.duree_min,
                   a.motif,
                   a.type_visite,
                   a.consultation_mode,
                   a.patient_target_type,
                   a.patient_prenom,
                   a.patient_nom,
                   a.patient_relation,
                   a.patient_telephone,
                   a.patient_email,
                   a.teleconsultation_notes,
                   a.statut,
                   a.confirmation_code,
                   a.created_at,
                   pu.prenom AS account_patient_prenom,
                   pu.nom AS account_patient_nom,
                   mu.prenom AS medecin_prenom,
                   mu.nom AS medecin_nom,
                   s.nom AS specialite_nom,
                   mp.adresse AS medecin_adresse,
                   COUNT(ad.id) AS documents_count
            FROM appointments a
            INNER JOIN users pu ON pu.id = a.patient_id
            INNER JOIN users mu ON mu.id = a.medecin_id
            LEFT JOIN medecin_profiles mp ON mp.user_id = mu.id
            LEFT JOIN specialites s ON s.id = mp.specialite_id
            LEFT JOIN appointment_documents ad ON ad.appointment_id = a.id
            WHERE 1 = 1";
    $params = [];

    if ($status !== '' && in_array($status, ['confirme', 'termine', 'annule'], true)) {
        $sql .= " AND a.statut = :status";
        $params['status'] = $status;
    }
    if ($mode !== '' && in_array($mode, ['cabinet', 'teleconsultation'], true)) {
        $sql .= " AND a.consultation_mode = :mode";
        $params['mode'] = $mode;
    }
    if ($search !== '') {
        $sql .= " AND (a.patient_prenom LIKE :search OR a.patient_nom LIKE :search OR pu.email LIKE :search OR mu.prenom LIKE :search OR mu.nom LIKE :search OR a.confirmation_code LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    $sql .= " GROUP BY a.id ORDER BY a.date_heure DESC LIMIT 250";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id < 1) {
        jsonResponse(['error' => 'id invalide'], 400);
    }
    $body = readJsonBody();
    $statut = (string) ($body['statut'] ?? '');
    if (!in_array($statut, ['confirme', 'termine', 'annule'], true)) {
        jsonResponse(['error' => 'Statut invalide'], 422);
    }
    $stmt = $pdo->prepare('UPDATE appointments SET statut = :statut, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['statut' => $statut, 'id' => $id]);
    jsonResponse(['message' => 'Rendez-vous mis a jour']);
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
