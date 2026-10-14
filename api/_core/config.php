<?php
declare(strict_types=1);

function loadEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, "\"'");
        $_ENV[$k] = $v;
    }
}

function env(string $key, ?string $default = null): ?string
{
    static $loaded = false;

    if (!$loaded) {
        loadEnvFile(__DIR__ . '/../.env');
        loadEnvFile(__DIR__ . '/../.env.local');
        $loaded = true;
    }

    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function dbConfig(): array
{
    return [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_NAME', 'tabibdz'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ];
}

function authConfig(): array
{
    return [
        'jwt_secret' => env('JWT_SECRET', 'change-me-in-production'),
        'jwt_issuer' => env('JWT_ISSUER', 'tabibdz-api'),
        'jwt_ttl' => (int) env('JWT_TTL_SECONDS', '86400'),
        'cookie_name' => env('AUTH_COOKIE_NAME', 'tabibdz_token'),
        'cookie_secure' => env('AUTH_COOKIE_SECURE', '0') === '1',
        'cookie_samesite' => env('AUTH_COOKIE_SAMESITE', 'Lax'),
    ];
}

function mailerConfig(): array
{
    return [
        'enabled' => env('MAIL_ENABLED', '0') === '1',
        'transport' => env('MAIL_TRANSPORT', 'smtp'),
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', '465'),
        'security' => strtolower((string) env('MAIL_SECURITY', 'ssl')),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from_email' => env('MAIL_FROM_EMAIL', ''),
        'from_name' => env('MAIL_FROM_NAME', 'TabibDZ'),
        'reply_to' => env('MAIL_REPLY_TO', ''),
        'timeout' => (int) env('MAIL_TIMEOUT', '15'),
    ];
}

function appAllowedOrigins(): array
{
    $raw = trim((string) env('APP_ALLOWED_ORIGINS', ''));
    if ($raw === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn(string $origin): string => rtrim(trim($origin), '/'),
        explode(',', $raw)
    )));
}

function appBaseUrl(): string
{
    $raw = trim((string) env('APP_BASE_URL', ''));
    if ($raw !== '') {
        return rtrim($raw, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000'));
    return $scheme . '://' . $host;
}

function isLoopbackAddress(?string $ip): bool
{
    return in_array((string) $ip, ['127.0.0.1', '::1'], true);
}

function maintenanceAccessAllowed(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    if (env('APP_ENV', 'local') !== 'local') {
        return false;
    }

    return isLoopbackAddress($_SERVER['REMOTE_ADDR'] ?? null);
}
