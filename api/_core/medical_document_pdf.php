<?php
declare(strict_types=1);

function medicalPdfEncode(string $text): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($text));
    $encoded = function_exists('iconv')
        ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value)
        : $value;
    if ($encoded === false) {
        $encoded = $value;
    }

    $encoded = str_replace('\\', '\\\\', $encoded);
    $encoded = str_replace('(', '\\(', $encoded);
    $encoded = str_replace(')', '\\)', $encoded);
    $encoded = str_replace("\n", ' ', $encoded);

    return $encoded;
}

function medicalPdfWrapText(string $text, int $lineLength = 88): array
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return ['Aucune information fournie.'];
    }

    $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
    $lines = [];
    foreach ($paragraphs as $paragraph) {
        $normalized = trim(preg_replace('/\s+/u', ' ', $paragraph) ?? $paragraph);
        if ($normalized === '') {
            continue;
        }
        $wrapped = wordwrap($normalized, $lineLength, "\n", true);
        foreach (explode("\n", $wrapped) as $line) {
            $lines[] = trim($line);
        }
        $lines[] = '';
    }

    if ($lines && end($lines) === '') {
        array_pop($lines);
    }

    return $lines ?: ['Aucune information fournie.'];
}

function medicalPdfAgeLabel(?string $birthDate): string
{
    $value = trim((string) $birthDate);
    if ($value === '') {
        return '';
    }

    try {
        $birth = new DateTimeImmutable($value);
        $now = new DateTimeImmutable('now');
        return (string) $birth->diff($now)->y . ' ans';
    } catch (Throwable) {
        return '';
    }
}

function medicalPdfText(string $font, int $size, int|float $x, int|float $y, string $text): string
{
    return 'BT /' . $font . ' ' . $size . ' Tf ' . $x . ' ' . $y . ' Td (' . medicalPdfEncode($text) . ') Tj ET';
}

function medicalPdfLine(int|float $x1, int|float $y1, int|float $x2, int|float $y2, float $width = 1.0): string
{
    return $width . ' w ' . $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . ' l S';
}

function medicalPdfRect(int|float $x, int|float $y, int|float $w, int|float $h, float $width = 1.0): string
{
    return $width . ' w ' . $x . ' ' . $y . ' ' . $w . ' ' . $h . ' re S';
}

function buildMedicalDocumentPdf(array $payload): string
{
    $template = trim((string) ($payload['template'] ?? 'default'));
    $title = trim((string) ($payload['title'] ?? 'Document medical'));
    $subtitle = trim((string) ($payload['subtitle'] ?? ''));
    $doctorName = trim((string) ($payload['doctor_name'] ?? ''));
    $doctorSpecialite = trim((string) ($payload['doctor_specialite'] ?? ''));
    $doctorAdresse = trim((string) ($payload['doctor_adresse'] ?? ''));
    $doctorAgrement = trim((string) ($payload['doctor_agrement'] ?? ''));
    $doctorTelephone = trim((string) ($payload['doctor_telephone'] ?? ''));
    $doctorEmail = trim((string) ($payload['doctor_email'] ?? ''));
    $patientName = trim((string) ($payload['patient_name'] ?? 'Patient'));
    $patientRelation = trim((string) ($payload['patient_relation'] ?? 'Patient'));
    $patientBirthDate = trim((string) ($payload['patient_birthdate'] ?? ''));
    $appointmentDate = trim((string) ($payload['appointment_date'] ?? ''));
    $reference = trim((string) ($payload['reference'] ?? ''));
    $bodyText = trim((string) ($payload['body'] ?? ''));
    $issuedAt = trim((string) ($payload['issued_at'] ?? date('d/m/Y H:i')));
    $cityLabel = trim((string) ($payload['city_label'] ?? ''));
    $patientAge = medicalPdfAgeLabel($patientBirthDate);

    $bodyLines = medicalPdfWrapText($bodyText, $template === 'ordonnance' ? 62 : 88);
    $pages = [];
    $lineHeight = $template === 'ordonnance' ? 17 : 15;

    $makeHeader = static function (int $pageNumber, bool $continued) use (
        $template,
        $title,
        $subtitle,
        $doctorName,
        $doctorSpecialite,
        $doctorAdresse,
        $doctorAgrement,
        $doctorTelephone,
        $doctorEmail,
        $patientName,
        $patientRelation,
        $patientBirthDate,
        $patientAge,
        $appointmentDate,
        $reference,
        $issuedAt,
        $cityLabel
    ): array {
        $commands = [];
        $commands[] = '0.18 0.36 0.67 RG';
        $commands[] = '0 0 0 rg';

        if ($template === 'ordonnance') {
            $commands[] = '0 0 0 rg';
            $commands[] = medicalPdfText('F2', 17, 54, 782, $doctorName !== '' ? $doctorName : 'Dr. Medecin');
            $commands[] = medicalPdfText('F1', 12, 54, 760, $doctorSpecialite !== '' ? $doctorSpecialite : 'Medecin');
            $topRightLines = array_values(array_filter([
                $doctorAdresse !== '' ? $doctorAdresse : null,
                $doctorEmail !== '' ? $doctorEmail : null,
            ]));
            $topRightY = 782;
            foreach ($topRightLines as $line) {
                $commands[] = medicalPdfText('F1', 11, 340, $topRightY, $line);
                $topRightY -= 20;
            }

            $commands[] = medicalPdfLine(54, 722, 542, 722, 0.8);
            $secondaryLine = $doctorAgrement !== '' ? $doctorAgrement : ($subtitle !== '' ? $subtitle : '');
            if ($secondaryLine !== '') {
                $commands[] = medicalPdfText('F3', 11, 54, 694, $secondaryLine);
            }
            if ($doctorTelephone !== '') {
                $commands[] = medicalPdfText('F1', 12, 255, 694, $doctorTelephone);
            }

            $commands[] = medicalPdfText('F2', 14, 54, 628, $patientName !== '' ? $patientName : 'Patient');
            $patientLines = array_values(array_filter([
                $patientRelation !== '' ? $patientRelation : null,
                $patientBirthDate !== '' ? 'Naissance : ' . $patientBirthDate : null,
                $patientAge !== '' ? 'Age : ' . $patientAge : null,
                $reference !== '' ? 'Reference : ' . $reference : null,
            ]));
            $patientInfoY = 602;
            foreach ($patientLines as $line) {
                $commands[] = medicalPdfText('F1', 11, 54, $patientInfoY, $line);
                $patientInfoY -= 18;
            }

            $rightTop = $cityLabel !== '' ? $cityLabel . ', le ' . $issuedAt : 'Le ' . $issuedAt;
            $commands[] = medicalPdfText('F1', 12, 405, 548, $rightTop);
            if ($appointmentDate !== '') {
                $commands[] = medicalPdfText('F1', 10, 405, 528, 'Consultation : ' . $appointmentDate);
            }

            if ($continued) {
                $commands[] = medicalPdfText('F2', 18, 54, 476, 'Suite de l ordonnance');
                return [$commands, 438];
            }

            $commands[] = medicalPdfLine(54, 112, 245, 112, 0.6);
            $commands[] = medicalPdfText('F1', 11, 128, 92, 'Signature et tampon :');

            return [$commands, 472];
        }

        $commands[] = medicalPdfText('F2', 24, 50, 800, 'TabibDZ');
        $commands[] = '0 0 0 rg';
        $commands[] = medicalPdfText('F2', 18, 50, 770, $continued ? $title . ' - suite' : $title);
        if (!$continued && $subtitle !== '') {
            $commands[] = medicalPdfText('F1', 11, 50, 750, $subtitle);
        }
        if ($continued) {
            return [$commands, 730];
        }

        $metaY = 728;
        $metaLines = [
            'Medecin : ' . trim($doctorName . ($doctorSpecialite !== '' ? ' - ' . $doctorSpecialite : '')),
            'Patient : ' . trim($patientName . ($patientRelation !== '' ? ' - ' . $patientRelation : '')),
            'Rendez-vous : ' . $appointmentDate,
            'Reference : ' . $reference,
            'Emission : ' . $issuedAt,
        ];
        if ($doctorAdresse !== '') {
            $metaLines[] = 'Adresse : ' . $doctorAdresse;
        }
        if ($doctorAgrement !== '') {
            $metaLines[] = 'Agrement : ' . $doctorAgrement;
        }

        foreach ($metaLines as $line) {
            $commands[] = medicalPdfText('F1', 11, 50, $metaY, $line);
            $metaY -= 18;
        }

        $commands[] = '0.08 0.66 0.44 rg';
        $commands[] = medicalPdfText('F2', 12, 50, $metaY - 8, 'Contenu du document');
        $commands[] = '0 0 0 rg';

        return [$commands, $metaY - 30];
    };

    $remaining = $bodyLines;
    $pageNumber = 1;
    while ($remaining) {
        [$commands, $startY] = $makeHeader($pageNumber, $pageNumber > 1);
        $y = $startY;
        $pageLines = [];
        while ($remaining) {
            if ($y < ($template === 'ordonnance' ? 95 : 70)) {
                break;
            }
            $line = array_shift($remaining);
            $pageLines[] = $line;
            $y -= $lineHeight;
        }

        $commands[] = '0 0 0 rg';
        foreach ($pageLines as $line) {
            if ($line === '') {
                $startY -= $lineHeight;
                continue;
            }
            $commands[] = medicalPdfText('F1', $template === 'ordonnance' ? 12 : 11, 56, $startY, $line);
            $startY -= $lineHeight;
        }

        if ($template !== 'ordonnance') {
            $commands[] = '0.45 0.45 0.45 rg';
            $commands[] = medicalPdfText('F1', 9, 50, 34, 'Document genere automatiquement par TabibDZ');
            $commands[] = medicalPdfText('F1', 9, 430, 34, 'Page ' . $pageNumber);
        } else {
            $commands[] = '0.18 0.36 0.67 rg';
            $commands[] = medicalPdfText('F1', 7, 34, 34, 'Document genere automatiquement par TabibDZ - Page ' . $pageNumber);
        }

        $pages[] = implode("\n", $commands);
        $pageNumber++;
    }

    if (!$pages) {
        [$commands, $bodyY] = $makeHeader(1, false);
        $commands[] = '0 0 0 rg';
        $commands[] = medicalPdfText('F1', 11, 56, $bodyY, 'Aucune information fournie.');
        $pages[] = implode("\n", $commands);
    }

    $fontRegularId = 1;
    $fontBoldId = 2;
    $fontItalicId = 3;
    $pagesRootId = 4;
    $catalogId = 5;
    $infoId = 6;
    $nextId = 7;
    $pageObjectIds = [];
    $contentObjectIds = [];

    foreach ($pages as $_page) {
        $pageObjectIds[] = $nextId++;
        $contentObjectIds[] = $nextId++;
    }

    $objects = [];
    $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>';
    $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>';
    $objects[$fontItalicId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>';

    foreach ($pages as $index => $stream) {
        $pageId = $pageObjectIds[$index];
        $contentId = $contentObjectIds[$index];
        $objects[$pageId] = '<< /Type /Page /Parent ' . $pagesRootId . ' 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontRegularId . ' 0 R /F2 ' . $fontBoldId . ' 0 R /F3 ' . $fontItalicId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $kids = implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageObjectIds));
    $objects[$pagesRootId] = '<< /Type /Pages /Count ' . count($pageObjectIds) . ' /Kids [ ' . $kids . ' ] >>';
    $objects[$catalogId] = '<< /Type /Catalog /Pages ' . $pagesRootId . ' 0 R >>';
    $objects[$infoId] = '<< /Title (' . medicalPdfEncode($title) . ') /Producer (' . medicalPdfEncode('TabibDZ') . ') /Creator (' . medicalPdfEncode('TabibDZ') . ') >>';

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];
    $maxId = max(array_keys($objects));

    foreach ($objects as $id => $objectBody) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $objectBody . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($id = 1; $id <= $maxId; $id++) {
        $offset = $offsets[$id] ?? 0;
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root " . $catalogId . " 0 R /Info " . $infoId . " 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}
