<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$body = readJsonBody();
$email = lowerUtf8((string) ($body['email'] ?? ''));
$pin = preg_replace('/\D+/', '', (string) ($body['pin'] ?? '')) ?? '';
$password = (string) ($body['new_password'] ?? '');
$confirm = (string) ($body['confirm_password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email invalide'], 422);
}
if (strlen($pin) !== 6) {
    jsonResponse(['error' => 'Le code PIN doit contenir 6 chiffres'], 422);
}
if ($password === '') {
    jsonResponse(['error' => 'Nouveau mot de passe requis'], 422);
}
if ($password !== $confirm) {
    jsonResponse(['error' => 'Les mots de passe ne correspondent pas'], 422);
}
if (strlen($password) < 8) {
    jsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caracteres'], 422);
}

enforceRateLimit('auth.reset_password', clientIp() . '|' . $email, 10, 900);

$userStmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$userStmt->execute(['email' => $email]);
$user = firstOrNull($userStmt);
if (!$user) {
    jsonResponse(['error' => 'Code PIN invalide ou expire'], 400);
}

$row = consumeOneTimePin('password_reset_tokens', (int) $user['id'], $pin);
if (!$row) {
    jsonResponse(['error' => 'Code PIN invalide ou expire'], 400);
}

$userId = (int) $row['user_id'];
$newHash = password_hash($password, PASSWORD_DEFAULT);

db()->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id')
    ->execute(['hash' => $newHash, 'id' => $userId]);

revokeAllSessionsForUser($userId);
auditLog('auth.password.reset.completed', 'users', $userId);

jsonResponse(['message' => 'Mot de passe reinitialise avec succes']);
