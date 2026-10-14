<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/config.php';
require_once __DIR__ . '/../_core/db.php';
require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/auth.php';
require_once __DIR__ . '/../_core/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if (!maintenanceAccessAllowed()) {
    jsonResponse(['error' => 'Forbidden'], 403);
}

try {
    $email = trim((string) ($_GET['email'] ?? ''));
    if ($email === '' && !empty($_COOKIE[authConfig()['cookie_name']])) {
        $session = currentSessionUser();
        if ($session) {
            $stmt = db()->prepare('SELECT email, prenom, nom FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $session['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $email = trim((string) ($row['email'] ?? ''));
            }
        }
    }

    if ($email === '') {
        jsonResponse(['error' => 'Ajoutez ?email=votre-adresse@example.com'], 422);
    }

    if (!mailerConfig()['enabled']) {
        jsonResponse(['error' => 'MAIL_ENABLED=0. Activez la configuration email dans api/.env.local'], 422);
    }

    $subject = 'Test email TabibDZ';
    $body = "Bonjour,\n\nCeci est un email de test reel envoye par TabibDZ.\n\nSi vous recevez ce message, la configuration SMTP fonctionne.\n\nTabibDZ";
    smtpSendMail($email, '[TabibDZ] ' . $subject, $body);
    mailerLog('Manual test email sent to ' . $email . '.');

    jsonResponse([
        'success' => true,
        'message' => 'Email de test envoye.',
        'email' => $email,
    ]);
} catch (Throwable $e) {
    mailerLog('Manual test email failed: ' . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()], 500);
}
