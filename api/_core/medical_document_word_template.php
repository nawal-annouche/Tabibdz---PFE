<?php
declare(strict_types=1);

function ordonnanceTemplatePythonCandidates(): array
{
    return array_values(array_filter([
        getenv('TABIBDZ_PYTHON') ?: null,
        'C:\\Users\\bos\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe',
        'python',
    ]));
}

function ordonnanceTemplateResolvePython(): string
{
    foreach (ordonnanceTemplatePythonCandidates() as $candidate) {
        if (str_contains($candidate, '\\') || str_contains($candidate, '/')) {
            if (is_file($candidate)) {
                return $candidate;
            }
            continue;
        }

        $probe = @shell_exec('where ' . escapeshellarg($candidate) . ' 2>NUL');
        if (is_string($probe) && trim($probe) !== '') {
            return $candidate;
        }
    }

    throw new RuntimeException('Aucun interprete Python disponible pour generer l ordonnance.');
}

function ordonnanceTemplateRun(array $command, ?string $cwd = null): void
{
    $parts = array_map(static fn(string $arg): string => escapeshellarg($arg), $command);
    $cmd = implode(' ', $parts);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptorSpec, $pipes, $cwd ?: null);
    if (!is_resource($process)) {
        throw new RuntimeException('Impossible de lancer la commande externe.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $message = trim((string) $stderr) ?: trim((string) $stdout) ?: 'Commande externe en echec.';
        throw new RuntimeException($message);
    }
}

function ordonnanceTemplateAddressParts(string $address): array
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
    if (!$parts) {
        return ['', ''];
    }
    if (count($parts) === 1) {
        return [$parts[0], ''];
    }

    return [$parts[0], implode(', ', array_slice($parts, 1))];
}

function ordonnanceTemplateIssuePlace(string $cityLabel, string $issuedAt): string
{
    $date = $issuedAt;
    $ts = strtotime($issuedAt);
    if ($ts !== false) {
        $date = date('d/m/Y', $ts);
    }
    return trim(($cityLabel !== '' ? $cityLabel : 'Le') . ', le ' . $date);
}

function buildOrdonnancePdfFromWordTemplate(array $payload): string
{
    $templatePath = dirname(__DIR__) . '/templates/ordonnance-template.docx';
    $pythonScript = __DIR__ . '/generate_ordonnance_template.py';
    $powershellScript = __DIR__ . '/convert_word_to_pdf.ps1';

    if (!is_file($templatePath)) {
        throw new RuntimeException('Template d ordonnance introuvable.');
    }
    if (!is_file($pythonScript) || !is_file($powershellScript)) {
        throw new RuntimeException('Scripts de generation de l ordonnance introuvables.');
    }

    [$addressLine1, $addressLine2] = ordonnanceTemplateAddressParts((string) ($payload['doctor_adresse'] ?? ''));
    $cityLabel = trim((string) ($payload['city_label'] ?? ''));
    if ($cityLabel === '' && $addressLine2 !== '') {
        $cityLabel = $addressLine2;
    }
    if ($cityLabel === '' && $addressLine1 !== '') {
        $cityLabel = $addressLine1;
    }

    $patientMetaLines = array_values(array_filter([
        trim((string) ($payload['patient_relation'] ?? '')) !== '' ? 'Qualite : ' . trim((string) $payload['patient_relation']) : null,
        trim((string) ($payload['patient_birthdate'] ?? '')) !== '' ? 'Naissance : ' . trim((string) $payload['patient_birthdate']) : null,
        trim((string) ($payload['patient_age'] ?? '')) !== '' ? 'Age : ' . trim((string) $payload['patient_age']) : null,
        trim((string) ($payload['reference'] ?? '')) !== '' ? 'Reference : ' . trim((string) $payload['reference']) : null,
    ]));

    $ordonnanceText = trim((string) ($payload['body'] ?? ''));
    $sourceLines = array_values(array_filter(array_map(
        static fn(string $line): string => trim($line),
        preg_split("/\r\n|\r|\n/", $ordonnanceText) ?: []
    )));

    $titleLine = '';
    $bodyLines = $sourceLines;
    if (count($sourceLines) > 1) {
        $titleLine = array_shift($bodyLines);
    }

    $issuedAt = trim((string) ($payload['issued_at'] ?? date('d/m/Y')));
    $workspace = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'tabibdz_ordonnance_' . bin2hex(random_bytes(6));
    if (!mkdir($workspace, 0775, true) && !is_dir($workspace)) {
        throw new RuntimeException('Impossible de creer un dossier temporaire pour l ordonnance.');
    }

    $payloadPath = $workspace . DIRECTORY_SEPARATOR . 'payload.json';
    $docxPath = $workspace . DIRECTORY_SEPARATOR . 'ordonnance.docx';
    $pdfPath = $workspace . DIRECTORY_SEPARATOR . 'ordonnance.pdf';

    $templatePayload = [
        'doctor_name' => (string) ($payload['doctor_name'] ?? 'Dr. Medecin'),
        'doctor_specialite' => (string) ($payload['doctor_specialite'] ?? 'Medecin'),
        'doctor_address_line1' => $addressLine1,
        'doctor_address_line2' => $addressLine2,
        'doctor_secondary_line' => (string) ($payload['doctor_secondary_line'] ?? ''),
        'doctor_phone' => (string) ($payload['doctor_telephone'] ?? ''),
        'patient_name' => (string) ($payload['patient_name'] ?? 'Patient'),
        'patient_meta_lines' => $patientMetaLines,
        'issue_place_date' => ordonnanceTemplateIssuePlace($cityLabel, $issuedAt),
        'prescription_title' => $titleLine,
        'prescription_lines' => $bodyLines,
    ];

    file_put_contents($payloadPath, json_encode($templatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    try {
        $python = ordonnanceTemplateResolvePython();
        ordonnanceTemplateRun([
            $python,
            $pythonScript,
            '--template',
            $templatePath,
            '--payload',
            $payloadPath,
            '--output',
            $docxPath,
        ], dirname(__DIR__, 2));

        ordonnanceTemplateRun([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $powershellScript,
            '-InputPath',
            $docxPath,
            '-OutputPath',
            $pdfPath,
        ], dirname(__DIR__, 2));

        $pdfBinary = @file_get_contents($pdfPath);
        if ($pdfBinary === false || $pdfBinary === '') {
            throw new RuntimeException('Le PDF ordonnance n a pas pu etre genere.');
        }

        return $pdfBinary;
    } finally {
        if (is_dir($workspace)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            @rmdir($workspace);
        }
    }
}
