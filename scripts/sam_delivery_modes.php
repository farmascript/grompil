<?php
/**
 * Read-only inventory of SAM delivery-mode and delivery-mode-specification
 * values attached to current AMPP package records.
 *
 * Usage:
 *   php sam_delivery_modes.php "..\AMP-1793368804542.xml" [YYYY-MM-DD]
 *
 * The script streams the large XML file one AMP record at a time and writes
 * a compact text report in the current working directory. It does not modify
 * the source XML or connect to a database.
 */

declare(strict_types=1);

function usage(string $script): never
{
    fwrite(STDERR, "Usage: php {$script} <AMP-export.xml> [YYYY-MM-DD]\n");
    exit(2);
}

function samLocalName(DOMNode $node): string
{
    return strtolower($node->localName ?? $node->nodeName);
}

/** @return list<DOMElement> */
function samDescendants(DOMElement $parent): array
{
    $matches = [];
    $walk = static function (DOMNode $node) use (&$walk, &$matches): void {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $matches[] = $child;
            $walk($child);
        }
    };
    $walk($parent);
    return $matches;
}

/** @return list<DOMElement> */
function samDirectChildren(DOMElement $parent, string $name): array
{
    $matches = [];
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && samLocalName($child) === strtolower($name)) {
            $matches[] = $child;
        }
    }
    return $matches;
}

function samAttribute(DOMElement $element, string ...$names): ?string
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

function samCurrentData(DOMElement $entity, string $asOf): ?DOMElement
{
    foreach (samDirectChildren($entity, 'Data') as $data) {
        $from = samAttribute($data, 'from');
        $to = samAttribute($data, 'to');
        if (($from === null || $from <= $asOf) && ($to === null || $to >= $asOf)) {
            return $data;
        }
    }
    return null;
}

function samStatus(?DOMElement $data): string
{
    if ($data === null) {
        return '(geen status)';
    }
    foreach ($data->childNodes as $child) {
        if ($child instanceof DOMElement && samLocalName($child) === 'status') {
            return strtoupper(trim($child->textContent) ?: '(geen status)');
        }
    }
    return '(geen status)';
}

/** Extract a code from an element represented as either an attribute or a child/text value. */
function samCodeValue(DOMElement $element): ?string
{
    $code = samAttribute($element, 'code', 'value');
    if ($code !== null) {
        return $code;
    }
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement && samLocalName($child) === 'code') {
            $code = trim($child->textContent);
            if ($code !== '') {
                return $code;
            }
        }
    }
    $name = samLocalName($element);
    if (str_ends_with($name, 'code')) {
        $code = trim($element->textContent);
        return $code === '' ? null : $code;
    }
    return null;
}

/** @return list<string> */
function samFieldCodes(DOMElement $ampp, ?DOMElement $currentAmppData, string $kind): array
{
    $base = $kind === 'mode' ? 'deliverymodus' : 'deliverymodusspecification';
    $acceptedNames = [$base, $base . 'code'];
    $searchRoots = [];
    if ($currentAmppData !== null) {
        $searchRoots[] = $currentAmppData;
    }
    $searchRoots[] = $ampp;

    $values = [];
    foreach ($searchRoots as $root) {
        $rootAttribute = samAttribute($root, $base . 'code');
        if ($rootAttribute !== null) {
            $values[$rootAttribute] = true;
        }
        $candidates = [];
        if (in_array(samLocalName($root), $acceptedNames, true)) {
            $candidates[] = $root;
        }
        foreach (samDescendants($root) as $element) {
            if (in_array(samLocalName($element), $acceptedNames, true)) {
                $candidates[] = $element;
            }
        }
        foreach ($candidates as $candidate) {
            $value = samCodeValue($candidate);
            if ($value !== null) {
                $values[$value] = true;
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

function samIncrement(array &$counts, string $key): void
{
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

function samSortedLines(array $counts): array
{
    ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
    $lines = [];
    foreach ($counts as $key => $count) {
        $lines[] = sprintf("  %-42s %s", $key . ':', number_format($count, 0, ',', '.'));
    }
    return $lines;
}

$scriptName = basename($argv[0] ?? 'sam_delivery_modes.php');
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc < 2 || $argc > 3) {
    usage($scriptName);
}

$inputPath = $argv[1];
if (!is_file($inputPath) || !is_readable($inputPath)) {
    fwrite(STDERR, "ERROR: input file does not exist or is not readable: {$inputPath}\n");
    exit(1);
}
if (!class_exists(XMLReader::class) || !class_exists(DOMDocument::class)) {
    fwrite(STDERR, "ERROR: PHP extensions XMLReader and DOM are required.\n");
    exit(1);
}

$asOf = $argv[2] ?? null;
if ($asOf !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf) || !checkdate((int) substr($asOf, 5, 2), (int) substr($asOf, 8, 2), (int) substr($asOf, 0, 4)))) {
    fwrite(STDERR, "ERROR: date must use YYYY-MM-DD format.\n");
    exit(2);
}

$reader = new XMLReader();
if (!$reader->open($inputPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
    fwrite(STDERR, "ERROR: could not open XML file: {$inputPath}\n");
    exit(1);
}

$version = '(onbekend)';
$samId = '(onbekend)';
$ampCount = 0;
$currentAmppCount = 0;
$currentStatusCounts = [];
$modeCounts = [];
$specificationCounts = [];
$combinationCounts = [];
$noModeCount = 0;
$noSpecificationCount = 0;
$examples = [];
$parseWarnings = 0;

libxml_use_internal_errors(true);
while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT) {
        continue;
    }
    if ($reader->depth === 0) {
        $version = $reader->getAttribute('version') ?? $version;
        $samId = $reader->getAttribute('SamId') ?? $samId;
        if ($asOf === null && preg_match('/\.(\d{4})(\d{2})(\d{2})_/', $samId, $match)) {
            $asOf = $match[1] . '-' . $match[2] . '-' . $match[3];
        }
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
    $ampCount++;

    foreach (samDescendants($amp) as $element) {
        if (samLocalName($element) !== 'ampp') {
            continue;
        }
        $ampp = $element;
        $amppData = $asOf === null ? null : samCurrentData($ampp, $asOf);
        if ($amppData === null) {
            continue;
        }

        $currentAmppCount++;
        $status = samStatus($amppData);
        samIncrement($currentStatusCounts, $status);

        $modes = samFieldCodes($ampp, $amppData, 'mode');
        $specifications = samFieldCodes($ampp, $amppData, 'specification');
        if ($modes === []) {
            $modes = ['(geen code gevonden)'];
            $noModeCount++;
        }
        if ($specifications === []) {
            $specifications = ['(geen specificatie)'];
            $noSpecificationCount++;
        }

        foreach ($modes as $mode) {
            samIncrement($modeCounts, $mode);
        }
        foreach ($specifications as $specification) {
            samIncrement($specificationCounts, $specification);
        }
        foreach ($modes as $mode) {
            foreach ($specifications as $specification) {
                $combination = $mode . ' | ' . $specification . ' | ' . $status;
                samIncrement($combinationCounts, $combination);
                if (count($examples[$combination] ?? []) < 5) {
                    $amppCode = samAttribute($ampp, 'ctiExtended', 'code') ?? '(AMPP-code ontbreekt)';
                    $ampCode = samAttribute($amp, 'code') ?? '(AMP-code ontbreekt)';
                    $examples[$combination][] = $ampCode . ' / ' . $amppCode;
                }
            }
        }
    }
}
$reader->close();

if ($asOf === null) {
    fwrite(STDERR, "ERROR: no date was supplied and no date could be read from SamId.\n");
    exit(1);
}
$xmlErrors = libxml_get_errors();
if ($xmlErrors !== []) {
    $parseWarnings += count($xmlErrors);
}
libxml_clear_errors();

$lines = [
    'SAM AMP delivery-mode inventory',
    'Input: ' . $inputPath,
    'Export version: ' . $version,
    'SAM ID: ' . $samId,
    'Validity date used: ' . $asOf,
    'Scope: current AMPP package records; code values are counted as found in the export.',
    'Note: this is a structural inventory, not a legal interpretation or availability decision.',
    '',
    'Counts:',
    '  AMP records read: ' . number_format($ampCount, 0, ',', '.'),
    '  AMPP packages with current data: ' . number_format($currentAmppCount, 0, ',', '.'),
    '  Current AMPP packages without a DeliveryModus code: ' . number_format($noModeCount, 0, ',', '.'),
    '  Current AMPP packages without a DeliveryModusSpecification: ' . number_format($noSpecificationCount, 0, ',', '.'),
    '',
    'Current DeliveryModus codes:',
];
$lines = array_merge($lines, samSortedLines($modeCounts));
$lines[] = '';
$lines[] = 'Current DeliveryModusSpecification codes:';
$lines = array_merge($lines, samSortedLines($specificationCounts));
$lines[] = '';
$lines[] = 'Code combinations by current AMPP status:';
$lines = array_merge($lines, samSortedLines($combinationCounts));
$lines[] = '';
$lines[] = 'Examples (AMP / AMPP) per combination:';
ksort($examples, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($examples as $combination => $codes) {
    $lines[] = '  ' . $combination . ': ' . implode(', ', $codes);
}
$lines[] = '';
$lines[] = 'Current AMPP statuses:';
$lines = array_merge($lines, samSortedLines($currentStatusCounts));
$lines[] = '';
$lines[] = 'XML parsing warnings: ' . number_format($parseWarnings, 0, ',', '.');

$safeSamId = preg_replace('/[^A-Za-z0-9._-]+/', '_', $samId) ?: 'unknown';
$reportPath = getcwd() . DIRECTORY_SEPARATOR . 'sam_delivery_modes_' . $safeSamId . '.txt';
$report = implode(PHP_EOL, $lines) . PHP_EOL;
if (file_put_contents($reportPath, $report) === false) {
    fwrite(STDERR, "ERROR: could not write report file: {$reportPath}\n");
    exit(1);
}

echo $report;
echo PHP_EOL . 'Report saved: ' . $reportPath . PHP_EOL;
