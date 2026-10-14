<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
assertMethod('PATCH');

$me = requireAuth('medecin');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

$pdo = db();

// Vérifier que ce RDV appartient bien à CE médecin
$stmt = $pdo->prepare(
    'SELECT id FROM appointments WHERE id = :id AND medecin_id = :medecin_id LIMIT 1'
);
$stmt->execute(['id' => $id, 'medecin_id' => $me['id']]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Rendez-vous introuvable ou accès refusé.'], 404);
}

$body = readJsonBody();

$fields = [];
$params = [];

// Statut
$allowedStatuts = ['confirme', 'en_attente', 'annule', 'termine'];
if (isset($body['statut']) && in_array($body['statut'], $allowedStatuts, true)) {
    $fields[] = 'statut = :statut';
    $params['statut'] = $body['statut'];
}

// Notes — la colonne réelle s'appelle teleconsultation_notes
if (array_key_exists('notes_medecin', $body)) {
    $fields[] = 'teleconsultation_notes = :notes';
    $params['notes'] = trim((string) $body['notes_medecin']);
}

if (empty($fields)) {
    jsonResponse(['error' => 'Aucun champ valide à mettre à jour.'], 400);
}

$params['id']         = $id;
$params['medecin_id'] = $me['id'];

$pdo->prepare(
    'UPDATE appointments SET ' . implode(', ', $fields) . ' WHERE id = :id AND medecin_id = :medecin_id'
)->execute($params);

$stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = :id');
$stmt->execute(['id' => $id]);

jsonResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);