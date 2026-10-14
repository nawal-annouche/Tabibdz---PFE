<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/config.php';
require_once __DIR__ . '/../_core/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (!maintenanceAccessAllowed()) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$email = trim((string) ($_GET['email'] ?? 'admin@tabibdz.local'));
$password = (string) ($_GET['password'] ?? '');

if ($password === '' || strlen($password) < 8) {
    http_response_code(400);
    echo "Usage: ?email=admin@tabibdz.local&password=VotreMotDePasse(>=8)\n";
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = db()->prepare('UPDATE users SET role = "admin", password_hash = :hash, email_verified_at = NOW(), is_active = 1 WHERE email = :email');
$stmt->execute(['hash' => $hash, 'email' => $email]);

if ($stmt->rowCount() === 0) {
    $ins = db()->prepare('INSERT INTO users (role, prenom, nom, email, password_hash, email_verified_at, is_active) VALUES ("admin","Admin","TabibDZ",:email,:hash,NOW(),1)');
    $ins->execute(['email' => $email, 'hash' => $hash]);
    echo "Admin créé et mot de passe défini.\n";
    exit;
}

echo "Mot de passe admin mis à jour.\n";
