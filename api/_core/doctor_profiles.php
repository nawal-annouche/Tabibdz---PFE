<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function doctorProfileOptionalColumns(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cfg = dbConfig();
    $dbName = (string) ($cfg['database'] ?? 'tabibdz');
    $colCheck = $pdo->prepare(
        'SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column
         LIMIT 1'
    );
    $columnExists = static function (string $table, string $column) use ($colCheck, $dbName): bool {
        $colCheck->execute([
            'schema' => $dbName,
            'table' => $table,
            'column' => $column,
        ]);
        return (bool) $colCheck->fetchColumn();
    };

    $cache = [
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
        'approved_at' => $columnExists('medecin_profiles', 'approved_at'),
        'review_note' => $columnExists('medecin_profiles', 'review_note'),
        'email_verified_at' => $columnExists('users', 'email_verified_at'),
    ];

    return $cache;
}

function doctorProfileBuildPayload(PDO $pdo, int $doctorId): array
{
    $optionalProfileColumns = doctorProfileOptionalColumns($pdo);

    $stmt = $pdo->prepare(
        "SELECT u.id, u.prenom, u.nom, u.email, u.telephone, u.photo_url, u.date_naissance, u.sexe,
                u.wilaya_id, u.commune, u.is_active,
                " . ($optionalProfileColumns['email_verified_at'] ? 'u.email_verified_at' : 'NULL') . " AS email_verified_at,
                w.nom AS wilaya_nom,
                mp.specialite_id, mp.tarif, mp.cnas, mp.casnos, mp.note, mp.avis_count, mp.adresse,
                " . ($optionalProfileColumns['langues'] ? 'mp.langues' : 'NULL') . " AS langues,
                " . ($optionalProfileColumns['secteur'] ? 'mp.secteur' : 'NULL') . " AS secteur,
                " . ($optionalProfileColumns['experience_years'] ? 'mp.experience_years' : 'NULL') . " AS experience_years,
                " . ($optionalProfileColumns['agrement_no'] ? 'mp.agrement_no' : 'NULL') . " AS agrement_no,
                " . ($optionalProfileColumns['bio'] ? 'mp.bio' : 'NULL') . " AS bio,
                " . ($optionalProfileColumns['formations_text'] ? 'mp.formations_text' : 'NULL') . " AS formations_text,
                " . ($optionalProfileColumns['hopitaux_text'] ? 'mp.hopitaux_text' : 'NULL') . " AS hopitaux_text,
                " . ($optionalProfileColumns['map_url'] ? 'mp.map_url' : 'NULL') . " AS map_url,
                " . ($optionalProfileColumns['mutuelle_private'] ? 'mp.mutuelle_private' : '0') . " AS mutuelle_private,
                " . ($optionalProfileColumns['approval_status'] ? 'mp.approval_status' : '"approved"') . " AS approval_status,
                " . ($optionalProfileColumns['submitted_at'] ? 'mp.submitted_at' : 'NULL') . " AS submitted_at,
                " . ($optionalProfileColumns['approved_at'] ? 'mp.approved_at' : 'NULL') . " AS approved_at,
                " . ($optionalProfileColumns['review_note'] ? 'mp.review_note' : 'NULL') . " AS review_note,
                s.nom AS specialite_nom, s.slug AS specialite_slug
         FROM users u
         LEFT JOIN wilayas w ON w.id = u.wilaya_id
         LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
         LEFT JOIN specialites s ON s.id = mp.specialite_id
         WHERE u.id = :id AND u.role = 'medecin'
         LIMIT 1"
    );
    $stmt->execute(['id' => $doctorId]);
    $row = firstOrNull($stmt);
    if (!$row) {
        throw new RuntimeException('Medecin introuvable');
    }

    $approvalStatus = (string) ($row['approval_status'] ?? 'approved');
    $submittedAt = $row['submitted_at'] ?? null;
    $row['workflow_status'] = $approvalStatus === 'pending' && empty($submittedAt) ? 'draft' : $approvalStatus;

    return $row;
}

function doctorProfileExtractWritablePayload(array $body, PDO $pdo): array
{
    $optional = doctorProfileOptionalColumns($pdo);

    $payload = [];
    $userFields = ['prenom', 'nom', 'telephone', 'email', 'wilaya_id', 'commune', 'photo_url'];
    $profileFields = ['specialite_id', 'tarif', 'cnas', 'casnos', 'adresse'];
    if ($optional['langues']) $profileFields[] = 'langues';
    if ($optional['secteur']) $profileFields[] = 'secteur';
    if ($optional['experience_years']) $profileFields[] = 'experience_years';
    if ($optional['agrement_no']) $profileFields[] = 'agrement_no';
    if ($optional['bio']) $profileFields[] = 'bio';
    if ($optional['formations_text']) $profileFields[] = 'formations_text';
    if ($optional['hopitaux_text']) $profileFields[] = 'hopitaux_text';
    if ($optional['map_url']) $profileFields[] = 'map_url';
    if ($optional['mutuelle_private']) $profileFields[] = 'mutuelle_private';

    foreach (array_merge($userFields, $profileFields) as $field) {
        if (array_key_exists($field, $body)) {
            $payload[$field] = $body[$field];
        }
    }

    return $payload;
}

function doctorProfileApplyPayload(PDO $pdo, int $doctorId, array $body): void
{
    $optional = doctorProfileOptionalColumns($pdo);

    $userFields = ['prenom', 'nom', 'telephone', 'email', 'wilaya_id', 'commune', 'photo_url'];
    $profileFields = ['specialite_id', 'tarif', 'cnas', 'casnos', 'adresse'];
    if ($optional['langues']) $profileFields[] = 'langues';
    if ($optional['secteur']) $profileFields[] = 'secteur';
    if ($optional['experience_years']) $profileFields[] = 'experience_years';
    if ($optional['agrement_no']) $profileFields[] = 'agrement_no';
    if ($optional['bio']) $profileFields[] = 'bio';
    if ($optional['formations_text']) $profileFields[] = 'formations_text';
    if ($optional['hopitaux_text']) $profileFields[] = 'hopitaux_text';
    if ($optional['map_url']) $profileFields[] = 'map_url';
    if ($optional['mutuelle_private']) $profileFields[] = 'mutuelle_private';

    $userSet = [];
    $userParams = ['id' => $doctorId];
    foreach ($userFields as $field) {
        if (array_key_exists($field, $body)) {
            $userSet[] = "{$field} = :{$field}";
            $userParams[$field] = $body[$field] === '' ? null : $body[$field];
        }
    }

    $profileSet = [];
    $profileParams = ['id' => $doctorId];
    foreach ($profileFields as $field) {
        if (array_key_exists($field, $body)) {
            $profileSet[] = "{$field} = :{$field}";
            $profileParams[$field] = $body[$field] === '' ? null : $body[$field];
        }
    }

    if (isset($userParams['email']) && $userParams['email'] !== null && !filter_var((string) $userParams['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email invalide');
    }

    $pdo->prepare('INSERT IGNORE INTO medecin_profiles (user_id, specialite_id, tarif, cnas, casnos, note, avis_count, adresse) VALUES (:id, 1, 0, 0, 0, 0, 0, NULL)')
        ->execute(['id' => $doctorId]);

    if ($userSet) {
        $sql = 'UPDATE users SET ' . implode(', ', $userSet) . ', updated_at = NOW() WHERE id = :id AND role = "medecin"';
        $pdo->prepare($sql)->execute($userParams);
    }

    if ($profileSet) {
        $sql = 'UPDATE medecin_profiles SET ' . implode(', ', $profileSet) . ' WHERE user_id = :id';
        $pdo->prepare($sql)->execute($profileParams);
    }
}
