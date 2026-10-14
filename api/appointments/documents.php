<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';
require_once __DIR__ . '/../_core/appointment_service.php';

assertMethod('GET', 'POST', 'DELETE');

$pdo = db();
$user = requireAuth();
$userId = (int) $user['id'];
$userRole = (string) $user['role'];
if (!in_array($userRole, ['patient', 'medecin'], true)) {
    jsonResponse(['error' => 'Acces interdit'], 403);
}

function normalizeAppointmentDocumentType(?string $rawType, string $role): string
{
    $value = trim((string) $rawType);
    $patientTypes = ['patient_document'];
    $doctorTypes = ['ordonnance', 'certificat', 'compte_rendu', 'document_medical'];

    if ($role === 'patient') {
        return in_array($value, $patientTypes, true) ? $value : 'patient_document';
    }

    return in_array($value, $doctorTypes, true) ? $value : 'document_medical';
}

function findAuthorizedAppointment(PDO $pdo, int $appointmentId, int $userId, string $role): ?array
{
    if ($role === 'patient') {
        $stmt = $pdo->prepare(
            'SELECT id, patient_id, medecin_id, consultation_mode, statut
             FROM appointments
             WHERE id = :id AND patient_id = :user_id
             LIMIT 1'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, patient_id, medecin_id, consultation_mode, statut
             FROM appointments
             WHERE id = :id AND medecin_id = :user_id
             LIMIT 1'
        );
    }
    $stmt->execute([
        'id' => $appointmentId,
        'user_id' => $userId,
    ]);

    return firstOrNull($stmt);
}

function formatDocumentRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'appointment_id' => (int) $row['appointment_id'],
        'original_name' => (string) $row['original_name'],
        'file_url' => (string) $row['file_url'],
        'mime_type' => (string) ($row['mime_type'] ?? ''),
        'file_size' => (int) ($row['file_size'] ?? 0),
        'document_type' => (string) ($row['document_type'] ?? 'patient_document'),
        'uploaded_by_role' => (string) ($row['uploaded_by_role'] ?? 'patient'),
        'created_at' => !empty($row['created_at']) ? date(DATE_ATOM, strtotime((string) $row['created_at'])) : null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $appointmentId = isset($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;
    if ($appointmentId < 1) {
        jsonResponse(['error' => 'appointment_id invalide'], 400);
    }

    $appointment = findAuthorizedAppointment($pdo, $appointmentId, $userId, $userRole);
    if (!$appointment) {
        jsonResponse(['error' => 'Rendez-vous introuvable'], 404);
    }

    $stmt = $pdo->prepare(
        'SELECT id, appointment_id, original_name, file_url, mime_type, file_size, document_type, uploaded_by_role, created_at
         FROM appointment_documents
         WHERE appointment_id = :appointment_id
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute(['appointment_id' => $appointmentId]);

    jsonResponse([
        'data' => array_map('formatDocumentRow', $stmt->fetchAll()),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
    if ($appointmentId < 1) {
        jsonResponse(['error' => 'appointment_id invalide'], 400);
    }

    $appointment = findAuthorizedAppointment($pdo, $appointmentId, $userId, $userRole);
    if (!$appointment) {
        jsonResponse(['error' => 'Rendez-vous introuvable'], 404);
    }
    if (($appointment['consultation_mode'] ?? 'cabinet') !== 'teleconsultation') {
        jsonResponse(['error' => 'Ce rendez-vous ne supporte pas les documents de teleconsultation'], 422);
    }
    if ($userRole === 'patient' && in_array((string) ($appointment['statut'] ?? ''), ['annule', 'termine'], true)) {
        jsonResponse(['error' => 'Les documents sont bloques pour ce rendez-vous'], 409);
    }
    if ($userRole === 'medecin' && (string) ($appointment['statut'] ?? '') === 'annule') {
        jsonResponse(['error' => 'Les documents sont bloques pour ce rendez-vous annule'], 409);
    }

    $files = appointmentNormalizeUploadedFiles($_FILES['document'] ?? []);
    if (!$files) {
        jsonResponse(['error' => 'Aucun document recu'], 422);
    }
    $documentType = normalizeAppointmentDocumentType($_POST['document_type'] ?? null, $userRole);

    try {
        $saved = appointmentStoreDocuments($pdo, $appointmentId, $files, [
            'document_type' => $documentType,
            'uploaded_by_role' => $userRole,
            'max_file_size_bytes' => 10 * 1024 * 1024,
            'size_message' => 'Chaque document doit faire moins de 10 Mo.',
            'upload_error_message' => 'Le document n\'a pas pu etre televerse.',
            'invalid_format_message' => 'Format non supporte. Utilisez PDF, JPG ou PNG.',
            'persist_error_message' => 'Impossible d\'enregistrer le document.',
        ]);
        if (!$saved) {
            jsonResponse(['error' => 'Aucun document valide a ete televerse'], 422);
        }
        $saved = array_map(
            static function (array $document): array {
                unset($document['absolute_path'], $document['stored_name']);
                return $document;
            },
            $saved
        );
        jsonResponse([
            'message' => 'Document ajoute',
            'data' => $saved,
        ], 201);
    } catch (AppointmentServiceException $e) {
        jsonResponse(['error' => $e->getMessage()], $e->status());
    } catch (Throwable $e) {
        jsonResponse(['error' => $e->getMessage()], 422);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $documentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($documentId < 1) {
        jsonResponse(['error' => 'id invalide'], 400);
    }

    $stmt = $pdo->prepare(
        'SELECT d.id, d.original_name, d.stored_name, d.file_url, d.appointment_id, d.uploaded_by_role, a.statut, a.patient_id, a.medecin_id
         FROM appointment_documents d
         INNER JOIN appointments a ON a.id = d.appointment_id
         WHERE d.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $documentId]);
    $document = firstOrNull($stmt);
    if (!$document) {
        jsonResponse(['error' => 'Document introuvable'], 404);
    }
    $isAllowed = false;
    if ($userRole === 'patient') {
        $isAllowed = (int) $document['patient_id'] === $userId && (string) ($document['uploaded_by_role'] ?? 'patient') === 'patient';
        if ($isAllowed && in_array((string) ($document['statut'] ?? ''), ['annule', 'termine'], true)) {
            jsonResponse(['error' => 'Suppression impossible pour ce rendez-vous'], 409);
        }
    } elseif ($userRole === 'medecin') {
        $isAllowed = (int) $document['medecin_id'] === $userId && (string) ($document['uploaded_by_role'] ?? '') === 'medecin';
        if ($isAllowed && (string) ($document['statut'] ?? '') === 'annule') {
            jsonResponse(['error' => 'Suppression impossible pour ce rendez-vous'], 409);
        }
    }
    if (!$isAllowed) {
        jsonResponse(['error' => 'Suppression non autorisee'], 403);
    }

    $storedName = trim((string) ($document['stored_name'] ?? ''));
    if ($storedName === '') {
        $storedName = basename((string) ($document['file_url'] ?? ''));
    }
    $absolutePath = dirname(__DIR__, 2) . '/uploads/appointments/' . $storedName;

    $pdo->prepare('DELETE FROM appointment_documents WHERE id = :id')->execute(['id' => $documentId]);
    if ($storedName !== '' && is_file($absolutePath)) {
        @unlink($absolutePath);
    }

    jsonResponse([
        'message' => 'Document supprime',
        'data' => [
            'id' => $documentId,
            'appointment_id' => (int) $document['appointment_id'],
        ],
    ]);
}
