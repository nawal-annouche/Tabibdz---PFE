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
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $cfg['host'],
    $cfg['port'],
    $cfg['database'],
    $cfg['charset']
);

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $seed007 = file_get_contents(__DIR__ . '/migrations/20260328_007_reset_and_seed_30_medecins.sql');
    if ($seed007 === false) {
        throw new RuntimeException('Impossible de lire migration 007');
    }
    $pdo->exec($seed007);

    $seed008Path = __DIR__ . '/migrations/20260328_008_normalize_seed_text.sql';
    if (is_file($seed008Path)) {
        $seed008 = file_get_contents($seed008Path);
        if ($seed008 !== false) {
            $pdo->exec($seed008);
        }
    }

    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'medecin'")->fetchColumn();

    echo "Reseed medecins termine.\n";
    echo "Total medecins: {$count}\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur: ' . $e->getMessage() . "\n";
}
