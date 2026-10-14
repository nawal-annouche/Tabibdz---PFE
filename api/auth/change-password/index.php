<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('POST');

$authUser = requireAuth();
$userId = (int) $authUser['id'];
$pdo = db();
$body = readJsonBody();

$ancien = (string) ($body['ancien_mdp'] ?? '');
$nouveau = (string) ($body['nouveau_mdp'] ?? '');
$confirm = (string) ($body['confirm_mdp'] ?? '');

if ($nouveau !== $confirm) {
    jsonResponse(['error' => 'Les mots de passe ne correspondent pas'], 422);
}
if (strlen($nouveau) < 8) {
    jsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], 422);
}

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = firstOrNull($stmt);
if (!$user) {
    jsonResponse(['error' => 'Utilisateur introuvable'], 404);
}

if (!password_verify($ancien, (string) $user['password_hash'])) {
    jsonResponse(['error' => 'Ancien mot de passe incorrect'], 401);
}

$newHash = password_hash($nouveau, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id')
    ->execute(['hash' => $newHash, 'id' => $userId]);

auditLog('auth.password.changed', 'users', $userId);
jsonResponse(['message' => 'Mot de passe modifié']);
