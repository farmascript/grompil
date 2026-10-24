<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Wed 2026-10-21 17:07:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Generates the multilingual navigation catalog from module metadata.
 *
 */

declare(strict_types=1);

/**
 * Build the browser URL-manager catalog from trusted module NAV comments and
 * the generated PHP language arrays. Application modules are scanned as text
 * and are never included or executed.
 *
 * Usage: php scripts/generate_navigation.php [--root=PATH] [--webroot=PATH]
 *        [--output=PATH] [--application=ID]
 */

$arguments = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
        fwrite(STDERR, "Invalid argument: {$argument}\n");
        exit(2);
    }
    [$name, $value] = explode('=', substr($argument, 2), 2);
    if (!in_array($name, ['root', 'webroot', 'output', 'application'], true) || $value === '') {
        fwrite(STDERR, "Unknown or empty argument: {$argument}\n");
        exit(2);
    }
    $arguments[$name] = $value;
}

$sourceRoot = realpath($arguments['root'] ?? dirname(__DIR__));
$webroot = realpath($arguments['webroot'] ?? ($sourceRoot ?: dirname(__DIR__)));
$application = $arguments['application'] ?? 'grompil';
if ($sourceRoot === false || $webroot === false || !is_dir($sourceRoot) || !is_dir($webroot)) {
    fwrite(STDERR, "The source directory and web root must be existing directories.\n");
    exit(2);
}

$versionFile = $sourceRoot . DIRECTORY_SEPARATOR . 'VERSION';
$applicationVersion = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
if (preg_match('/^\d+\.\d+\.\d+$/D', $applicationVersion) !== 1) {
    fwrite(STDERR, "VERSION must contain a semantic version in major.minor.revision format.\n");
    exit(1);
}
if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $application) !== 1) {
    fwrite(STDERR, "Invalid application identifier.\n");
    exit(2);
}

$languages = ['nl', 'fr', 'en', 'de'];
$languageDirectory = getenv('GROMPIL_LANGUAGE_DIR') ?: $sourceRoot . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'lang';
$languageArrays = [];
$errors = [];
$warnings = [];

$loadLanguageArray = static function (string $path): ?array {
    $arrSubst = [];
    try {
        require $path;
    } catch (Throwable $exception) {
        return null;
    }
    return is_array($arrSubst) ? $arrSubst : null;
};

foreach ($languages as $language) {
    $path = rtrim($languageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'lang_' . $language . '.php';
    if (!is_file($path)) {
        $path = $sourceRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'lang'
            . DIRECTORY_SEPARATOR . 'lang_' . $language . '.php';
    }
    $array = is_file($path) ? $loadLanguageArray($path) : null;
    if ($array === null) {
        $errors[] = "Language file is missing or does not contain the $arrSubst array for {$language}: {$path}";
        continue;
    }
    $languageArrays[$language] = $array;
}

$extractNavBlocks = static function (string $source): array {
    $blocks = [];
    if (!preg_match_all('~/\*(.*?)\*/~s', $source, $comments)) {
        return $blocks;
    }
    foreach ($comments[1] as $comment) {
        $comment = preg_replace('/^[\t ]*\* ?/m', '', $comment) ?? $comment;
        if (preg_match_all('/^[\t ]*@NAV[\t ]*\R(.*?)^[\t ]*@ENDNAV[\t ]*\r?$/ms', $comment, $matches)) {
            foreach ($matches[1] as $block) {
                $blocks[] = $block;
            }
        }
    }
    return $blocks;
};

$parseNavBlock = static function (string $block, string $file) use (&$errors): ?array {
    $metadata = [];
    $allowed = ['id', 'group', 'group_title', 'action', 'title', 'description', 'keywords', 'public_visibility', 'sequence_number'];
    foreach (preg_split('/\R/', trim($block)) ?: [] as $lineNumber => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/^([a-z_]+):[\t ]*(.*?)\s*$/', $line, $match)) {
            $errors[] = "Invalid NAV line in {$file}:" . ($lineNumber + 1) . ": {$line}";
            return null;
        }
        $key = $match[1];
        if (!in_array($key, $allowed, true)) {
            $errors[] = "Unknown NAV field '{$key}' in {$file}.";
            return null;
        }
        if (array_key_exists($key, $metadata)) {
            $errors[] = "Duplicate NAV field '{$key}' in {$file}.";
            return null;
        }
        $metadata[$key] = trim($match[2]);
    }

    foreach (['id', 'group', 'group_title', 'title'] as $required) {
        if (!isset($metadata[$required]) || $metadata[$required] === '') {
            $errors[] = "Required NAV field '{$required}' is missing in {$file}.";
            return null;
        }
    }
    if (!preg_match('/^[1-9][0-9]{0,15}$/', $metadata['id'])) {
        $errors[] = "NAV id must be a positive integer in {$file}.";
        return null;
    }
    $id = (int) $metadata['id'];
    if ($id > 9007199254740991) {
        $errors[] = "NAV id exceeds the safe JSON integer limit in {$file}.";
        return null;
    }
    if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $metadata['group']) !== 1) {
        $errors[] = "Invalid technical group ID in {$file}.";
        return null;
    }
    if (isset($metadata['action']) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $metadata['action']) !== 1) {
        $errors[] = "Invalid action in {$file}.";
        return null;
    }
    if (isset($metadata['public_visibility']) && !in_array(strtolower($metadata['public_visibility']), ['true', 'false'], true)) {
        $errors[] = "NAV field 'public_visibility' must be true or false in {$file}.";
        return null;
    }
    if (isset($metadata['sequence_number'])
        && (!preg_match('/^[1-9][0-9]*$/', $metadata['sequence_number'])
            || (int) $metadata['sequence_number'] > 99)) {
        $errors[] = "NAV field 'sequence_number' must be a unique fixed number from 1 to 99 in {$file}.";
        return null;
    }
    foreach (['group_title', 'title', 'description', 'keywords'] as $tokenField) {
        if (isset($metadata[$tokenField]) && $metadata[$tokenField] !== ''
            && preg_match('/^@[A-Za-z0-9_]+@$/', $metadata[$tokenField]) !== 1) {
            $errors[] = "NAV field '{$tokenField}' must contain one translation token (@keyword@) in {$file}.";
            return null;
        }
    }
    $metadata['public_visibility'] = strtolower($metadata['public_visibility'] ?? 'true') === 'true';
    if (isset($metadata['sequence_number'])) {
        $metadata['sequence_number'] = (int) $metadata['sequence_number'];
    }
    return ['id' => $id] + $metadata;
};

$translateToken = static function (string $token, string $language) use (&$languageArrays, &$errors, &$warnings): string {
    if ($token === '') {
        return '';
    }
    preg_match('/^@([A-Za-z0-9_]+)@$/', $token, $match);
    $key = $match[1] ?? '';
    foreach ([$language, 'en', 'nl'] as $fallbackLanguage) {
        $value = $languageArrays[$fallbackLanguage][$key] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    $placeholder = '!' . $key . '!';
    $warnings[] = "Translation token {$token} is missing from all language files; using placeholder {$placeholder} for {$language}.";
    return $placeholder;
};

$relativePath = static function (string $file) use ($webroot): ?string {
    $realFile = realpath($file);
    if ($realFile === false) {
        return null;
    }
    $prefix = rtrim($webroot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with(strtolower($realFile), strtolower($prefix))) {
        return null;
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', substr($realFile, strlen($prefix)));
};

$sourceIterator = new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS);
$filteredIterator = new RecursiveCallbackFilterIterator(
    $sourceIterator,
    static function (SplFileInfo $entry): bool {
        if ($entry->isDir()) {
            return !in_array($entry->getFilename(), ['.git', 'vendor', 'node_modules'], true)
                && !str_starts_with($entry->getFilename(), '.');
        }
        return strtolower($entry->getExtension()) === 'php';
    }
);
$phpFiles = new RecursiveIteratorIterator($filteredIterator);
$itemsById = [];
$groupsById = [];
$sequenceNumbers = [];

foreach ($phpFiles as $entry) {
    if (!$entry->isFile() || $entry->isLink()) {
        continue;
    }
    $file = $entry->getPathname();
    $source = file_get_contents($file);
    if ($source === false) {
        continue;
    }
    $blocks = $extractNavBlocks($source);
    if ($blocks === []) {
        continue;
    }
    if (count($blocks) > 1) {
        $errors[] = "Meerdere @NAV-blokken in één PHP-bestand zijn nog niet ondersteund: {$file}";
        continue;
    }
    $metadata = $parseNavBlock($blocks[0], $file);
    if ($metadata === null) {
        continue;
    }
    $modulePath = $relativePath($file);
    if ($modulePath === null || $modulePath === '') {
        $errors[] = "Module ligt buiten de opgegeven webroot en kan geen relatieve URL krijgen: {$file}";
        continue;
    }
    if (isset($itemsById[$metadata['id']])) {
        $errors[] = "Dubbel NAV-id {$metadata['id']}: {$itemsById[$metadata['id']]['module']} en {$modulePath}.";
        continue;
    }
    if (isset($metadata['sequence_number'])) {
        if (isset($sequenceNumbers[$metadata['sequence_number']])) {
            $errors[] = "Dubbel sequence_number {$metadata['sequence_number']}: {$sequenceNumbers[$metadata['sequence_number']]} en {$modulePath}.";
            continue;
        }
        $sequenceNumbers[$metadata['sequence_number']] = $modulePath;
    }

    $item = [
        'id' => $metadata['id'],
        'module' => $modulePath,
        'group' => $metadata['group'],
        'public_visibility' => $metadata['public_visibility'],
        'i18n' => [],
    ];
    if (isset($metadata['sequence_number'])) {
        $item['sequence_number'] = $metadata['sequence_number'];
    }
    if (isset($metadata['action'])) {
        $item['action'] = $metadata['action'];
    }
    foreach ($languages as $language) {
        if (!isset($languageArrays[$language])) {
            continue;
        }
        $title = $translateToken($metadata['title'], $language);
        if ($title === '') {
            $errors[] = "Lege titel voor NAV-id {$metadata['id']} in taal {$language}.";
        }
        $entryI18n = ['title' => $title];
        if (isset($metadata['description']) && $metadata['description'] !== '') {
            $entryI18n['description'] = $translateToken($metadata['description'], $language);
        } else {
            $entryI18n['description'] = '';
        }
        $keywords = [];
        if (isset($metadata['keywords']) && $metadata['keywords'] !== '') {
            $keywordText = $translateToken($metadata['keywords'], $language);
            $keywords = array_values(array_unique(array_filter(array_map('trim', explode('|', $keywordText)), static fn (string $word): bool => $word !== '')));
        }
        $entryI18n['keywords'] = $keywords;
        $item['i18n'][$language] = $entryI18n;

        $groupTitle = $translateToken($metadata['group_title'], $language);
        if (isset($groupsById[$metadata['group']]['i18n'][$language]['title'])
            && $groupsById[$metadata['group']]['i18n'][$language]['title'] !== $groupTitle) {
            $errors[] = "Group '{$metadata['group']}' gebruikt verschillende group_title-tokens of vertalingen.";
        }
        $groupsById[$metadata['group']]['id'] = $metadata['group'];
        $groupsById[$metadata['group']]['i18n'][$language]['title'] = $groupTitle;
    }
    $itemsById[$metadata['id']] = $item;
}

if ($itemsById === []) {
    $errors[] = 'Geen geldige NAV-modules gevonden.';
}
if ($errors !== []) {
    foreach (array_unique($errors) as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    fwrite(STDERR, "navigation.json was not changed. Fix the errors and run the generator again.\n");
    exit(1);
}

ksort($groupsById, SORT_STRING);
ksort($itemsById, SORT_NUMERIC);
$nextSequenceNumber = 100;
foreach ($itemsById as &$item) {
    if (!isset($item['sequence_number'])) {
        while (isset($sequenceNumbers[$nextSequenceNumber])) {
            $nextSequenceNumber++;
        }
        $item['sequence_number'] = $nextSequenceNumber;
        $sequenceNumbers[$nextSequenceNumber] = $item['module'];
        $nextSequenceNumber++;
    }
}
unset($item);
uasort($itemsById, static fn (array $left, array $right): int => $left['sequence_number'] <=> $right['sequence_number']);
$catalog = [
    'schemaVersion' => 1,
    'application' => $application,
    'applicationVersion' => $applicationVersion,
    'defaultLanguage' => 'nl',
    'languages' => $languages,
    'groups' => array_values($groupsById),
    'items' => array_values($itemsById),
    'managerI18n' => array_map(
        static fn (array $translations): array => array_filter(
            $translations,
            static fn (string $key): bool => str_starts_with($key, 'manager_') || $key === 'nav_logout',
            ARRAY_FILTER_USE_KEY
        ),
        $languageArrays
    ),
];

try {
    $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        . PHP_EOL;
} catch (JsonException $exception) {
    fwrite(STDERR, 'JSON generation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

$output = $arguments['output'] ?? $webroot . DIRECTORY_SEPARATOR . 'navigation.json';
$outputDirectory = dirname($output);
if (!is_dir($outputDirectory) || !is_writable($outputDirectory)) {
    fwrite(STDERR, "Output directory does not exist or is not writable: {$outputDirectory}\n");
    exit(1);
}
$temporary = $output . '.' . bin2hex(random_bytes(8)) . '.tmp';
if (file_put_contents($temporary, $json, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write temporary JSON file: {$temporary}\n");
    exit(1);
}
if (!@rename($temporary, $output)) {
    if (!@copy($temporary, $output)) {
        @unlink($temporary);
        fwrite(STDERR, "Could not publish navigation.json: {$output}\n");
        exit(1);
    }
    @unlink($temporary);
}

$managerDirectory = $sourceRoot . DIRECTORY_SEPARATOR . 'url-manager';
if (is_dir($managerDirectory)) {
    $catalogScript = 'window.GROMPIL_NAVIGATION = ' . rtrim($json) . ';' . PHP_EOL;
    $scriptPath = $managerDirectory . DIRECTORY_SEPARATOR . 'catalog.js';
    $scriptTemporary = $scriptPath . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($scriptTemporary, $catalogScript, LOCK_EX) === false) {
        fwrite(STDERR, "Could not write the temporary manager catalog: {$scriptTemporary}\n");
        exit(1);
    }
    if (!@rename($scriptTemporary, $scriptPath)) {
        if (!@copy($scriptTemporary, $scriptPath)) {
            @unlink($scriptTemporary);
            fwrite(STDERR, "Could not publish the manager catalog: {$scriptPath}\n");
            exit(1);
        }
        @unlink($scriptTemporary);
    }

    $versionScript = 'window.GROMPIL_URL_MANAGER_VERSION = ' . json_encode($applicationVersion, JSON_THROW_ON_ERROR) . ';' . PHP_EOL;
    $versionScriptPath = $managerDirectory . DIRECTORY_SEPARATOR . 'version.js';
    $versionScriptTemporary = $versionScriptPath . '.' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($versionScriptTemporary, $versionScript, LOCK_EX) === false) {
        fwrite(STDERR, "Could not write the temporary manager version: {$versionScriptTemporary}\n");
        exit(1);
    }
    if (!@rename($versionScriptTemporary, $versionScriptPath)) {
        if (!@copy($versionScriptTemporary, $versionScriptPath)) {
            @unlink($versionScriptTemporary);
            fwrite(STDERR, "Could not publish the manager version: {$versionScriptPath}\n");
            exit(1);
        }
        @unlink($versionScriptTemporary);
    }

    $managerIndexPath = $managerDirectory . DIRECTORY_SEPARATOR . 'index.html';
    if (is_file($managerIndexPath)) {
        $managerIndex = file_get_contents($managerIndexPath);
        if ($managerIndex === false) {
            fwrite(STDERR, "Could not read the manager page: {$managerIndexPath}\n");
            exit(1);
        }
        $catalogVersion = substr(hash('sha256', $catalogScript), 0, 16);
        $updatedManagerIndex = preg_replace(
            '~(src=["\']catalog\.js)(?:\?v=[^"\']*)?(["\'])~',
            '$1?v=' . $catalogVersion . '$2',
            $managerIndex,
            1,
            $catalogReferenceCount
        );
        if ($updatedManagerIndex === null || $catalogReferenceCount !== 1) {
            fwrite(STDERR, "The catalog reference in {$managerIndexPath} could not be updated unambiguously.\n");
            exit(1);
        }
        $updatedManagerIndex = preg_replace(
            '~(src=["\']version\.js)(?:\?v=[^"\']*)?(["\'])~',
            '$1?v=' . rawurlencode($applicationVersion) . '$2',
            $updatedManagerIndex,
            1,
            $versionReferenceCount
        );
        if ($updatedManagerIndex === null || $versionReferenceCount !== 1) {
            fwrite(STDERR, "The version reference in {$managerIndexPath} could not be updated unambiguously.\n");
            exit(1);
        }
        if ($updatedManagerIndex !== $managerIndex
            && file_put_contents($managerIndexPath, $updatedManagerIndex, LOCK_EX) === false) {
            fwrite(STDERR, "Could not update the manager page: {$managerIndexPath}\n");
            exit(1);
        }
    }
}

fwrite(STDOUT, count($itemsById) . ' modules and ' . count($groupsById)
    . " groups published to {$output}" . (is_dir($managerDirectory) ? " and url-manager/catalog.js.\n" : ".\n"));
foreach (array_unique($warnings) as $warning) {
    fwrite(STDERR, "WARNING: {$warning}\n");
}

