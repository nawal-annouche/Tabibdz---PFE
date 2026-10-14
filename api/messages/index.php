<?php
declare(strict_types=1);

require_once __DIR__ . '/../_core/helpers.php';

$user = requireAuth();
if (!in_array($user['role'], ['patient', 'medecin'], true)) {
    jsonResponse(['error' => 'Acces interdit'], 403);
}

$pdo = db();
$userId = (int) $user['id'];
$userRole = (string) $user['role'];

$context = isset($_GET['context']) ? trim((string) $_GET['context']) : null;
if ($context !== null && $context !== '') {
    if (!in_array($context, ['patient', 'medecin'], true)) {
        jsonResponse(['error' => 'Contexte de messagerie invalide'], 422);
    }
    if ($context !== $userRole) {
        jsonResponse([
            'error' => 'Session incompatible avec cet espace',
            'expected_role' => $context,
            'current_role' => $userRole,
        ], 403);
    }
}

function normalizeMessageBody($value): string
{
    $text = trim((string) $value);
    $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
    return trim($text);
}

function normalizeUploadedMessageFile(array $file): ?array
{
    if (!isset($file['name'])) {
        return null;
    }

    return [
        'name' => $file['name'] ?? '',
        'type' => $file['type'] ?? '',
        'tmp_name' => $file['tmp_name'] ?? '',
        'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE,
        'size' => $file['size'] ?? 0,
    ];
}

function attachmentKindLabel(string $kind, ?string $name = null): string
{
    if ($kind === 'image') {
        return 'Photo';
    }
    if ($kind === 'audio') {
        return 'Vocal';
    }
    if ($name !== null && trim($name) !== '') {
        return 'Fichier : ' . trim($name);
    }
    return 'Fichier';
}

function buildMessagePreviewText(string $body, array $attachments): string
{
    $body = normalizeMessageBody($body);
    if ($body !== '') {
        return $body;
    }
    if (!$attachments) {
        return '';
    }

    $first = $attachments[0];
    $preview = attachmentKindLabel((string) ($first['kind'] ?? 'file'), (string) ($first['original_name'] ?? ''));
    $extraCount = count($attachments) - 1;
    if ($extraCount > 0) {
        $preview .= ' +' . $extraCount;
    }
    return $preview;
}

function formatMessageAttachment(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'message_id' => (int) $row['message_id'],
        'kind' => (string) $row['kind'],
        'original_name' => (string) $row['original_name'],
        'file_url' => (string) $row['file_url'],
        'mime_type' => (string) ($row['mime_type'] ?? ''),
        'file_size' => (int) ($row['file_size'] ?? 0),
        'created_at' => !empty($row['created_at']) ? date(DATE_ATOM, strtotime((string) $row['created_at'])) : null,
    ];
}

function loadMessageAttachmentsMap(PDO $pdo, array $messageIds): array
{
    if (!$messageIds) {
        return [];
    }

    $messageIds = array_values(array_unique(array_map('intval', $messageIds)));
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, message_id, kind, original_name, stored_name, file_url, mime_type, file_size, created_at
         FROM message_attachments
         WHERE message_id IN ({$placeholders})
         ORDER BY id ASC"
    );
    $stmt->execute($messageIds);

    $map = [];
    while ($row = $stmt->fetch()) {
        $messageId = (int) $row['message_id'];
        $map[$messageId] ??= [];
        $map[$messageId][] = formatMessageAttachment($row);
    }

    return $map;
}

function storeMessageAttachment(PDO $pdo, int $messageId, array $file, ?string $requestedKind = null): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Aucune piece jointe recue.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('La piece jointe n\'a pas pu etre televersee.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Fichier temporaire invalide.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 12 * 1024 * 1024) {
        throw new RuntimeException('Chaque piece jointe doit faire moins de 12 Mo.');
    }

    $allowedKinds = ['image', 'file', 'audio'];
    $requestedKind = $requestedKind !== null ? trim($requestedKind) : null;
    if ($requestedKind !== null && $requestedKind !== '' && !in_array($requestedKind, $allowedKinds, true)) {
        throw new RuntimeException('Type de piece jointe invalide.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName) ?: '';

    $allowedMimeTypes = [
        'image' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ],
        'audio' => [
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'video/webm' => 'webm',
        ],
        'file' => [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ],
    ];

    $detectedKind = null;
    $extension = null;
    foreach ($allowedMimeTypes as $kind => $mimeMap) {
        if (isset($mimeMap[$mimeType])) {
            $detectedKind = $kind;
            $extension = $mimeMap[$mimeType];
            break;
        }
    }

    if ($detectedKind === null || $extension === null) {
        throw new RuntimeException('Format de fichier non supporte.');
    }
    if ($requestedKind !== null && $requestedKind !== '' && $requestedKind !== $detectedKind) {
        throw new RuntimeException('Le type de piece jointe ne correspond pas au fichier envoye.');
    }

    $originalName = basename((string) ($file['name'] ?? 'piece-jointe'));
    $targetDir = dirname(__DIR__, 2) . '/uploads/messages';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Impossible de creer le dossier des messages.');
    }

    $storedName = sprintf(
        'message_%d_%s.%s',
        $messageId,
        bin2hex(random_bytes(8)),
        $extension
    );
    $targetPath = $targetDir . '/' . $storedName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Impossible d\'enregistrer la piece jointe.');
    }

    $fileUrl = '/uploads/messages/' . $storedName;
    $insert = $pdo->prepare(
        'INSERT INTO message_attachments (
            message_id, kind, original_name, stored_name, file_url, mime_type, file_size
        ) VALUES (
            :message_id, :kind, :original_name, :stored_name, :file_url, :mime_type, :file_size
        )'
    );
    $insert->execute([
        'message_id' => $messageId,
        'kind' => $detectedKind,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'file_url' => $fileUrl,
        'mime_type' => $mimeType,
        'file_size' => $size,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'message_id' => $messageId,
        'kind' => $detectedKind,
        'original_name' => $originalName,
        'file_url' => $fileUrl,
        'mime_type' => $mimeType,
        'file_size' => $size,
        'created_at' => date(DATE_ATOM),
        'absolute_path' => $targetPath,
    ];
}

function fetchEligiblePeers(PDO $pdo, int $userId, string $role): array
{
    if ($role === 'patient') {
        $stmt = $pdo->prepare(
            "SELECT u.id,
                    u.prenom,
                    u.nom,
                    u.photo_url,
                    u.commune,
                    w.nom AS wilaya,
                    s.nom AS specialite,
                    MAX(a.date_heure) AS last_appointment_at
             FROM appointments a
             INNER JOIN users u ON u.id = a.medecin_id AND u.role = 'medecin'
             LEFT JOIN wilayas w ON w.id = u.wilaya_id
             LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
             LEFT JOIN specialites s ON s.id = mp.specialite_id
             WHERE a.patient_id = :id
               AND a.statut = 'termine'
             GROUP BY u.id, u.prenom, u.nom, u.photo_url, u.commune, w.nom, s.nom
             ORDER BY last_appointment_at DESC, u.nom ASC, u.prenom ASC"
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    $stmt = $pdo->prepare(
        "SELECT u.id,
                u.prenom,
                u.nom,
                u.photo_url,
                u.commune,
                w.nom AS wilaya,
                NULL AS specialite,
                MAX(a.date_heure) AS last_appointment_at
         FROM appointments a
         INNER JOIN users u ON u.id = a.patient_id AND u.role = 'patient'
         LEFT JOIN wilayas w ON w.id = u.wilaya_id
         WHERE a.medecin_id = :id
           AND a.statut = 'termine'
         GROUP BY u.id, u.prenom, u.nom, u.photo_url, u.commune, w.nom
         ORDER BY last_appointment_at DESC, u.nom ASC, u.prenom ASC"
    );
    $stmt->execute(['id' => $userId]);
    return $stmt->fetchAll() ?: [];
}

function resolveThread(PDO $pdo, int $patientId, int $medecinId): array
{
    $select = $pdo->prepare('SELECT id, patient_id, medecin_id, created_at, updated_at, last_message_at FROM message_threads WHERE patient_id = :patient_id AND medecin_id = :medecin_id LIMIT 1');
    $select->execute([
        'patient_id' => $patientId,
        'medecin_id' => $medecinId,
    ]);
    $thread = firstOrNull($select);
    if ($thread) {
        return $thread;
    }

    $insert = $pdo->prepare('INSERT INTO message_threads (patient_id, medecin_id, last_message_at) VALUES (:patient_id, :medecin_id, NULL)');
    $insert->execute([
        'patient_id' => $patientId,
        'medecin_id' => $medecinId,
    ]);

    $select->execute([
        'patient_id' => $patientId,
        'medecin_id' => $medecinId,
    ]);
    $thread = firstOrNull($select);
    if (!$thread) {
        throw new RuntimeException('Conversation introuvable apres creation');
    }
    return $thread;
}

function serializeConversation(array $peer, ?array $thread, int $unreadCount, ?array $lastMessage, string $role): array
{
    return [
        'peer' => [
            'id' => (int) $peer['id'],
            'role' => $role === 'patient' ? 'medecin' : 'patient',
            'prenom' => (string) ($peer['prenom'] ?? ''),
            'nom' => (string) ($peer['nom'] ?? ''),
            'photo_url' => $peer['photo_url'] ?? null,
            'commune' => $peer['commune'] ?? null,
            'wilaya' => $peer['wilaya'] ?? null,
            'specialite' => $peer['specialite'] ?? null,
        ],
        'thread_id' => $thread ? (int) $thread['id'] : null,
        'last_appointment_at' => !empty($peer['last_appointment_at']) ? date(DATE_ATOM, strtotime((string) $peer['last_appointment_at'])) : null,
        'last_message' => $lastMessage ? [
            'id' => (int) $lastMessage['id'],
            'body' => (string) $lastMessage['body'],
            'preview' => (string) ($lastMessage['preview'] ?? buildMessagePreviewText((string) $lastMessage['body'], $lastMessage['attachments'] ?? [])),
            'created_at' => date(DATE_ATOM, strtotime((string) $lastMessage['created_at'])),
            'sender_role' => (string) $lastMessage['sender_role'],
            'attachments' => $lastMessage['attachments'] ?? [],
        ] : null,
        'unread_count' => $unreadCount,
    ];
}

function loadThreadMessages(PDO $pdo, int $threadId, string $viewerRole): array
{
    $stmt = $pdo->prepare(
        "SELECT id, sender_user_id, sender_role, body, created_at
         FROM messages
         WHERE thread_id = :thread_id
         ORDER BY created_at ASC, id ASC
         LIMIT 200"
    );
    $stmt->execute(['thread_id' => $threadId]);
    $messages = [];
    $messageIds = [];
    while ($row = $stmt->fetch()) {
        $messageId = (int) $row['id'];
        $messages[] = [
            'id' => $messageId,
            'sender_user_id' => (int) $row['sender_user_id'],
            'sender_role' => (string) $row['sender_role'],
            'body' => (string) $row['body'],
            'created_at' => date(DATE_ATOM, strtotime((string) $row['created_at'])),
            'is_mine' => (string) $row['sender_role'] === $viewerRole,
        ];
        $messageIds[] = $messageId;
    }

    $attachmentMap = loadMessageAttachmentsMap($pdo, $messageIds);
    foreach ($messages as &$message) {
        $message['attachments'] = $attachmentMap[$message['id']] ?? [];
        $message['preview'] = buildMessagePreviewText((string) $message['body'], $message['attachments']);
    }
    unset($message);

    return $messages;
}

function markThreadAsRead(PDO $pdo, int $threadId, string $viewerRole): void
{
    $column = $viewerRole === 'patient' ? 'read_at_patient' : 'read_at_medecin';
    $pdo->prepare("UPDATE messages
                   SET {$column} = NOW()
                   WHERE thread_id = :thread_id
                     AND sender_role <> :viewer_role
                     AND {$column} IS NULL")
        ->execute([
            'thread_id' => $threadId,
            'viewer_role' => $viewerRole,
        ]);
}

$eligiblePeers = fetchEligiblePeers($pdo, $userId, $userRole);
$eligibleById = [];
foreach ($eligiblePeers as $peer) {
    $eligibleById[(int) $peer['id']] = $peer;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $isMultipart = !empty($_FILES) || str_contains($contentType, 'multipart/form-data');
    $body = $isMultipart ? $_POST : readJsonBody();
    $postContext = isset($body['context']) ? trim((string) $body['context']) : '';
    if ($postContext !== '') {
        if (!in_array($postContext, ['patient', 'medecin'], true)) {
            jsonResponse(['error' => 'Contexte de messagerie invalide'], 422);
        }
        if ($postContext !== $userRole) {
            jsonResponse([
                'error' => 'Session incompatible avec cet espace',
                'expected_role' => $postContext,
                'current_role' => $userRole,
            ], 403);
        }
    }

    $peerId = isset($body['peer_id']) ? (int) $body['peer_id'] : 0;
    $messageBody = normalizeMessageBody($body['body'] ?? '');
    $attachmentKind = isset($body['attachment_kind']) ? trim((string) $body['attachment_kind']) : null;
    $attachmentFile = $isMultipart ? normalizeUploadedMessageFile($_FILES['attachment'] ?? []) : null;

    if ($peerId < 1) {
        jsonResponse(['error' => 'peer_id requis'], 422);
    }
    if ($messageBody === '' && $attachmentFile === null) {
        jsonResponse(['error' => 'Message ou piece jointe requis'], 422);
    }
    if (mb_strlen($messageBody, 'UTF-8') > 2000) {
        jsonResponse(['error' => 'Message trop long'], 422);
    }
    if (!isset($eligibleById[$peerId])) {
        jsonResponse(['error' => 'Messagerie autorisee apres au moins un rendez-vous termine'], 403);
    }

    $patientId = $userRole === 'patient' ? $userId : $peerId;
    $medecinId = $userRole === 'medecin' ? $userId : $peerId;

    $pdo->beginTransaction();
    $savedAttachment = null;
    try {
        $thread = resolveThread($pdo, $patientId, $medecinId);
        $insert = $pdo->prepare(
            'INSERT INTO messages (thread_id, sender_user_id, sender_role, body, read_at_patient, read_at_medecin)
             VALUES (:thread_id, :sender_user_id, :sender_role, :body, :read_at_patient, :read_at_medecin)'
        );
        $insert->execute([
            'thread_id' => (int) $thread['id'],
            'sender_user_id' => $userId,
            'sender_role' => $userRole,
            'body' => $messageBody,
            'read_at_patient' => $userRole === 'patient' ? date('Y-m-d H:i:s') : null,
            'read_at_medecin' => $userRole === 'medecin' ? date('Y-m-d H:i:s') : null,
        ]);

        $messageId = (int) $pdo->lastInsertId();
        if ($attachmentFile !== null && (int) ($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $savedAttachment = storeMessageAttachment($pdo, $messageId, $attachmentFile, $attachmentKind);
        }
        $pdo->prepare('UPDATE message_threads SET last_message_at = NOW(), updated_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $thread['id']]);
        $pdo->commit();

        $attachments = [];
        if ($savedAttachment) {
            unset($savedAttachment['absolute_path']);
            $attachments[] = $savedAttachment;
        }
        jsonResponse([
            'message' => 'Message envoye',
            'thread_id' => (int) $thread['id'],
            'data' => [
                'id' => $messageId,
                'sender_user_id' => $userId,
                'sender_role' => $userRole,
                'body' => $messageBody,
                'created_at' => date(DATE_ATOM),
                'is_mine' => true,
                'attachments' => $attachments,
                'preview' => buildMessagePreviewText($messageBody, $attachments),
            ],
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($savedAttachment && !empty($savedAttachment['absolute_path']) && is_file((string) $savedAttachment['absolute_path'])) {
            @unlink((string) $savedAttachment['absolute_path']);
        }
        $status = $e instanceof RuntimeException ? 422 : 500;
        jsonResponse([
            'error' => $status === 422 ? $e->getMessage() : 'Envoi impossible',
            'details' => $status === 500 && appDebug() ? $e->getMessage() : null,
        ], $status);
    }
}

assertMethod('GET');

$threadMap = [];
$lastMessageMap = [];
$unreadMap = [];

if ($eligiblePeers) {
    $ids = array_map(static fn(array $peer): int => (int) $peer['id'], $eligiblePeers);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($userRole === 'patient') {
        $params = array_merge([$userId], $ids);
        $threadsStmt = $pdo->prepare(
            "SELECT id, patient_id, medecin_id, created_at, updated_at, last_message_at
             FROM message_threads
             WHERE patient_id = ? AND medecin_id IN ({$placeholders})"
        );
        $threadsStmt->execute($params);
    } else {
        $params = array_merge([$userId], $ids);
        $threadsStmt = $pdo->prepare(
            "SELECT id, patient_id, medecin_id, created_at, updated_at, last_message_at
             FROM message_threads
             WHERE medecin_id = ? AND patient_id IN ({$placeholders})"
        );
        $threadsStmt->execute($params);
    }

    $threadIds = [];
    while ($row = $threadsStmt->fetch()) {
        $peerId = $userRole === 'patient' ? (int) $row['medecin_id'] : (int) $row['patient_id'];
        $threadMap[$peerId] = $row;
        $threadIds[] = (int) $row['id'];
    }

    if ($threadIds) {
        $threadPlaceholders = implode(',', array_fill(0, count($threadIds), '?'));
        $messageRoleColumn = $userRole === 'patient' ? 'read_at_patient' : 'read_at_medecin';

        $lastStmt = $pdo->prepare(
            "SELECT m.thread_id, m.id, m.body, m.created_at, m.sender_role
             FROM messages m
             INNER JOIN (
                SELECT thread_id, MAX(id) AS max_id
                FROM messages
                WHERE thread_id IN ({$threadPlaceholders})
                GROUP BY thread_id
             ) latest ON latest.max_id = m.id"
        );
        $lastStmt->execute($threadIds);
        while ($row = $lastStmt->fetch()) {
            $lastMessageMap[(int) $row['thread_id']] = $row;
        }

        if ($lastMessageMap) {
            $lastAttachmentMap = loadMessageAttachmentsMap($pdo, array_map(
                static fn(array $row): int => (int) $row['id'],
                array_values($lastMessageMap)
            ));
            foreach ($lastMessageMap as &$lastRow) {
                $attachments = $lastAttachmentMap[(int) $lastRow['id']] ?? [];
                $lastRow['attachments'] = $attachments;
                $lastRow['preview'] = buildMessagePreviewText((string) $lastRow['body'], $attachments);
            }
            unset($lastRow);
        }

        $unreadStmt = $pdo->prepare(
            "SELECT thread_id, COUNT(*) AS unread_count
             FROM messages
             WHERE thread_id IN ({$threadPlaceholders})
               AND sender_role <> ?
               AND {$messageRoleColumn} IS NULL
             GROUP BY thread_id"
        );
        $unreadStmt->execute(array_merge($threadIds, [$userRole]));
        while ($row = $unreadStmt->fetch()) {
            $unreadMap[(int) $row['thread_id']] = (int) $row['unread_count'];
        }
    }
}

$conversations = [];
foreach ($eligiblePeers as $peer) {
    $peerId = (int) $peer['id'];
    $thread = $threadMap[$peerId] ?? null;
    $threadId = $thread ? (int) $thread['id'] : 0;
    $conversations[] = serializeConversation(
        $peer,
        $thread,
        $threadId ? ($unreadMap[$threadId] ?? 0) : 0,
        $threadId ? ($lastMessageMap[$threadId] ?? null) : null,
        $userRole
    );
}

usort($conversations, static function (array $a, array $b): int {
    $aTime = $a['last_message']['created_at'] ?? $a['last_appointment_at'] ?? '';
    $bTime = $b['last_message']['created_at'] ?? $b['last_appointment_at'] ?? '';
    return strcmp($bTime, $aTime);
});

$requestedPeerId = isset($_GET['peer_id']) ? (int) $_GET['peer_id'] : 0;
$selectedPeer = null;
if ($requestedPeerId > 0 && isset($eligibleById[$requestedPeerId])) {
    $selectedPeer = $eligibleById[$requestedPeerId];
} elseif ($conversations) {
    $selectedPeerId = (int) $conversations[0]['peer']['id'];
    $selectedPeer = $eligibleById[$selectedPeerId] ?? null;
}

$selected = null;
if ($selectedPeer) {
    $selectedPeerId = (int) $selectedPeer['id'];
    $patientId = $userRole === 'patient' ? $userId : $selectedPeerId;
    $medecinId = $userRole === 'medecin' ? $userId : $selectedPeerId;
    $thread = resolveThread($pdo, $patientId, $medecinId);
    markThreadAsRead($pdo, (int) $thread['id'], $userRole);
    $messages = loadThreadMessages($pdo, (int) $thread['id'], $userRole);
    $selected = [
        'thread_id' => (int) $thread['id'],
        'peer' => [
            'id' => $selectedPeerId,
            'role' => $userRole === 'patient' ? 'medecin' : 'patient',
            'prenom' => (string) ($selectedPeer['prenom'] ?? ''),
            'nom' => (string) ($selectedPeer['nom'] ?? ''),
            'photo_url' => $selectedPeer['photo_url'] ?? null,
            'commune' => $selectedPeer['commune'] ?? null,
            'wilaya' => $selectedPeer['wilaya'] ?? null,
            'specialite' => $selectedPeer['specialite'] ?? null,
            'last_appointment_at' => !empty($selectedPeer['last_appointment_at']) ? date(DATE_ATOM, strtotime((string) $selectedPeer['last_appointment_at'])) : null,
        ],
        'messages' => $messages,
    ];

    foreach ($conversations as &$conversation) {
        if ((int) $conversation['peer']['id'] === $selectedPeerId) {
            $conversation['thread_id'] = (int) $thread['id'];
            $conversation['unread_count'] = 0;
        }
    }
    unset($conversation);
}

jsonResponse([
    'role' => $userRole,
    'eligibility_rule' => 'Messagerie autorisee apres au moins un rendez-vous termine.',
    'conversations' => $conversations,
    'selected' => $selected,
]);
