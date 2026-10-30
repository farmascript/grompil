<?php
/**
 * Export current SAM public-pharmacy CNK records to a one-row-per-DMPP/CNK CSV.
 *
 * Usage:
 *   php sam_public_catalog_export.php <AMP-export.xml> [output.csv] [YYYY-MM-DD]
 *
 * This is an exploratory, read-only extractor. It does not connect to a
 * database. Current AMP/AMPP/DMPP Data slices are retained as JSON columns so
 * fields not yet mapped into the proposed catalog schema are not discarded.
 */

declare(strict_types=1);

function showUsage(string $script): never
{
    fwrite(STDERR, "Usage: php {$script} <AMP-export.xml> [output.csv] [YYYY-MM-DD]\n");
    exit(2);
}

function xmlLocalName(DOMNode $node): string
{
    return $node->localName ?? $node->nodeName;
}

function xmlNameIs(DOMNode $node, string ...$names): bool
{
    $actual = strtolower(xmlLocalName($node));
    foreach ($names as $name) {
        if ($actual === strtolower($name)) {
            return true;
        }
    }
    return false;
}

/** @return list<DOMElement> */
function xmlDirectChildren(DOMElement $parent, string ...$names): array
{
    $result = [];
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && xmlNameIs($child, ...$names)) {
            $result[] = $child;
        }
    }
    return $result;
}

/** @return list<DOMElement> */
function xmlDescendants(DOMElement $parent): array
{
    $result = [];
    $walk = static function (DOMNode $node) use (&$walk, &$result): void {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $result[] = $child;
            $walk($child);
        }
    };
    $walk($parent);
    return $result;
}

function xmlAttribute(DOMElement $element, string ...$names): ?string
{
    foreach ($element->attributes as $attribute) {
        foreach ($names as $name) {
            if (strtolower($attribute->name) === strtolower($name)) {
                $value = trim($attribute->value);
                return $value === '' ? null : $value;
            }
        }
    }
    return null;
}

function currentDataSlice(DOMElement $entity, string $date): ?DOMElement
{
    foreach (xmlDirectChildren($entity, 'Data') as $data) {
        $from = xmlAttribute($data, 'from');
        $to = xmlAttribute($data, 'to');
        if (($from === null || $from <= $date) && ($to === null || $to >= $date)) {
            return $data;
        }
    }
    return null;
}

function firstDescendantText(DOMElement $parent, string ...$names): ?string
{
    foreach (xmlDescendants($parent) as $element) {
        if (!xmlNameIs($element, ...$names)) {
            continue;
        }
        $value = trim($element->textContent);
        if ($value !== '') {
            return $value;
        }
    }
    return null;
}

function directChildText(DOMElement $parent, string ...$names): ?string
{
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && xmlNameIs($child, ...$names)) {
            $value = trim($child->textContent);
            return $value === '' ? null : $value;
        }
    }
    return null;
}

function languageText(?DOMElement $parent, string $containerName, string $language): ?string
{
    if ($parent === null) {
        return null;
    }
    $elements = xmlNameIs($parent, $containerName) ? [$parent] : [];
    $elements = array_merge($elements, xmlDescendants($parent));
    foreach ($elements as $element) {
        if (!xmlNameIs($element, $containerName)) {
            continue;
        }
        foreach (xmlDirectChildren($element, $language) as $languageElement) {
            $value = trim($languageElement->textContent);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return null;
}

/** Convert an XML element into a JSON-safe map, preserving repeated elements and attributes. */
function xmlElementValue(DOMElement $element): mixed
{
    $value = [];
    foreach ($element->attributes as $attribute) {
        $value['@' . $attribute->name] = $attribute->value;
    }

    $children = [];
    $text = '';
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $key = xmlLocalName($child);
            $childValue = xmlElementValue($child);
            if (array_key_exists($key, $children)) {
                if (!is_array($children[$key]) || !array_is_list($children[$key])) {
                    $children[$key] = [$children[$key]];
                }
                $children[$key][] = $childValue;
            } else {
                $children[$key] = $childValue;
            }
        } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
            $text .= $child->nodeValue ?? '';
        }
    }

    if ($children !== []) {
        $value = array_merge($value, $children);
        $text = trim($text);
        if ($text !== '') {
            $value['#text'] = $text;
        }
        return $value;
    }

    $text = trim($text);
    if ($value === []) {
        return $text;
    }
    if ($text !== '') {
        $value['#text'] = $text;
    }
    return $value;
}

/** @return list<string> */
function deliveryCodes(DOMElement $ampp, ?DOMElement $currentData, string $fieldName): array
{
    $field = strtolower($fieldName);
    $accepted = [$field, $field . 'code'];
    $roots = array_values(array_filter([$currentData, $ampp]));
    $values = [];

    foreach ($roots as $root) {
        $rootCode = xmlAttribute($root, $field . 'code');
        if ($rootCode !== null) {
            $values[$rootCode] = true;
        }
        $candidates = [];
        if (in_array(strtolower(xmlLocalName($root)), $accepted, true)) {
            $candidates[] = $root;
        }
        foreach (xmlDescendants($root) as $element) {
            if (in_array(strtolower(xmlLocalName($element)), $accepted, true)) {
                $candidates[] = $element;
            }
        }
        foreach ($candidates as $candidate) {
            $code = xmlAttribute($candidate, 'code', 'value');
            if ($code === null) {
                $code = directChildText($candidate, 'Code');
            }
            if ($code === null && str_ends_with(strtolower(xmlLocalName($candidate)), 'code')) {
                $code = trim($candidate->textContent) ?: null;
            }
            if ($code !== null) {
                $values[$code] = true;
            }
        }
        if ($values !== []) {
            break;
        }
    }

    $codes = array_keys($values);
    sort($codes, SORT_NATURAL | SORT_FLAG_CASE);
    return $codes;
}

function csvValue(?string $value): string
{
    return $value ?? '';
}

$script = basename($argv[0] ?? 'sam_public_catalog_export.php');
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc < 2 || $argc > 4) {
    showUsage($script);
}
if (!class_exists(XMLReader::class) || !class_exists(DOMDocument::class)) {
    fwrite(STDERR, "ERROR: PHP extensions XMLReader and DOM are required.\n");
    exit(1);
}

$inputPath = $argv[1];
$outputPath = $argv[2] ?? null;
$explicitDate = $argv[3] ?? null;
if (!is_file($inputPath) || !is_readable($inputPath)) {
    fwrite(STDERR, "ERROR: input file does not exist or is not readable: {$inputPath}\n");
    exit(1);
}
if ($explicitDate !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $explicitDate) || !checkdate((int) substr($explicitDate, 5, 2), (int) substr($explicitDate, 8, 2), (int) substr($explicitDate, 0, 4)))) {
    fwrite(STDERR, "ERROR: date must use YYYY-MM-DD format.\n");
    exit(2);
}

$reader = new XMLReader();
if (!$reader->open($inputPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
    fwrite(STDERR, "ERROR: could not open XML file: {$inputPath}\n");
    exit(1);
}

$exportVersion = '(onbekend)';
$samId = '(onbekend)';
$asOf = $explicitDate;
$rows = 0;
$parseWarnings = 0;
$output = null;

libxml_use_internal_errors(true);
while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT) {
        continue;
    }
    if ($reader->depth === 0) {
        $exportVersion = $reader->getAttribute('version') ?? $exportVersion;
        $samId = $reader->getAttribute('SamId') ?? $samId;
        if ($asOf === null && preg_match('/\.(\d{4})(\d{2})(\d{2})_/', $samId, $match)) {
            $asOf = $match[1] . '-' . $match[2] . '-' . $match[3];
        }
        if ($outputPath === null) {
            $safeSamId = preg_replace('/[^A-Za-z0-9._-]+/', '_', $samId) ?: 'unknown';
            $outputPath = getcwd() . DIRECTORY_SEPARATOR . 'sam_public_catalog_' . $safeSamId . '.csv';
        }
        $output = fopen($outputPath, 'wb');
        if ($output === false) {
            fwrite(STDERR, "ERROR: could not create CSV file: {$outputPath}\n");
            exit(1);
        }
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel on Windows.
        fputcsv($output, [
            'sam_export_version', 'sam_id', 'validity_date',
            'amp_code', 'ampp_code', 'cnk', 'delivery_environment', 'cnk_code_type',
            'amp_status', 'ampp_status', 'official_name', 'name_nl', 'name_fr',
            'prescription_name_nl', 'prescription_name_fr', 'medicine_type',
            'delivery_modus_codes_json', 'delivery_modus_specification_codes_json',
            'amp_data_json', 'ampp_data_json', 'dmpp_data_json',
            'pack_display_nl', 'pack_display_fr',
        ], ',', '"', '');
        continue;
    }
    if (strtolower($reader->localName) !== 'amp') {
        continue;
    }

    $ampXml = $reader->readOuterXML();
    if ($ampXml === '') {
        $parseWarnings++;
        continue;
    }
    $ampDocument = new DOMDocument();
    if (!$ampDocument->loadXML($ampXml, LIBXML_NONET | LIBXML_COMPACT) || !$ampDocument->documentElement) {
        $parseWarnings++;
        continue;
    }
    $amp = $ampDocument->documentElement;
    $ampData = $asOf === null ? null : currentDataSlice($amp, $asOf);
    $ampCode = xmlAttribute($amp, 'code', 'ampCode') ?? '';
    $officialName = $ampData === null ? null : directChildText($ampData, 'OfficialName');
    $ampName = null;
    $prescriptionName = null;
    $medicineType = $ampData === null ? null : directChildText($ampData, 'MedicineType');
    if ($ampData !== null) {
        foreach (xmlDirectChildren($ampData, 'Name') as $nameNode) {
            $ampName = $nameNode;
            break;
        }
        foreach (xmlDirectChildren($ampData, 'PrescriptionNameFamhp', 'PrescriptionName') as $nameNode) {
            $prescriptionName = $nameNode;
            break;
        }
    }

    foreach (xmlDescendants($amp) as $ampp) {
        if (!xmlNameIs($ampp, 'Ampp')) {
            continue;
        }
        $amppData = $asOf === null ? null : currentDataSlice($ampp, $asOf);
        if ($amppData === null) {
            continue;
        }
        $amppCode = xmlAttribute($ampp, 'ctiExtended', 'code', 'cti_extended', 'ctiExtendedCode') ?? '';
        $amppStatus = directChildText($amppData, 'Status') ?? '';
        $deliveryModes = deliveryCodes($ampp, $amppData, 'DeliveryModus');
        $deliverySpecs = deliveryCodes($ampp, $amppData, 'DeliveryModusSpecification');

        foreach (xmlDescendants($ampp) as $dmpp) {
            if (!xmlNameIs($dmpp, 'Dmpp')) {
                continue;
            }
            $environment = strtoupper(xmlAttribute($dmpp, 'deliveryEnvironment', 'environment') ?? '');
            $codeType = strtoupper(xmlAttribute($dmpp, 'codeType', 'type') ?? '');
            $cnk = xmlAttribute($dmpp, 'code');
            if ($environment !== 'P' || $codeType !== 'CNK' || $cnk === null) {
                continue;
            }
            $dmppData = $asOf === null ? null : currentDataSlice($dmpp, $asOf);
            if ($dmppData === null) {
                continue;
            }

            $ampDataJson = $ampData === null ? 'null' : json_encode(xmlElementValue($ampData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $amppDataJson = json_encode(xmlElementValue($amppData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $dmppDataJson = json_encode(xmlElementValue($dmppData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            fputcsv($output, [
                $exportVersion,
                $samId,
                $asOf ?? '',
                $ampCode,
                $amppCode,
                $cnk,
                $environment,
                $codeType,
                $ampData === null ? '' : (directChildText($ampData, 'Status') ?? ''),
                $amppStatus,
                csvValue($officialName),
                csvValue(languageText($ampName, 'Name', 'Nl')),
                csvValue(languageText($ampName, 'Name', 'Fr')),
                csvValue(languageText($prescriptionName, 'PrescriptionNameFamhp', 'Nl') ?? languageText($prescriptionName, 'PrescriptionName', 'Nl')),
                csvValue(languageText($prescriptionName, 'PrescriptionNameFamhp', 'Fr') ?? languageText($prescriptionName, 'PrescriptionName', 'Fr')),
                csvValue($medicineType),
                json_encode($deliveryModes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($deliverySpecs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $ampDataJson === false ? 'null' : $ampDataJson,
                $amppDataJson === false ? '' : $amppDataJson,
                $dmppDataJson === false ? 'null' : $dmppDataJson,
                csvValue(languageText($amppData, 'PackDisplayValue', 'Nl')),
                csvValue(languageText($amppData, 'PackDisplayValue', 'Fr')),
            ], ',', '"', '');
            $rows++;
        }
    }
}
$reader->close();

if (is_resource($output)) {
    fclose($output);
}
if ($asOf === null || $outputPath === null) {
    fwrite(STDERR, "ERROR: no validity date or SAM export header was found. Supply YYYY-MM-DD as the final argument.\n");
    exit(1);
}
$xmlErrors = libxml_get_errors();
if ($xmlErrors !== []) {
    $parseWarnings += count($xmlErrors);
}
libxml_clear_errors();

echo "SAM public catalog CSV created\n";
echo 'Input: ' . $inputPath . "\n";
echo 'Export version: ' . $exportVersion . "\n";
echo 'SAM ID: ' . $samId . "\n";
echo 'Validity date used: ' . $asOf . "\n";
echo 'Rows (current P/CNK DMPP entries): ' . number_format($rows, 0, ',', '.') . "\n";
echo 'XML parsing warnings: ' . number_format($parseWarnings, 0, ',', '.') . "\n";
echo 'CSV: ' . $outputPath . "\n";
