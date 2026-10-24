<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages company-specific translations and text overrides.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
$user = require_login();
$company = require_company_context($user);
if (!grompil_can_access_module('company_translations.php', $user, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}

$languages = ['NL', 'FR', 'EN', 'DE'];
$language = strtoupper((string) ($_GET['language'] ?? $_POST['language'] ?? 'NL'));
if (!in_array($language, $languages, true)) {
    $language = 'NL';
}
$messageKey = '';
$messageType = 'info';
$sourceTranslations = fn_translationSeed($language);
$knownKeys = array_keys($sourceTranslations);
sort($knownKeys, SORT_STRING);
$keywordOptions = '';
foreach ($knownKeys as $key) {
    if ($key !== 'html_language') {
        $keywordOptions .= '<option value="' . e((string) $key) . '" data-original="'
            . e((string) ($sourceTranslations[$key] ?? '')) . '"></option>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $ok = false;
    if ($action === 'add') {
        $key = trim((string) ($_POST['translation_key'] ?? ''));
        $value = $_POST['translation_value'] ?? null;
        $knownKeyMap = array_fill_keys($knownKeys, true);
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,120}$/', $key)
            || $key === 'html_language'
            || !isset($knownKeyMap[$key])
            || !is_scalar($value)
            || trim((string) $value) === '') {
            $messageKey = 'company_translation_invalid';
        } else {
            $result = pg_query_params(
                db(),
                'INSERT INTO substitutions (company_id, "key", "value", code)
                 VALUES ($1, $2, $3, $4)
                 ON CONFLICT (company_id, "key", code) DO UPDATE
                 SET "value" = EXCLUDED."value", updated_at = now()',
                [(int) $company['id'], $key, (string) $value, $language]
            );
            $ok = (bool) $result && fn_exportCompanyLanguageFile((int) $company['id'], $language);
            $messageKey = $ok ? 'company_translation_saved' : 'company_translation_publish_failed';
        }
    } elseif ($action === 'save') {
        $deleteKey = trim((string) ($_POST['delete_key'] ?? ''));
        if ($deleteKey !== '' && !preg_match('/^[a-zA-Z0-9_.-]{1,120}$/', $deleteKey)) {
            $messageKey = 'company_translation_invalid';
        } elseif ($deleteKey !== '') {
            $result = pg_query_params(
                db(),
                'DELETE FROM substitutions WHERE company_id = $1 AND "key" = $2 AND code = $3',
                [(int) $company['id'], $deleteKey, $language]
            );
            $ok = (bool) $result && fn_exportCompanyLanguageFile((int) $company['id'], $language);
            $messageKey = $ok ? 'company_translation_deleted' : 'company_translation_publish_failed';
        } else {
            $transaction = (bool) pg_query(db(), 'BEGIN');
            $ok = $transaction;
            foreach ((array) ($_POST['translations'] ?? []) as $key => $value) {
                $key = (string) $key;
                if (!preg_match('/^[a-zA-Z0-9_.-]{1,120}$/', $key) || !is_scalar($value)) {
                    $ok = false;
                    break;
                }
                $result = pg_query_params(
                    db(),
                    'UPDATE substitutions SET "value" = $1, updated_at = now()
                     WHERE company_id = $2 AND "key" = $3 AND code = $4',
                    [(string) $value, (int) $company['id'], $key, $language]
                );
                if (!$result) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $ok = (bool) pg_query(db(), 'COMMIT');
                $ok = $ok && fn_exportCompanyLanguageFile((int) $company['id'], $language);
            } else {
                pg_query(db(), 'ROLLBACK');
            }
            $messageKey = $ok ? 'company_translation_saved' : 'company_translation_publish_failed';
        }
    }
    if (in_array($messageKey, ['company_translation_invalid', 'company_translation_publish_failed'], true)) {
        $messageType = 'danger';
    } elseif (in_array($messageKey, ['company_translation_saved', 'company_translation_deleted'], true)) {
        $messageType = 'success';
    }
}

$result = pg_query_params(
    db(),
    'SELECT "key", "value" FROM substitutions
     WHERE company_id = $1 AND code = $2 ORDER BY "key"',
    [(int) $company['id'], $language]
);
$rows = '';
while ($result && $row = pg_fetch_assoc($result)) {
    $rows .= fn_templateRender('company_translations_row.html', [
        'translation_key' => (string) $row['key'],
        'translation_value' => (string) $row['value'],
    ]);
}
$languageSelect = '<select class="form-select form-select-sm w-auto" aria-label="'
    . e(fn_t('translations_language_label')) . '" data-language-parameter="language">';
foreach ($languages as $item) {
    $selected = $item === $language ? ' selected' : '';
    $languageSelect .= '<option value="' . e($item) . '"' . $selected . '>' . e($item) . '</option>';
}
$languageSelect .= '</select>';
$message = $messageKey === '' ? '' : '<div class="alert alert-' . e($messageType)
    . '" role="status">' . e(fn_t($messageKey)) . '</div>';

page_start(fn_t('company_translations_heading'), $user);
echo fn_templateRender('company_translations.html', [
    '_message_html' => $message,
    '_language_select_html' => $languageSelect,
    '_keyword_options_html' => $keywordOptions,
    'original_content_label' => fn_t('company_translation_original'),
    '_override_rows_html' => $rows !== ''
        ? $rows
        : '<tr><td colspan="3" class="text-secondary">' . e(fn_t('company_translations_empty')) . '</td></tr>',
    'company_name' => (string) $company['name'],
    'language' => $language,
    '_csrf_field_html' => csrf_field(),
]);
page_end();

