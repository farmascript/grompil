<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Wed 2026-10-21 09:10:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Checks templates for missing or unused placeholders.
 *
 */

declare(strict_types=1);

/*
@NAV
id: 48361
sequence_number: 7
public_visibility: false
group: platform
group_title: @nav_admin@
action: manage
title: @template_check_heading@
description: @template_check_intro@
@ENDNAV
*/

$isCli = PHP_SAPI === 'cli';
$currentUser = null;
if (!$isCli) {
    require dirname(__DIR__) . '/includes/bootstrap.php';
    $currentUser = require_permission('companies.manage');
    $currentUser = require_level_range(1, 100, $currentUser);
}

$projectRoot = dirname(__DIR__);
$templateRoot = $projectRoot . DIRECTORY_SEPARATOR . 'tmpl';
$languageRoot = getenv('GROMPIL_LANGUAGE_DIR') ?: $projectRoot . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'lang';
$languages = ['nl', 'fr', 'en', 'de'];
$languageArrays = [];
$errors = [];
$missingTranslations = [];

foreach ($languages as $language) {
    $path = rtrim($languageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lang_' . $language . '.php';
    if (!is_file($path)) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'lang'
            . DIRECTORY_SEPARATOR . 'lang_' . $language . '.php';
    }
    if (!is_file($path)) {
        $errors[] = "Language file is missing: {$path}";
        continue;
    }
    $arrSubst = [];
    require $path;
    if (!is_array($arrSubst)) {
        $errors[] = "Taalbestand bevat geen geldige \$arrSubst-array: {$path}";
        continue;
    }
    $languageArrays[$language] = $arrSubst;
}

$phpValues = [];
$phpIterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $entry): bool {
            if (!$entry->isDir()) {
                return true;
            }
            return !in_array($entry->getFilename(), ['.git', 'vendor', 'node_modules'], true)
                && !str_starts_with($entry->getFilename(), '.');
        }
    )
);
foreach ($phpIterator as $entry) {
    if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'php') {
        continue;
    }
    $source = file_get_contents($entry->getPathname());
    if ($source === false) {
        continue;
    }
    if (preg_match_all('/[\'"]([A-Za-z0-9_]+)[\'"][\t ]*=>/', $source, $matches)) {
        foreach ($matches[1] as $key) {
            $phpValues[$key] = true;
        }
    }
}

$templateFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
);
$checkedTemplates = 0;
$checkedTokens = 0;
foreach ($templateFiles as $entry) {
    if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'html') {
        continue;
    }
    $checkedTemplates++;
    $path = $entry->getPathname();
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($projectRoot) + 1));
    $content = file_get_contents($path);
    if ($content === false) {
        $errors[] = "Could not read template: {$relativePath}";
        continue;
    }
    if (!preg_match_all('/@([A-Za-z0-9_]+)@/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[1] as [$key, $offset]) {
        $checkedTokens++;
        $line = substr_count(substr($content, 0, $offset), "\n") + 1;
        if (isset($phpValues[$key])) {
            continue;
        }

        if (str_starts_with($key, '_')) {
            $errors[] = "{$relativePath}:{$line}: templatewaarde @{$key}@ komt niet voor als PHP-templatewaarde.";
            continue;
        }

        $missingLanguages = [];
        foreach ($languages as $language) {
            if (!array_key_exists($key, $languageArrays[$language] ?? [])) {
                $missingLanguages[] = $language;
            }
        }
        if ($missingLanguages !== []) {
            $missingTranslations[$key] = $missingLanguages;
            $errors[] = "{$relativePath}:{$line}: translation key '{$key}' is missing from language file(s): "
                . implode(', ', $missingLanguages) . '.';
        }
    }
}

$errors = array_values(array_unique($errors));
$summary = sprintf('%d templates en %d tokens gecontroleerd; %d fout(en).', $checkedTemplates, $checkedTokens, count($errors));

if ($isCli) {
    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, "ERROR: {$error}\n");
        }
        fwrite(STDERR, $summary . "\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf("Check passed: %d templates and %d tokens checked.\n", $checkedTemplates, $checkedTokens));
    exit(0);
}

$flashMessage = (string) ($_SESSION['template_check_flash_message'] ?? '');
$flashType = (string) ($_SESSION['template_check_flash_type'] ?? 'success');
unset($_SESSION['template_check_flash_message'], $_SESSION['template_check_flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ok = pg_query(db(), 'BEGIN') !== false;
    $inserted = 0;
    foreach ($ok ? $missingTranslations : [] as $key => $missingLanguages) {
        foreach ($missingLanguages as $language) {
            $result = pg_query_params(
                db(),
                'INSERT INTO substitutions (company_id, "key", "value", code)
                 VALUES (0, $1, $2, $3)
                 ON CONFLICT (company_id, "key", code) DO NOTHING',
                [$key, '!' . $key . '!', strtoupper($language)]
            );
            if (!$result) {
                $ok = false;
                break 2;
            }
            $inserted += pg_affected_rows($result);
        }
    }

    if ($ok && pg_query(db(), 'COMMIT')) {
        $published = true;
        foreach (array_map('strtoupper', $languages) as $language) {
            $published = fn_exportLanguageFile($language) && $published;
        }
        $_SESSION['template_check_flash_message'] = $published
            ? sprintf('%d ontbrekende vertalingen toegevoegd als !keyword!.', $inserted)
            : sprintf('%d ontbrekende vertalingen staan in de database, maar een of meer taalbestanden konden niet worden bijgewerkt.', $inserted);
        $_SESSION['template_check_flash_type'] = $published ? 'success' : 'warning';
    } else {
        pg_query(db(), 'ROLLBACK');
        $_SESSION['template_check_flash_message'] = 'The update failed; no changes were saved.';
        $_SESSION['template_check_flash_type'] = 'danger';
    }

    header('Location: ' . (string) ($_SERVER['SCRIPT_NAME'] ?? 'check_templates.php'));
    exit;
}

page_start(fn_t('template_check_heading'), $currentUser);
echo '<div class="mb-4"><span class="eyebrow">' . e(fn_t('nav_admin')) . '</span>'
    . '<h1 class="h2 mt-2">' . e(fn_t('template_check_heading')) . '</h1>'
    . '<p class="text-secondary">' . e(fn_t('template_check_intro')) . '</p></div>';
echo '<div class="alert alert-' . ($errors === [] ? 'success' : 'warning') . '" role="status">'
    . e(fn_t($errors === [] ? 'template_check_success' : 'template_check_failure'))
    . '<div class="small mt-1">' . e($summary) . '</div></div>';
if ($errors !== []) {
    echo '<div class="card"><ul class="list-group list-group-flush">';
    foreach ($errors as $error) {
        echo '<li class="list-group-item"><code>' . e($error) . '</code></li>';
    }
    echo '</ul></div>';
    if ($missingTranslations !== []) {
        echo '<form method="post" class="mt-3 d-flex justify-content-end">'
            . csrf_field()
            . '<button class="btn btn-primary" type="submit">Probleem oplossen</button>'
            . '</form>';
    }
}
if ($flashMessage !== '') {
    echo '<div class="alert alert-' . e($flashType) . ' mt-3" role="status">' . e($flashMessage) . '</div>';
}
page_end();

