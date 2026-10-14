<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/mailer.php';
require_once __DIR__ . '/../../_core/mail_templates.php';
assertMethod('POST');

$body = readJsonBody();
$email = lowerUtf8((string) ($body['email'] ?? ''));
if ($email === '') {
    jsonResponse(['error' => 'Email requis'], 422);
}

enforceRateLimit('auth.forgot_password', clientIp() . '|' . $email, 5, 900);

$stmt = db()->prepare('SELECT id, prenom, nom, email FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = firstOrNull($stmt);

$response = ['message' => 'Si cet email existe, un code PIN et un lien de reinitialisation ont ete envoyes.'];

if ($user) {
    $reset = createOneTimeTokenBundleRecord('password_reset_tokens', (int) $user['id'], 3600);
    auditLog('auth.password.reset.requested', 'users', (int) $user['id'], ['email' => $email]);

    if (mailerConfig()['enabled']) {
        try {
            $bundle = passwordResetEmailBundle(
                (string) ($user['prenom'] ?? ''),
                (string) $user['email'],
                $reset['token'],
                $reset['pin']
            );
            smtpSendMail((string) $user['email'], '[TabibDZ] ' . $bundle['subject'], $bundle['text'], $bundle['html']);
        } catch (Throwable $mailError) {
            mailerLog('Forgot password email failed for ' . (string) $user['email'] . ': ' . $mailError->getMessage());
        }
    }

    if (appDebug()) {
        $response['debug_reset_token'] = $reset['token'];
        $response['debug_reset_pin'] = $reset['pin'];
    }
}

jsonResponse($response);
