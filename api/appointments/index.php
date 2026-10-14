<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/appointment_notifications.php';
require_once __DIR__ . '/../_core/appointment_service.php';

$pdo = db();
$patientId = currentPatientId();

function getRequestBody(): array
{
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'multipart/form-data') !== false) {
        $payload = json_decode((string) ($_POST['payload'] ?? '{}'), true);
        if (!is_array($payload)) {
            jsonResponse(['error' => 'Payload invalide'], 422);
        }
        return $payload;
    }

    return readJsonBody();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rawStatut = trim((string) ($_GET['statut'] ?? ''));
    $statuses = [];
    if ($rawStatut !== '' && $rawStatut !== 'tous') {
        $statuses = array_values(array_filter(
            array_map('trim', explode(',', $rawStatut)),
            static fn ($status) => in_array($status, ['confirme', 'termine', 'annule'], true)
        ));
    }

    $order = strtolower((string) ($_GET['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $consultationMode = trim((string) ($_GET['consultation_mode'] ?? ''));
    if (!in_array($consultationMode, ['', 'tous', 'cabinet', 'teleconsultation'], true)) {
        jsonResponse(['error' => 'Mode de consultation invalide'], 422);
    }
    $patientTargetType = trim((string) ($_GET['patient_target_type'] ?? ''));
    if (!in_array($patientTargetType, ['', 'tous', 'self', 'family'], true)) {
        jsonResponse(['error' => 'Type de patient invalide'], 422);
    }
    $familyMemberId = isset($_GET['family_member_id']) && ctype_digit((string) $_GET['family_member_id'])
        ? (int) $_GET['family_member_id']
        : 0;
    $page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : 10;
    $limit = max(1, min($limit, 50));
    $offset = ($page - 1) * $limit;

    $where = ['a.patient_id = :pid'];
    $params = ['pid' => $patientId];
    if ($statuses) {
        $placeholders = [];
        foreach ($statuses as $index => $status) {
            $key = 's' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }
        $where[] = 'a.statut IN (' . implode(',', $placeholders) . ')';
    }
    if ($consultationMode !== '' && $consultationMode !== 'tous') {
        $where[] = 'a.consultation_mode = :consultation_mode';
        $params['consultation_mode'] = $consultationMode;
    }
    if ($patientTargetType !== '' && $patientTargetType !== 'tous') {
        $where[] = 'a.patient_target_type = :patient_target_type';
        $params['patient_target_type'] = $patientTargetType;
    }
    if ($familyMemberId > 0) {
        $where[] = 'a.family_member_id = :family_member_id';
        $params['family_member_id'] = $familyMemberId;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*)
                                FROM appointments a
                                WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT a.id, a.date_heure, a.duree_min, a.motif, a.type_visite, a.consultation_mode,
                   a.patient_target_type, a.family_member_id, a.patient_prenom, a.patient_nom,
                   a.patient_date_naissance, a.patient_telephone, a.patient_email, a.patient_relation,
                   a.teleconsultation_notes, a.statut, a.confirmation_code, a.creneau_id,
                   u.id AS medecin_id, u.prenom AS medecin_prenom, u.nom AS medecin_nom, u.photo_url AS medecin_photo,
                   u.commune AS medecin_commune, w.nom AS medecin_wilaya, mp.adresse AS medecin_adresse,
                   s.nom AS specialite_nom
            FROM appointments a
            INNER JOIN users u ON u.id = a.medecin_id
            INNER JOIN medecin_profiles mp ON mp.user_id = u.id
            INNER JOIN specialites s ON s.id = mp.specialite_id
            LEFT JOIN wilayas w ON w.id = u.wilaya_id
            WHERE $whereSql
            ORDER BY a.date_heure $order
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $appointmentIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
    $documentsByAppointment = [];
    if ($appointmentIds) {
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $docStmt = $pdo->prepare(
            "SELECT id, appointment_id, original_name, file_url, mime_type, file_size, created_at
             FROM appointment_documents
             WHERE appointment_id IN ($placeholders)
             ORDER BY created_at ASC, id ASC"
        );
        $docStmt->execute($appointmentIds);
        foreach ($docStmt->fetchAll() as $doc) {
            $appointmentKey = (int) $doc['appointment_id'];
            $documentsByAppointment[$appointmentKey] ??= [];
            $documentsByAppointment[$appointmentKey][] = [
                'id' => (int) $doc['id'],
                'original_name' => $doc['original_name'],
                'file_url' => $doc['file_url'],
                'mime_type' => $doc['mime_type'],
                'file_size' => (int) $doc['file_size'],
                'created_at' => date(DATE_ATOM, strtotime((string) $doc['created_at'])),
            ];
        }
    }

    $data = array_map(static function (array $row) use ($documentsByAppointment): array {
        $appointmentId = (int) $row['id'];
        $documents = $documentsByAppointment[$appointmentId] ?? [];

        return [
            'id' => $appointmentId,
            'creneau_id' => $row['creneau_id'] !== null ? (int) $row['creneau_id'] : null,
            'date_heure' => date(DATE_ATOM, strtotime((string) $row['date_heure'])),
            'duree_min' => (int) $row['duree_min'],
            'motif' => $row['motif'],
            'type_visite' => $row['type_visite'],
            'consultation_mode' => $row['consultation_mode'] ?: 'cabinet',
            'teleconsultation_notes' => $row['teleconsultation_notes'] ?: null,
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
                'commune' => $row['medecin_commune'] ?: null,
                'wilaya' => $row['medecin_wilaya'] ?: null,
                'adresse' => $row['medecin_adresse'] ?: null,
                'specialite' => $row['specialite_nom'],
            ],
        ];
    }, $rows);

    jsonResponse([
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / $limit)),
        'data' => $data,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();

    $medecinId = isset($body['medecin_id']) ? (int) $body['medecin_id'] : 0;
    $creneauId = isset($body['creneau_id']) ? (int) $body['creneau_id'] : 0;
    $dateHeure = (string) ($body['date_heure'] ?? '');
    $motif = trim((string) ($body['motif'] ?? ''));
    $typeVisite = (string) ($body['type_visite'] ?? 'new');
    $consultationMode = (string) ($body['consultation_mode'] ?? 'cabinet');
    $patientTargetType = (string) ($body['patient_target_type'] ?? 'self');
    $familyMemberId = isset($body['family_member_id']) ? (int) $body['family_member_id'] : 0;
    $teleconsultationNotes = appointmentNormalizeNullableString($body['teleconsultation_notes'] ?? null);
    $patientProfile = is_array($body['patient_profile'] ?? null) ? $body['patient_profile'] : [];

    if ($medecinId < 1) {
        jsonResponse(['error' => 'medecin_id requis'], 422);
    }
    if ($creneauId < 1 && $dateHeure === '') {
        jsonResponse(['error' => 'creneau_id ou date_heure requis'], 422);
    }
    if (!in_array($typeVisite, ['new', 'suivi'], true)) {
        $typeVisite = 'new';
    }
    if (!in_array($consultationMode, ['cabinet', 'teleconsultation'], true)) {
        $consultationMode = 'cabinet';
    }
    if (!in_array($patientTargetType, ['self', 'family'], true)) {
        $patientTargetType = 'self';
    }

    $requestedDate = $dateHeure !== '' ? date('Y-m-d H:i:s', strtotime($dateHeure)) : null;
    $confirmation = 'TBZ-' . date('Y') . '-' . random_int(1000, 9999);
    $selfPatient = appointmentGetCurrentPatientRow($pdo, $patientId);
    if (!$selfPatient) {
        jsonResponse(['error' => 'Patient introuvable'], 404);
    }

    $familyMember = null;
    if ($patientTargetType === 'family') {
        if ($familyMemberId < 1) {
            jsonResponse(['error' => 'family_member_id requis pour un rendez-vous famille'], 422);
        }
        $familyMember = appointmentGetFamilyMemberRow($pdo, $patientId, $familyMemberId);
        if (!$familyMember) {
            jsonResponse(['error' => 'Membre de la famille introuvable'], 404);
        }
    }

    $snapshotSource = $familyMember ?: $selfPatient;
    $patientPrenom = appointmentNormalizeNullableString($patientProfile['prenom'] ?? $snapshotSource['prenom'] ?? null);
    $patientNom = appointmentNormalizeNullableString($patientProfile['nom'] ?? $snapshotSource['nom'] ?? null);
    $patientDateNaissance = appointmentNormalizeNullableString($patientProfile['date_naissance'] ?? $snapshotSource['date_naissance'] ?? null);
    $patientTelephone = appointmentNormalizeNullableString($patientProfile['telephone'] ?? $snapshotSource['telephone'] ?? null);
    $patientEmail = appointmentNormalizeNullableString($patientProfile['email'] ?? $snapshotSource['email'] ?? null);
    $patientRelation = appointmentNormalizeNullableString(
        $patientProfile['relation'] ?? (appointmentFamilyMemberRelationLabel($familyMember) ?? ($patientTargetType === 'family' ? 'Membre de la famille' : 'Moi-meme'))
    );

    if ($patientPrenom === null || $patientNom === null) {
        jsonResponse(['error' => 'Le profil du patient est incomplet'], 422);
    }

    $uploadedPaths = [];
    $documents = [];
    $pdo->beginTransaction();
    try {
        $slot = $creneauId > 0
            ? appointmentLockSlotById($pdo, $creneauId, 409)
            : appointmentLockSlotByDoctorAndStart($pdo, $medecinId, (string) $requestedDate, 409);

        appointmentAssertSlotBelongsToDoctor($slot, $medecinId, 'Le creneau ne correspond pas au medecin demande');
        appointmentAssertSlotAvailable($slot, 'Ce creneau vient d\'etre reserve');
        appointmentAssertSlotUnoccupied($pdo, (int) $slot['id'], 'Ce creneau est deja pris');

        $duree = appointmentSlotDurationMinutes($slot);
        $dt = appointmentSlotStartAt($slot);

        if (appointmentHasPatientScheduleConflict(
            $pdo,
            $patientId,
            $patientTargetType,
            $familyMember ? (int) $familyMember['id'] : null,
            $dt,
            $duree
        )) {
            throw new AppointmentServiceException('Ce patient a deja un rendez-vous sur ce meme horaire', 409);
        }

        appointmentReserveSlot($pdo, (int) $slot['id']);

        $ins = $pdo->prepare(
            'INSERT INTO appointments (
                patient_id, medecin_id, creneau_id, date_heure, duree_min, motif, type_visite,
                consultation_mode, patient_target_type, family_member_id, patient_prenom, patient_nom,
                patient_date_naissance, patient_telephone, patient_email, patient_relation,
                teleconsultation_notes, statut, confirmation_code
            ) VALUES (
                :patient_id, :medecin_id, :creneau_id, :date_heure, :duree_min, :motif, :type_visite,
                :consultation_mode, :patient_target_type, :family_member_id, :patient_prenom, :patient_nom,
                :patient_date_naissance, :patient_telephone, :patient_email, :patient_relation,
                :teleconsultation_notes, "confirme", :confirmation_code
            )'
        );
        $ins->execute([
            'patient_id' => $patientId,
            'medecin_id' => $medecinId,
            'creneau_id' => (int) $slot['id'],
            'date_heure' => $dt,
            'duree_min' => $duree,
            'motif' => $motif,
            'type_visite' => $typeVisite,
            'consultation_mode' => $consultationMode,
            'patient_target_type' => $patientTargetType,
            'family_member_id' => $familyMember ? (int) $familyMember['id'] : null,
            'patient_prenom' => $patientPrenom,
            'patient_nom' => $patientNom,
            'patient_date_naissance' => $patientDateNaissance,
            'patient_telephone' => $patientTelephone,
            'patient_email' => $patientEmail,
            'patient_relation' => $patientRelation,
            'teleconsultation_notes' => $teleconsultationNotes,
            'confirmation_code' => $confirmation,
        ]);

        $appointmentId = (int) $pdo->lastInsertId();
        $documents = appointmentStoreDocuments($pdo, $appointmentId, appointmentNormalizeUploadedFiles($_FILES['documents'] ?? []));
        foreach ($documents as $document) {
            $uploadedPaths[] = $document['absolute_path'] ?? null;
        }

        $pdo->commit();

        try {
            enqueueAppointmentConfirmationNotification($pdo, $appointmentId);
        } catch (Throwable $notificationError) {
            // Ne jamais annuler le rendez-vous si la notification echoue.
        }

        jsonResponse([
            'message' => 'Rendez-vous confirme',
            'appointment_id' => $appointmentId,
            'confirmation_code' => $confirmation,
            'creneau_id' => (int) $slot['id'],
            'date_heure' => date(DATE_ATOM, strtotime($dt)),
            'consultation_mode' => $consultationMode,
            'patient_profile' => [
                'type' => $patientTargetType,
                'family_member_id' => $familyMember ? (int) $familyMember['id'] : null,
                'display_name' => trim($patientPrenom . ' ' . $patientNom),
                'relation' => $patientRelation,
                'telephone' => $patientTelephone,
                'email' => $patientEmail,
                'date_naissance' => $patientDateNaissance,
            ],
            'documents_count' => count($documents),
        ], 201);
    } catch (AppointmentServiceException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($uploadedPaths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
        jsonResponse(['error' => $e->getMessage()], $e->status());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($uploadedPaths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
        jsonResponse(['error' => 'Reservation impossible', 'details' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
