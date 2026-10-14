<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
$pdo = db();
$patientId = currentPatientId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT u.id, u.prenom, u.nom, u.photo_url, u.commune, w.nom AS wilaya,
                                  s.nom AS specialite, mp.note,
                                  (
                                    SELECT c.start_at FROM creneaux c
                                    WHERE c.medecin_id = u.id AND c.is_available = 1 AND c.start_at >= NOW()
                                    ORDER BY c.start_at LIMIT 1
                                  ) AS prochain_creneau
                           FROM patient_favoris f
                           INNER JOIN users u ON u.id = f.medecin_id
                           INNER JOIN medecin_profiles mp ON mp.user_id = u.id
                           INNER JOIN specialites s ON s.id = mp.specialite_id
                           LEFT JOIN wilayas w ON w.id = u.wilaya_id
                           WHERE f.patient_id = :pid
                           ORDER BY f.created_at DESC");
    $stmt->execute(['pid' => $patientId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['note'] = (float) $row['note'];
        $row['prochain_creneau'] = $row['prochain_creneau'] ? date(DATE_ATOM, strtotime((string) $row['prochain_creneau'])) : null;
    }
    unset($row);
    jsonResponse($rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = readJsonBody();
    $medecinId = isset($body['medecin_id']) ? (int) $body['medecin_id'] : 0;
    if ($medecinId < 1) {
        jsonResponse(['error' => 'medecin_id invalide'], 400);
    }
    $pdo->prepare('INSERT IGNORE INTO patient_favoris (patient_id, medecin_id) VALUES (:pid, :mid)')
        ->execute(['pid' => $patientId, 'mid' => $medecinId]);
    jsonResponse(['message' => 'Ajouté aux favoris']);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $medecinId = isset($_GET['medecin_id']) ? (int) $_GET['medecin_id'] : 0;
    if ($medecinId < 1) {
        jsonResponse(['error' => 'medecin_id invalide'], 400);
    }
    $pdo->prepare('DELETE FROM patient_favoris WHERE patient_id = :pid AND medecin_id = :mid')
        ->execute(['pid' => $patientId, 'mid' => $medecinId]);
    jsonResponse(['message' => 'Retiré des favoris']);
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
