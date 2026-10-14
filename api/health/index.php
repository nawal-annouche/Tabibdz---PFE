<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

try {
    db()->query('SELECT 1');
    $db = 'ok';
} catch (Throwable $e) {
    $db = 'down';
}

jsonResponse([
    'status' => $db === 'ok' ? 'ok' : 'degraded',
    'db' => $db,
    'time' => date(DATE_ATOM),
    'env' => env('APP_ENV', 'local'),
]);
