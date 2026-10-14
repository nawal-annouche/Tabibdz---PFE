<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$doctor = requireAuth('medecin');
$doctorId = (int) $doctor['id'];

if (!isset($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
    jsonResponse(['error' => 'Aucun fichier recu'], 400);
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
    jsonResponse(['error' => 'Format image non supporte'], 400);
}

$ext = $allowed[$mime];
$targetDir = dirname(__DIR__, 3) . '/uploads/medecins';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    jsonResponse(['error' => 'Impossible de creer le dossier upload'], 500);
}

$fileName = sprintf('medecin_%d_%d.%s', $doctorId, time(), $ext);
$targetPath = $targetDir . '/' . $fileName;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jsonResponse(['error' => 'Upload echoue'], 500);
}

$photoUrl = '/uploads/medecins/' . $fileName;
db()->prepare('UPDATE users SET photo_url = :photo, updated_at = NOW() WHERE id = :id AND role = "medecin"')
    ->execute(['photo' => $photoUrl, 'id' => $doctorId]);

jsonResponse(['photo_url' => $photoUrl]);
