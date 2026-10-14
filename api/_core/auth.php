<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function base64UrlEncode(string $input): string
{
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function base64UrlDecode(string $input): string
{
    $padding = strlen($input) % 4;
    if ($padding > 0) {
        $input .= str_repeat('=', 4 - $padding);
    }
    return (string) base64_decode(strtr($input, '-_', '+/'));
}

function jwtSign(array $payload): string
{
    $cfg = authConfig();
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $headerPart = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadPart = base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $headerPart . '.' . $payloadPart, $cfg['jwt_secret'], true);
    $signaturePart = base64UrlEncode($signature);
    return $headerPart . '.' . $payloadPart . '.' . $signaturePart;
}

function jwtDecode(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerPart, $payloadPart, $sigPart] = $parts;
    $cfg = authConfig();
    $expectedSig = base64UrlEncode(hash_hmac('sha256', $headerPart . '.' . $payloadPart, $cfg['jwt_secret'], true));
    if (!hash_equals($expectedSig, $sigPart)) {
        return null;
    }

    $payloadJson = base64UrlDecode($payloadPart);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        return null;
    }

    $now = time();
    $cfg = authConfig();
    if (!isset($payload['iss']) || (string) $payload['iss'] !== (string) $cfg['jwt_issuer']) {
        return null;
    }
    if (isset($payload['exp']) && (int) $payload['exp'] < $now) {
        return null;
    }

    return $payload;
}

function authTokenFromRequest(): ?string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        return trim($m[1]);
    }

    $cfg = authConfig();
    if (!empty($_COOKIE[$cfg['cookie_name']])) {
        return (string) $_COOKIE[$cfg['cookie_name']];
    }

    return null;
}

function createAuthToken(int $userId, string $role): array
{
    $cfg = authConfig();
    $now = time();
    $exp = $now + max(300, (int) $cfg['jwt_ttl']);
    $jti = bin2hex(random_bytes(16));

    $payload = [
        'iss' => $cfg['jwt_issuer'],
        'sub' => $userId,
        'role' => $role,
        'iat' => $now,
        'exp' => $exp,
        'jti' => $jti,
    ];

    $token = jwtSign($payload);

    $stmt = db()->prepare('INSERT INTO auth_sessions (jti, user_id, expires_at, user_agent, ip_address) VALUES (:jti, :uid, :exp, :ua, :ip)');
    $stmt->execute([
        'jti' => $jti,
        'uid' => $userId,
        'exp' => date('Y-m-d H:i:s', $exp),
        'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    return ['token' => $token, 'exp' => $exp, 'jti' => $jti];
}

function revokeSessionByJti(string $jti): void
{
    db()->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE jti = :jti')->execute(['jti' => $jti]);
}

function revokeAllSessionsForUser(int $userId): void
{
    db()->prepare('UPDATE auth_sessions SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL')
        ->execute(['user_id' => $userId]);
}

function setAuthCookie(string $token, int $exp): void
{
    $cfg = authConfig();
    setcookie($cfg['cookie_name'], $token, [
        'expires' => $exp,
        'path' => '/',
        'secure' => (bool) $cfg['cookie_secure'],
        'httponly' => true,
        'samesite' => $cfg['cookie_samesite'],
    ]);
}

function clearAuthCookie(): void
{
    $cfg = authConfig();
    setcookie($cfg['cookie_name'], '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (bool) $cfg['cookie_secure'],
        'httponly' => true,
        'samesite' => $cfg['cookie_samesite'],
    ]);
}

function authUserFromRequest(): ?array
{
    $token = authTokenFromRequest();
    if (!$token) {
        return null;
    }

    $payload = jwtDecode($token);
    if (!$payload || empty($payload['sub']) || empty($payload['jti'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT s.jti, s.user_id, s.expires_at, s.revoked_at, u.role, u.email, u.prenom, u.nom, u.email_verified_at, u.is_active
                           FROM auth_sessions s
                           INNER JOIN users u ON u.id = s.user_id
                           WHERE s.jti = :jti AND s.user_id = :uid
                           LIMIT 1');
    $stmt->execute([
        'jti' => (string) $payload['jti'],
        'uid' => (int) $payload['sub'],
    ]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        return null;
    }

    if (!empty($session['revoked_at'])) {
        return null;
    }

    if (strtotime((string) $session['expires_at']) < time()) {
        return null;
    }
    if ((int) ($session['is_active'] ?? 1) !== 1) {
        return null;
    }

    return [
        'id' => (int) $session['user_id'],
        'role' => (string) $session['role'],
        'email' => (string) $session['email'],
        'prenom' => (string) $session['prenom'],
        'nom' => (string) $session['nom'],
        'email_verified_at' => $session['email_verified_at'],
        'is_active' => (int) ($session['is_active'] ?? 1),
        'jti' => (string) $session['jti'],
        'token_payload' => $payload,
    ];
}
