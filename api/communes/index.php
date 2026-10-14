<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

$wilayaId = isset($_GET['wilaya_id']) && ctype_digit((string) $_GET['wilaya_id']) ? (int) $_GET['wilaya_id'] : null;

$sql = 'SELECT id, wilaya_id, nom FROM communes';
$params = [];
if ($wilayaId) {
    $sql .= ' WHERE wilaya_id = :wilaya_id';
    $params['wilaya_id'] = $wilayaId;
}
$sql .= ' ORDER BY nom';

$stmt = db()->prepare($sql);
$stmt->execute($params);
jsonResponse($stmt->fetchAll());
