<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

assertMethod('GET');

$pdo = db();
$patientId = currentPatientId();

$allowedTypes = ['ordonnance', 'certificat', 'compte_rendu', 'document_medical'];
$typeFilter = trim((string) ($_GET['type'] ?? ''));
if ($typeFilter !== '' && $typeFilter !== 'tous' && !in_array($typeFilter, $allowedTypes, true)) {
    jsonResponse(['error' => 'Type de document invalide'], 422);
}

$limit = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = max(1, min($limit, 200));

$params = ['pid' => $patientId];
$where = [
    'a.patient_id = :pid',
    "d.uploaded_by_role = 'medecin'",
    "d.document_type IN ('ordonnance', 'certificat', 'compte_rendu', 'document_medical')",
];

if ($typeFilter !== '' && $typeFilter !== 'tous') {
    $where[] = 'd.document_type = :document_type';
    $params['document_type'] = $typeFilter;
}

$sql = 'SELECT d.id, d.original_name, d.file_url, d.mime_type, d.file_size, d.document_type, d.created_at,
               a.id AS appointment_id, a.date_heure, a.statut AS appointment_statut, a.consultation_mode, a.confirmation_code,
               u.id AS medecin_id, u.prenom AS medecin_prenom, u.nom AS medecin_nom, u.photo_url AS medecin_photo,
               u.commune AS medecin_commune, w.nom AS medecin_wilaya, mp.adresse AS medecin_adresse, s.nom AS specialite_nom
        FROM appointment_documents d
        INNER JOIN appointments a ON a.id = d.appointment_id
        INNER JOIN users u ON u.id = a.medecin_id
        LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
        LEFT JOIN specialites s ON s.id = mp.specialite_id
        LEFT JOIN wilayas w ON w.id = u.wilaya_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY d.created_at DESC, d.id DESC
        LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$summaryStmt = $pdo->prepare(
    "SELECT d.document_type, COUNT(*) AS total
     FROM appointment_documents d
     INNER JOIN appointments a ON a.id = d.appointment_id
     WHERE a.patient_id = :pid
       AND d.uploaded_by_role = 'medecin'
       AND d.document_type IN ('ordonnance', 'certificat', 'compte_rendu', 'document_medical')
     GROUP BY d.document_type"
);
$summaryStmt->execute(['pid' => $patientId]);
$summaryRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$summary = [
    'all' => 0,
    'ordonnance' => 0,
    'certificat' => 0,
    'compte_rendu' => 0,
    'document_medical' => 0,
];
foreach ($summaryRows as $row) {
    $documentType = (string) ($row['document_type'] ?? '');
    $count = (int) ($row['total'] ?? 0);
    if (array_key_exists($documentType, $summary)) {
        $summary[$documentType] = $count;
        $summary['all'] += $count;
    }
}

$items = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'original_name' => (string) $row['original_name'],
        'file_url' => (string) $row['file_url'],
        'mime_type' => (string) ($row['mime_type'] ?? ''),
        'file_size' => (int) ($row['file_size'] ?? 0),
        'document_type' => (string) ($row['document_type'] ?? 'document_medical'),
        'created_at' => !empty($row['created_at']) ? date(DATE_ATOM, strtotime((string) $row['created_at'])) : null,
        'appointment' => [
            'id' => (int) $row['appointment_id'],
            'date_heure' => !empty($row['date_heure']) ? date(DATE_ATOM, strtotime((string) $row['date_heure'])) : null,
            'statut' => (string) ($row['appointment_statut'] ?? ''),
            'consultation_mode' => (string) ($row['consultation_mode'] ?? 'cabinet'),
            'confirmation_code' => (string) ($row['confirmation_code'] ?? ''),
        ],
        'medecin' => [
            'id' => (int) $row['medecin_id'],
            'prenom' => (string) ($row['medecin_prenom'] ?? ''),
            'nom' => (string) ($row['medecin_nom'] ?? ''),
            'photo_url' => (string) ($row['medecin_photo'] ?? ''),
            'commune' => (string) ($row['medecin_commune'] ?? ''),
            'wilaya' => (string) ($row['medecin_wilaya'] ?? ''),
            'adresse' => (string) ($row['medecin_adresse'] ?? ''),
            'specialite' => (string) ($row['specialite_nom'] ?? ''),
        ],
    ];
}, $rows);

jsonResponse([
    'summary' => $summary,
    'items' => $items,
]);
