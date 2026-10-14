<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Vary: Origin');
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = rtrim((string) $_SERVER['HTTP_ORIGIN'], '/');
    $allowedOrigins = appAllowedOrigins();
    $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?? ''));
    $sameHost = $originHost !== '' && $originHost === preg_replace('/:\d+$/', '', $currentHost);
    $localDevOrigin = env('APP_ENV', 'local') === 'local' && in_array($originHost, ['127.0.0.1', 'localhost'], true);

    if ($sameHost || $localDevOrigin || in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Patient-Id');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function assertMethod(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
    }
}

function currentPatientId(): int
{
    $user = authUserFromRequest();
    if (!$user) {
        jsonResponse(['error' => 'Non authentifié'], 401);
    }
    if ($user['role'] !== 'patient') {
        jsonResponse(['error' => 'Accès réservé aux patients'], 403);
    }
    return (int) $user['id'];
}

function currentAuthUser(): ?array
{
    return authUserFromRequest();
}

function requireAuth(?string $requiredRole = null): array
{
    $user = authUserFromRequest();
    if (!$user) {
        jsonResponse(['error' => 'Non authentifié'], 401);
    }
    if ($requiredRole !== null && $user['role'] !== $requiredRole) {
        jsonResponse(['error' => 'Accès interdit'], 403);
    }
    return $user;
}

function firstOrNull(PDOStatement $stmt): ?array
{
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function lowerUtf8(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

function slugify(string $text): string
{
    $text = lowerUtf8($text);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? $text;
    return trim($text, '-');
}

function auditLog(string $action, ?string $entityType = null, $entityId = null, ?array $payload = null): void
{
    try {
        $actor = currentAuthUser();
        $stmt = db()->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, payload_json, ip_address, user_agent)
                               VALUES (:actor_user_id, :action, :entity_type, :entity_id, :payload_json, :ip_address, :user_agent)');
        $stmt->execute([
            'actor_user_id' => $actor['id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        // Ne jamais casser la requête métier si l'audit échoue.
    }
}

function appDebug(): bool
{
    return env('APP_DEBUG', '0') === '1';
}

function clientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function enforceRateLimit(string $actionKey, string $subjectKey, int $maxAttempts, int $windowSeconds): void
{
    $pdo = db();
    $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM rate_limit_events WHERE action_key = :action_key AND subject_key = :subject_key AND created_at >= :window_start');
    $countStmt->execute([
        'action_key' => $actionKey,
        'subject_key' => $subjectKey,
        'window_start' => $windowStart,
    ]);
    $count = (int) $countStmt->fetchColumn();

    if ($count >= $maxAttempts) {
        jsonResponse([
            'error' => 'Trop de tentatives. Réessayez plus tard.',
            'retry_after_seconds' => $windowSeconds,
        ], 429);
    }

    $ins = $pdo->prepare('INSERT INTO rate_limit_events (action_key, subject_key, ip_address) VALUES (:action_key, :subject_key, :ip_address)');
    $ins->execute([
        'action_key' => $actionKey,
        'subject_key' => $subjectKey,
        'ip_address' => clientIp(),
    ]);
}

function createOneTimeTokenRecord(string $table, int $userId, int $ttlSeconds): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));

    db()->prepare("INSERT INTO {$table} (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)")
        ->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

    return $token;
}

function createOneTimeTokenBundleRecord(string $table, int $userId, int $ttlSeconds): array
{
    $token = bin2hex(random_bytes(32));
    $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $tokenHash = hash('sha256', $token);
    $pinHash = hash('sha256', $pin);
    $expiresAt = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));

    db()->prepare("INSERT INTO {$table} (user_id, token_hash, pin_hash, expires_at) VALUES (:user_id, :token_hash, :pin_hash, :expires_at)")
        ->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'pin_hash' => $pinHash,
            'expires_at' => $expiresAt,
        ]);

    return ['token' => $token, 'pin' => $pin];
}

function consumeOneTimeToken(string $table, string $rawToken): ?array
{
    $tokenHash = hash('sha256', $rawToken);
    $stmt = db()->prepare("SELECT id, user_id, expires_at, used_at FROM {$table} WHERE token_hash = :token_hash LIMIT 1");
    $stmt->execute(['token_hash' => $tokenHash]);
    $row = firstOrNull($stmt);
    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return null;
    }

    db()->prepare("UPDATE {$table} SET used_at = NOW() WHERE id = :id")->execute(['id' => (int) $row['id']]);
    return $row;
}

function consumeOneTimePin(string $table, int $userId, string $pin): ?array
{
    $pinHash = hash('sha256', $pin);
    $stmt = db()->prepare("SELECT id, user_id, expires_at, used_at FROM {$table} WHERE user_id = :user_id AND pin_hash = :pin_hash LIMIT 1");
    $stmt->execute([
        'user_id' => $userId,
        'pin_hash' => $pinHash,
    ]);
    $row = firstOrNull($stmt);
    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return null;
    }

    db()->prepare("UPDATE {$table} SET used_at = NOW() WHERE id = :id")->execute(['id' => (int) $row['id']]);
    return $row;
}
