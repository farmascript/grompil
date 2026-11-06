<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:45:46
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages translation text and publishes the general language files.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:translations:translations
 */

declare(strict_types=1);


require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('translations.php');
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
        try {
            $transaction = db_query_params('translations.import.begin', 'BEGIN');
        } catch (GrompilDatabaseQueryException) {
            $transaction = false;
        }
        $ok = (bool) $transaction;
        foreach ($languages as $importLanguage) {
            if (!$ok) {
                break;
            }
            $arrSubst = fn_translationSeed($importLanguage);
            foreach ($arrSubst as $key => $value) {
                try {
                    $insert = db_query_params(
                        'translations.import_value',
                        'INSERT INTO substitutions (company_id, "key", "value", code) VALUES (0, $1, $2, $3) ON CONFLICT (company_id, "key", code) DO NOTHING',
                        [(string) $key, (string) $value, $importLanguage]
                    );
                } catch (GrompilDatabaseQueryException) {
                    $insert = false;
                }
                if (!$insert) {
                    $ok = false;
                    break;
                }
            }
        }
        if ($ok) {
            try {
                db_query_params('translations.import.commit', 'COMMIT');
            } catch (GrompilDatabaseQueryException) {
                $ok = false;
            }
        }
        if ($ok) {
            $messageKey = 'translations_imported';
            foreach ($languages as $exportLanguage) {
                $ok = $ok && fn_exportLanguageFile($exportLanguage);
            }
            if (!$ok) {
                $messageKey = 'translations_publish_failed';
                $messageType = 'danger';
            }
        } else {
            try {
                db_query_params('translations.import.rollback', 'ROLLBACK');
            } catch (GrompilDatabaseQueryException) {
                // The helper already logged the rollback failure.
            }
            $messageKey = 'translations_import_failed';
            $messageType = 'danger';
        }
    } elseif ($action === 'save') {
        try {
            $ok = (bool) db_query_params('translations.save.begin', 'BEGIN');
        } catch (GrompilDatabaseQueryException) {
            $ok = false;
        }
        foreach ((array) ($_POST['translations'] ?? []) as $key => $value) {
            $key = (string) $key;
            if (!preg_match('/^[a-zA-Z0-9_.-]{1,120}$/', $key) || !is_scalar($value)) {
                $ok = false;
                break;
            }
            try {
                $result = db_query_params(
                    'translations.save_value',
                    'INSERT INTO substitutions (company_id, "key", "value", code) VALUES (0, $1, $2, $3) ON CONFLICT (company_id, "key", code) DO UPDATE SET "value" = EXCLUDED."value", updated_at = now()',
                    [$key, (string) $value, $language]
                );
            } catch (GrompilDatabaseQueryException) {
                $result = false;
            }
            if (!$result) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            try {
                db_query_params('translations.save.commit', 'COMMIT');
            } catch (GrompilDatabaseQueryException) {
                $ok = false;
            }
        }
        if ($ok) {
            $ok = fn_exportLanguageFile($language);
        } else {
            try {
                db_query_params('translations.save.rollback', 'ROLLBACK');
            } catch (GrompilDatabaseQueryException) {
                // The helper already logged the rollback failure.
            }
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

try {
    $result = db_query_params(
        'translations.list',
        'SELECT "key", "value" FROM substitutions WHERE company_id = 0 AND code = $1 ORDER BY "key"',
        [$language]
    );
} catch (GrompilDatabaseQueryException) {
    $result = false;
}
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

