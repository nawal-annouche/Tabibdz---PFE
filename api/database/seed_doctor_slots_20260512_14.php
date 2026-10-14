<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../_core/db.php';

$pdo = db();

$fromDate = '2026-05-12';
$toDate = '2026-05-14';
$slotMinutes = 30;
$hourStarts = [
    '09:00',
    '09:30',
    '10:00',
    '10:30',
    '11:00',
    '11:30',
    '12:00',
    '12:30',
    '13:00',
    '13:30',
    '14:00',
    '14:30',
    '15:00',
    '15:30',
];

$doctorIds = $pdo->query("SELECT id FROM users WHERE role = 'medecin' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
if (!$doctorIds) {
    fwrite(STDERR, "No doctors found.\n");
    exit(1);
}

$insert = $pdo->prepare(
    'INSERT IGNORE INTO creneaux (medecin_id, start_at, end_at, is_available)
     VALUES (:medecin_id, :start_at, :end_at, 1)'
);

$start = new DateTimeImmutable($fromDate . ' 00:00:00');
$end = new DateTimeImmutable($toDate . ' 00:00:00');

$created = 0;
$requested = 0;

$pdo->beginTransaction();

try {
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        foreach ($doctorIds as $doctorId) {
            foreach ($hourStarts as $time) {
                $slotStart = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $time . ':00');
                $slotEnd = $slotStart->modify('+' . $slotMinutes . ' minutes');
                $requested++;

                $insert->execute([
                    'medecin_id' => (int) $doctorId,
                    'start_at' => $slotStart->format('Y-m-d H:i:s'),
                    'end_at' => $slotEnd->format('Y-m-d H:i:s'),
                ]);

                $created += $insert->rowCount() > 0 ? 1 : 0;
            }
        }
    }

    $pdo->commit();

    echo 'Doctors: ' . count($doctorIds) . PHP_EOL;
    echo 'Date range: ' . $fromDate . ' -> ' . $toDate . PHP_EOL;
    echo 'Slots requested: ' . $requested . PHP_EOL;
    echo 'Slots created: ' . $created . PHP_EOL;
    echo 'Slots already present: ' . ($requested - $created) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
