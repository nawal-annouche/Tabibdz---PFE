<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

final class AppointmentServiceException extends RuntimeException
{
    private int $status;

    public function __construct(string $message, int $status = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
    }

    public function status(): int
    {
        return $this->status;
    }
}

function appointmentNormalizeNullableString(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function appointmentGetCurrentPatientRow(PDO $pdo, int $patientId): ?array
{
    $stmt = $pdo->prepare('SELECT id, prenom, nom, telephone, email, date_naissance
                           FROM users
                           WHERE id = :id AND role = "patient"
                           LIMIT 1');
    $stmt->execute(['id' => $patientId]);
    return firstOrNull($stmt);
}

function appointmentGetFamilyMemberRow(PDO $pdo, int $patientId, int $memberId): ?array
{
    $stmt = $pdo->prepare('SELECT *
                           FROM patient_family_members
                           WHERE id = :id AND patient_id = :patient_id
                           LIMIT 1');
    $stmt->execute([
        'id' => $memberId,
        'patient_id' => $patientId,
    ]);
    return firstOrNull($stmt);
}

function appointmentFamilyMemberRelationLabel(?array $row): ?string
{
    if (!$row) {
        return null;
    }

    if (!empty($row['relation'])) {
        return (string) $row['relation'];
    }

    $legacy = (string) ($row['lien_parent'] ?? '');
    return match ($legacy) {
        'enfant' => 'Enfant',
        'conjoint' => 'Conjoint',
        'mere' => 'Mere',
        'pere' => 'Pere',
        'frere' => 'Frere',
        'soeur' => 'Soeur',
        default => null,
    };
}

function appointmentBuildPatientProfile(array $row): array
{
    $prenom = trim((string) ($row['patient_prenom'] ?? ''));
    $nom = trim((string) ($row['patient_nom'] ?? ''));
    $displayName = trim($prenom . ' ' . $nom);
    $targetType = (string) ($row['patient_target_type'] ?? '');
    if ($targetType === '') {
        $targetType = !empty($row['family_member_id']) ? 'family' : ((string) ($row['beneficiary_type'] ?? '') === 'family' ? 'family' : 'self');
    }

    return [
        'type' => $targetType,
        'family_member_id' => $row['family_member_id'] !== null ? (int) $row['family_member_id'] : null,
        'display_name' => $displayName !== '' ? $displayName : 'Patient',
        'prenom' => $prenom,
        'nom' => $nom,
        'relation' => $row['patient_relation'] ?: ($targetType === 'family' ? 'Membre de la famille' : 'Moi-meme'),
        'date_naissance' => !empty($row['patient_date_naissance']) ? date('Y-m-d', strtotime((string) $row['patient_date_naissance'])) : null,
        'telephone' => $row['patient_telephone'] ?: null,
        'email' => $row['patient_email'] ?: null,
    ];
}

function appointmentNormalizeUploadedFiles(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function appointmentStoreDocuments(PDO $pdo, int $appointmentId, array $files, array $options = []): array
{
    if (!$files) {
        return [];
    }

    $allowedMimeTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $maxFileSizeBytes = (int) ($options['max_file_size_bytes'] ?? (5 * 1024 * 1024));
    $sizeMessage = (string) ($options['size_message'] ?? 'Chaque document doit faire moins de 5 Mo.');
    $uploadErrorMessage = (string) ($options['upload_error_message'] ?? 'Un document n\'a pas pu etre televerse.');
    $invalidFormatMessage = (string) ($options['invalid_format_message'] ?? 'Format de document non supporte. Utilisez PDF, JPG ou PNG.');
    $persistErrorMessage = (string) ($options['persist_error_message'] ?? 'Impossible d\'enregistrer un document.');
    $documentType = isset($options['document_type']) ? appointmentNormalizeNullableString($options['document_type']) : null;
    $uploadedByRole = isset($options['uploaded_by_role']) ? appointmentNormalizeNullableString($options['uploaded_by_role']) : null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $targetDir = dirname(__DIR__, 2) . '/uploads/appointments';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new AppointmentServiceException('Impossible de creer le dossier des documents.', 500);
    }

    if ($documentType !== null && $uploadedByRole !== null) {
        $insert = $pdo->prepare(
            'INSERT INTO appointment_documents (
                appointment_id, original_name, stored_name, file_url, mime_type, file_size, document_type, uploaded_by_role
            ) VALUES (
                :appointment_id, :original_name, :stored_name, :file_url, :mime_type, :file_size, :document_type, :uploaded_by_role
            )'
        );
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO appointment_documents (
                appointment_id, original_name, stored_name, file_url, mime_type, file_size
            ) VALUES (
                :appointment_id, :original_name, :stored_name, :file_url, :mime_type, :file_size
            )'
        );
    }

    $saved = [];
    try {
        foreach ($files as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new AppointmentServiceException($uploadErrorMessage, 422);
            }

            $size = (int) ($file['size'] ?? 0);
            if ($size < 1 || $size > $maxFileSizeBytes) {
                throw new AppointmentServiceException($sizeMessage, 422);
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            $mimeType = $finfo->file($tmpName) ?: '';
            if (!isset($allowedMimeTypes[$mimeType])) {
                throw new AppointmentServiceException($invalidFormatMessage, 422);
            }

            $originalName = basename((string) ($file['name'] ?? 'document'));
            $storedName = sprintf(
                'appointment_%d_%s.%s',
                $appointmentId,
                bin2hex(random_bytes(8)),
                $allowedMimeTypes[$mimeType]
            );
            $targetPath = $targetDir . '/' . $storedName;
            if (!move_uploaded_file($tmpName, $targetPath)) {
                throw new AppointmentServiceException($persistErrorMessage, 500);
            }

            $params = [
                'appointment_id' => $appointmentId,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'file_url' => '/uploads/appointments/' . $storedName,
                'mime_type' => $mimeType,
                'file_size' => $size,
            ];
            if ($documentType !== null && $uploadedByRole !== null) {
                $params['document_type'] = $documentType;
                $params['uploaded_by_role'] = $uploadedByRole;
            }

            $insert->execute($params);

            $saved[] = [
                'id' => (int) $pdo->lastInsertId(),
                'appointment_id' => $appointmentId,
                'original_name' => $originalName,
                'file_url' => $params['file_url'],
                'mime_type' => $mimeType,
                'file_size' => $size,
                'document_type' => $documentType,
                'uploaded_by_role' => $uploadedByRole,
                'created_at' => date(DATE_ATOM),
                'stored_name' => $storedName,
                'absolute_path' => $targetPath,
            ];
        }
    } catch (Throwable $e) {
        foreach ($saved as $document) {
            if (!empty($document['absolute_path']) && is_file((string) $document['absolute_path'])) {
                @unlink((string) $document['absolute_path']);
            }
        }

        if ($e instanceof AppointmentServiceException) {
            throw $e;
        }

        throw new AppointmentServiceException('Impossible de traiter les documents du rendez-vous.', 500, $e);
    }

    return $saved;
}

function appointmentHasPatientScheduleConflict(
    PDO $pdo,
    int $patientId,
    string $patientTargetType,
    ?int $familyMemberId,
    string $startAt,
    int $durationMinutes,
    ?int $excludeAppointmentId = null
): bool {
    $sql = 'SELECT id
            FROM appointments
            WHERE patient_id = :patient_id
              AND statut = "confirme"
              AND patient_target_type = :patient_target_type
              AND ';

    $params = [
        'patient_id' => $patientId,
        'patient_target_type' => $patientTargetType,
        'start_at' => $startAt,
        'end_at' => date('Y-m-d H:i:s', strtotime($startAt . ' +' . $durationMinutes . ' minutes')),
    ];

    if ($patientTargetType === 'family') {
        $sql .= 'family_member_id <=> :family_member_id';
        $params['family_member_id'] = $familyMemberId;
    } else {
        $sql .= 'family_member_id IS NULL';
    }

    $sql .= '
              AND date_heure < :end_at
              AND DATE_ADD(date_heure, INTERVAL duree_min MINUTE) > :start_at';

    if ($excludeAppointmentId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeAppointmentId;
    }

    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return firstOrNull($stmt) !== null;
}

function appointmentLockSlotById(PDO $pdo, int $slotId, int $notFoundStatus = 404, string $notFoundMessage = 'Creneau introuvable'): array
{
    $stmt = $pdo->prepare('SELECT id, medecin_id, start_at, end_at, is_available
                           FROM creneaux
                           WHERE id = :id
                           FOR UPDATE');
    $stmt->execute(['id' => $slotId]);
    $slot = firstOrNull($stmt);
    if (!$slot) {
        throw new AppointmentServiceException($notFoundMessage, $notFoundStatus);
    }

    return $slot;
}

function appointmentLockSlotByDoctorAndStart(PDO $pdo, int $medecinId, string $startAt, int $notFoundStatus = 409, string $notFoundMessage = 'Creneau introuvable'): array
{
    $stmt = $pdo->prepare('SELECT id, medecin_id, start_at, end_at, is_available
                           FROM creneaux
                           WHERE medecin_id = :mid AND start_at = :start_at
                           FOR UPDATE');
    $stmt->execute([
        'mid' => $medecinId,
        'start_at' => $startAt,
    ]);
    $slot = firstOrNull($stmt);
    if (!$slot) {
        throw new AppointmentServiceException($notFoundMessage, $notFoundStatus);
    }

    return $slot;
}

function appointmentAssertSlotBelongsToDoctor(array $slot, int $medecinId, string $message): void
{
    if ((int) ($slot['medecin_id'] ?? 0) !== $medecinId) {
        throw new AppointmentServiceException($message, 422);
    }
}

function appointmentAssertSlotAvailable(array $slot, string $message): void
{
    if ((int) ($slot['is_available'] ?? 0) !== 1) {
        throw new AppointmentServiceException($message, 409);
    }
}

function appointmentAssertSlotUnoccupied(PDO $pdo, int $slotId, string $message): void
{
    $stmt = $pdo->prepare('SELECT id
                           FROM appointments
                           WHERE creneau_id = :creneau_id AND statut = "confirme"
                           LIMIT 1
                           FOR UPDATE');
    $stmt->execute(['creneau_id' => $slotId]);
    if (firstOrNull($stmt)) {
        throw new AppointmentServiceException($message, 409);
    }
}

function appointmentReserveSlot(PDO $pdo, int $slotId, string $message = 'Ce creneau n\'est plus disponible'): void
{
    $stmt = $pdo->prepare('UPDATE creneaux
                           SET is_available = 0
                           WHERE id = :id AND is_available = 1');
    $stmt->execute(['id' => $slotId]);
    if ($stmt->rowCount() !== 1) {
        throw new AppointmentServiceException($message, 409);
    }
}

function appointmentReleaseSlot(PDO $pdo, int $slotId): void
{
    $stmt = $pdo->prepare('UPDATE creneaux
                           SET is_available = 1
                           WHERE id = :id');
    $stmt->execute(['id' => $slotId]);
}

function appointmentSlotDurationMinutes(array $slot): int
{
    $durationSeconds = max(60, strtotime((string) ($slot['end_at'] ?? '')) - strtotime((string) ($slot['start_at'] ?? '')));
    return max(10, (int) ceil($durationSeconds / 60));
}

function appointmentSlotStartAt(array $slot): string
{
    return date('Y-m-d H:i:s', strtotime((string) ($slot['start_at'] ?? 'now')));
}
