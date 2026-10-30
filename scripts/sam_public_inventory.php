<?php
/**
 * Read-only inventory of current SAM AMPP packages with a public CNK DMPP.
 *
 * Usage:
 *   php sam_public_inventory.php "..\AMP-1793368804542.xml" [YYYY-MM-DD]
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

function localName(DOMNode $node): string
{
    return strtolower($node->localName ?? $node->nodeName);
}

/** @return list<DOMElement> */
function directChildrenNamed(DOMElement $parent, string $wanted): array
{
    $matches = [];
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && localName($child) === strtolower($wanted)) {
            $matches[] = $child;
        }
    }
    return $matches;
}

/** @return list<DOMElement> */
function descendantsNamed(DOMElement $parent, string $wanted): array
{
    $matches = [];
    $wanted = strtolower($wanted);
    $walk = static function (DOMNode $node) use (&$walk, &$matches, $wanted): void {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if (localName($child) === $wanted) {
                $matches[] = $child;
            }
            $walk($child);
        }
    };
    $walk($parent);
    return $matches;
}

function attributeValue(DOMElement $element, string ...$names): ?string
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

function directChildText(DOMElement $parent, string ...$names): ?string
{
    foreach ($parent->childNodes as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }
        foreach ($names as $name) {
            if (localName($child) === strtolower($name)) {
                $value = trim($child->textContent);
                return $value === '' ? null : $value;
            }
        }
    }
    return null;
}

/** Return the Data slice valid on the requested date, if any. */
function currentData(DOMElement $entity, string $asOf): ?DOMElement
{
    foreach (directChildrenNamed($entity, 'Data') as $data) {
        $from = attributeValue($data, 'from');
        $to = attributeValue($data, 'to');

        if (($from === null || $from <= $asOf) && ($to === null || $to >= $asOf)) {
            return $data;
        }
    }
    return null;
}

function statusOf(?DOMElement $data): string
{
    if ($data === null) {
        return '(geen status)';
    }
    return strtoupper(directChildText($data, 'Status') ?? '(geen status)');
}

function increment(array &$counts, string $key, int $amount = 1): void
{
    $counts[$key] = ($counts[$key] ?? 0) + $amount;
}

function formatCounts(array $counts): array
{
    ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
    $lines = [];
    foreach ($counts as $key => $count) {
        $lines[] = sprintf("  %-20s %s", $key . ':', number_format($count, 0, ',', '.'));
    }
    return $lines;
}

$scriptName = basename($argv[0] ?? 'sam_public_inventory.php');
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

$explicitDate = $argv[2] ?? null;
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
$dateFromSamId = null;
$ampRecords = 0;
$currentAmpRecords = 0;
$currentAmpStatuses = [];
$ampps = 0;
$currentAmpps = 0;
$currentAmppStatuses = [];
$currentAmppsWithPublicCnk = 0;
$currentPublicCnkAmppStatuses = [];
$currentPublicCnkByAmpAndAmppStatus = [];
$currentPublicCnkEntries = 0;
$publicCnksWithoutCurrentData = 0;
$warningCount = 0;

$reportDate = $explicitDate;

libxml_use_internal_errors(true);
while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT) {
        continue;
    }

    if ($reader->depth === 0) {
        $rootVersion = $reader->getAttribute('version');
        $rootSamId = $reader->getAttribute('SamId');
        if ($rootVersion !== null) {
            $exportVersion = $rootVersion;
        }
        if ($rootSamId !== null) {
            $samId = $rootSamId;
            if (preg_match('/\.(\d{4})(\d{2})(\d{2})_/', $samId, $match)) {
                $dateFromSamId = $match[1] . '-' . $match[2] . '-' . $match[3];
            }
        }
        if ($reportDate === null && $dateFromSamId !== null) {
            $reportDate = $dateFromSamId;
        }
        continue;
    }

    if (localNameFromReader($reader) !== 'amp') {
        continue;
    }

    $ampXml = $reader->readOuterXML();
    if ($ampXml === '') {
        $warningCount++;
        continue;
    }

    $ampDocument = new DOMDocument();
    if (!$ampDocument->loadXML($ampXml, LIBXML_NONET | LIBXML_COMPACT) || !$ampDocument->documentElement) {
        $warningCount++;
        continue;
    }

    $amp = $ampDocument->documentElement;
    $ampRecords++;
    $ampData = $reportDate === null ? null : currentData($amp, $reportDate);
    $ampStatus = statusOf($ampData);
    if ($ampData !== null) {
        $currentAmpRecords++;
        increment($currentAmpStatuses, $ampStatus);
    }

    foreach (descendantsNamed($amp, 'Ampp') as $ampp) {
        $ampps++;
        $amppData = $reportDate === null ? null : currentData($ampp, $reportDate);
        if ($amppData === null) {
            continue;
        }

        $currentAmpps++;
        $amppStatus = statusOf($amppData);
        increment($currentAmppStatuses, $amppStatus);

        $hasCurrentPublicCnk = false;
        foreach (descendantsNamed($ampp, 'Dmpp') as $dmpp) {
            $environment = strtoupper(attributeValue($dmpp, 'deliveryEnvironment', 'environment') ?? '');
            $codeType = strtoupper(attributeValue($dmpp, 'codeType', 'type') ?? '');
            $code = attributeValue($dmpp, 'code');

            if ($environment !== 'P' || $codeType !== 'CNK' || $code === null) {
                continue;
            }

            $dmppData = $reportDate === null ? null : currentData($dmpp, $reportDate);
            if ($dmppData === null) {
                $publicCnksWithoutCurrentData++;
                continue;
            }

            $hasCurrentPublicCnk = true;
            $currentPublicCnkEntries++;
        }

        if ($hasCurrentPublicCnk) {
            $currentAmppsWithPublicCnk++;
            increment($currentPublicCnkAmppStatuses, $amppStatus);
            increment($currentPublicCnkByAmpAndAmppStatus, $ampStatus . ' / ' . $amppStatus);
        }
    }
}
$reader->close();

if ($reportDate === null) {
    fwrite(STDERR, "ERROR: no date was supplied and no date could be read from SamId.\n");
    exit(1);
}

$xmlErrors = libxml_get_errors();
if ($xmlErrors !== []) {
    $warningCount += count($xmlErrors);
}
libxml_clear_errors();

$lines = [
    'SAM AMP public-CNK inventory',
    'Input: ' . $inputPath,
    'Export version: ' . $exportVersion,
    'SAM ID: ' . $samId,
    'Validity date used: ' . $reportDate,
    'Scope: current AMPP packages with at least one current DMPP code of type CNK in delivery environment P.',
    'Note: these are structural source counts. They do not decide legal or commercial availability.',
    '',
    'Counts:',
    '  AMP records: ' . number_format($ampRecords, 0, ',', '.'),
    '  AMP records with current data: ' . number_format($currentAmpRecords, 0, ',', '.'),
    '  AMPP package records: ' . number_format($ampps, 0, ',', '.'),
    '  AMPP packages with current data: ' . number_format($currentAmpps, 0, ',', '.'),
    '  Current AMPP packages with current public CNK: ' . number_format($currentAmppsWithPublicCnk, 0, ',', '.'),
    '  Current public CNK DMPP/code entries: ' . number_format($currentPublicCnkEntries, 0, ',', '.'),
    '  Public CNK DMPP entries without current data: ' . number_format($publicCnksWithoutCurrentData, 0, ',', '.'),
    '',
    'Current AMP statuses:',
];
$lines = array_merge($lines, formatCounts($currentAmpStatuses));
$lines[] = '';
$lines[] = 'Current AMPP statuses:';
$lines = array_merge($lines, formatCounts($currentAmppStatuses));
$lines[] = '';
$lines[] = 'Current AMPP packages with a current public CNK, by AMPP status:';
$lines = array_merge($lines, formatCounts($currentPublicCnkAmppStatuses));
$lines[] = '';
$lines[] = 'Current public-CNK AMPP packages, by AMP status / AMPP status:';
$lines = array_merge($lines, formatCounts($currentPublicCnkByAmpAndAmppStatus));
$lines[] = '';
$lines[] = 'XML parsing warnings: ' . number_format($warningCount, 0, ',', '.');

$safeSamId = preg_replace('/[^A-Za-z0-9._-]+/', '_', $samId) ?: 'unknown';
$reportPath = getcwd() . DIRECTORY_SEPARATOR . 'sam_public_inventory_' . $safeSamId . '.txt';
$report = implode(PHP_EOL, $lines) . PHP_EOL;
if (file_put_contents($reportPath, $report) === false) {
    fwrite(STDERR, "ERROR: could not write report file: {$reportPath}\n");
    exit(1);
}

echo $report;
echo PHP_EOL . 'Report saved: ' . $reportPath . PHP_EOL;

function localNameFromReader(XMLReader $reader): string
{
    return strtolower($reader->localName);
}
