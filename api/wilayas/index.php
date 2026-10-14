<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

$stmt = db()->query('SELECT id, nom FROM wilayas ORDER BY id');
jsonResponse($stmt->fetchAll());
