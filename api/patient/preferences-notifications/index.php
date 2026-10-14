<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
$pdo = db();
$patientId = currentPatientId();

$validKeys = ['sms_24h','sms_2h','email_24h','email_2h','sms_annulation','email_annulation'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT sms_24h, sms_2h, email_24h, email_2h, sms_annulation, email_annulation FROM notification_preferences WHERE patient_id = :id');
    $stmt->execute(['id' => $patientId]);
    $prefs = firstOrNull($stmt);
    if (!$prefs) {
        $pdo->prepare('INSERT INTO notification_preferences (patient_id) VALUES (:id)')->execute(['id' => $patientId]);
        $prefs = ['sms_24h'=>1,'sms_2h'=>0,'email_24h'=>1,'email_2h'=>1,'sms_annulation'=>1,'email_annulation'=>1];
    }
    foreach ($prefs as $k => $v) {
        $prefs[$k] = (bool) $v;
    }
    jsonResponse($prefs);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    $set = [];
    $params = ['id' => $patientId];

    foreach ($body as $k => $v) {
        if (in_array($k, $validKeys, true)) {
            $set[] = "$k = :$k";
            $params[$k] = $v ? 1 : 0;
        }
    }

    if (!$set) {
        jsonResponse(['error' => 'Aucune préférence valide'], 400);
    }

    $pdo->prepare('INSERT IGNORE INTO notification_preferences (patient_id) VALUES (:id)')->execute(['id' => $patientId]);

    $stmt = $pdo->prepare('UPDATE notification_preferences SET ' . implode(', ', $set) . ' WHERE patient_id = :id');
    $stmt->execute($params);

    jsonResponse(['message' => 'Préférence enregistrée']);
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
