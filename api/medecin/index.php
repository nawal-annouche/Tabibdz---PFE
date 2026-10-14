<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

$cfg = dbConfig();
$dbName = (string) ($cfg['database'] ?? 'tabibdz');
$pdo = db();

$colCheck = $pdo->prepare(
    'SELECT 1
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column
     LIMIT 1'
);
$exists = static function (string $table, string $column) use ($colCheck, $dbName): bool {
    $colCheck->execute([
        'schema' => $dbName,
        'table' => $table,
        'column' => $column,
    ]);
    return (bool) $colCheck->fetchColumn();
};

$hasLangues = $exists('medecin_profiles', 'langues');
$hasSecteur = $exists('medecin_profiles', 'secteur');
$hasExperienceYears = $exists('medecin_profiles', 'experience_years');
$hasAgrementNo = $exists('medecin_profiles', 'agrement_no');
$hasBio = $exists('medecin_profiles', 'bio');
$hasFormationsText = $exists('medecin_profiles', 'formations_text');
$hasHopitauxText = $exists('medecin_profiles', 'hopitaux_text');
$hasMapUrl = $exists('medecin_profiles', 'map_url');
$hasMutuellePrivate = $exists('medecin_profiles', 'mutuelle_private');

$stmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.email, u.telephone, u.photo_url, u.wilaya_id, u.commune, w.nom AS wilaya,
            mp.tarif,
            COALESCE(rr.average_rating, mp.note, 0) AS note,
            COALESCE(rr.total_reviews, mp.avis_count, 0) AS avis_count,
            mp.cnas, mp.casnos, mp.adresse,
            " . ($hasLangues ? 'mp.langues' : 'NULL') . " AS langues,
            " . ($hasSecteur ? 'mp.secteur' : 'NULL') . " AS secteur,
            " . ($hasExperienceYears ? 'mp.experience_years' : 'NULL') . " AS experience_years,
            " . ($hasAgrementNo ? 'mp.agrement_no' : 'NULL') . " AS agrement_no,
            " . ($hasBio ? 'mp.bio' : 'NULL') . " AS bio,
            " . ($hasFormationsText ? 'mp.formations_text' : 'NULL') . " AS formations_text,
            " . ($hasHopitauxText ? 'mp.hopitaux_text' : 'NULL') . " AS hopitaux_text,
            " . ($hasMapUrl ? 'mp.map_url' : 'NULL') . " AS map_url,
            " . ($hasMutuellePrivate ? 'mp.mutuelle_private' : '0') . " AS mutuelle_private,
            s.nom AS specialite, s.slug AS specialite_slug
     FROM users u
     INNER JOIN medecin_profiles mp ON mp.user_id = u.id
     INNER JOIN specialites s ON s.id = mp.specialite_id
     LEFT JOIN wilayas w ON w.id = u.wilaya_id
     LEFT JOIN (
        SELECT medecin_id, COUNT(*) AS total_reviews, ROUND(AVG(rating), 2) AS average_rating
        FROM medecin_reviews
        GROUP BY medecin_id
     ) rr ON rr.medecin_id = u.id
     WHERE u.id = :id AND u.role = 'medecin' AND COALESCE(mp.approval_status, 'approved') = 'approved'"
);
$stmt->execute(['id' => $id]);
$doc = firstOrNull($stmt);
if (!$doc) {
    jsonResponse(['error' => 'Medecin introuvable'], 404);
}

jsonResponse($doc);
