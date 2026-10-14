<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
$pdo = db();
$patientId = currentPatientId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT u.id, u.prenom, u.nom, u.email, u.telephone, u.photo_url, u.date_naissance, u.sexe,
                                   u.wilaya_id, u.commune, u.groupe_sanguin, u.num_assurance, u.allergies,
                                   (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = u.id) AS rdv_count,
                                   (SELECT COUNT(*) FROM patient_favoris f WHERE f.patient_id = u.id) AS favoris_count,
                                   (SELECT COUNT(*) FROM patient_family_members fm WHERE fm.patient_id = u.id) AS family_members_count
                            FROM users u
                            WHERE u.id = :id AND u.role = 'patient'");
    $stmt->execute(['id' => $patientId]);
    $row = firstOrNull($stmt);
    if (!$row) {
        jsonResponse(['error' => 'Patient introuvable'], 404);
    }
    jsonResponse($row);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    if (!$body) {
        jsonResponse(['error' => 'Body JSON vide'], 400);
    }

    $fields = ['prenom','nom','telephone','email','date_naissance','sexe','wilaya_id','commune','groupe_sanguin','num_assurance','allergies'];
    $set = [];
    $params = ['id' => $patientId];

    foreach ($fields as $field) {
        if (array_key_exists($field, $body)) {
            $set[] = "$field = :$field";
            $params[$field] = $body[$field] === '' ? null : $body[$field];
        }
    }

    if (!$set) {
        jsonResponse(['error' => 'Aucun champ à mettre à jour'], 400);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id AND role = "patient"';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(['message' => 'Profil mis à jour']);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM notifications WHERE patient_id = :id')->execute(['id' => $patientId]);
        $pdo->prepare('DELETE FROM notification_preferences WHERE patient_id = :id')->execute(['id' => $patientId]);
        $pdo->prepare('DELETE FROM patient_favoris WHERE patient_id = :id')->execute(['id' => $patientId]);
        $pdo->prepare('DELETE FROM patient_family_members WHERE patient_id = :id')->execute(['id' => $patientId]);
        $pdo->prepare('DELETE FROM appointments WHERE patient_id = :id')->execute(['id' => $patientId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id AND role = "patient"')->execute(['id' => $patientId]);
        $pdo->commit();
        jsonResponse(['message' => 'Compte supprimé']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Suppression impossible', 'details' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
