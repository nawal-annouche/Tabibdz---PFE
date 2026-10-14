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

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Impossible de lire schema.sql');
    }
    $pdo->exec($schema);

    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      migration_name VARCHAR(191) NOT NULL UNIQUE,
      executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');

    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    sort($migrationFiles);

    foreach ($migrationFiles as $file) {
        $name = basename($file);

        $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_name = :name LIMIT 1');
        $check->execute(['name' => $name]);
        $alreadyApplied = (bool) $check->fetchColumn();
        $check->closeCursor();
        if ($alreadyApplied) {
            echo "SKIP  $name\n";
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Impossible de lire $name");
        }

        try {
            // DDL can trigger implicit commits in MariaDB/MySQL, so avoid strict transaction wrapping here.
            $pdo->exec($sql);
            $ins = $pdo->prepare('INSERT INTO schema_migrations (migration_name) VALUES (:name)');
            $ins->execute(['name' => $name]);
            echo "APPLY $name\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "FAIL  $name\n";
            throw $e;
        }
    }

    echo "Migrations terminees.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erreur: ' . $e->getMessage() . "\n";
}

