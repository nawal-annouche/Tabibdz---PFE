<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../_core/helpers.php';

$doctor = requireAuth('medecin');
$pdo = db();
$doctorId = (int) $doctor['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $from = (string) ($_GET['from'] ?? date(DATE_ATOM));
    $to = (string) ($_GET['to'] ?? date(DATE_ATOM, strtotime('+14 days')));

    $stmt = $pdo->prepare(
        "SELECT c.id, c.start_at, c.end_at, c.is_available,
                EXISTS(
                  SELECT 1 FROM appointments a
                  WHERE a.creneau_id = c.id AND a.statut = 'confirme'
                ) AS is_booked
         FROM creneaux c
         WHERE c.medecin_id = :id AND c.start_at >= :from_at AND c.start_at < :to_at
         ORDER BY c.start_at ASC"
    );
    $stmt->execute([
        'id' => $doctorId,
        'from_at' => date('Y-m-d H:i:s', strtotime($from)),
        'to_at' => date('Y-m-d H:i:s', strtotime($to)),
    ]);
    jsonResponse($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = readJsonBody();
    $slots = isset($body['slots']) && is_array($body['slots']) ? $body['slots'] : null;

    if ($slots !== null) {
        if ($slots === []) {
            jsonResponse(['error' => 'Aucun créneau à créer'], 422);
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO creneaux (medecin_id, start_at, end_at, is_available) VALUES (:mid, :start_at, :end_at, 1)');
        $created = 0;
        foreach ($slots as $slot) {
            $startAt = (string) ($slot['start_at'] ?? '');
            $endAt = (string) ($slot['end_at'] ?? '');
            if ($startAt === '' || $endAt === '') {
                continue;
            }
            $stmt->execute([
                'mid' => $doctorId,
                'start_at' => date('Y-m-d H:i:s', strtotime($startAt)),
                'end_at' => date('Y-m-d H:i:s', strtotime($endAt)),
            ]);
            $created += $stmt->rowCount() > 0 ? 1 : 0;
        }

        jsonResponse([
            'message' => 'Créneaux générés',
            'created_count' => $created,
            'requested_count' => count($slots),
        ], 201);
    }

    $startAt = (string) ($body['start_at'] ?? '');
    $endAt = (string) ($body['end_at'] ?? '');
    if ($startAt === '' || $endAt === '') {
        jsonResponse(['error' => 'start_at et end_at requis'], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO creneaux (medecin_id, start_at, end_at, is_available) VALUES (:mid, :start_at, :end_at, 1)');
    $stmt->execute([
        'mid' => $doctorId,
        'start_at' => date('Y-m-d H:i:s', strtotime($startAt)),
        'end_at' => date('Y-m-d H:i:s', strtotime($endAt)),
    ]);

    jsonResponse(['message' => 'Créneau créé', 'id' => (int) $pdo->lastInsertId()], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id < 1) {
        jsonResponse(['error' => 'id invalide'], 400);
    }

    $lock = $pdo->prepare(
        "SELECT c.id,
                EXISTS(
                  SELECT 1 FROM appointments a
                  WHERE a.creneau_id = c.id AND a.statut = 'confirme'
                ) AS is_booked
         FROM creneaux c
         WHERE c.id = :id AND c.medecin_id = :mid
         LIMIT 1"
    );
    $lock->execute(['id' => $id, 'mid' => $doctorId]);
    $slot = firstOrNull($lock);
    if (!$slot) {
        jsonResponse(['error' => 'Créneau introuvable'], 404);
    }
    if ((int) $slot['is_booked'] === 1) {
        jsonResponse(['error' => 'Impossible de supprimer un créneau réservé'], 409);
    }

    $pdo->prepare('DELETE FROM creneaux WHERE id = :id AND medecin_id = :mid')->execute([
        'id' => $id,
        'mid' => $doctorId,
    ]);
    jsonResponse(['message' => 'Créneau supprimé']);
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
