<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('GET');

$medecinId = isset($_GET['medecin_id']) ? (int) $_GET['medecin_id'] : 0;
if ($medecinId < 1) {
    jsonResponse(['error' => 'medecin_id invalide'], 400);
}
$from = isset($_GET['from']) ? (string) $_GET['from'] : date('Y-m-d H:i:s');

$stmt = db()->prepare('SELECT id, start_at, end_at FROM creneaux WHERE medecin_id = :mid AND is_available = 1 AND end_at >= :from ORDER BY start_at ASC LIMIT 50');
$stmt->execute(['mid' => $medecinId, 'from' => date('Y-m-d H:i:s', strtotime($from))]);
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['start_at'] = date(DATE_ATOM, strtotime((string) $row['start_at']));
    $row['end_at'] = date(DATE_ATOM, strtotime((string) $row['end_at']));
}
unset($row);

jsonResponse($rows);
