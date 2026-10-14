<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/appointment_service.php';
$pdo = db();
$patientId = currentPatientId();

function derivePatientTeleconsultationStatus(array $row): ?string
{
    $status = (string) ($row['teleconsultation_status'] ?? '');
    $appointmentDate = !empty($row['date_heure']) ? strtotime((string) $row['date_heure']) : false;
    $startedAt = !empty($row['teleconsultation_started_at']) ? strtotime((string) $row['teleconsultation_started_at']) : false;

    if ($status === '') {
        if (($row['statut'] ?? '') === 'annule') {
            return 'cancelled';
        }
        if (($row['statut'] ?? '') === 'termine') {
            return 'completed';
        }
        if ($startedAt) {
            return 'in_progress';
        }
        if ($appointmentDate !== false && time() >= ($appointmentDate - 600) && time() <= ($appointmentDate + 5400)) {
            return 'room_open';
        }
        if ($appointmentDate !== false && time() > ($appointmentDate + 5400)) {
            return 'missed';
        }
        return 'planned';
    }

    if ($status === 'planned' && ($row['statut'] ?? '') === 'confirme' && $appointmentDate !== false && time() > ($appointmentDate + 5400)) {
        return 'missed';
    }

    return $status;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.date_heure, a.duree_min, a.motif, a.type_visite, a.consultation_mode,
                a.patient_target_type, a.family_member_id, a.patient_prenom, a.patient_nom,
                a.patient_date_naissance, a.patient_telephone, a.patient_email, a.patient_relation,
                a.teleconsultation_notes, a.teleconsultation_status, a.teleconsultation_started_at,
                a.teleconsultation_ended_at, a.teleconsultation_summary,
                a.teleconsultation_ordonnance_text, a.teleconsultation_certificat_text,
                a.statut, a.confirmation_code, a.creneau_id,
                u.id AS medecin_id, u.prenom AS medecin_prenom, u.nom AS medecin_nom,
                u.photo_url AS medecin_photo, u.telephone AS medecin_telephone, u.email AS medecin_email,
                u.commune AS medecin_commune, w.nom AS medecin_wilaya, mp.adresse AS medecin_adresse,
                mp.agrement_no AS medecin_agrement_no, mp.bio AS medecin_bio,
                mp.formations_text AS medecin_formations_text, mp.hopitaux_text AS medecin_hopitaux_text,
                s.nom AS specialite_nom
         FROM appointments a
         INNER JOIN users u ON u.id = a.medecin_id
         INNER JOIN medecin_profiles mp ON mp.user_id = u.id
         INNER JOIN specialites s ON s.id = mp.specialite_id
         LEFT JOIN wilayas w ON w.id = u.wilaya_id
         WHERE a.id = :id AND a.patient_id = :pid
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $id,
        'pid' => $patientId,
    ]);
    $row = firstOrNull($stmt);
    if (!$row) {
        jsonResponse(['error' => 'Rendez-vous introuvable'], 404);
    }

    $docStmt = $pdo->prepare(
        'SELECT id, original_name, file_url, mime_type, file_size, document_type, uploaded_by_role, created_at
         FROM appointment_documents
         WHERE appointment_id = :appointment_id
         ORDER BY created_at ASC, id ASC'
    );
    $docStmt->execute(['appointment_id' => $id]);
    $documents = array_map(
        static fn (array $doc): array => [
            'id' => (int) $doc['id'],
            'original_name' => $doc['original_name'],
            'file_url' => $doc['file_url'],
            'mime_type' => $doc['mime_type'],
            'file_size' => (int) $doc['file_size'],
            'document_type' => $doc['document_type'] ?? 'patient_document',
            'uploaded_by_role' => $doc['uploaded_by_role'] ?? 'patient',
            'created_at' => date(DATE_ATOM, strtotime((string) $doc['created_at'])),
        ],
        $docStmt->fetchAll()
    );

    jsonResponse([
        'data' => [
            'id' => (int) $row['id'],
            'creneau_id' => $row['creneau_id'] !== null ? (int) $row['creneau_id'] : null,
            'date_heure' => date(DATE_ATOM, strtotime((string) $row['date_heure'])),
            'duree_min' => (int) $row['duree_min'],
            'motif' => $row['motif'],
            'type_visite' => $row['type_visite'],
            'consultation_mode' => $row['consultation_mode'] ?: 'cabinet',
            'teleconsultation_notes' => $row['teleconsultation_notes'] ?: null,
            'teleconsultation_status' => derivePatientTeleconsultationStatus($row),
            'teleconsultation_started_at' => !empty($row['teleconsultation_started_at']) ? date(DATE_ATOM, strtotime((string) $row['teleconsultation_started_at'])) : null,
            'teleconsultation_ended_at' => !empty($row['teleconsultation_ended_at']) ? date(DATE_ATOM, strtotime((string) $row['teleconsultation_ended_at'])) : null,
            'teleconsultation_summary' => $row['teleconsultation_summary'] ?: null,
            'teleconsultation_ordonnance_text' => $row['teleconsultation_ordonnance_text'] ?: null,
            'teleconsultation_certificat_text' => $row['teleconsultation_certificat_text'] ?: null,
            'statut' => $row['statut'],
            'confirmation_code' => $row['confirmation_code'],
            'patient_profile' => appointmentBuildPatientProfile($row),
            'documents' => $documents,
            'documents_count' => count($documents),
            'medecin' => [
                'id' => (int) $row['medecin_id'],
                'prenom' => $row['medecin_prenom'],
                'nom' => $row['medecin_nom'],
                'photo_url' => $row['medecin_photo'],
                'telephone' => $row['medecin_telephone'] ?: null,
                'email' => $row['medecin_email'] ?: null,
                'commune' => $row['medecin_commune'] ?: null,
                'wilaya' => $row['medecin_wilaya'] ?: null,
                'adresse' => $row['medecin_adresse'] ?: null,
                'agrement_no' => $row['medecin_agrement_no'] ?: null,
                'bio' => $row['medecin_bio'] ?: null,
                'formations_text' => $row['medecin_formations_text'] ?: null,
                'hopitaux_text' => $row['medecin_hopitaux_text'] ?: null,
                'specialite' => $row['specialite_nom'],
            ],
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, creneau_id, statut
                               FROM appointments
                               WHERE id = :id AND patient_id = :pid
                               FOR UPDATE');
        $lock->execute(['id' => $id, 'pid' => $patientId]);
        $appointment = firstOrNull($lock);
        if (!$appointment) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Rendez-vous introuvable'], 404);
        }

        if ($appointment['statut'] === 'annule') {
            $pdo->rollBack();
            jsonResponse(['message' => 'Rendez-vous deja annule']);
        }

        $stmt = $pdo->prepare('UPDATE appointments
                               SET statut = "annule",
                                   teleconsultation_status = CASE WHEN consultation_mode = "teleconsultation" THEN "cancelled" ELSE teleconsultation_status END,
                                   updated_at = NOW()
                               WHERE id = :id AND patient_id = :pid');
        $stmt->execute(['id' => $id, 'pid' => $patientId]);

        if (!empty($appointment['creneau_id'])) {
            appointmentReleaseSlot($pdo, (int) $appointment['creneau_id']);
        }

        $pdo->commit();
        jsonResponse(['message' => 'Rendez-vous annule']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => 'Annulation impossible', 'details' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    $newSlotId = isset($body['creneau_id']) ? (int) $body['creneau_id'] : 0;
    $newDateRaw = trim((string) ($body['nouvelle_date'] ?? ''));
    $newMotif = array_key_exists('motif', $body) ? trim((string) $body['motif']) : null;

    if ($newSlotId < 1 && $newDateRaw !== '') {
        jsonResponse(['error' => 'Utilisez un creneau reel pour reporter le rendez-vous'], 422);
    }
    if ($newSlotId < 1 && $newMotif === null) {
        jsonResponse(['error' => 'Aucun champ a mettre a jour'], 400);
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, patient_id, medecin_id, creneau_id, date_heure, duree_min, statut, motif,
                                      patient_target_type, family_member_id
                               FROM appointments
                               WHERE id = :id AND patient_id = :pid
                               FOR UPDATE');
        $lock->execute(['id' => $id, 'pid' => $patientId]);
        $appointment = firstOrNull($lock);
        if (!$appointment) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Rendez-vous introuvable'], 404);
        }

        $fields = [];
        $params = ['id' => $id, 'pid' => $patientId];

        if ($newMotif !== null) {
            $fields[] = 'motif = :motif';
            $params['motif'] = $newMotif;
        }

        if ($newSlotId > 0 && (int) ($appointment['creneau_id'] ?? 0) !== $newSlotId) {
            $newSlot = appointmentLockSlotById($pdo, $newSlotId, 404, 'Nouveau creneau introuvable');
            appointmentAssertSlotBelongsToDoctor($newSlot, (int) $appointment['medecin_id'], 'Le nouveau creneau ne correspond pas a ce medecin');
            appointmentAssertSlotAvailable($newSlot, 'Le nouveau creneau n est plus disponible');
            appointmentAssertSlotUnoccupied($pdo, $newSlotId, 'Ce creneau est deja reserve');
            appointmentReserveSlot($pdo, $newSlotId, 'Le nouveau creneau n est plus disponible');

            if (!empty($appointment['creneau_id'])) {
                appointmentReleaseSlot($pdo, (int) $appointment['creneau_id']);
            }

            $newStartAt = appointmentSlotStartAt($newSlot);
            $newDuration = appointmentSlotDurationMinutes($newSlot);

            if (appointmentHasPatientScheduleConflict(
                $pdo,
                $patientId,
                (string) ($appointment['patient_target_type'] ?? 'self'),
                isset($appointment['family_member_id']) ? ((int) $appointment['family_member_id'] ?: null) : null,
                $newStartAt,
                $newDuration,
                $id
            )) {
                throw new AppointmentServiceException('Ce patient a deja un rendez-vous sur ce meme horaire', 409);
            }

            $fields[] = 'creneau_id = :creneau_id';
            $fields[] = 'date_heure = :date_heure';
            $fields[] = 'duree_min = :duree_min';
            $params['creneau_id'] = $newSlotId;
            $params['date_heure'] = $newStartAt;
            $params['duree_min'] = $newDuration;
        }

        if (!$fields) {
            $pdo->rollBack();
            jsonResponse(['message' => 'Aucune modification necessaire']);
        }

        $sql = 'UPDATE appointments SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id AND patient_id = :pid';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->commit();
        jsonResponse(['message' => 'Rendez-vous mis a jour']);
    } catch (AppointmentServiceException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => $e->getMessage()], $e->status());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => 'Mise a jour impossible', 'details' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
