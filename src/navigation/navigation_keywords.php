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
 * @description: Provides translated labels for the fixed Grompil navigation.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = require_permission('companies.manage');
$currentUser = require_level_range(1, 100, $currentUser);

$languages = ['NL', 'FR', 'EN', 'DE'];
$message = '';
$messageType = 'info';
$candidateKeys = [];
$sourceRoot = GROMPIL_ROOT;
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $entry): bool {
            if (!$entry->isDir()) {
                return true;
            }
            return !in_array($entry->getFilename(), ['.git', 'vendor', 'node_modules'], true)
                && !str_starts_with($entry->getFilename(), '.');
        }
    )
);

foreach ($iterator as $entry) {
    if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'php') {
        continue;
    }
    $source = file_get_contents($entry->getPathname());
    if ($source === false || !preg_match_all('~/\*(.*?)\*/~s', $source, $comments)) {
        continue;
    }
    foreach ($comments[1] as $comment) {
        $comment = preg_replace('/^[\t ]*\* ?/m', '', $comment) ?? $comment;
        if (!preg_match_all('/^[\t ]*@NAV[\t ]*\R(.*?)^[\t ]*@ENDNAV[\t ]*$/ms', $comment, $blocks)) {
            continue;
        }
        foreach ($blocks[1] as $block) {
            if (preg_match_all('/@([A-Za-z0-9_]+)@/', $block, $tokens)) {
                foreach ($tokens[1] as $key) {
                    $candidateKeys[$key] = true;
                }
            }
        }
    }
}

$candidateKeys = array_keys($candidateKeys);
sort($candidateKeys, SORT_NATURAL | SORT_FLAG_CASE);
$missingByKey = [];
foreach ($candidateKeys as $key) {
    $result = pg_query_params(
        db(),
        'SELECT code FROM substitutions WHERE company_id = 0 AND "key" = $1',
        [$key]
    );
    if (!$result) {
        grompil_show_error(500, 61724, 'De bestaande vertalingen konden niet worden gecontroleerd. Probeer het later opnieuw.', 'Controle mislukt');
    }
    $existing = [];
    while ($row = pg_fetch_assoc($result)) {
        $existing[] = strtoupper((string) $row['code']);
    }
    $missing = array_values(array_diff($languages, $existing));
    if ($missing !== []) {
        $missingByKey[$key] = $missing;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ok = (bool) pg_query(db(), 'BEGIN');
    $inserted = 0;
    foreach ($ok ? $missingByKey : [] as $key => $missingLanguages) {
        foreach ($missingLanguages as $language) {
            $result = pg_query_params(
                db(),
                'INSERT INTO substitutions (company_id, "key", "value", code)
                 VALUES (0, $1, $2, $3)
                 ON CONFLICT (company_id, "key", code) DO NOTHING',
                [$key, '!' . $key . '!', $language]
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
        foreach ($languages as $language) {
            $published = fn_exportLanguageFile($language) && $published;
        }
        $message = $inserted > 0
            ? sprintf('%d ontbrekende taalvertalingen toegevoegd als !keyword!.', $inserted)
            : 'Er waren geen ontbrekende taalvertalingen.';
        if (!$published) {
            $message .= ' De taalbestanden konden niet allemaal worden bijgewerkt.';
            $messageType = 'warning';
        } else {
            $messageType = 'success';
        }
        $missingByKey = [];
    } else {
        pg_query(db(), 'ROLLBACK');
        $message = 'The update failed; no changes were saved.';
        $messageType = 'danger';
    }
}

$rows = '';
foreach ($missingByKey as $key => $missingLanguages) {
    $rows .= '<tr><td><code>' . e($key) . '</code></td><td>'
        . e(implode(', ', $missingLanguages)) . '</td></tr>';
}
$messageHtml = $message === '' ? ''
    : '<div class="alert alert-' . e($messageType) . '" role="status">' . e($message) . '</div>';
$csrfField = csrf_field();

page_start(fn_t('translations_heading'), $currentUser);
echo '<main class="container py-4">'
    . $messageHtml
    . '<h1 class="h2">Ontbrekende navigatievertalingen</h1>'
    . '<p class="text-secondary">Deze controle leest de @NAV-blokken uit PHP-bestanden. Ontbrekende talen worden als <code>!keyword!</code> toegevoegd aan de algemene vertalingen. Bestaande vertalingen blijven behouden.</p>'
    . '<p><a href="translations.php">Vertalingen beheren</a></p>';

if ($rows !== '') {
    echo '<form method="post" class="card shadow-sm">' . $csrfField
        . '<div class="table-responsive"><table class="table table-striped align-middle mb-0">'
        . '<thead><tr><th scope="col">Keyword</th><th scope="col">Ontbrekende talen</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>'
        . '<div class="card-footer d-flex justify-content-between align-items-center gap-3">'
        . '<span>' . count($missingByKey) . ' keywords gevonden</span>'
        . '<button class="btn btn-primary" type="submit">Ontbrekende keywords toevoegen</button>'
        . '</div></form>';
} else {
    echo '<div class="alert alert-success" role="status">Alle keywords uit de navigatie zijn al in de algemene vertalingen aanwezig.</div>';
}
echo '</main>';
page_end();

