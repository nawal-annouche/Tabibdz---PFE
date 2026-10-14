<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

$admin = requireAuth('admin');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare(
        "SELECT u.id,
                u.prenom,
                u.nom,
                u.email,
                u.telephone,
                u.photo_url,
                u.wilaya_id,
                u.commune,
                u.created_at,
                u.is_active,
                u.email_verified_at,
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
         WHERE u.id = :id
           AND u.role = 'medecin'
         LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $doctor = firstOrNull($stmt);
    if (!$doctor) {
        jsonResponse(['error' => 'Medecin introuvable'], 404);
    }
    jsonResponse(['data' => $doctor]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    $pdo = db();

    $userFields = ['prenom','nom','telephone','email','wilaya_id','commune','is_active'];
    $profileFields = ['specialite_id','tarif','cnas','casnos','note','avis_count','adresse','experience_years','agrement_no','bio','formations_text','hopitaux_text','approval_status','review_note'];

    $userSet = [];
    $userParams = ['id' => $id];
    foreach ($userFields as $field) {
        if (array_key_exists($field, $body)) {
            $userSet[] = $field . ' = :' . $field;
            $userParams[$field] = $body[$field];
        }
    }

    $profileSet = [];
    $profileParams = ['id' => $id];
    foreach ($profileFields as $field) {
        if (array_key_exists($field, $body)) {
            $profileSet[] = $field . ' = :' . $field;
            $profileParams[$field] = $body[$field];
        }
    }

    if (!$userSet && !$profileSet) {
        jsonResponse(['error' => 'Aucune modification recue'], 422);
    }

    $pdo->beginTransaction();
    try {
        if ($userSet) {
            $sql = 'UPDATE users SET ' . implode(', ', $userSet) . ', updated_at = NOW() WHERE id = :id AND role = "medecin"';
            $pdo->prepare($sql)->execute($userParams);
        }

        if ($profileSet) {
            if (array_key_exists('approval_status', $body)) {
                $profileSet[] = 'approved_at = CASE WHEN :approval_status_case = "approved" THEN NOW() ELSE NULL END';
                $profileSet[] = 'submitted_at = COALESCE(submitted_at, NOW())';
                $profileParams['approval_status_case'] = $body['approval_status'];
                if (($body['approval_status'] ?? '') === 'rejected' && !array_key_exists('review_note', $body)) {
                    $profileSet[] = 'review_note = COALESCE(review_note, "Dossier a corriger avant validation")';
                }
            }

            $sql = 'UPDATE medecin_profiles SET ' . implode(', ', $profileSet) . ' WHERE user_id = :id';
            $pdo->prepare($sql)->execute($profileParams);
        }

        $pdo->commit();
        auditLog('admin.medecins.update', 'users', $id, ['admin_id' => $admin['id'], 'fields' => array_keys($body)]);
        jsonResponse(['message' => 'Dossier medecin mis a jour']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => 'Mise a jour impossible', 'details' => appDebug() ? $e->getMessage() : null], 500);
    }
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
