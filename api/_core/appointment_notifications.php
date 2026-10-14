<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mail_templates.php';

function appointmentNotificationLabel(string $consultationMode): string
{
    return $consultationMode === 'teleconsultation' ? 'teleconsultation' : 'consultation au cabinet';
}

function appointmentNotificationWhen(string $dateHeure): string
{
    $timestamp = strtotime($dateHeure);
    if ($timestamp === false) {
        return 'a une date a confirmer';
    }

    return date('d/m/Y a H:i', $timestamp);
}

function appointmentNotificationDoctorName(array $row): string
{
    $prenom = trim((string) ($row['medecin_prenom'] ?? ''));
    $nom = trim((string) ($row['medecin_nom'] ?? ''));
    return trim('Dr. ' . trim($prenom . ' ' . $nom));
}

function appointmentNotificationEventPayload(PDO $pdo, int $appointmentId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.id, a.patient_id, a.date_heure, a.duree_min, a.consultation_mode, a.confirmation_code,
                u.prenom AS medecin_prenom, u.nom AS medecin_nom,
                s.nom AS specialite_nom
         FROM appointments a
         INNER JOIN users u ON u.id = a.medecin_id
         LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
         LEFT JOIN specialites s ON s.id = mp.specialite_id
         WHERE a.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $appointmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function appointmentNotificationRecipient(PDO $pdo, int $patientId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.prenom, u.nom, u.email,
                np.sms_24h, np.sms_2h, np.email_24h, np.email_2h, np.sms_annulation, np.email_annulation
         FROM users u
         LEFT JOIN notification_preferences np ON np.patient_id = u.id
         WHERE u.id = :id AND u.role = "patient"
         LIMIT 1'
    );
    $stmt->execute(['id' => $patientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function appointmentNotificationEmailAllowed(array $recipient, string $eventKey): bool
{
    $email = trim((string) ($recipient['email'] ?? ''));
    if ($email === '') {
        return false;
    }

    return match ($eventKey) {
        'reminder_24h' => (int) ($recipient['email_24h'] ?? 1) === 1,
        'reminder_12h' => (int) ($recipient['email_24h'] ?? 1) === 1,
        'reminder_1h', 'tele_reminder_15m', 'tele_start_now' => (int) ($recipient['email_2h'] ?? 1) === 1,
        default => true,
    };
}

function markAppointmentNotificationEmailStatus(PDO $pdo, int $appointmentId, string $eventKey, ?string $sentAt, ?string $error): void
{
    $pdo->prepare(
        'UPDATE appointment_notification_events
         SET email_sent_at = :email_sent_at, email_error = :email_error
         WHERE appointment_id = :appointment_id AND event_key = :event_key'
    )->execute([
        'email_sent_at' => $sentAt,
        'email_error' => $error,
        'appointment_id' => $appointmentId,
        'event_key' => $eventKey,
    ]);
}

function tryDeliverAppointmentNotificationEmail(PDO $pdo, int $appointmentId, int $patientId, string $eventKey, string $title, string $body): bool
{
    $recipient = appointmentNotificationRecipient($pdo, $patientId);
    if (!$recipient || !appointmentNotificationEmailAllowed($recipient, $eventKey)) {
        return false;
    }

    $cfg = mailerConfig();
    if (!$cfg['enabled']) {
        return false;
    }

    $prenom = trim((string) ($recipient['prenom'] ?? ''));
    $nom = trim((string) ($recipient['nom'] ?? ''));
    $displayName = trim($prenom . ' ' . $nom);
    $bundle = appointmentEmailBundle($displayName, $title, $body);

    try {
        smtpSendMail((string) $recipient['email'], '[TabibDZ] ' . $bundle['subject'], $bundle['text'], $bundle['html']);
        markAppointmentNotificationEmailStatus($pdo, $appointmentId, $eventKey, date('Y-m-d H:i:s'), null);
        mailerLog('Email sent to ' . (string) $recipient['email'] . ' for event ' . $eventKey . ' (patient #' . $patientId . ').');
        return true;
    } catch (Throwable $e) {
        markAppointmentNotificationEmailStatus($pdo, $appointmentId, $eventKey, null, mb_substr($e->getMessage(), 0, 65000));
        mailerLog('Email failed for patient #' . $patientId . ' (' . (string) ($recipient['email'] ?? '') . ') event ' . $eventKey . ': ' . $e->getMessage());
        return false;
    }
}

function enqueueAppointmentNotificationEvent(PDO $pdo, int $appointmentId, int $patientId, string $eventKey, string $title, string $body): bool
{
    $claim = $pdo->prepare(
        'INSERT IGNORE INTO appointment_notification_events (appointment_id, patient_id, event_key, notification_id)
         VALUES (:appointment_id, :patient_id, :event_key, NULL)'
    );
    $claim->execute([
        'appointment_id' => $appointmentId,
        'patient_id' => $patientId,
        'event_key' => $eventKey,
    ]);

    if ($claim->rowCount() < 1) {
        return false;
    }

    $insertNotification = $pdo->prepare(
        'INSERT INTO notifications (patient_id, title, body, is_read) VALUES (:patient_id, :title, :body, 0)'
    );
    $insertNotification->execute([
        'patient_id' => $patientId,
        'title' => $title,
        'body' => $body,
    ]);

    $notificationId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'UPDATE appointment_notification_events
         SET notification_id = :notification_id
         WHERE appointment_id = :appointment_id AND event_key = :event_key'
    )->execute([
        'notification_id' => $notificationId,
        'appointment_id' => $appointmentId,
        'event_key' => $eventKey,
    ]);

    tryDeliverAppointmentNotificationEmail($pdo, $appointmentId, $patientId, $eventKey, $title, $body);

    return true;
}

function deliverPendingAppointmentNotificationEmails(PDO $pdo, int $limit = 100): array
{
    if (!mailerConfig()['enabled']) {
        return ['sent_count' => 0, 'sent' => []];
    }

    $safeLimit = max(1, min($limit, 500));
    $stmt = $pdo->prepare(
        'SELECT ane.appointment_id, ane.patient_id, ane.event_key, n.title, n.body
         FROM appointment_notification_events ane
         INNER JOIN notifications n ON n.id = ane.notification_id
         WHERE ane.notification_id IS NOT NULL
           AND ane.email_sent_at IS NULL
         ORDER BY ane.created_at ASC
         LIMIT ' . $safeLimit
    );
    $stmt->execute();

    $sent = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ok = tryDeliverAppointmentNotificationEmail(
            $pdo,
            (int) $row['appointment_id'],
            (int) $row['patient_id'],
            (string) $row['event_key'],
            (string) $row['title'],
            (string) $row['body']
        );
        if ($ok) {
            $sent[] = [
                'appointment_id' => (int) $row['appointment_id'],
                'event_key' => (string) $row['event_key'],
            ];
        }
    }

    return [
        'sent_count' => count($sent),
        'sent' => $sent,
    ];
}

function enqueueAppointmentConfirmationNotification(PDO $pdo, int $appointmentId): bool
{
    $appointment = appointmentNotificationEventPayload($pdo, $appointmentId);
    if (!$appointment) {
        return false;
    }

    $doctor = appointmentNotificationDoctorName($appointment);
    $specialite = trim((string) ($appointment['specialite_nom'] ?? 'Consultation'));
    $when = appointmentNotificationWhen((string) $appointment['date_heure']);
    $mode = appointmentNotificationLabel((string) ($appointment['consultation_mode'] ?? 'cabinet'));
    $code = trim((string) ($appointment['confirmation_code'] ?? ''));

    $title = 'Rendez-vous confirme';
    $body = sprintf(
        'Votre rendez-vous avec %s (%s) est confirme pour le %s en %s.%s',
        $doctor,
        $specialite !== '' ? $specialite : 'Consultation',
        $when,
        $mode,
        $code !== '' ? ' Code de confirmation : ' . $code . '.' : ''
    );

    return enqueueAppointmentNotificationEvent(
        $pdo,
        (int) $appointment['id'],
        (int) $appointment['patient_id'],
        'confirmation',
        $title,
        $body
    );
}

function processAppointmentReminderNotifications(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    $lookAhead = $now->modify('+25 hours')->format('Y-m-d H:i:s');
    $lookBack = $now->modify('-20 minutes')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'SELECT a.id, a.patient_id, a.date_heure, a.duree_min, a.consultation_mode, a.confirmation_code,
                u.prenom AS medecin_prenom, u.nom AS medecin_nom,
                s.nom AS specialite_nom
         FROM appointments a
         INNER JOIN users u ON u.id = a.medecin_id
         LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
         LEFT JOIN specialites s ON s.id = mp.specialite_id
         WHERE a.statut = "confirme"
           AND a.date_heure >= :look_back
           AND a.date_heure <= :look_ahead
         ORDER BY a.date_heure ASC'
    );
    $stmt->execute([
        'look_back' => $lookBack,
        'look_ahead' => $lookAhead,
    ]);

    $definitions = [
        ['event_key' => 'reminder_24h', 'min_minutes' => 24 * 60 - 69, 'max_minutes' => 24 * 60, 'label' => '24 heures', 'tele_only' => false],
        ['event_key' => 'reminder_12h', 'min_minutes' => 12 * 60 - 69, 'max_minutes' => 12 * 60, 'label' => '12 heures', 'tele_only' => false],
        ['event_key' => 'reminder_1h',  'min_minutes' => 60 - 69,      'max_minutes' => 60,      'label' => '1 heure',   'tele_only' => false],
        ['event_key' => 'tele_reminder_15m', 'min_minutes' => 0,  'max_minutes' => 15, 'label' => '15 minutes', 'tele_only' => true],
        ['event_key' => 'tele_start_now',    'min_minutes' => -20,'max_minutes' => 0,  'label' => 'maintenant',  'tele_only' => true],
    ];

    $created = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $appointment) {
        $timestamp = strtotime((string) $appointment['date_heure']);
        if ($timestamp === false) {
            continue;
        }

        $minutesUntil = (int) floor(($timestamp - $now->getTimestamp()) / 60);
        $isTeleconsultation = (string) ($appointment['consultation_mode'] ?? '') === 'teleconsultation';

        foreach ($definitions as $definition) {
            if (!empty($definition['tele_only']) && !$isTeleconsultation) {
                continue;
            }
            if ($minutesUntil < (int) $definition['min_minutes'] || $minutesUntil > (int) $definition['max_minutes']) {
                continue;
            }

            $doctor = appointmentNotificationDoctorName($appointment);
            $when = appointmentNotificationWhen((string) $appointment['date_heure']);
            $mode = appointmentNotificationLabel((string) ($appointment['consultation_mode'] ?? 'cabinet'));
            $specialite = trim((string) ($appointment['specialite_nom'] ?? 'Consultation'));
            $eventKey = (string) $definition['event_key'];

            if ($eventKey === 'tele_reminder_15m') {
                $title = 'Teleconsultation imminente';
                $body = sprintf(
                    'Important : votre teleconsultation avec %s (%s) commence dans 15 minutes, le %s. Preparez-vous a rejoindre la consultation.',
                    $doctor,
                    $specialite !== '' ? $specialite : 'Consultation',
                    $when
                );
            } elseif ($eventKey === 'tele_start_now') {
                $title = 'Teleconsultation en cours';
                $body = sprintf(
                    'Important : votre teleconsultation avec %s (%s) commence maintenant. Rejoignez la consultation des que possible.',
                    $doctor,
                    $specialite !== '' ? $specialite : 'Consultation'
                );
            } else {
                $title = 'Rappel rendez-vous - ' . $definition['label'];
                $body = sprintf(
                    'Rappel : votre rendez-vous avec %s (%s) est prevu le %s en %s. Debut dans %s.',
                    $doctor,
                    $specialite !== '' ? $specialite : 'Consultation',
                    $when,
                    $mode,
                    $definition['label']
                );
            }

            $wasCreated = enqueueAppointmentNotificationEvent(
                $pdo,
                (int) $appointment['id'],
                (int) $appointment['patient_id'],
                $eventKey,
                $title,
                $body
            );

            if ($wasCreated) {
                $created[] = [
                    'appointment_id' => (int) $appointment['id'],
                    'event_key' => (string) $definition['event_key'],
                ];
            }
        }
    }

    return [
        'processed_at' => $now->format(DATE_ATOM),
        'created_count' => count($created),
        'created' => $created,
    ];
}
