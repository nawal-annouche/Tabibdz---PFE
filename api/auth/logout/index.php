<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$user = requireAuth();
revokeSessionByJti((string) $user['jti']);
clearAuthCookie();
auditLog('auth.logout', 'users', (int) $user['id']);

jsonResponse(['message' => 'Déconnexion réussie']);
