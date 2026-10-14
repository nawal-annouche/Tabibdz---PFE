<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$patientId = currentPatientId();

if (!isset($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
    jsonResponse(['error' => 'Aucun fichier reçu'], 400);
}

$file = $_FILES['photo'];
if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
    jsonResponse(['error' => 'Image trop volumineuse (max 2 Mo)'], 400);
}

$mime = mime_content_type($file['tmp_name']) ?: '';
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];
if (!isset($allowed[$mime])) {
    jsonResponse(['error' => 'Format image non supporté'], 400);
}

$ext = $allowed[$mime];
$targetDir = dirname(__DIR__, 3) . '/uploads/patients';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    jsonResponse(['error' => 'Impossible de créer le dossier upload'], 500);
}

$fileName = sprintf('patient_%d_%d.%s', $patientId, time(), $ext);
$targetPath = $targetDir . '/' . $fileName;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jsonResponse(['error' => 'Upload échoué'], 500);
}

$photoUrl = '/uploads/patients/' . $fileName;
db()->prepare('UPDATE users SET photo_url = :photo, updated_at = NOW() WHERE id = :id AND role = "patient"')
    ->execute(['photo' => $photoUrl, 'id' => $patientId]);

jsonResponse(['photo_url' => $photoUrl]);
