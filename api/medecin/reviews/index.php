<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    jsonResponse(['error' => 'Methode non autorisee'], 405);
}

$medecinId = 0;
if ($method === 'GET') {
    $medecinId = isset($_GET['medecin_id']) ? (int) $_GET['medecin_id'] : 0;
} else {
    $body = readJsonBody();
    $medecinId = isset($body['medecin_id']) ? (int) $body['medecin_id'] : 0;
}

if ($medecinId < 1) {
    jsonResponse(['error' => 'medecin_id invalide'], 400);
}

$pdo = db();
$existsStmt = $pdo->prepare('SELECT 1 FROM users WHERE id = :id AND role = "medecin" LIMIT 1');
$existsStmt->execute(['id' => $medecinId]);
if (!$existsStmt->fetchColumn()) {
    jsonResponse(['error' => 'Medecin introuvable'], 404);
}

$recomputeStats = static function (PDO $pdo, int $doctorId): void {
    $statsStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS average_rating
         FROM medecin_reviews
         WHERE medecin_id = :medecin_id'
    );
    $statsStmt->execute(['medecin_id' => $doctorId]);
    $stats = $statsStmt->fetch() ?: ['total_reviews' => 0, 'average_rating' => 0];

    $updateStmt = $pdo->prepare(
        'UPDATE medecin_profiles
         SET avis_count = :avis_count, note = :note
         WHERE user_id = :doctor_id'
    );
    $updateStmt->execute([
        'avis_count' => (int) ($stats['total_reviews'] ?? 0),
        'note' => round((float) ($stats['average_rating'] ?? 0), 2),
        'doctor_id' => $doctorId,
    ]);
};

if ($method === 'POST') {
    $patient = requireAuth('patient');
    $body = readJsonBody();
    $rating = isset($body['rating']) ? (int) $body['rating'] : 0;
    $comment = trim((string) ($body['comment'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        jsonResponse(['error' => 'La note doit etre comprise entre 1 et 5'], 422);
    }
    if ($comment === '' || mb_strlen($comment, 'UTF-8') < 8) {
        jsonResponse(['error' => 'Ajoutez un avis plus detaille'], 422);
    }

    $eligibilityStmt = $pdo->prepare(
        'SELECT 1
         FROM appointments
         WHERE medecin_id = :medecin_id AND patient_id = :patient_id AND statut = "termine"
         LIMIT 1'
    );
    $eligibilityStmt->execute([
        'medecin_id' => $medecinId,
        'patient_id' => (int) $patient['id'],
    ]);
    if (!$eligibilityStmt->fetchColumn()) {
        jsonResponse(['error' => 'Vous pouvez laisser un avis uniquement apres une consultation terminee'], 403);
    }

    $patientName = trim(((string) ($patient['prenom'] ?? '')) . ' ' . strtoupper(substr((string) ($patient['nom'] ?? ''), 0, 1)) . '.');
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    $existingStmt = $pdo->prepare(
        'SELECT id FROM medecin_reviews WHERE medecin_id = :medecin_id AND patient_id = :patient_id LIMIT 1'
    );
    $existingStmt->execute([
        'medecin_id' => $medecinId,
        'patient_id' => (int) $patient['id'],
    ]);
    $existingId = $existingStmt->fetchColumn();

    $pdo->beginTransaction();
    try {
        if ($existingId) {
            $updateStmt = $pdo->prepare(
                'UPDATE medecin_reviews
                 SET rating = :rating, comment = :comment, patient_name = :patient_name, is_verified = 1, created_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'rating' => $rating,
                'comment' => $comment,
                'patient_name' => $patientName,
                'id' => (int) $existingId,
            ]);
        } else {
            $insertStmt = $pdo->prepare(
                'INSERT INTO medecin_reviews (medecin_id, patient_id, patient_name, rating, comment, is_verified)
                 VALUES (:medecin_id, :patient_id, :patient_name, :rating, :comment, 1)'
            );
            $insertStmt->execute([
                'medecin_id' => $medecinId,
                'patient_id' => (int) $patient['id'],
                'patient_name' => $patientName,
                'rating' => $rating,
                'comment' => $comment,
            ]);
        }

        $recomputeStats($pdo, $medecinId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Impossible d enregistrer votre avis', 'details' => $e->getMessage()], 500);
    }

    jsonResponse(['message' => 'Avis enregistre avec succes'], 201);
}

$limit = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : 5;
$limit = max(1, min($limit, 20));

$rowsStmt = $pdo->prepare(
    'SELECT id, patient_name, rating, comment, is_verified, created_at
     FROM medecin_reviews
     WHERE medecin_id = :medecin_id
     ORDER BY created_at DESC, id DESC
     LIMIT :lim'
);
$rowsStmt->bindValue(':medecin_id', $medecinId, PDO::PARAM_INT);
$rowsStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll();

$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_reviews,
        COALESCE(AVG(rating), 0) AS average_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS c5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS c4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS c3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS c2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS c1
     FROM medecin_reviews
     WHERE medecin_id = :medecin_id'
);
$statsStmt->execute(['medecin_id' => $medecinId]);
$stats = $statsStmt->fetch() ?: [
    'total_reviews' => 0,
    'average_rating' => 0,
    'c5' => 0,
    'c4' => 0,
    'c3' => 0,
    'c2' => 0,
    'c1' => 0,
];

jsonResponse([
    'data' => array_map(static function (array $r): array {
        return [
            'id' => (int) $r['id'],
            'patient_name' => $r['patient_name'],
            'rating' => (int) $r['rating'],
            'comment' => $r['comment'],
            'is_verified' => (bool) $r['is_verified'],
            'created_at' => date(DATE_ATOM, strtotime((string) $r['created_at'])),
        ];
    }, $rows),
    'stats' => [
        'total_reviews' => (int) $stats['total_reviews'],
        'average_rating' => round((float) $stats['average_rating'], 1),
        'by_rating' => [
            '5' => (int) $stats['c5'],
            '4' => (int) $stats['c4'],
            '3' => (int) $stats['c3'],
            '2' => (int) $stats['c2'],
            '1' => (int) $stats['c1'],
        ],
    ],
]);
