<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/config.php';
require_once __DIR__ . '/../_core/db.php';
require_once __DIR__ . '/../_core/appointment_notifications.php';

header('Content-Type: text/plain; charset=utf-8');

if (!maintenanceAccessAllowed()) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

try {
    $result = processAppointmentReminderNotifications(db());
    $emailResult = deliverPendingAppointmentNotificationEmails(db());
    echo "Appointment reminder notifications processed.\n";
    echo 'Processed at: ' . $result['processed_at'] . "\n";
    echo 'Created: ' . (int) $result['created_count'] . "\n";
    echo 'Emails sent: ' . (int) $emailResult['sent_count'] . "\n";

    foreach ($result['created'] as $item) {
        echo '- appointment #' . (int) $item['appointment_id'] . ' -> ' . $item['event_key'] . "\n";
    }
    foreach ($emailResult['sent'] as $item) {
        echo '- email appointment #' . (int) $item['appointment_id'] . ' -> ' . $item['event_key'] . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
