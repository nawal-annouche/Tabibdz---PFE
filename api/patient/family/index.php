<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

$pdo = db();
$patientId = currentPatientId();

function familyTableColumns(PDO $pdo): array
{
    static $columns = null;
    if (is_array($columns)) {
        return $columns;
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM patient_family_members')->fetchAll() as $column) {
        $name = (string) ($column['Field'] ?? '');
        if ($name !== '') {
            $columns[] = $name;
        }
    }

    return $columns;
}

function familyHasColumn(PDO $pdo, string $column): bool
{
    return in_array($column, familyTableColumns($pdo), true);
}

function familyRelationColumn(PDO $pdo): string
{
    return familyHasColumn($pdo, 'relation') ? 'relation' : 'lien_parent';
}

function familyRelationLabelFromRow(array $row): string
{
    if (!empty($row['relation'])) {
        return (string) $row['relation'];
    }

    $legacy = (string) ($row['lien_parent'] ?? '');
    return match ($legacy) {
        'enfant' => 'Enfant',
        'conjoint' => 'Conjoint',
        'mere' => 'Mere',
        'pere' => 'Pere',
        'frere' => 'Frere',
        'soeur' => 'Soeur',
        default => 'Autre',
    };
}

function familyRelationStorageValue(PDO $pdo, string $value): string
{
    $value = trim($value);
    if (familyRelationColumn($pdo) === 'relation') {
        return $value;
    }

    $normalized = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $normalized = str_replace(['é', 'è', 'ê', 'ë'], 'e', $normalized);
    $normalized = str_replace(['à', 'â'], 'a', $normalized);
    $normalized = str_replace(['î', 'ï'], 'i', $normalized);
    $normalized = str_replace(['ô', 'ö'], 'o', $normalized);
    $normalized = str_replace(['ù', 'û', 'ü'], 'u', $normalized);
    $normalized = trim($normalized);

    return match (true) {
        in_array($normalized, ['fils', 'fille', 'enfant', 'enfants'], true) => 'enfant',
        in_array($normalized, ['conjoint', 'conjointe', 'epoux', 'epouse', 'mari', 'femme'], true) => 'conjoint',
        in_array($normalized, ['mere', 'maman'], true) => 'mere',
        in_array($normalized, ['pere', 'papa'], true) => 'pere',
        in_array($normalized, ['frere'], true) => 'frere',
        in_array($normalized, ['soeur', 'soeur'], true) => 'soeur',
        default => 'autre',
    };
}

function familyMemberPayload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'prenom' => (string) $row['prenom'],
        'nom' => (string) $row['nom'],
        'relation' => familyRelationLabelFromRow($row),
        'display_name' => trim(((string) $row['prenom']) . ' ' . ((string) $row['nom'])),
        'date_naissance' => !empty($row['date_naissance']) ? date('Y-m-d', strtotime((string) $row['date_naissance'])) : null,
        'sexe' => ($row['sexe'] ?? null) ?: null,
        'telephone' => ($row['telephone'] ?? null) ?: null,
        'email' => ($row['email'] ?? null) ?: null,
        'notes_medicales' => ($row['notes_medicales'] ?? null) ?: null,
        'created_at' => !empty($row['created_at'] ?? null) ? date(DATE_ATOM, strtotime((string) $row['created_at'])) : null,
        'updated_at' => !empty($row['updated_at'] ?? null) ? date(DATE_ATOM, strtotime((string) $row['updated_at'])) : null,
    ];
}

function familyInsertStatement(PDO $pdo): PDOStatement
{
    $columns = ['patient_id', 'prenom', 'nom', familyRelationColumn($pdo), 'date_naissance', 'sexe', 'telephone'];
    if (familyHasColumn($pdo, 'email')) {
        $columns[] = 'email';
    }
    if (familyHasColumn($pdo, 'notes_medicales')) {
        $columns[] = 'notes_medicales';
    }

    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
    $sql = 'INSERT INTO patient_family_members (' . implode(', ', $columns) . ')
            VALUES (' . implode(', ', $placeholders) . ')';

    return $pdo->prepare($sql);
}

function fetchFamilyMember(PDO $pdo, int $patientId, int $memberId): ?array
{
    $stmt = $pdo->prepare('SELECT *
                           FROM patient_family_members
                           WHERE id = :id AND patient_id = :patient_id
                           LIMIT 1');
    $stmt->execute([
        'id' => $memberId,
        'patient_id' => $patientId,
    ]);

    return firstOrNull($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where = 'patient_id = :patient_id';
    if (familyHasColumn($pdo, 'archived_at')) {
        $where .= ' AND archived_at IS NULL';
    }

    $stmt = $pdo->prepare('SELECT *
                           FROM patient_family_members
                           WHERE ' . $where . '
                           ORDER BY updated_at DESC, created_at DESC, id DESC');
    $stmt->execute(['patient_id' => $patientId]);
    $rows = $stmt->fetchAll();

    jsonResponse(array_map('familyMemberPayload', $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = readJsonBody();

    $prenom = trim((string) ($body['prenom'] ?? ''));
    $nom = trim((string) ($body['nom'] ?? ''));
    $relation = trim((string) ($body['relation'] ?? ''));
    $dateNaissance = trim((string) ($body['date_naissance'] ?? ''));
    $sexe = trim((string) ($body['sexe'] ?? ''));
    $telephone = trim((string) ($body['telephone'] ?? ''));
    $email = trim((string) ($body['email'] ?? ''));
    $notesMedicales = trim((string) ($body['notes_medicales'] ?? ''));

    if ($prenom === '' || $nom === '' || $relation === '') {
        jsonResponse(['error' => 'Prenom, nom et relation sont requis'], 422);
    }

    if ($dateNaissance !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateNaissance)) {
        jsonResponse(['error' => 'Format de date invalide'], 422);
    }

    if ($sexe !== '' && !in_array($sexe, ['Homme', 'Femme'], true)) {
        jsonResponse(['error' => 'Valeur de sexe invalide'], 422);
    }

    $params = [
        'patient_id' => $patientId,
        'prenom' => $prenom,
        'nom' => $nom,
        familyRelationColumn($pdo) => familyRelationStorageValue($pdo, $relation),
        'date_naissance' => $dateNaissance !== '' ? $dateNaissance : null,
        'sexe' => $sexe !== '' ? $sexe : null,
        'telephone' => $telephone !== '' ? $telephone : null,
    ];
    if (familyHasColumn($pdo, 'email')) {
        $params['email'] = $email !== '' ? $email : null;
    }
    if (familyHasColumn($pdo, 'notes_medicales')) {
        $params['notes_medicales'] = $notesMedicales !== '' ? $notesMedicales : null;
    }

    $stmt = familyInsertStatement($pdo);
    $stmt->execute($params);

    $created = fetchFamilyMember($pdo, $patientId, (int) $pdo->lastInsertId());

    jsonResponse([
        'message' => 'Membre de la famille ajoute',
        'member' => $created ? familyMemberPayload($created) : null,
    ], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($memberId < 1) {
        jsonResponse(['error' => 'id requis'], 422);
    }

    if (!fetchFamilyMember($pdo, $patientId, $memberId)) {
        jsonResponse(['error' => 'Membre introuvable'], 404);
    }

    $body = readJsonBody();
    $allowedFields = ['prenom', 'nom', 'relation', 'date_naissance', 'sexe', 'telephone', 'email', 'notes_medicales'];
    $set = [];
    $params = [
        'id' => $memberId,
        'patient_id' => $patientId,
    ];

    foreach ($allowedFields as $field) {
        if (!array_key_exists($field, $body)) {
            continue;
        }

        $value = is_string($body[$field]) ? trim((string) $body[$field]) : $body[$field];
        if (in_array($field, ['prenom', 'nom', 'relation'], true) && $value === '') {
            jsonResponse(['error' => sprintf('Le champ %s est requis', $field)], 422);
        }
        if ($field === 'date_naissance' && $value !== '' && $value !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            jsonResponse(['error' => 'Format de date invalide'], 422);
        }
        if ($field === 'sexe' && $value !== '' && $value !== null && !in_array($value, ['Homme', 'Femme'], true)) {
            jsonResponse(['error' => 'Valeur de sexe invalide'], 422);
        }

        $fieldName = $field === 'relation' ? familyRelationColumn($pdo) : $field;
        if (!familyHasColumn($pdo, $fieldName)) {
            continue;
        }
        $set[] = $fieldName . ' = :' . $field;
        $params[$field] = $field === 'relation'
            ? familyRelationStorageValue($pdo, (string) $value)
            : ($value === '' ? null : $value);
    }

    if (!$set) {
        jsonResponse(['error' => 'Aucun champ a mettre a jour'], 400);
    }

    $sql = 'UPDATE patient_family_members
            SET ' . implode(', ', $set) . ', updated_at = NOW()
            WHERE id = :id AND patient_id = :patient_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updated = fetchFamilyMember($pdo, $patientId, $memberId);

    jsonResponse([
        'message' => 'Membre de la famille mis a jour',
        'member' => $updated ? familyMemberPayload($updated) : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $memberId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($memberId < 1) {
        jsonResponse(['error' => 'id requis'], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM patient_family_members WHERE id = :id AND patient_id = :patient_id');
    $stmt->execute([
        'id' => $memberId,
        'patient_id' => $patientId,
    ]);

    if ($stmt->rowCount() !== 1) {
        jsonResponse(['error' => 'Membre introuvable'], 404);
    }

    jsonResponse(['message' => 'Membre supprime']);
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
