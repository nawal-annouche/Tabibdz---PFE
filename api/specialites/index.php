<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
assertMethod('GET');

$featured = ($_GET['featured'] ?? 'false') === 'true';
$limit = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : 8;
$limit = max(1, min($limit, 100));

$sql = "SELECT s.id, s.nom, s.slug, s.icon_name,
               COUNT(mp.user_id) AS medecins_count
        FROM specialites s
        LEFT JOIN medecin_profiles mp ON mp.specialite_id = s.id";
$params = [];
if ($featured) {
    $sql .= ' WHERE s.featured = 1';
}
$sql .= ' GROUP BY s.id, s.nom, s.slug, s.icon_name ORDER BY s.nom LIMIT :lim';

$stmt = db()->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
jsonResponse($stmt->fetchAll());
