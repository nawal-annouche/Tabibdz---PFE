<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$timezone = env('APP_TIMEZONE', 'Africa/Algiers') ?? 'Africa/Algiers';
if (!@date_default_timezone_set($timezone)) {
    date_default_timezone_set('UTC');
}

$debug = (env('APP_DEBUG', '0') === '1');
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/app-' . date('Y-m-d') . '.log';

set_exception_handler(static function (Throwable $e) use ($debug, $logFile): void {
    $context = sprintf(
        "[%s] UNCAUGHT %s: %s in %s:%d\nTrace: %s\n\n",
        date('c'),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logFile, $context, FILE_APPEND);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    if ($debug) {
        echo json_encode([
            'error' => 'Erreur serveur',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    echo json_encode(['error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
