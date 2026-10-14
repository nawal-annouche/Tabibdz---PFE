<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

requireAuth('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $sql = "SELECT id, full_name, email, phone, subject, message, status, created_at, updated_at
            FROM contact_messages
            WHERE 1 = 1";
    $params = [];
    if ($status !== '' && in_array($status, ['new', 'in_progress', 'closed'], true)) {
        $sql .= " AND status = :status";
        $params['status'] = $status;
    }
    $sql .= " ORDER BY created_at DESC LIMIT 200";
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
    $status = (string) ($body['status'] ?? '');
    if (!in_array($status, ['new', 'in_progress', 'closed'], true)) {
        jsonResponse(['error' => 'Statut invalide'], 422);
    }
    $stmt = $pdo->prepare('UPDATE contact_messages SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['status' => $status, 'id' => $id]);
    jsonResponse(['message' => 'Message mis a jour']);
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
