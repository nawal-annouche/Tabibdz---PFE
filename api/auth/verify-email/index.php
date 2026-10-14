<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$body = readJsonBody();
$email = lowerUtf8((string) ($body['email'] ?? ''));
$pin = preg_replace('/\D+/', '', (string) ($body['pin'] ?? '')) ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email invalide'], 422);
}
if (strlen($pin) !== 6) {
    jsonResponse(['error' => 'Le code PIN doit contenir 6 chiffres'], 422);
}

enforceRateLimit('auth.verify_email', clientIp() . '|' . $email, 15, 3600);

$userStmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$userStmt->execute(['email' => $email]);
$user = firstOrNull($userStmt);
if (!$user) {
    jsonResponse(['error' => 'Code PIN invalide ou expire'], 400);
}

$row = consumeOneTimePin('email_verification_tokens', (int) $user['id'], $pin);
if (!$row) {
    jsonResponse(['error' => 'Code PIN invalide ou expire'], 400);
}

$userId = (int) $row['user_id'];
db()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = :id')
    ->execute(['id' => $userId]);

auditLog('auth.email.verified', 'users', $userId);
jsonResponse(['message' => 'Email verifie avec succes']);
