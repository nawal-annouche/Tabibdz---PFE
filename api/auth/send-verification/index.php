<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/mailer.php';
require_once __DIR__ . '/../../_core/mail_templates.php';
assertMethod('POST');

$body = readJsonBody();
$requestedEmail = lowerUtf8((string) ($body['email'] ?? ''));
$authUser = currentAuthUser();

$email = $requestedEmail;
if ($email === '' && $authUser) {
    $email = lowerUtf8((string) ($authUser['email'] ?? ''));
}
if ($email === '') {
    jsonResponse(['error' => 'Email requis'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email invalide'], 422);
}

enforceRateLimit('auth.send_verification', clientIp() . '|' . $email, 5, 3600);

$checkStmt = db()->prepare('SELECT id, prenom, nom, email, email_verified_at FROM users WHERE email = :email LIMIT 1');
$checkStmt->execute(['email' => $email]);
$row = firstOrNull($checkStmt);
if (!$row) {
    jsonResponse(['error' => 'Utilisateur introuvable'], 404);
}
if (!empty($row['email_verified_at'])) {
    jsonResponse(['message' => 'Email deja verifie']);
}

$verification = createOneTimeTokenBundleRecord('email_verification_tokens', (int) $row['id'], 86400);
auditLog('auth.email.verification.sent', 'users', (int) $row['id']);

if (mailerConfig()['enabled']) {
    try {
        $bundle = verificationEmailBundle(
            (string) ($row['prenom'] ?? ''),
            (string) $row['email'],
            $verification['token'],
            $verification['pin']
        );
        smtpSendMail((string) $row['email'], '[TabibDZ] ' . $bundle['subject'], $bundle['text'], $bundle['html']);
    } catch (Throwable $mailError) {
        mailerLog('Send verification email failed for user #' . (int) $row['id'] . ': ' . $mailError->getMessage());
    }
}

$response = ['message' => 'Code de verification envoye'];
if (appDebug()) {
    $response['debug_verification_token'] = $verification['token'];
    $response['debug_verification_pin'] = $verification['pin'];
}

jsonResponse($response);
