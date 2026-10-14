<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$user = requireAuth();
revokeSessionByJti((string) $user['jti']);
$tokenData = createAuthToken((int) $user['id'], (string) $user['role']);
setAuthCookie($tokenData['token'], (int) $tokenData['exp']);
auditLog('auth.refresh', 'users', (int) $user['id']);

jsonResponse([
    'message' => 'Session renouvelée',
    'token' => $tokenData['token'],
    'expires_at' => date(DATE_ATOM, (int) $tokenData['exp']),
]);
