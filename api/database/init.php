<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!maintenanceAccessAllowed()) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$cfg = dbConfig();
$dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']);

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Impossible de lire schema.sql');
    }

    $pdo->exec($sql);
    echo "Base initialisée avec succès.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur: ' . $e->getMessage() . "\n";
}
