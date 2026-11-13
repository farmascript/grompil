<?php
/**
 * Export the SAM v2 CMP compounding formulas and ingredients to CSV.
 * Each source Data period is retained as a separate versioned row.
 *
 * @date_cre: Fri 2026-11-13
 * @date_mut: Fri 2026-11-13
 * @description: Streams the CMP XML export and preserves multilingual names and source data for formula and ingredient records.
 *
 * Usage:
 *   php sam_compounding_catalog_export.php <CMP-export.xml> [output.csv]
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:sam_compounding_catalog_export
 */

declare(strict_types=1);

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php {$argv[0]} <CMP-export.xml> [output.csv]\n");
    exit(2);
}

$inputPath = $argv[1];
if (!is_file($inputPath) || !is_readable($inputPath)) {
    fwrite(STDERR, "ERROR: CMP XML file is missing or unreadable: {$inputPath}\n");
    exit(1);
}
if (!class_exists(XMLReader::class)) {
    fwrite(STDERR, "ERROR: PHP XMLReader extension is required.\n");
    exit(1);
}

function cmpDate(?string $value): ?string
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
}

function cmpCsvValue(?string $value): string
{
    return trim((string) $value);
}

$reader = new XMLReader();
if (!$reader->open($inputPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
    fwrite(STDERR, "ERROR: could not open CMP XML file.\n");
    exit(1);
}

$samId = '';
$exportVersion = '';
$rows = 0;
$formulas = 0;
$ingredients = 0;
$warnings = 0;
$outputPath = $argv[2] ?? ('sam_compounding_catalog_' . date('Ymd_His') . '.csv');
$output = fopen($outputPath, 'wb');
if ($output === false) {
    $reader->close();
    fwrite(STDERR, "ERROR: could not create CSV: {$outputPath}\n");
    exit(1);
}
fputcsv($output, [
    'sam_export_version', 'sam_id', 'entity_type', 'product_id', 'code', 'code_type',
    'data_version', 'valid_from', 'valid_to', 'preferred_name_nl', 'preferred_name_fr',
    'preferred_name_la', 'synonyms_json', 'source_payload_json',
], ',', '"', '');

while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT) {
        continue;
    }
    if ($reader->localName === 'ExportCompounding') {
        $samId = $reader->getAttribute('SamId') ?: '';
        $exportVersion = $reader->getAttribute('version') ?: '';
        continue;
    }
    if (!in_array($reader->localName, ['CompoundingFormula', 'CompoundingIngredient'], true)) {
        continue;
    }

    $entityType = $reader->localName === 'CompoundingFormula' ? 'FORMULA' : 'INGREDIENT';
    $productId = cmpCsvValue($reader->getAttribute('ProductId'));
    $code = cmpCsvValue($reader->getAttribute('code'));
    $codeType = cmpCsvValue($reader->getAttribute('codeType'));
    $sourceXml = $reader->readOuterXML();
    if ($sourceXml === '') {
        $warnings++;
        continue;
    }

    $previous = libxml_use_internal_errors(true);
    $element = simplexml_load_string($sourceXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$element instanceof SimpleXMLElement) {
        $warnings++;
        continue;
    }
    if ($productId === '') {
        $warnings++;
        continue;
    }

    $dataSlices = $element->xpath('./*[local-name()="Data"]') ?: [];
    if ($dataSlices === []) {
        $dataSlices = [null];
    }
    foreach ($dataSlices as $dataIndex => $data) {
        $validFrom = null;
        $validTo = null;
        $synonyms = [];
        $preferred = ['nl' => '', 'fr' => '', 'la' => ''];
        if ($data instanceof SimpleXMLElement) {
            $dataAttributes = $data->attributes();
            $validFrom = cmpDate(isset($dataAttributes['from']) ? (string) $dataAttributes['from'] : null);
            $validTo = cmpDate(isset($dataAttributes['to']) ? (string) $dataAttributes['to'] : null);
            foreach (($data->xpath('./*[local-name()="Synonym"]') ?: []) as $synonym) {
                $attributes = $synonym->attributes('http://www.w3.org/XML/1998/namespace');
                $language = strtolower(trim((string) ($attributes['lang'] ?? '')));
                $rankAttributes = $synonym->attributes();
                $rankRaw = trim((string) ($rankAttributes['rank'] ?? ''));
                $text = trim((string) $synonym);
                if ($text === '') {
                    continue;
                }
                $rank = ctype_digit($rankRaw) ? (int) $rankRaw : null;
                $synonyms[] = ['language' => $language, 'rank' => $rank, 'text' => $text];
                if (in_array($language, ['nl', 'fr', 'la'], true)
                    && ($preferred[$language] === '' || $rank === 1)) {
                    $preferred[$language] = $text;
                }
            }
        }
        $payload = [
            'source_attributes' => [
                'ProductId' => $productId,
                'code' => $code,
                'codeType' => $codeType,
            ],
            'data_index' => $dataIndex + 1,
            'source_data_xml' => $data instanceof SimpleXMLElement ? $data->asXML() : null,
        ];
        $synonymsJson = json_encode($synonyms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        fputcsv($output, [
            $exportVersion, $samId, $entityType, $productId,
            $code === '' ? '\\N' : $code, $codeType === '' ? '\\N' : $codeType,
            $dataIndex + 1, $validFrom ?? '\\N', $validTo ?? '\\N',
            $preferred['nl'], $preferred['fr'], $preferred['la'], $synonymsJson, $payloadJson,
        ], ',', '"', '');
        $rows++;
    }
    if ($entityType === 'FORMULA') {
        $formulas++;
    } else {
        $ingredients++;
    }
}
$reader->close();
fclose($output);

if ($rows === 0 || $samId === '' || $exportVersion === '') {
    @unlink($outputPath);
    fwrite(STDERR, "ERROR: CMP export metadata or compounding records are missing.\n");
    exit(1);
}
if ($warnings > 0) {
    @unlink($outputPath);
    fwrite(STDERR, "ERROR: {$warnings} CMP records could not be parsed; refusing partial CSV.\n");
    exit(1);
}

printf("SAM CMP catalog CSV created\n");
printf("Input: %s\n", $inputPath);
printf("Export version: %s\n", $exportVersion);
printf("SAM ID: %s\n", $samId);
printf("Formula records: %d\n", $formulas);
printf("Ingredient records: %d\n", $ingredients);
printf("Data-period rows: %d\n", $rows);
printf("XML parsing warnings: %d\n", $warnings);
printf("CSV: %s\n", $outputPath);
