<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';
require_once __DIR__ . '/../../_core/medical_document_pdf.php';
require_once __DIR__ . '/../../_core/medical_document_word_template.php';

$doctor = requireAuth('medecin');
$pdo = db();
$doctorId = (int) $doctor['id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    jsonResponse(['error' => 'id invalide'], 400);
}

function buildMedicalDocumentFilename(string $documentType, array $appointment): string
{
    $prefix = $documentType === 'certificat' ? 'certificat' : 'ordonnance';
    $reference = trim((string) ($appointment['confirmation_code'] ?? ''));
    if ($reference === '') {
        $reference = 'rdv-' . (int) ($appointment['id'] ?? 0);
    }
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $reference));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'document';
    }

    return $prefix . '-' . $slug . '.pdf';
}

function absoluteAppointmentUploadPath(string $storedName): string
{
    return dirname(__DIR__, 3) . '/uploads/appointments/' . $storedName;
}

function deleteGeneratedMedicalDocuments(PDO $pdo, int $appointmentId, array $documentTypes): array
{
    if (!$documentTypes) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($documentTypes), '?'));
    $params = array_merge([$appointmentId], $documentTypes);
    $select = $pdo->prepare(
        "SELECT id, stored_name
         FROM appointment_documents
         WHERE appointment_id = ?
           AND uploaded_by_role = 'medecin'
           AND document_type IN ($placeholders)"
    );
    $select->execute($params);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rows) {
        return [];
    }

    $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
    $deletePlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $delete = $pdo->prepare("DELETE FROM appointment_documents WHERE id IN ($deletePlaceholders)");
    $delete->execute($ids);

    $paths = [];
    foreach ($rows as $row) {
        $storedName = trim((string) ($row['stored_name'] ?? ''));
        if ($storedName !== '') {
            $paths[] = absoluteAppointmentUploadPath($storedName);
        }
    }

    return $paths;
}

function persistGeneratedMedicalPdf(PDO $pdo, int $appointmentId, string $documentType, string $originalName, string $pdfBinary): array
{
    $targetDir = dirname(__DIR__, 3) . '/uploads/appointments';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Impossible de creer le dossier des documents.');
    }

    $storedName = sprintf('appointment_%d_%s.pdf', $appointmentId, $documentType);
    $absolutePath = $targetDir . '/' . $storedName;
    $bytes = file_put_contents($absolutePath, $pdfBinary);
    if ($bytes === false) {
        throw new RuntimeException('Impossible de generer le document PDF.');
    }

    $fileUrl = '/uploads/appointments/' . $storedName;
    $stmt = $pdo->prepare(
        'INSERT INTO appointment_documents (
            appointment_id, original_name, stored_name, file_url, mime_type, file_size, document_type, uploaded_by_role
        ) VALUES (
            :appointment_id, :original_name, :stored_name, :file_url, :mime_type, :file_size, :document_type, :uploaded_by_role
        )'
    );
    $stmt->execute([
        'appointment_id' => $appointmentId,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'file_url' => $fileUrl,
        'mime_type' => 'application/pdf',
        'file_size' => strlen($pdfBinary),
        'document_type' => $documentType,
        'uploaded_by_role' => 'medecin',
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'absolute_path' => $absolutePath,
    ];
}

$fetchAppointment = static function (PDO $pdo, int $doctorId, int $id): ?array {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.date_heure, a.duree_min, a.motif, a.type_visite, a.consultation_mode,
                a.teleconsultation_notes, a.teleconsultation_reason, a.teleconsultation_contact,
                a.statut, a.teleconsultation_status, a.teleconsultation_started_at, a.teleconsultation_ended_at,
                a.teleconsultation_summary, a.teleconsultation_ordonnance_text, a.teleconsultation_certificat_text,
                a.confirmation_code,
                COALESCE(NULLIF(a.patient_prenom, ''), p.prenom) AS patient_prenom,
                COALESCE(NULLIF(a.patient_nom, ''), p.nom) AS patient_nom,
                COALESCE(a.patient_date_naissance, p.date_naissance) AS patient_date_naissance,
                COALESCE(NULLIF(a.patient_telephone, ''), p.telephone) AS patient_telephone,
                COALESCE(NULLIF(a.patient_email, ''), p.email) AS patient_email,
                COALESCE(NULLIF(a.patient_relation, ''), 'Patient') AS patient_relation,
                d.prenom AS doctor_prenom,
                d.nom AS doctor_nom,
                d.email AS doctor_email,
                d.telephone AS doctor_telephone,
                mp.adresse AS doctor_adresse,
                NULL AS doctor_agrement_no,
                s.nom AS doctor_specialite
         FROM appointments a
         INNER JOIN users p ON p.id = a.patient_id
         INNER JOIN users d ON d.id = a.medecin_id
         LEFT JOIN medecin_profiles mp ON mp.user_id = d.id
         LEFT JOIN specialites s ON s.id = mp.specialite_id
         WHERE a.id = :id AND a.medecin_id = :doctor_id AND a.consultation_mode = 'teleconsultation'
         LIMIT 1"
    );
    $stmt->execute(['id' => $id, 'doctor_id' => $doctorId]);
    $row = firstOrNull($stmt);
    if (!$row) {
        return null;
    }

    $docStmt = $pdo->prepare(
        'SELECT id, original_name, file_url, mime_type, file_size, document_type, uploaded_by_role, created_at
         FROM appointment_documents
         WHERE appointment_id = :id
         ORDER BY created_at ASC, id ASC'
    );
    $docStmt->execute(['id' => $id]);
    $documents = array_map(static fn(array $doc): array => [
        'id' => (int) $doc['id'],
        'original_name' => $doc['original_name'],
        'file_url' => $doc['file_url'],
        'mime_type' => $doc['mime_type'],
        'file_size' => (int) $doc['file_size'],
        'document_type' => $doc['document_type'] ?? 'patient_document',
        'uploaded_by_role' => $doc['uploaded_by_role'] ?? 'patient',
        'created_at' => date(DATE_ATOM, strtotime((string) $doc['created_at'])),
    ], $docStmt->fetchAll());

    $date = strtotime((string) $row['date_heure']);
    $joinOpenAt = $date !== false ? $date - 600 : time();
    $derivedStatus = (string) ($row['teleconsultation_status'] ?? '');
    if ($derivedStatus === '') {
        if ($row['statut'] === 'annule') {
            $derivedStatus = 'cancelled';
        } elseif ($row['statut'] === 'termine') {
            $derivedStatus = 'completed';
        } elseif (!empty($row['teleconsultation_started_at'])) {
            $derivedStatus = 'in_progress';
        } elseif (time() >= $joinOpenAt && time() <= ($date + 5400)) {
            $derivedStatus = 'room_open';
        } elseif ($date !== false && time() > ($date + 5400)) {
            $derivedStatus = 'missed';
        } else {
            $derivedStatus = 'planned';
        }
    } elseif ($derivedStatus === 'planned' && $date !== false && time() > ($date + 5400) && $row['statut'] === 'confirme') {
        $derivedStatus = 'missed';
    }

    $doctorDisplayName = trim('Dr. ' . (string) $row['doctor_prenom'] . ' ' . (string) $row['doctor_nom']);
    if ($doctorDisplayName === 'Dr.') {
        $doctorDisplayName = 'Dr. Medecin';
    }

    return [
        'id' => (int) $row['id'],
        'date_heure' => date(DATE_ATOM, $date ?: time()),
        'duree_min' => (int) ($row['duree_min'] ?? 0),
        'motif' => $row['motif'] ?: 'Teleconsultation',
        'type_visite' => $row['type_visite'] ?: null,
        'consultation_mode' => 'teleconsultation',
        'statut' => $row['statut'],
        'teleconsultation_status' => $derivedStatus,
        'teleconsultation_reason' => $row['teleconsultation_reason'] ?: null,
        'teleconsultation_notes' => $row['teleconsultation_notes'] ?: null,
        'teleconsultation_contact' => $row['teleconsultation_contact'] ?: 'video',
        'teleconsultation_started_at' => !empty($row['teleconsultation_started_at']) ? date(DATE_ATOM, strtotime((string) $row['teleconsultation_started_at'])) : null,
        'teleconsultation_ended_at' => !empty($row['teleconsultation_ended_at']) ? date(DATE_ATOM, strtotime((string) $row['teleconsultation_ended_at'])) : null,
        'teleconsultation_summary' => $row['teleconsultation_summary'] ?: null,
        'teleconsultation_ordonnance_text' => $row['teleconsultation_ordonnance_text'] ?: null,
        'teleconsultation_certificat_text' => $row['teleconsultation_certificat_text'] ?: null,
        'join_open_at' => date(DATE_ATOM, $joinOpenAt),
        'documents' => $documents,
        'patient' => [
            'display_name' => trim((string) $row['patient_prenom'] . ' ' . (string) $row['patient_nom']) ?: 'Patient',
            'prenom' => $row['patient_prenom'] ?: null,
            'nom' => $row['patient_nom'] ?: null,
            'telephone' => $row['patient_telephone'] ?: null,
            'email' => $row['patient_email'] ?: null,
            'relation' => $row['patient_relation'] ?: 'Patient',
            'date_naissance' => !empty($row['patient_date_naissance']) ? date('Y-m-d', strtotime((string) $row['patient_date_naissance'])) : null,
        ],
        'doctor' => [
            'display_name' => $doctorDisplayName,
            'prenom' => $row['doctor_prenom'] ?: null,
            'nom' => $row['doctor_nom'] ?: null,
            'specialite' => $row['doctor_specialite'] ?: null,
            'adresse' => $row['doctor_adresse'] ?: null,
            'agrement_no' => $row['doctor_agrement_no'] ?: null,
            'telephone' => $row['doctor_telephone'] ?: null,
            'email' => $row['doctor_email'] ?: null,
        ],
        'confirmation_code' => $row['confirmation_code'] ?: null,
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $item = $fetchAppointment($pdo, $doctorId, $id);
    if (!$item) {
        jsonResponse(['error' => 'Teleconsultation introuvable'], 404);
    }
    jsonResponse(['data' => $item]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $body = readJsonBody();
    $action = (string) ($body['action'] ?? '');

    // Action: save_ordo — sauvegarde ordonnance + certificat + génère les PDFs sans terminer la consultation
    if ($action === 'save_ordo') {
        $ordonnance = trim((string) ($body['ordonnance_text'] ?? ''));
        $certificat = trim((string) ($body['certificat_text'] ?? ''));

        $appointment = $fetchAppointment($pdo, $doctorId, $id);
        if (!$appointment) {
            jsonResponse(['error' => 'Teleconsultation introuvable'], 404);
        }

        // Préparer les documents PDF à générer
        $generatedDocuments = [];
        if ($ordonnance !== '') {
            $ordonnancePayload = [
                'template'              => 'ordonnance',
                'title'                 => 'Ordonnance medicale',
                'subtitle'              => 'Prescription remise a la fin de la teleconsultation',
                'doctor_name'           => (string) ($appointment['doctor']['display_name'] ?? 'Dr. Medecin'),
                'doctor_specialite'     => (string) ($appointment['doctor']['specialite'] ?? ''),
                'doctor_adresse'        => (string) ($appointment['doctor']['adresse'] ?? ''),
                'doctor_agrement'       => (string) ($appointment['doctor']['agrement_no'] ?? ''),
                'doctor_telephone'      => (string) ($appointment['doctor']['telephone'] ?? ''),
                'doctor_email'          => (string) ($appointment['doctor']['email'] ?? ''),
                'doctor_secondary_line' => (string) (($appointment['doctor']['agrement_no'] ?? '') !== '' ? $appointment['doctor']['agrement_no'] : 'Prescription remise a la fin de la teleconsultation'),
                'patient_name'          => (string) ($appointment['patient']['display_name'] ?? 'Patient'),
                'patient_relation'      => (string) ($appointment['patient']['relation'] ?? 'Patient'),
                'patient_birthdate'     => (string) ($appointment['patient']['date_naissance'] ?? ''),
                'patient_age'           => medicalPdfAgeLabel((string) ($appointment['patient']['date_naissance'] ?? '')),
                'appointment_date'      => !empty($appointment['date_heure']) ? date('d/m/Y H:i', strtotime((string) $appointment['date_heure'])) : '',
                'reference'             => (string) ($appointment['confirmation_code'] ?? ''),
                'issued_at'             => date('d/m/Y H:i'),
                'city_label'            => '',
                'body'                  => $ordonnance,
            ];
            try {
                $pdfBinary = buildOrdonnancePdfFromWordTemplate($ordonnancePayload);
            } catch (Throwable) {
                $pdfBinary = buildMedicalDocumentPdf($ordonnancePayload);
            }
            $generatedDocuments[] = [
                'document_type' => 'ordonnance',
                'original_name' => buildMedicalDocumentFilename('ordonnance', $appointment),
                'binary'        => $pdfBinary,
            ];
        }

        if ($certificat !== '') {
            $generatedDocuments[] = [
                'document_type' => 'certificat',
                'original_name' => buildMedicalDocumentFilename('certificat', $appointment),
                'binary'        => buildMedicalDocumentPdf([
                    'template'          => 'certificat',
                    'title'             => 'Certificat medical',
                    'subtitle'          => 'Document emis a la fin de la teleconsultation',
                    'doctor_name'       => (string) ($appointment['doctor']['display_name'] ?? 'Dr. Medecin'),
                    'doctor_specialite' => (string) ($appointment['doctor']['specialite'] ?? ''),
                    'doctor_adresse'    => (string) ($appointment['doctor']['adresse'] ?? ''),
                    'doctor_agrement'   => (string) ($appointment['doctor']['agrement_no'] ?? ''),
                    'doctor_telephone'  => (string) ($appointment['doctor']['telephone'] ?? ''),
                    'doctor_email'      => (string) ($appointment['doctor']['email'] ?? ''),
                    'patient_name'      => (string) ($appointment['patient']['display_name'] ?? 'Patient'),
                    'patient_relation'  => (string) ($appointment['patient']['relation'] ?? 'Patient'),
                    'patient_birthdate' => (string) ($appointment['patient']['date_naissance'] ?? ''),
                    'appointment_date'  => !empty($appointment['date_heure']) ? date('d/m/Y H:i', strtotime((string) $appointment['date_heure'])) : '',
                    'reference'         => (string) ($appointment['confirmation_code'] ?? ''),
                    'issued_at'         => date('d/m/Y H:i'),
                    'body'              => $certificat,
                ]),
            ];
        }

        $createdPaths = [];
        try {
            $pdo->beginTransaction();

            // Mettre à jour le texte dans appointments
            $stmt = $pdo->prepare(
                "UPDATE appointments
                 SET teleconsultation_ordonnance_text = :ordonnance,
                     teleconsultation_certificat_text = :certificat,
                     updated_at = NOW()
                 WHERE id = :id AND medecin_id = :doctor_id AND consultation_mode = 'teleconsultation'"
            );
            $stmt->execute([
                'ordonnance' => $ordonnance !== '' ? $ordonnance : null,
                'certificat' => $certificat !== '' ? $certificat : null,
                'id'         => $id,
                'doctor_id'  => $doctorId,
            ]);

            // Supprimer les anciens PDFs générés pour éviter les doublons
            $typesToDelete = array_map(static fn(array $d): string => $d['document_type'], $generatedDocuments);
            if ($typesToDelete) {
                $oldPaths = deleteGeneratedMedicalDocuments($pdo, $id, $typesToDelete);
            }

            // Enregistrer les nouveaux PDFs
            foreach ($generatedDocuments as $document) {
                $saved = persistGeneratedMedicalPdf(
                    $pdo,
                    $id,
                    (string) $document['document_type'],
                    (string) $document['original_name'],
                    (string) $document['binary']
                );
                $createdPaths[] = (string) $saved['absolute_path'];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($createdPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Impossible d\'enregistrer l\'ordonnance';
            jsonResponse(['error' => $message], 422);
        }

        $item = $fetchAppointment($pdo, $doctorId, $id);
        jsonResponse(['message' => 'Ordonnance enregistree', 'data' => $item]);
    }

    if ($action !== 'complete') {
        jsonResponse(['error' => 'Action invalide'], 422);
    }

    $summary = trim((string) ($body['summary'] ?? ''));
    $ordonnance = trim((string) ($body['ordonnance_text'] ?? ''));
    $certificat = trim((string) ($body['certificat_text'] ?? ''));
    if ($summary === '') {
        jsonResponse(['error' => 'Le resume medical est requis'], 422);
    }

    $appointment = $fetchAppointment($pdo, $doctorId, $id);
    if (!$appointment) {
        jsonResponse(['error' => 'Teleconsultation introuvable'], 404);
    }

    $generatedDocuments = [];
    if ($ordonnance !== '') {
        $ordonnancePayload = [
            'template' => 'ordonnance',
            'title' => 'Ordonnance medicale',
            'subtitle' => 'Prescription remise a la fin de la teleconsultation',
            'doctor_name' => (string) ($appointment['doctor']['display_name'] ?? 'Dr. Medecin'),
            'doctor_specialite' => (string) ($appointment['doctor']['specialite'] ?? ''),
            'doctor_adresse' => (string) ($appointment['doctor']['adresse'] ?? ''),
            'doctor_agrement' => (string) ($appointment['doctor']['agrement_no'] ?? ''),
            'doctor_telephone' => (string) ($appointment['doctor']['telephone'] ?? ''),
            'doctor_email' => (string) ($appointment['doctor']['email'] ?? ''),
            'doctor_secondary_line' => (string) (($appointment['doctor']['agrement_no'] ?? '') !== '' ? $appointment['doctor']['agrement_no'] : 'Prescription remise a la fin de la teleconsultation'),
            'patient_name' => (string) ($appointment['patient']['display_name'] ?? 'Patient'),
            'patient_relation' => (string) ($appointment['patient']['relation'] ?? 'Patient'),
            'patient_birthdate' => (string) ($appointment['patient']['date_naissance'] ?? ''),
            'patient_age' => medicalPdfAgeLabel((string) ($appointment['patient']['date_naissance'] ?? '')),
            'appointment_date' => !empty($appointment['date_heure']) ? date('d/m/Y H:i', strtotime((string) $appointment['date_heure'])) : '',
            'reference' => (string) ($appointment['confirmation_code'] ?? ''),
            'issued_at' => date('d/m/Y H:i'),
            'city_label' => '',
            'body' => $ordonnance,
        ];

        try {
            $ordonnanceBinary = buildOrdonnancePdfFromWordTemplate($ordonnancePayload);
        } catch (Throwable) {
            $ordonnanceBinary = buildMedicalDocumentPdf($ordonnancePayload);
        }

        $generatedDocuments[] = [
            'document_type' => 'ordonnance',
            'original_name' => buildMedicalDocumentFilename('ordonnance', $appointment),
            'binary' => $ordonnanceBinary,
        ];
    }

    if ($certificat !== '') {
        $generatedDocuments[] = [
            'document_type' => 'certificat',
            'original_name' => buildMedicalDocumentFilename('certificat', $appointment),
            'binary' => buildMedicalDocumentPdf([
                'template' => 'certificat',
                'title' => 'Certificat medical',
                'subtitle' => 'Document emis a la fin de la teleconsultation',
                'doctor_name' => (string) ($appointment['doctor']['display_name'] ?? 'Dr. Medecin'),
                'doctor_specialite' => (string) ($appointment['doctor']['specialite'] ?? ''),
                'doctor_adresse' => (string) ($appointment['doctor']['adresse'] ?? ''),
                'doctor_agrement' => (string) ($appointment['doctor']['agrement_no'] ?? ''),
                'doctor_telephone' => (string) ($appointment['doctor']['telephone'] ?? ''),
                'doctor_email' => (string) ($appointment['doctor']['email'] ?? ''),
                'patient_name' => (string) ($appointment['patient']['display_name'] ?? 'Patient'),
                'patient_relation' => (string) ($appointment['patient']['relation'] ?? 'Patient'),
                'patient_birthdate' => (string) ($appointment['patient']['date_naissance'] ?? ''),
                'appointment_date' => !empty($appointment['date_heure']) ? date('d/m/Y H:i', strtotime((string) $appointment['date_heure'])) : '',
                'reference' => (string) ($appointment['confirmation_code'] ?? ''),
                'issued_at' => date('d/m/Y H:i'),
                'body' => $certificat,
            ]),
        ];
    }

    $createdPaths = [];
    $oldPaths = [];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE appointments
             SET statut = 'termine',
                 teleconsultation_status = 'completed',
                 teleconsultation_ended_at = NOW(),
                 teleconsultation_summary = :summary,
                 teleconsultation_ordonnance_text = :ordonnance,
                 teleconsultation_certificat_text = :certificat,
                 updated_at = NOW()
             WHERE id = :id AND medecin_id = :doctor_id AND consultation_mode = 'teleconsultation'"
        );
        $stmt->execute([
            'summary' => $summary,
            'ordonnance' => $ordonnance !== '' ? $ordonnance : null,
            'certificat' => $certificat !== '' ? $certificat : null,
            'id' => $id,
            'doctor_id' => $doctorId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Teleconsultation introuvable');
        }

        $oldPaths = deleteGeneratedMedicalDocuments($pdo, $id, ['ordonnance', 'certificat']);
        foreach ($generatedDocuments as $document) {
            $saved = persistGeneratedMedicalPdf(
                $pdo,
                $id,
                (string) $document['document_type'],
                (string) $document['original_name'],
                (string) $document['binary']
            );
            $createdPaths[] = (string) $saved['absolute_path'];
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($createdPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $message = $e instanceof RuntimeException ? $e->getMessage() : 'Impossible de terminer la teleconsultation';
        $status = $message === 'Teleconsultation introuvable' ? 404 : 422;
        jsonResponse(['error' => $message], $status);
    }

    foreach ($oldPaths as $path) {
        if (in_array($path, $createdPaths, true)) {
            continue;
        }
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $item = $fetchAppointment($pdo, $doctorId, $id);
    jsonResponse(['message' => 'Teleconsultation terminee', 'data' => $item]);
}

jsonResponse(['error' => 'Methode non autorisee'], 405);
