<?php
/**
 * Export SAM ATC classification code descriptions from a REF XML file to CSV.
 *
 * Usage:
 *   php sam_atc_classifications_export.php <REF-export.xml> [output.csv]
 */

declare(strict_types=1);

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php {$argv[0]} <REF-export.xml> [output.csv]\n");
    exit(2);
}

$inputPath = $argv[1];
if (!is_file($inputPath) || !is_readable($inputPath)) {
    fwrite(STDERR, "ERROR: REF XML file is missing or unreadable: {$inputPath}\n");
    exit(1);
}

$reader = new XMLReader();
if (!$reader->open($inputPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
    fwrite(STDERR, "ERROR: could not open REF XML file.\n");
    exit(1);
}

$samId = '';
$exportVersion = '';
$rows = [];
while ($reader->read()) {
    if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'ExportReferences') {
        $samId = $reader->getAttribute('SamId') ?: '';
        $exportVersion = $reader->getAttribute('version') ?: '';
    }
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'AtcClassification') {
        continue;
    }

    $code = trim((string) $reader->getAttribute('code'));
    $depth = $reader->depth;
    $description = '';
    if (!$reader->isEmptyElement) {
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT
                && $reader->depth === $depth
                && $reader->localName === 'AtcClassification') {
                break;
            }
            if ($reader->nodeType === XMLReader::ELEMENT
                && $reader->depth === $depth + 1
                && $reader->localName === 'Description') {
                $description = trim($reader->readString());
            }
        }
    }
    if ($code !== '' && $description !== '') {
        $rows[$code] = $description;
    }
}
$reader->close();

if ($rows === []) {
    fwrite(STDERR, "ERROR: no AtcClassification records were found in the REF XML file.\n");
    exit(1);
}

$outputPath = $argv[2] ?? ('sam_atc_classifications_' . ($samId !== '' ? $samId : 'export') . '.csv');
$output = fopen($outputPath, 'wb');
if ($output === false) {
    fwrite(STDERR, "ERROR: could not create CSV: {$outputPath}\n");
    exit(1);
}
fputcsv($output, ['atc_code', 'description'], ',', '"', '');
ksort($rows, SORT_STRING);
foreach ($rows as $code => $description) {
    fputcsv($output, [$code, $description], ',', '"', '');
}
fclose($output);

echo "SAM ATC classifications CSV created\n";
echo 'Input: ' . $inputPath . "\n";
echo 'Export version: ' . ($exportVersion !== '' ? $exportVersion : '(not found)') . "\n";
echo 'SAM ID: ' . ($samId !== '' ? $samId : '(not found)') . "\n";
echo 'ATC classification rows: ' . number_format(count($rows), 0, ',', '.') . "\n";
echo 'CSV: ' . $outputPath . "\n";
