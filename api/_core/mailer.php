<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function mailerLog(string $message): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/mailer.log', $line, FILE_APPEND);
}

function mailerHeaders(string $fromEmail, string $fromName, string $toEmail, string $subject, ?string $replyTo = null): string
{
    return mailerMultipartHeaders($fromEmail, $fromName, $toEmail, $subject, $replyTo)['headers'];
}

function mailerMultipartHeaders(string $fromEmail, string $fromName, string $toEmail, string $subject, ?string $replyTo = null): array
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
    $boundary = 'tabibdz_' . bin2hex(random_bytes(12));
    $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $domain);

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: ' . $messageId,
        'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
        'To: <' . $toEmail . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: TabibDZ Mailer',
    ];

    if ($replyTo) {
        $headers[] = 'Reply-To: <' . $replyTo . '>';
    }

    return [
        'headers' => implode("\r\n", $headers) . "\r\n\r\n",
        'boundary' => $boundary,
    ];
}

function mailerReadResponse($socket): string
{
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }

    return $response;
}

function mailerExpect($socket, array $expectedCodes, string $step): string
{
    $response = mailerReadResponse($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException($step . ' failed: ' . trim($response));
    }

    return $response;
}

function mailerWrite($socket, string $command): void
{
    $written = fwrite($socket, $command . "\r\n");
    if ($written === false) {
        throw new RuntimeException('Unable to write SMTP command.');
    }
}

function buildEmailMimePayload(string $headers, string $boundary, string $textBody, ?string $htmlBody = null): string
{
    $parts = [];
    $safeText = str_replace(["\r\n", "\r"], "\n", $textBody);
    $parts[] = '--' . $boundary;
    $parts[] = 'Content-Type: text/plain; charset=UTF-8';
    $parts[] = 'Content-Transfer-Encoding: 8bit';
    $parts[] = '';
    $parts[] = str_replace("\n", "\r\n", trim($safeText));

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], trim($htmlBody));
    }

    $parts[] = '--' . $boundary . '--';
    $parts[] = '';

    $payload = $headers . implode("\r\n", $parts);
    return preg_replace('/^\./m', '..', $payload) ?? $payload;
}

function smtpSendMail(string $toEmail, string $subject, string $body, ?string $htmlBody = null): bool
{
    $cfg = mailerConfig();
    if (!$cfg['enabled']) {
        return false;
    }

    if ($cfg['transport'] !== 'smtp') {
        throw new RuntimeException('Unsupported mail transport.');
    }

    $host = trim((string) $cfg['host']);
    $fromEmail = trim((string) $cfg['from_email']);
    $username = trim((string) $cfg['username']);
    $password = (string) $cfg['password'];

    if ($host === '' || $fromEmail === '' || $username === '' || $password === '') {
        throw new RuntimeException('Mailer configuration is incomplete.');
    }

    $security = strtolower((string) $cfg['security']);
    $remote = $host . ':' . (int) $cfg['port'];
    if ($security === 'ssl') {
        $remote = 'ssl://' . $remote;
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);

    $socket = @stream_socket_client(
        $remote,
        $errorNumber,
        $errorString,
        (int) $cfg['timeout'],
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        throw new RuntimeException('SMTP connection failed: ' . $errorString . ' (' . $errorNumber . ')');
    }

    stream_set_timeout($socket, (int) $cfg['timeout']);

    try {
        mailerExpect($socket, [220], 'SMTP greeting');

        mailerWrite($socket, 'EHLO tabibdz.local');
        mailerExpect($socket, [250], 'EHLO');

        if ($security === 'tls') {
            mailerWrite($socket, 'STARTTLS');
            mailerExpect($socket, [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to enable TLS encryption.');
            }
            mailerWrite($socket, 'EHLO tabibdz.local');
            mailerExpect($socket, [250], 'EHLO after STARTTLS');
        }

        mailerWrite($socket, 'AUTH LOGIN');
        mailerExpect($socket, [334], 'AUTH LOGIN');

        mailerWrite($socket, base64_encode($username));
        mailerExpect($socket, [334], 'SMTP username');

        mailerWrite($socket, base64_encode($password));
        mailerExpect($socket, [235], 'SMTP password');

        mailerWrite($socket, 'MAIL FROM:<' . $fromEmail . '>');
        mailerExpect($socket, [250], 'MAIL FROM');

        mailerWrite($socket, 'RCPT TO:<' . $toEmail . '>');
        mailerExpect($socket, [250, 251], 'RCPT TO');

        mailerWrite($socket, 'DATA');
        mailerExpect($socket, [354], 'DATA');

        $headerData = mailerMultipartHeaders(
            $fromEmail,
            (string) $cfg['from_name'],
            $toEmail,
            $subject,
            trim((string) $cfg['reply_to']) !== '' ? trim((string) $cfg['reply_to']) : null
        );
        $payload = buildEmailMimePayload($headerData['headers'], $headerData['boundary'], $body, $htmlBody) . "\r\n.";
        mailerWrite($socket, $payload);
        mailerExpect($socket, [250], 'Message body');

        mailerWrite($socket, 'QUIT');
        mailerReadResponse($socket);
    } finally {
        fclose($socket);
    }

    return true;
}
