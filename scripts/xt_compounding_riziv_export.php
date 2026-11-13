<?php
/**
 * Convert the official RIZIV magistral reference workbook into a normalized CSV.
 *
 * Usage:
 *   php xt_compounding_riziv_export.php <unzipped-xlsx-directory> <output.csv>
 *
 * The workbook is read-only. The original workbook fields are retained in a
 * JSON column so reimbursement details are not lost while the schema evolves.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:xt_compounding_riziv_export
 */

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

if ($argc !== 3) {
    fail('Usage: php xt_compounding_riziv_export.php <unzipped-xlsx-directory> <output.csv>');
}

$root = rtrim($argv[1], DIRECTORY_SEPARATOR);
$sheetPath = $root . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';
$stringsPath = $root . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
if (!is_file($sheetPath) || !is_readable($sheetPath) || !is_file($stringsPath) || !is_readable($stringsPath)) {
    fail('The extracted workbook is missing xl/worksheets/sheet1.xml or xl/sharedStrings.xml.');
}

function loadXml(string $path): DOMDocument
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->load($path, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        fail('Could not parse workbook XML: ' . basename($path));
    }
    return $document;
}

function columnNumber(string $cellReference): int
{
    preg_match('/^[A-Z]+/', strtoupper($cellReference), $matches);
    $letters = $matches[0] ?? '';
    $number = 0;
    foreach (str_split($letters) as $letter) {
        $number = ($number * 26) + (ord($letter) - 64);
    }
    return $number;
}

function columnLetters(int $number): string
{
    $letters = '';
    while ($number > 0) {
        $number--;
        $letters = chr(65 + ($number % 26)) . $letters;
        $number = intdiv($number, 26);
    }
    return $letters;
}

function trimUnicodeWhitespace(string $value): string
{
    return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value) ?? trim($value);
}

function cellValue(DOMElement $cell, array $sharedStrings): string
{
    $type = $cell->getAttribute('t');
    $valueNode = $cell->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v')->item(0);
    if ($type === 'inlineStr') {
        return trimUnicodeWhitespace($cell->textContent);
    }
    if (!$valueNode instanceof DOMElement) {
        return '';
    }
    $value = trim($valueNode->textContent);
    if ($type === 's') {
        $index = (int) $value;
        return trimUnicodeWhitespace($sharedStrings[$index] ?? '');
    }
    return $value;
}

$namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$sharedDocument = loadXml($stringsPath);
$sharedStrings = [];
foreach ($sharedDocument->getElementsByTagNameNS($namespace, 'si') as $item) {
    $sharedStrings[] = trim($item->textContent);
}

$sheet = loadXml($sheetPath);
$rows = [];
foreach ($sheet->getElementsByTagNameNS($namespace, 'row') as $row) {
    if (!$row instanceof DOMElement) {
        continue;
    }
    $rowNumber = (int) $row->getAttribute('r');
    if ($rowNumber < 1) {
        continue;
    }
    $values = [];
    foreach ($row->getElementsByTagNameNS($namespace, 'c') as $cell) {
        if (!$cell instanceof DOMElement) {
            continue;
        }
        $column = columnNumber($cell->getAttribute('r'));
        if ($column > 0) {
            $values[$column] = cellValue($cell, $sharedStrings);
        }
    }
    $rows[$rowNumber] = $values;
}

$effectiveText = trim($rows[1][3] ?? '');
if (!preg_match('/\b(\d{2})\.(\d{2})\.(\d{4})\b/', $effectiveText, $dateMatch)) {
    fail('Could not read the effective date from workbook cell C1.');
}
$validFrom = sprintf('%04d-%02d-%02d', (int) $dateMatch[3], (int) $dateMatch[2], (int) $dateMatch[1]);
$sourceVersion = $validFrom;

$header = $rows[3] ?? [];
$expectedHeaders = [1 => 'CNK', 3 => 'DCI FR', 4 => 'DCI NL'];
foreach ($expectedHeaders as $column => $expected) {
    $actual = preg_replace('/\s+/', ' ', trim($header[$column] ?? '')) ?? '';
    if (strcasecmp($actual, $expected) !== 0) {
        fail("Workbook structure changed: expected {$expected} in column {$column}.");
    }
}
if (stripos($header[5] ?? '', 'chapitre') === false) {
    fail('Workbook structure changed: expected the reimbursement chapter column at E3.');
}
$headerMap = [];
foreach ($header as $column => $name) {
    $name = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);
    if ($name !== '') {
        $headerMap[$column] = $name;
    }
}

// These RIZIV chapter values identify reimbursable substances/raw materials.
// 51 = Chapter I, 52 = Chapter II, 54xx = Chapter IV, 55 = Chapter V.
// Preparations (53) and devices (56) are deliberately excluded from this raw-material catalogue.
$chapterCode = static function (string $value): ?string {
    if ($value === '51') return 'I';
    if ($value === '52') return 'II';
    if (preg_match('/^54\d{3}$/', $value)) return 'IV';
    if ($value === '55') return 'V';
    return null;
};

$outputPath = $argv[2];
$output = fopen($outputPath, 'wb');
if ($output === false) {
    fail('Could not open output CSV for writing.');
}
fwrite($output, "\xEF\xBB\xBF");
$columns = [
    'source_version', 'valid_from', 'cnk', 'chapter_code', 'list_number',
    'name_fr', 'name_nl', 'synonyms_fr_json', 'synonyms_nl_json', 'source_payload_json',
];
fputcsv($output, $columns, ',', '"', '');

$rowsWritten = 0;
$seenKeys = [];
$nameByCnk = [];
foreach ($rows as $rowNumber => $row) {
    if ($rowNumber <= 5) {
        continue;
    }
    $cnkRaw = trim($row[1] ?? '');
    $rawChapter = trim($row[5] ?? '');
    $chapter = $chapterCode($rawChapter);
    if ($cnkRaw === '' || $chapter === null) {
        continue;
    }
    if (!preg_match('/^\d{1,7}$/', $cnkRaw)) {
        fclose($output);
        $cnkDiagnostic = json_encode($cnkRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fail("Unexpected CNK value in cell A{$rowNumber} on workbook row {$rowNumber}: {$cnkDiagnostic}");
    }
    $cnk = str_pad($cnkRaw, 7, '0', STR_PAD_LEFT);
    $nameFr = trim($row[3] ?? '');
    $nameNl = trim($row[4] ?? '');
    if ($nameFr === '' && $nameNl === '') {
        fclose($output);
        fail("RIZIV row {$rowNumber} has a chapter and CNK but no NL/FR name.");
    }
    if (isset($nameByCnk[$cnk]) && $nameByCnk[$cnk] !== [$nameFr, $nameNl]) {
        // Some entries intentionally have chapter-specific wording. Preserve each row,
        // while the database material's preferred name is selected deterministically.
    }
    $nameByCnk[$cnk] ??= [$nameFr, $nameNl];
    $key = implode('|', [$cnk, $chapter, $rawChapter]);
    if (isset($seenKeys[$key])) {
        fclose($output);
        fail("Duplicate CNK/chapter/list identity on workbook row {$rowNumber}.");
    }
    $seenKeys[$key] = true;

    $synonymsFr = array_values(array_filter(array_map('trim', [$row[17] ?? '', $row[19] ?? '', $row[21] ?? '']), static fn(string $v): bool => $v !== ''));
    $synonymsNl = array_values(array_filter(array_map('trim', [$row[18] ?? '', $row[20] ?? '', $row[22] ?? '']), static fn(string $v): bool => $v !== ''));
    $sourcePayload = ['fields' => []];
    foreach ($headerMap as $column => $fieldName) {
        // Keep column letters because the workbook repeats supplier headers.
        $sourcePayload['fields'][] = [
            'column' => columnLetters($column),
            'label' => $fieldName,
            'value' => $row[$column] ?? '',
        ];
    }
    $sourcePayload['source_row'] = $rowNumber;
    $csvRow = [
        $sourceVersion,
        $validFrom,
        $cnk,
        $chapter,
        $rawChapter,
        $nameFr,
        $nameNl,
        json_encode($synonymsFr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode($synonymsNl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode($sourcePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ];
    fputcsv($output, $csvRow, ',', '"', '');
    $rowsWritten++;
}
fclose($output);

if ($rowsWritten === 0) {
    @unlink($outputPath);
    fail('No reimbursable raw-material rows were found in the RIZIV workbook.');
}

fwrite(STDOUT, "RIZIV magistral reference CSV created\n");
fwrite(STDOUT, "Effective date: {$validFrom}\n");
fwrite(STDOUT, "Rows (CNK/chapter list entries): {$rowsWritten}\n");
fwrite(STDOUT, "Distinct CNK materials: " . count($nameByCnk) . "\n");
fwrite(STDOUT, "CSV: {$outputPath}\n");
