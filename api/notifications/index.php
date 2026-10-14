<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET', 'PATCH');

$patientId = currentPatientId();

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    $markAll = !empty($body['mark_all']);
    if ($markAll) {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE patient_id = :pid AND is_read = 0')
            ->execute(['pid' => $patientId]);
        $countStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE patient_id = :pid AND is_read = 0');
        $countStmt->execute(['pid' => $patientId]);
        jsonResponse([
            'message' => 'Notifications marquées comme lues',
            'unread_count' => (int) $countStmt->fetchColumn(),
        ]);
    }

    $notificationId = isset($body['id']) ? (int) $body['id'] : 0;
    if ($notificationId < 1) {
        jsonResponse(['error' => 'id invalide'], 400);
    }

    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND patient_id = :pid')
        ->execute([
            'id' => $notificationId,
            'pid' => $patientId,
        ]);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE patient_id = :pid AND is_read = 0');
    $countStmt->execute(['pid' => $patientId]);
    jsonResponse([
        'message' => 'Notification marquée comme lue',
        'unread_count' => (int) $countStmt->fetchColumn(),
    ]);
}

$unreadOnly = ($_GET['unread'] ?? 'false') === 'true';

$sql = 'SELECT id, title, body, is_read, created_at FROM notifications WHERE patient_id = :pid';
if ($unreadOnly) {
    $sql .= ' AND is_read = 0';
}
$sql .= ' ORDER BY created_at DESC LIMIT 30';

$stmt = db()->prepare($sql);
$stmt->execute(['pid' => $patientId]);
$items = $stmt->fetchAll();

$cStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE patient_id = :pid AND is_read = 0');
$cStmt->execute(['pid' => $patientId]);
$unreadCount = (int) $cStmt->fetchColumn();

jsonResponse([
    'unread_count' => $unreadCount,
    'items' => $items,
]);
