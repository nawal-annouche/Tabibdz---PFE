<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

/**
 * Backward compatibility:
 * - Legacy callers still receive an array (default)
 * - Search page asks with `with_meta=1` to get {data, meta}
 */
$withMeta = ($_GET['with_meta'] ?? '0') === '1';

$cfg = dbConfig();
$dbName = (string) ($cfg['database'] ?? 'tabibdz');
$pdo = db();

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

$hasLangues = $columnExists('medecin_profiles', 'langues');
$hasSecteur = $columnExists('medecin_profiles', 'secteur');

$legacyLimit = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : null;
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) && ctype_digit((string) $_GET['per_page']) ? (int) $_GET['per_page'] : 12;
$page = max(1, $page);
$perPage = max(1, min($perPage, 50));

if ($legacyLimit !== null) {
    $perPage = max(1, min($legacyLimit, 100));
    $page = 1;
}

$offset = ($page - 1) * $perPage;

$wilayaId = isset($_GET['wilaya_id']) && ctype_digit((string) $_GET['wilaya_id']) ? (int) $_GET['wilaya_id'] : null;
$wilaya = trim((string) ($_GET['wilaya'] ?? ''));
$commune = trim((string) ($_GET['commune'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$specialite = trim((string) ($_GET['specialite'] ?? ''));
$tarifMax = isset($_GET['tarif_max']) && is_numeric((string) $_GET['tarif_max']) ? (float) $_GET['tarif_max'] : null;
$cnas = ($_GET['cnas'] ?? '') === '1';
$casnos = ($_GET['casnos'] ?? '') === '1';
$dispo = strtolower(trim((string) ($_GET['dispo'] ?? '')));
$secteur = strtolower(trim((string) ($_GET['secteur'] ?? '')));
$lang = trim((string) ($_GET['lang'] ?? ''));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'pertinence')));

$where = ['u.role = "medecin"', 'COALESCE(mp.approval_status, "approved") = "approved"'];
$params = [];

if ($wilayaId !== null) {
    $where[] = 'u.wilaya_id = :wilaya_id';
    $params['wilaya_id'] = $wilayaId;
}

if ($wilaya !== '') {
    $where[] = 'LOWER(COALESCE(w.nom, "")) = :wilaya';
    $params['wilaya'] = strtolower($wilaya);
}

if ($commune !== '') {
    $where[] = 'LOWER(COALESCE(u.commune, "")) LIKE :commune';
    $params['commune'] = '%' . strtolower($commune) . '%';
}

if ($q !== '') {
    $where[] = '(
      LOWER(CONCAT_WS(" ", COALESCE(u.prenom, ""), COALESCE(u.nom, ""))) LIKE :q1
      OR LOWER(COALESCE(s.nom, "")) LIKE :q2
      OR LOWER(COALESCE(s.slug, "")) LIKE :q3
      OR LOWER(COALESCE(w.nom, "")) LIKE :q4
      OR LOWER(COALESCE(u.commune, "")) LIKE :q5
    )';
    $qLike = '%' . strtolower($q) . '%';
    $params['q1'] = $qLike;
    $params['q2'] = $qLike;
    $params['q3'] = $qLike;
    $params['q4'] = $qLike;
    $params['q5'] = $qLike;
}

if ($specialite !== '') {
    $where[] = '(LOWER(COALESCE(s.nom, "")) LIKE :specialite_nom OR LOWER(COALESCE(s.slug, "")) LIKE :specialite_slug)';
    $sLike = '%' . strtolower($specialite) . '%';
    $params['specialite_nom'] = $sLike;
    $params['specialite_slug'] = $sLike;
}

if ($tarifMax !== null) {
    $where[] = 'mp.tarif <= :tarif_max';
    $params['tarif_max'] = $tarifMax;
}

if ($cnas) {
    $where[] = 'mp.cnas = 1';
}

if ($casnos) {
    $where[] = 'mp.casnos = 1';
}

if ($dispo === 'today') {
    $where[] = 'EXISTS (
      SELECT 1
      FROM creneaux c2
      WHERE c2.medecin_id = u.id
        AND c2.is_available = 1
        AND c2.start_at >= NOW()
        AND c2.start_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    )';
} elseif ($dispo === 'week') {
    $where[] = 'EXISTS (
      SELECT 1
      FROM creneaux c2
      WHERE c2.medecin_id = u.id
        AND c2.is_available = 1
        AND c2.start_at >= NOW()
        AND c2.start_at < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    )';
}

if ($hasSecteur && in_array($secteur, ['public', 'prive', 'mixte'], true)) {
    $where[] = 'mp.secteur = :secteur';
    $params['secteur'] = $secteur;
}

if ($hasLangues && $lang !== '') {
    $langs = array_values(array_filter(array_map('trim', explode(',', $lang)), static fn (string $v): bool => $v !== ''));
    if ($langs) {
        $langClauses = [];
        foreach ($langs as $i => $oneLang) {
            $k = 'lang_' . $i;
            $langClauses[] = 'LOWER(COALESCE(mp.langues, "")) LIKE :' . $k;
            $params[$k] = '%' . strtolower($oneLang) . '%';
        }
        $where[] = '(' . implode(' OR ', $langClauses) . ')';
    }
}

$baseFrom = '
  FROM users u
  INNER JOIN medecin_profiles mp ON mp.user_id = u.id
  INNER JOIN specialites s ON s.id = mp.specialite_id
  LEFT JOIN wilayas w ON w.id = u.wilaya_id
  LEFT JOIN (
    SELECT medecin_id, COUNT(*) AS total_reviews, ROUND(AVG(rating), 2) AS average_rating
    FROM medecin_reviews
    GROUP BY medecin_id
  ) rr ON rr.medecin_id = u.id
';
$whereSql = ' WHERE ' . implode(' AND ', $where);

$orderSql = ' ORDER BY note DESC, avis_count DESC ';
if ($sort === 'note') {
    $orderSql = ' ORDER BY note DESC, avis_count DESC ';
} elseif ($sort === 'disponibilite') {
    $orderSql = ' ORDER BY (prochain_creneau IS NULL) ASC, prochain_creneau ASC, note DESC ';
} elseif ($sort === 'tarif_asc') {
    $orderSql = ' ORDER BY mp.tarif ASC, note DESC ';
}

$countSql = 'SELECT COUNT(*) ' . $baseFrom . $whereSql;
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v);
}
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$selectCols = '
  SELECT u.id, u.prenom, u.nom, u.photo_url, u.commune,
         w.nom AS wilaya,
         mp.tarif,
         COALESCE(rr.average_rating, mp.note, 0) AS note,
         COALESCE(rr.total_reviews, mp.avis_count, 0) AS avis_count,
         mp.cnas, mp.casnos,
         ' . ($hasLangues ? 'mp.langues' : 'NULL') . ' AS langues,
         ' . ($hasSecteur ? 'mp.secteur' : 'NULL') . ' AS secteur,
         s.id AS specialite_id, s.nom AS specialite_nom, s.slug AS specialite_slug,
         (
           SELECT c.start_at
           FROM creneaux c
           WHERE c.medecin_id = u.id AND c.is_available = 1 AND c.start_at >= NOW()
           ORDER BY c.start_at ASC
           LIMIT 1
         ) AS prochain_creneau
';

$dataSql = $selectCols . $baseFrom . $whereSql . $orderSql . ' LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();
$payload = array_map(static function (array $row): array {
    $rawLangues = trim((string) ($row['langues'] ?? ''));
    $langues = [];
    if ($rawLangues !== '') {
        $langues = array_values(array_filter(array_map('trim', explode(',', $rawLangues))));
    }
    if (!$langues) {
        $langues = ['Arabe', 'Francais'];
    }

    return [
        'id' => (int) $row['id'],
        'prenom' => $row['prenom'],
        'nom' => $row['nom'],
        'photo_url' => $row['photo_url'],
        'wilaya' => $row['wilaya'] ?? '',
        'commune' => $row['commune'] ?? '',
        'tarif' => (float) $row['tarif'],
        'note' => (float) $row['note'],
        'avis_count' => (int) $row['avis_count'],
        'cnas' => (bool) $row['cnas'],
        'casnos' => (bool) $row['casnos'],
        'langues' => $langues,
        'secteur' => $row['secteur'] ?: 'prive',
        'prochain_creneau' => $row['prochain_creneau'] ? date(DATE_ATOM, strtotime((string) $row['prochain_creneau'])) : null,
        'specialite' => [
            'id' => (int) $row['specialite_id'],
            'nom' => $row['specialite_nom'],
            'slug' => $row['specialite_slug'],
        ],
    ];
}, $rows);

if ($withMeta) {
    jsonResponse([
        'data' => $payload,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ],
    ]);
}

jsonResponse($payload);
