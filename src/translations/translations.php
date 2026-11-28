<?php
/**
 * @date_cre: Wed 2026-10-14 21:45:46
 * @date_mut: Sat 2026-11-28 15:32:43
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

if (($_GET['ajax'] ?? '') === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $respond = static function (array $data, int $status = 200): never {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    };
    $keyQuery = trim((string) ($_GET['key'] ?? ''));
    $textQuery = trim((string) ($_GET['text'] ?? ''));
    $showAll = ($_GET['all'] ?? '') === '1';
    $page = max(1, min(1000000, (int) ($_GET['page'] ?? 1)));
    $perPage = 50;
    if (strlen($keyQuery) > 200 || strlen($textQuery) > 2000) {
        $respond(['error' => 'invalid_query'], 400);
    }
    if ($keyQuery === '' && $textQuery === '' && !$showAll) {
        $respond(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage]);
    }
    $likePattern = static function (string $query): string {
        return '%' . strtr($query, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    };
    try {
        $countResult = db_query_params(
            'translations.search_count',
            'SELECT COUNT(*) AS total
             FROM ft_substitutions
             WHERE company_id = 0 AND code = $1
               AND ($2 = \'\' OR "key" ILIKE $3 ESCAPE E\'\\\\\')
               AND ($4 = \'\' OR "value" ILIKE $5 ESCAPE E\'\\\\\')',
            [$language, $keyQuery, $likePattern($keyQuery), $textQuery, $likePattern($textQuery)]
        );
        $total = (int) pg_fetch_result($countResult, 0, 'total');
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pageCount);
        $offset = ($page - 1) * $perPage;
        $itemsResult = db_query_params(
            'translations.search_items',
            'SELECT "key", "value"
             FROM ft_substitutions
             WHERE company_id = 0 AND code = $1
               AND ($2 = \'\' OR "key" ILIKE $3 ESCAPE E\'\\\\\')
               AND ($4 = \'\' OR "value" ILIKE $5 ESCAPE E\'\\\\\')
             ORDER BY "key" LIMIT $6 OFFSET $7',
            [$language, $keyQuery, $likePattern($keyQuery), $textQuery, $likePattern($textQuery), $perPage, $offset]
        );
        $items = [];
        while ($row = pg_fetch_assoc($itemsResult)) {
            $items[] = ['key' => (string) $row['key'], 'value' => (string) $row['value']];
        }
        $respond(['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    } catch (GrompilDatabaseQueryException) {
        $respond(['error' => 'search_failed'], 500);
    }
}

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
                        'INSERT INTO ft_substitutions (company_id, "key", "value", code) VALUES (0, $1, $2, $3) ON CONFLICT (company_id, "key", code) DO NOTHING',
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
                    'INSERT INTO ft_substitutions (company_id, "key", "value", code) VALUES (0, $1, $2, $3) ON CONFLICT (company_id, "key", code) DO UPDATE SET "value" = EXCLUDED."value", updated_at = now()',
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
    if ($action === 'save'
        && (($_POST['_translation_ajax_save'] ?? '') === '1'
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode([
            'success' => $messageType === 'success',
            'message' => fn_t($messageKey),
            'message_type' => $messageType,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

$languageLinks = '<select id="translations-language" name="language" class="form-select form-select-sm w-auto" aria-label="'
    . e(fn_t('translations_language_label')) . '" data-language-parameter="language">';
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
    'language' => $language,
    'language_name' => strtoupper($language),
    '_csrf_field_html' => csrf_field(),
]);
page_end();

