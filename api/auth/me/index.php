<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('GET');

$user = requireAuth();
jsonResponse([
    'user' => [
        'id' => (int) $user['id'],
        'role' => $user['role'],
        'prenom' => $user['prenom'],
        'nom' => $user['nom'],
        'email' => $user['email'],
        'email_verified' => !empty($user['email_verified_at']),
        'is_active' => (int) ($user['is_active'] ?? 1) === 1,
    ],
]);
