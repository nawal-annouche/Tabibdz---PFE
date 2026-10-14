<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/doctor_profiles.php';

$doctor = requireAuth('medecin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT id, title, description, status, admin_note, submitted_at, processed_at, cancelled_at
         FROM medecin_profile_change_requests
         WHERE medecin_id = :id
         ORDER BY submitted_at DESC, id DESC
         LIMIT 10"
    );
    $stmt->execute(['id' => (int) $doctor['id']]);
    $rows = $stmt->fetchAll();
    $current = null;
    foreach ($rows as $row) {
        if (($row['status'] ?? '') === 'pending') {
            $current = $row;
            break;
        }
    }
    jsonResponse([
        'current_request' => $current,
        'history' => $rows,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = readJsonBody();
    $title = trim((string) ($body['title'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    $payload = doctorProfileExtractWritablePayload($body['payload'] ?? [], $pdo);

    if ($title === '') {
        jsonResponse(['error' => 'Le titre de la demande est requis.'], 422);
    }
    if (!$payload) {
        jsonResponse(['error' => 'Aucune modification a envoyer.'], 422);
    }

    $doctorData = doctorProfileBuildPayload($pdo, (int) $doctor['id']);
    if (($doctorData['workflow_status'] ?? '') === 'draft') {
        jsonResponse(['error' => 'Envoyez d abord le dossier initial pour utiliser les demandes de modification.'], 422);
    }

    $check = $pdo->prepare(
        "SELECT id
         FROM medecin_profile_change_requests
         WHERE medecin_id = :id AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $check->execute(['id' => (int) $doctor['id']]);
    if ($check->fetchColumn()) {
        jsonResponse(['error' => 'Une demande de changement est deja en attente.'], 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO medecin_profile_change_requests (medecin_id, title, description, payload_json, status)
         VALUES (:medecin_id, :title, :description, :payload_json, 'pending')"
    );
    $stmt->execute([
        'medecin_id' => (int) $doctor['id'],
        'title' => $title,
        'description' => $description === '' ? null : $description,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $requestId = (int) $pdo->lastInsertId();

    auditLog('medecin.profile_change.submit', 'medecin_profile_change_requests', $requestId, [
        'medecin_id' => (int) $doctor['id'],
        'title' => $title,
        'fields' => array_keys($payload),
    ]);

    $row = $pdo->prepare(
        "SELECT id, title, description, status, admin_note, submitted_at, processed_at, cancelled_at
         FROM medecin_profile_change_requests
         WHERE id = :id
         LIMIT 1"
    );
    $row->execute(['id' => $requestId]);

    jsonResponse([
        'message' => 'Demande de changement envoyee',
        'request' => firstOrNull($row),
    ], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    if (($body['action'] ?? '') !== 'cancel') {
        jsonResponse(['error' => 'Action non supportee'], 422);
    }

    $stmt = $pdo->prepare(
        "UPDATE medecin_profile_change_requests
         SET status = 'cancelled',
             cancelled_at = NOW(),
             processed_at = NOW()
         WHERE medecin_id = :medecin_id
           AND status = 'pending'"
    );
    $stmt->execute(['medecin_id' => (int) $doctor['id']]);
    if ($stmt->rowCount() < 1) {
        jsonResponse(['error' => 'Aucune demande en attente a annuler.'], 404);
    }

    auditLog('medecin.profile_change.cancel', 'users', (int) $doctor['id']);
    jsonResponse(['message' => 'Demande de changement annulee']);
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
