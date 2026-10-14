<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/mailer.php';
require_once __DIR__ . '/../../_core/mail_templates.php';
assertMethod('POST');

$body = readJsonBody();
$role = (string) ($body['role'] ?? 'patient');
$prenom = trim((string) ($body['prenom'] ?? ''));
$nom = trim((string) ($body['nom'] ?? ''));
$email = lowerUtf8((string) ($body['email'] ?? ''));
$telephone = trim((string) ($body['telephone'] ?? ''));
$password = (string) ($body['password'] ?? '');

if (!in_array($role, ['patient', 'medecin'], true)) {
    jsonResponse(['error' => 'Role invalide'], 422);
}
if ($prenom === '' || $nom === '' || $email === '' || $password === '') {
    jsonResponse(['error' => 'Champs requis manquants'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Email invalide'], 422);
}
if (strlen($password) < 8) {
    jsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caracteres'], 422);
}

enforceRateLimit('auth.register', clientIp() . '|' . $email, 6, 3600);

$pdo = db();
$cfg = dbConfig();
$dbName = (string) ($cfg['database'] ?? 'tabibdz');
$colCheckStmt = $pdo->prepare(
    'SELECT 1
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column
     LIMIT 1'
);
$columnExists = static function (string $table, string $column) use ($colCheckStmt, $dbName): bool {
    $colCheckStmt->execute([
        'schema' => $dbName,
        'table' => $table,
        'column' => $column,
    ]);
    return (bool) $colCheckStmt->fetchColumn();
};
$optionalProfileColumns = [
    'langues' => $columnExists('medecin_profiles', 'langues'),
    'secteur' => $columnExists('medecin_profiles', 'secteur'),
    'experience_years' => $columnExists('medecin_profiles', 'experience_years'),
    'agrement_no' => $columnExists('medecin_profiles', 'agrement_no'),
    'bio' => $columnExists('medecin_profiles', 'bio'),
    'formations_text' => $columnExists('medecin_profiles', 'formations_text'),
    'hopitaux_text' => $columnExists('medecin_profiles', 'hopitaux_text'),
    'map_url' => $columnExists('medecin_profiles', 'map_url'),
    'mutuelle_private' => $columnExists('medecin_profiles', 'mutuelle_private'),
    'approval_status' => $columnExists('medecin_profiles', 'approval_status'),
    'submitted_at' => $columnExists('medecin_profiles', 'submitted_at'),
];
$exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$exists->execute(['email' => $email]);
if ($exists->fetch()) {
    auditLog('auth.register.conflict', 'users', null, ['email' => $email]);
    jsonResponse(['error' => 'Cet email est deja utilise'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO users (role, prenom, nom, email, telephone, password_hash, photo_url, wilaya_id, commune) VALUES (:role, :prenom, :nom, :email, :telephone, :hash, :photo_url, :wilaya_id, :commune)');
    $stmt->execute([
        'role' => $role,
        'prenom' => $prenom,
        'nom' => $nom,
        'email' => $email,
        'telephone' => ($telephone === '' ? null : $telephone),
        'hash' => $hash,
        'photo_url' => (($body['photo_url'] ?? '') === '' ? null : (string) $body['photo_url']),
        'wilaya_id' => isset($body['wilaya_id']) && $body['wilaya_id'] !== '' ? (int) $body['wilaya_id'] : null,
        'commune' => (($body['commune'] ?? '') === '' ? null : (string) $body['commune']),
    ]);

    $userId = (int) $pdo->lastInsertId();

    if ($role === 'patient') {
        $pdo->prepare('INSERT INTO notification_preferences (patient_id) VALUES (:id)')->execute(['id' => $userId]);
    } elseif ($role === 'medecin') {
        $profileFields = ['user_id', 'specialite_id', 'tarif', 'cnas', 'casnos', 'note', 'avis_count', 'adresse'];
        $profilePlaceholders = [':user_id', ':specialite_id', ':tarif', ':cnas', ':casnos', ':note', ':avis_count', ':adresse'];
        $profileParams = [
            'user_id' => $userId,
            'specialite_id' => isset($body['specialite_id']) && $body['specialite_id'] !== '' ? (int) $body['specialite_id'] : 1,
            'tarif' => isset($body['tarif']) && $body['tarif'] !== '' ? (float) $body['tarif'] : 0,
            'cnas' => !empty($body['cnas']) ? 1 : 0,
            'casnos' => !empty($body['casnos']) ? 1 : 0,
            'note' => 0,
            'avis_count' => 0,
            'adresse' => (($body['adresse'] ?? '') === '' ? null : (string) $body['adresse']),
        ];
        $optionalValues = [
            'langues' => (($body['langues'] ?? '') === '' ? 'Arabe,Francais' : (string) $body['langues']),
            'secteur' => (($body['secteur'] ?? '') === '' ? 'prive' : (string) $body['secteur']),
            'experience_years' => isset($body['experience_years']) && $body['experience_years'] !== '' ? (int) $body['experience_years'] : null,
            'agrement_no' => (($body['agrement_no'] ?? '') === '' ? null : (string) $body['agrement_no']),
            'bio' => (($body['bio'] ?? '') === '' ? null : (string) $body['bio']),
            'formations_text' => (($body['formations_text'] ?? '') === '' ? null : (string) $body['formations_text']),
            'hopitaux_text' => (($body['hopitaux_text'] ?? '') === '' ? null : (string) $body['hopitaux_text']),
            'map_url' => (($body['map_url'] ?? '') === '' ? null : (string) $body['map_url']),
            'mutuelle_private' => !empty($body['mutuelle_private']) ? 1 : 0,
            'approval_status' => 'pending',
            'submitted_at' => null,
        ];
        foreach ($optionalValues as $column => $value) {
            if (!$optionalProfileColumns[$column]) {
                continue;
            }
            $profileFields[] = $column;
            $profilePlaceholders[] = ':' . $column;
            $profileParams[$column] = $value;
        }

        $profileSql = sprintf(
            'INSERT INTO medecin_profiles (%s) VALUES (%s)',
            implode(', ', $profileFields),
            implode(', ', $profilePlaceholders)
        );
        $pdo->prepare($profileSql)->execute($profileParams);
    }

    $verification = createOneTimeTokenBundleRecord('email_verification_tokens', $userId, 86400);

    $pdo->commit();
    auditLog('auth.register.success', 'users', $userId, ['role' => $role, 'email' => $email]);

    if (mailerConfig()['enabled']) {
        try {
            $bundle = verificationEmailBundle($prenom, $email, $verification['token'], $verification['pin']);
            smtpSendMail($email, '[TabibDZ] ' . $bundle['subject'], $bundle['text'], $bundle['html']);
        } catch (Throwable $mailError) {
            mailerLog('Register verification email failed for ' . $email . ': ' . $mailError->getMessage());
        }
    }

    jsonResponse([
        'message' => 'Compte cree. Verification email requise',
        'email_verification_required' => true,
        'user' => [
            'id' => $userId,
            'role' => $role,
            'prenom' => $prenom,
            'nom' => $nom,
            'email' => $email,
            'email_verified' => false,
        ],
        'debug_verification_token' => appDebug() ? $verification['token'] : null,
        'debug_verification_pin' => appDebug() ? $verification['pin'] : null,
    ], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    auditLog('auth.register.failed', 'users', null, ['email' => $email, 'error' => $e->getMessage()]);
    jsonResponse(['error' => 'Inscription impossible', 'details' => $e->getMessage()], 500);
}
