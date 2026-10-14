<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

$doctor = requireAuth('medecin');
$pdo = db();

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
    'approved_at' => $columnExists('medecin_profiles', 'approved_at'),
    'review_note' => $columnExists('medecin_profiles', 'review_note'),
];

$buildDoctorPayload = static function (int $doctorId) use ($pdo, $optionalProfileColumns): array {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.prenom, u.nom, u.email, u.telephone, u.photo_url, u.date_naissance, u.sexe,
                u.wilaya_id, u.commune, u.is_active, w.nom AS wilaya_nom,
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
        jsonResponse(['error' => 'Medecin introuvable'], 404);
    }
    $approvalStatus = (string) ($row['approval_status'] ?? 'approved');
    $submittedAt = $row['submitted_at'] ?? null;
    $row['workflow_status'] = $approvalStatus === 'pending' && empty($submittedAt) ? 'draft' : $approvalStatus;
    return $row;
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse($buildDoctorPayload((int) $doctor['id']));
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    if (!$body) {
        jsonResponse(['error' => 'Body JSON vide'], 400);
    }

    $doctorId = (int) $doctor['id'];
    $submitForReview = !empty($body['submit_for_review']);
    $revertToDraft = !empty($body['revert_to_draft']);

    $userFields = ['prenom', 'nom', 'telephone', 'email', 'wilaya_id', 'commune', 'photo_url'];
    $profileFields = ['specialite_id', 'tarif', 'cnas', 'casnos', 'adresse'];
    if ($optionalProfileColumns['langues']) $profileFields[] = 'langues';
    if ($optionalProfileColumns['secteur']) $profileFields[] = 'secteur';
    if ($optionalProfileColumns['experience_years']) $profileFields[] = 'experience_years';
    if ($optionalProfileColumns['agrement_no']) $profileFields[] = 'agrement_no';
    if ($optionalProfileColumns['bio']) $profileFields[] = 'bio';
    if ($optionalProfileColumns['formations_text']) $profileFields[] = 'formations_text';
    if ($optionalProfileColumns['hopitaux_text']) $profileFields[] = 'hopitaux_text';
    if ($optionalProfileColumns['map_url']) $profileFields[] = 'map_url';
    if ($optionalProfileColumns['mutuelle_private']) $profileFields[] = 'mutuelle_private';

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
        jsonResponse(['error' => 'Email invalide'], 422);
    }

    if ($submitForReview) {
        $required = [
            'prenom' => 'Le prenom est requis.',
            'nom' => 'Le nom est requis.',
            'email' => 'L email est requis.',
            'telephone' => 'Le telephone est requis.',
            'specialite_id' => 'Choisissez une specialite.',
            'adresse' => 'L adresse du cabinet est requise.',
            'agrement_no' => 'Le numero d agrement est requis.',
            'bio' => 'La bio medicale est requise avant envoi.',
        ];
        foreach ($required as $field => $message) {
            $value = $body[$field] ?? null;
            if ($value === null || trim((string) $value) === '') {
                jsonResponse(['error' => $message], 422);
            }
        }
    }

    $pdo->beginTransaction();
    try {
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

        if ($submitForReview) {
            $pdo->prepare(
                'UPDATE medecin_profiles
                 SET approval_status = "pending",
                     submitted_at = NOW(),
                     approved_at = NULL,
                     review_note = NULL
                 WHERE user_id = :id'
            )->execute(['id' => $doctorId]);
        } elseif ($revertToDraft) {
            $pdo->prepare(
                'UPDATE medecin_profiles
                 SET approval_status = "pending",
                     submitted_at = NULL,
                     approved_at = NULL
                 WHERE user_id = :id'
            )->execute(['id' => $doctorId]);
        }

        $pdo->commit();
        auditLog(
            $submitForReview ? 'medecin.profile.submit' : ($revertToDraft ? 'medecin.profile.revert_to_draft' : 'medecin.profile.update'),
            'users',
            $doctorId,
            ['fields' => array_keys($body)]
        );
        jsonResponse([
            'message' => $submitForReview ? 'Dossier envoye pour validation' : ($revertToDraft ? 'Dossier repasse en brouillon' : 'Profil medecin mis a jour'),
            'doctor' => $buildDoctorPayload($doctorId),
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Mise a jour impossible', 'details' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
