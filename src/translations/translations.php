<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:45:46
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages translation text and publishes the general language files.
 *
 */

declare(strict_types=1);


require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = require_permission('translations.manage');
$currentUser = require_level_range(1, 100, $currentUser);
$languages = ['NL', 'FR', 'EN', 'DE'];
$language = strtoupper((string) ($_GET['language'] ?? $_POST['language'] ?? 'NL'));
if (!in_array($language, $languages, true)) {
    $language = 'NL';
}
$messageKey = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'import') {
        $transaction = pg_query(db(), 'BEGIN');
        $ok = (bool) $transaction;
        foreach ($languages as $importLanguage) {
            if (!$ok) {
                break;
            }
            $arrSubst = fn_translationSeed($importLanguage);
            foreach ($arrSubst as $key => $value) {
                $insert = pg_query_params(db(), 'INSERT INTO substitutions (company_id, "key", "value", code) VALUES (0, $1, $2, $3) ON CONFLICT (company_id, "key", code) DO NOTHING', [(string) $key, (string) $value, $importLanguage]);
                if (!$insert) {
                    $ok = false;
                    break;
                }
            }
        }
        if ($ok) {
            pg_query(db(), 'COMMIT');
            $messageKey = 'translations_imported';
            foreach ($languages as $exportLanguage) {
                $ok = $ok && fn_exportLanguageFile($exportLanguage);
            }
            if (!$ok) {
                $messageKey = 'translations_publish_failed';
                $messageType = 'danger';
            }
        } else {
            pg_query(db(), 'ROLLBACK');
            $messageKey = 'translations_import_failed';
            $messageType = 'danger';
        }
    } elseif ($action === 'save') {
        $ok = (bool) pg_query(db(), 'BEGIN');
        foreach ((array) ($_POST['translations'] ?? []) as $key => $value) {
            $key = (string) $key;
            if (!preg_match('/^[a-zA-Z0-9_.-]{1,120}$/', $key) || !is_scalar($value)) {
                $ok = false;
                break;
            }
            $result = pg_query_params(db(), 'INSERT INTO substitutions (company_id, "key", "value", code) VALUES (0, $1, $2, $3) ON CONFLICT (company_id, "key", code) DO UPDATE SET "value" = EXCLUDED."value", updated_at = now()', [$key, (string) $value, $language]);
            if (!$result) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            pg_query(db(), 'COMMIT');
            $ok = fn_exportLanguageFile($language);
        } else {
            pg_query(db(), 'ROLLBACK');
        }
        $messageKey = $ok ? 'translations_saved' : 'translations_publish_failed';
        $messageType = $ok ? 'success' : 'danger';
    } elseif ($action === 'export') {
        $ok = true;
        foreach ($languages as $exportLanguage) {
            $ok = fn_exportLanguageFile($exportLanguage) && $ok;
        }
        $messageKey = $ok ? 'translations_published' : 'translations_publish_failed';
        $messageType = $ok ? 'success' : 'danger';
    }
}

$result = pg_query_params(db(), 'SELECT "key", "value" FROM substitutions WHERE company_id = 0 AND code = $1 ORDER BY "key"', [$language]);
$rows = '';
while ($result && $row = pg_fetch_assoc($result)) {
    $rows .= fn_templateRender('translations_row.html', [
        'translation_key' => $row['key'],
        'translation_value' => $row['value'],
    ]);
}
$languageLinks = '<select class="form-select form-select-sm w-auto" aria-label="Translation language" data-language-parameter="language">';
foreach ($languages as $item) {
    $selected = $item === $language ? ' selected' : '';
    $languageLinks .= '<option value="' . e($item) . '"' . $selected . '>' . e($item) . '</option>';
}
$languageLinks .= '</select>';
$message = $messageKey === '' ? '' : '<div class="alert alert-' . e($messageType) . '" role="status">' . e(fn_t($messageKey)) . '</div>';
page_start(fn_t('nav_translations'), $currentUser);
echo fn_templateRender('translations.html', [
    '_message_html' => $message,
    '_language_links_html' => $languageLinks,
    '_translation_rows_html' => $rows,
    'language' => $language,
    'language_name' => strtoupper($language),
    '_csrf_field_html' => csrf_field(),
]);
page_end();

