<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/doctor_profiles.php';

$admin = requireAuth('admin');
$pdo = db();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

$loadRequest = static function (int $requestId) use ($pdo): ?array {
    $stmt = $pdo->prepare(
        "SELECT r.id,
                r.medecin_id,
                r.title,
                r.description,
                r.payload_json,
                r.status,
                r.admin_note,
                r.submitted_at,
                r.processed_at,
                r.cancelled_at,
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
                s.nom AS specialite_nom
         FROM medecin_profile_change_requests r
         INNER JOIN users u ON u.id = r.medecin_id
         LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
         LEFT JOIN specialites s ON s.id = mp.specialite_id
         WHERE r.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $requestId]);
    $row = firstOrNull($stmt);
    if (!$row) {
        return null;
    }
    $row['payload'] = json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [];
    unset($row['payload_json']);
    return $row;
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = $loadRequest($id);
    if (!$row) {
        jsonResponse(['error' => 'Demande introuvable'], 404);
    }
    jsonResponse(['data' => $row]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    $action = (string) ($body['action'] ?? '');
    $adminNote = trim((string) ($body['admin_note'] ?? ''));
    if (!in_array($action, ['approve', 'reject', 'note'], true)) {
        jsonResponse(['error' => 'Action invalide'], 422);
    }

    $request = $loadRequest($id);
    if (!$request) {
        jsonResponse(['error' => 'Demande introuvable'], 404);
    }
    if (($request['status'] ?? '') !== 'pending') {
        jsonResponse(['error' => 'Cette demande a deja ete traitee.'], 409);
    }

    $pdo->beginTransaction();
    try {
        if ($action === 'approve') {
            doctorProfileApplyPayload($pdo, (int) $request['medecin_id'], $request['payload']);
        }

        if ($action === 'note') {
            $stmt = $pdo->prepare(
                "UPDATE medecin_profile_change_requests
                 SET admin_note = :admin_note,
                     admin_user_id = :admin_user_id
                 WHERE id = :id"
            );
            $stmt->execute([
                'admin_note' => $adminNote === '' ? null : $adminNote,
                'admin_user_id' => (int) $admin['id'],
                'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE medecin_profile_change_requests
                 SET status = :status,
                     admin_note = :admin_note,
                     processed_at = NOW(),
                     admin_user_id = :admin_user_id
                 WHERE id = :id"
            );
            $stmt->execute([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'admin_note' => $adminNote === '' ? null : $adminNote,
                'admin_user_id' => (int) $admin['id'],
                'id' => $id,
            ]);
        }

        $pdo->commit();
        auditLog('admin.profile_change.' . $action, 'medecin_profile_change_requests', $id, [
            'admin_id' => (int) $admin['id'],
            'medecin_id' => (int) $request['medecin_id'],
        ]);
        jsonResponse(['message' => $action === 'approve' ? 'Changement approuve' : ($action === 'reject' ? 'Changement refuse' : 'Note enregistree')]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => 'Traitement impossible', 'details' => appDebug() ? $e->getMessage() : null], 500);
    }
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
