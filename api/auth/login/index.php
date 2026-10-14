<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$body = readJsonBody();
$email = lowerUtf8((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse(['error' => 'Email et mot de passe requis'], 422);
}

enforceRateLimit('auth.login', clientIp() . '|' . $email, 10, 900);

$stmt = db()->prepare('SELECT id, role, prenom, nom, email, email_verified_at, is_active, password_hash FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = firstOrNull($stmt);

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    auditLog('auth.login.failed', 'users', null, ['email' => $email]);
    jsonResponse(['error' => 'Identifiants invalides'], 401);
}
if ((int) ($user['is_active'] ?? 1) !== 1) {
    jsonResponse(['error' => 'Compte desactive'], 403);
}
if (empty($user['email_verified_at'])) {
    auditLog('auth.login.blocked_unverified', 'users', (int) $user['id'], ['email' => $email]);
    jsonResponse([
        'error' => 'Verification email requise',
        'email_verification_required' => true,
        'email' => (string) $user['email'],
        'user' => [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'prenom' => $user['prenom'],
            'nom' => $user['nom'],
            'email' => $user['email'],
            'email_verified' => false,
        ],
    ], 403);
}

$tokenData = createAuthToken((int) $user['id'], (string) $user['role']);
setAuthCookie($tokenData['token'], (int) $tokenData['exp']);
auditLog('auth.login.success', 'users', (int) $user['id']);

jsonResponse([
    'message' => 'Connexion reussie',
    'token' => $tokenData['token'],
    'expires_at' => date(DATE_ATOM, (int) $tokenData['exp']),
    'user' => [
        'id' => (int) $user['id'],
        'role' => $user['role'],
        'prenom' => $user['prenom'],
        'nom' => $user['nom'],
        'email' => $user['email'],
        'email_verified' => true,
    ],
]);
