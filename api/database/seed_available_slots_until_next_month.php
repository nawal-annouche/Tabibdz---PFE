<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../_core/db.php';

$pdo = db();
$slotMinutes = 30;
$slotsPerDoctorPerWeek = 4;
$times = ['08:30:00', '09:30:00', '10:30:00', '14:00:00', '15:00:00', '16:00:00'];
$weekdayOffsets = [0, 1, 2, 3, 4];

$startDate = new DateTimeImmutable('tomorrow 00:00:00');
$endDate = (new DateTimeImmutable('first day of next month 00:00:00'))->modify('last day of this month');

$doctors = $pdo->query(
    'SELECT u.id
     FROM users u
     INNER JOIN medecin_profiles mp ON mp.user_id = u.id
     WHERE u.role = "medecin"
       AND COALESCE(mp.approval_status, "approved") = "approved"
     ORDER BY u.id ASC'
)->fetchAll(PDO::FETCH_COLUMN);

if (!$doctors) {
    fwrite(STDERR, "No approved doctors found.\n");
    exit(1);
}

$appointmentExists = $pdo->prepare(
    'SELECT 1
     FROM appointments
     WHERE medecin_id = :medecin_id
       AND date_heure = :start_at
       AND statut <> "annule"
     LIMIT 1'
);

$slotExists = $pdo->prepare(
    'SELECT id, is_available
     FROM creneaux
     WHERE medecin_id = :medecin_id
       AND start_at = :start_at
     LIMIT 1'
);

$insertSlot = $pdo->prepare(
    'INSERT INTO creneaux (medecin_id, start_at, end_at, is_available)
     VALUES (:medecin_id, :start_at, :end_at, 1)'
);

$inserted = 0;
$alreadyAvailable = 0;
$bookedOrBlocked = 0;

$pdo->beginTransaction();

try {
    foreach ($doctors as $doctorIndex => $doctorId) {
        for ($weekIndex = 0; ; $weekIndex++) {
            $weekHasDate = false;

            for ($slotIndex = 0; $slotIndex < $slotsPerDoctorPerWeek; $slotIndex++) {
                $dayOffset = ($weekIndex * 7) + $weekdayOffsets[($doctorIndex + $slotIndex) % count($weekdayOffsets)];
                $date = $startDate->modify('+' . $dayOffset . ' days');

                while (in_array($date->format('N'), ['6', '7'], true)) {
                    $date = $date->modify('+1 day');
                }

                if ($date > $endDate) {
                    continue;
                }

                $weekHasDate = true;
                $time = $times[($doctorIndex + $weekIndex + $slotIndex) % count($times)];
                $start = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time);
                $end = $start->modify('+' . $slotMinutes . ' minutes');
                $startSql = $start->format('Y-m-d H:i:s');

                $appointmentExists->execute([
                    'medecin_id' => (int) $doctorId,
                    'start_at' => $startSql,
                ]);
                if ($appointmentExists->fetchColumn()) {
                    $bookedOrBlocked++;
                    continue;
                }

                $slotExists->execute([
                    'medecin_id' => (int) $doctorId,
                    'start_at' => $startSql,
                ]);
                $existingSlot = $slotExists->fetch(PDO::FETCH_ASSOC);
                if ($existingSlot) {
                    if ((int) $existingSlot['is_available'] === 1) {
                        $alreadyAvailable++;
                    } else {
                        $bookedOrBlocked++;
                    }
                    continue;
                }

                $insertSlot->execute([
                    'medecin_id' => (int) $doctorId,
                    'start_at' => $startSql,
                    'end_at' => $end->format('Y-m-d H:i:s'),
                ]);
                $inserted++;
            }

            if (!$weekHasDate) {
                break;
            }
        }
    }

    $pdo->commit();

    echo 'Range: ' . $startDate->format('Y-m-d') . ' -> ' . $endDate->format('Y-m-d') . PHP_EOL;
    echo 'Doctors scanned: ' . count($doctors) . PHP_EOL;
    echo 'Available slots inserted: ' . $inserted . PHP_EOL;
    echo 'Already available slots: ' . $alreadyAvailable . PHP_EOL;
    echo 'Booked or blocked slots skipped: ' . $bookedOrBlocked . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
