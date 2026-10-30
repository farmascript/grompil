<?php
/**
 *
 * @date_cre: Fri 2026-10-30
 * @date_mut: Fri 2026-10-30
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages platform-wide hierarchical dropdown values and translations.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('dd-varia.php');
if (($currentUser['company_id'] ?? null) !== null || !is_platform_operator($currentUser)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
}

$languages = ['NL', 'FR', 'EN', 'DE'];
$messageKey = (string) ($_SESSION['dd_varia_message']['key'] ?? '');
$messageType = (string) ($_SESSION['dd_varia_message']['type'] ?? 'info');
unset($_SESSION['dd_varia_message']);
$editingId = 0;
$formValues = [
    'list_key' => strtolower(trim((string) ($_GET['list'] ?? ''))),
    'key' => '',
    'parent_id' => '',
    'info' => '',
    'sort_order' => '0',
    'is_active' => true,
    'translations' => array_fill_keys($languages, ''),
];

/**
 * Store a translated message for the next request and redirect after a change.
 *
 * @param string $key The translation key.
 * @param string $type The Bootstrap alert type.
 * @param string $listKey The list to show after redirecting.
 * @return never
 */
function dd_varia_admin_redirect(string $key, string $type, string $listKey): never
{
    $_SESSION['dd_varia_message'] = ['key' => $key, 'type' => $type];
    redirect('dd-varia.php?list=' . rawurlencode($listKey));
}

/**
 * Validate and save the four platform-wide translations for one lookup row.
 *
 * @param int $id The lookup row ID.
 * @param array $values The translation values by language code.
 * @return bool
 */
function dd_varia_admin_save_translations(int $id, array $values): bool
{
    $translationKey = 'ddv_' . $id;
    foreach (['NL', 'FR', 'EN', 'DE'] as $language) {
        $result = pg_query_params(
            db(),
            'INSERT INTO substitutions (company_id, "key", "value", code)
             VALUES (0, $1, $2, $3)
             ON CONFLICT (company_id, "key", code) DO UPDATE
             SET "value" = EXCLUDED."value", updated_at = now()',
            [$translationKey, $values[$language], $language]
        );
        if (!$result) {
            return false;
        }
    }
    return true;
}

/**
 * Publish all four general language files after lookup labels change.
 *
 * @return bool
 */
function dd_varia_admin_publish_translations(): bool
{
    $published = true;
    foreach (['NL', 'FR', 'EN', 'DE'] as $language) {
        $published = fn_exportLanguageFile($language) && $published;
    }
    return $published;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $listKey = strtolower(trim((string) ($_POST['list_key'] ?? '')));
    $selectedList = $listKey;

    if ($action === 'deactivate') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $result = $id !== false && $id !== null
            ? pg_query_params(
                db(),
                'UPDATE dd_varia AS item SET is_active = FALSE, updated_at = now()
                 WHERE item.id = $1 AND item.list_key = $2
                   AND NOT EXISTS (
                       SELECT 1 FROM dd_varia AS child
                       WHERE child.parent_id = item.id AND child.is_active
                   )',
                [$id, $listKey]
            )
            : false;
        if ($result && pg_affected_rows($result) === 1) {
            dd_varia_clear_options_cache();
            dd_varia_admin_redirect('dd_varia_deactivated', 'success', $listKey);
        }
        dd_varia_admin_redirect('dd_varia_deactivate_blocked', 'danger', $listKey);
    }

    $editingId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $editingId = $editingId === false || $editingId === null ? 0 : (int) $editingId;
    $key = strtolower(trim((string) ($_POST['key'] ?? '')));
    $parentInput = trim((string) ($_POST['parent_id'] ?? ''));
    $parentId = $parentInput === '' ? null : filter_var($parentInput, FILTER_VALIDATE_INT);
    $info = trim((string) ($_POST['info'] ?? ''));
    $sortOrder = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
    $isActive = isset($_POST['is_active']) && (string) $_POST['is_active'] === '1';
    $translationValues = [];
    foreach ($languages as $language) {
        $rawValue = $_POST['translation_' . strtolower($language)] ?? null;
        $translationValues[$language] = is_string($rawValue) ? trim($rawValue) : '';
    }

    $formValues = [
        'list_key' => $listKey,
        'key' => $key,
        'parent_id' => $parentInput,
        'info' => $info,
        'sort_order' => is_int($sortOrder) ? (string) $sortOrder : '',
        'is_active' => $isActive,
        'translations' => $translationValues,
    ];

    $valid = preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $listKey) === 1
        && preg_match('/^(_root|[a-z][a-z0-9_-]{0,79})$/', $key) === 1
        && $parentId !== false
        && ($parentId === null || (is_int($parentId) && $parentId > 0))
        && is_int($sortOrder) && $sortOrder >= -100000 && $sortOrder <= 100000
        && strlen($info) <= 4000;
    foreach ($translationValues as $translationValue) {
        $valid = $valid && $translationValue !== '' && strlen($translationValue) <= 2000;
    }

    $existing = null;
    if ($valid && $editingId > 0) {
        $existingResult = pg_query_params(
            db(),
            'SELECT id, list_key, "key", content FROM dd_varia WHERE id = $1',
            [$editingId]
        );
        $existing = $existingResult ? pg_fetch_assoc($existingResult) : false;
        $valid = is_array($existing)
            && $listKey === (string) $existing['list_key']
            && $key === (string) $existing['key'];
    } elseif ($editingId > 0) {
        $valid = false;
    }

    if ($valid && $editingId === 0) {
        $duplicateResult = pg_query_params(
            db(),
            'SELECT 1 FROM dd_varia WHERE list_key = $1 AND "key" = $2',
            [$listKey, $key]
        );
        $valid = (bool) $duplicateResult && pg_num_rows($duplicateResult) === 0;
    }

    if ($valid && $parentId !== null) {
        $parentResult = pg_query_params(
            db(),
            'SELECT id FROM dd_varia
             WHERE id = $1 AND list_key = $2 AND (is_active OR $3::BOOLEAN = false)',
            [$parentId, $listKey, $isActive ? 'true' : 'false']
        );
        $valid = (bool) $parentResult && pg_num_rows($parentResult) === 1;
    }

    if ($valid && $editingId > 0 && $parentId !== null) {
        $cycleResult = pg_query_params(
            db(),
            'WITH RECURSIVE descendants(id) AS (
                 SELECT id FROM dd_varia WHERE id = $1
                 UNION
                 SELECT child.id FROM dd_varia AS child
                 JOIN descendants AS tree ON child.parent_id = tree.id
             )
             SELECT EXISTS (SELECT 1 FROM descendants WHERE id = $2) AS creates_cycle',
            [$editingId, $parentId]
        );
        $cycle = $cycleResult ? pg_fetch_result($cycleResult, 0, 0) : 't';
        $valid = $cycle === 'f';
    }

    if ($valid && $editingId > 0 && !$isActive) {
        $activeChildResult = pg_query_params(
            db(),
            'SELECT EXISTS (
                SELECT 1 FROM dd_varia WHERE parent_id = $1 AND is_active
             )',
            [$editingId]
        );
        $valid = $activeChildResult !== false
            && pg_fetch_result($activeChildResult, 0, 0) === 'f';
    }

    if (!$valid) {
        $messageKey = 'dd_varia_invalid';
        $messageType = 'danger';
    } else {
        $contentToken = $editingId > 0 ? (string) $existing['content'] : '@ddv_pending@';
        $started = (bool) pg_query(db(), 'BEGIN');
        if ($started && $editingId > 0) {
            $saved = pg_query_params(
                db(),
                'UPDATE dd_varia
                 SET parent_id = $1::BIGINT, info = $2, sort_order = $3,
                     is_active = $4, updated_at = now()
                 WHERE id = $5 AND list_key = $6',
                [$parentId, $info, $sortOrder, $isActive ? 'true' : 'false', $editingId, $listKey]
            );
            $rowId = $editingId;
        } elseif ($started) {
            $saved = pg_query_params(
                db(),
                'INSERT INTO dd_varia (list_key, "key", content, parent_id, info, sort_order, is_active)
                 VALUES ($1, $2, $3, $4::BIGINT, $5, $6, $7)
                 RETURNING id',
                [$listKey, $key, $contentToken, $parentId, $info, $sortOrder, $isActive ? 'true' : 'false']
            );
            $inserted = $saved ? pg_fetch_assoc($saved) : false;
            $rowId = is_array($inserted) ? (int) $inserted['id'] : 0;
            if ($rowId > 0) {
                $contentToken = '@ddv_' . $rowId . '@';
                $saved = pg_query_params(
                    db(),
                    'UPDATE dd_varia SET content = $1 WHERE id = $2',
                    [$contentToken, $rowId]
                );
            }
        } else {
            $saved = false;
            $rowId = 0;
        }

        if ($saved && $rowId > 0) {
            $saved = dd_varia_admin_save_translations($rowId, $translationValues);
        }
        if ($saved && $rowId > 0 && pg_query(db(), 'COMMIT')) {
            dd_varia_clear_options_cache();
            $published = dd_varia_admin_publish_translations();
            dd_varia_admin_redirect(
                $published ? 'dd_varia_saved' : 'dd_varia_publish_failed',
                $published ? 'success' : 'warning',
                $listKey
            );
        }
        pg_query(db(), 'ROLLBACK');
        error_log('Platform lookup value could not be saved.');
        $messageKey = 'dd_varia_save_failed';
        $messageType = 'danger';
    }
}

$selectedList = strtolower(trim((string) ($formValues['list_key'] ?: ($_GET['list'] ?? ''))));
$listResult = pg_query(db(), 'SELECT DISTINCT list_key FROM dd_varia ORDER BY list_key');
$listKeys = [];
while ($listResult && $listRow = pg_fetch_assoc($listResult)) {
    $listKeys[] = (string) $listRow['list_key'];
}
if ($selectedList === '' && $listKeys !== []) {
    $selectedList = $listKeys[0];
    $formValues['list_key'] = $selectedList;
}

$rowsById = [];
$childrenByParent = [];
if ($selectedList !== '' && preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $selectedList)) {
    $listResult = pg_query_params(
        db(),
        'SELECT id, list_key, "key", content, parent_id, info, sort_order, is_active, updated_at
         FROM dd_varia WHERE list_key = $1 ORDER BY sort_order, "key", id',
        [$selectedList]
    );
    while ($listResult && $row = pg_fetch_assoc($listResult)) {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $rowsById[$row['id']] = $row;
        $parentIndex = $row['parent_id'] === null ? 'root' : (string) $row['parent_id'];
        $childrenByParent[$parentIndex][] = $row['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($editId !== false && $editId !== null && (int) $editId > 0) {
        $editResult = pg_query_params(db(), 'SELECT * FROM dd_varia WHERE id = $1', [(int) $editId]);
        $editRow = $editResult ? pg_fetch_assoc($editResult) : false;
        if ($editRow && (string) $editRow['list_key'] === $selectedList) {
            $editingId = (int) $editRow['id'];
            $formValues['list_key'] = (string) $editRow['list_key'];
            $formValues['key'] = (string) $editRow['key'];
            $formValues['parent_id'] = $editRow['parent_id'] === null ? '' : (string) $editRow['parent_id'];
            $formValues['info'] = (string) $editRow['info'];
            $formValues['sort_order'] = (string) $editRow['sort_order'];
            $formValues['is_active'] = $editRow['is_active'] === 't';
            $translationKey = 'ddv_' . $editingId;
            $translationResult = pg_query_params(
                db(),
                'SELECT code, "value" FROM substitutions
                 WHERE company_id = 0 AND "key" = $1 AND code IN (\'NL\', \'FR\', \'EN\', \'DE\')',
                [$translationKey]
            );
            while ($translationResult && $translation = pg_fetch_assoc($translationResult)) {
                $formValues['translations'][$translation['code']] = (string) $translation['value'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editingId > 0) {
    $formValues['key'] = strtolower(trim((string) ($_POST['key'] ?? '')));
}

$listOptionsHtml = '<option value="">' . e(fn_t('dd_varia_list_choose')) . '</option>';
foreach ($listKeys as $listKeyOption) {
    $selected = $selectedList === $listKeyOption ? ' selected' : '';
    $listOptionsHtml .= '<option value="' . e($listKeyOption) . '"' . $selected . '>'
        . e($listKeyOption) . '</option>';
}

$parentOptionsHtml = '<option value="">' . e(fn_t('dd_varia_parent_none')) . '</option>';
$descendantIds = [];
if ($editingId > 0) {
    $collectDescendants = static function (int $parentId) use (&$collectDescendants, &$descendantIds, $childrenByParent): void {
        foreach ($childrenByParent[(string) $parentId] ?? [] as $childId) {
            $descendantIds[$childId] = true;
            $collectDescendants($childId);
        }
    };
    $collectDescendants($editingId);
}
$addParentOptions = static function (?int $parentId, int $depth) use (&$addParentOptions, &$parentOptionsHtml, $childrenByParent, $rowsById, $descendantIds, $editingId, $formValues): void {
    foreach ($childrenByParent[$parentId === null ? 'root' : (string) $parentId] ?? [] as $rowId) {
        if ($rowId === $editingId || isset($descendantIds[$rowId])) {
            continue;
        }
        $row = $rowsById[$rowId];
        if ($row['is_active'] !== 't' && (string) $formValues['parent_id'] !== (string) $rowId) {
            continue;
        }
        $selected = (string) $formValues['parent_id'] === (string) $rowId ? ' selected' : '';
        $prefix = str_repeat('— ', min($depth, 12));
        $parentOptionsHtml .= '<option value="' . $rowId . '"' . $selected . '>'
            . e($prefix . $row['key']) . '</option>';
        $addParentOptions($rowId, $depth + 1);
    }
};
$addParentOptions(null, 0);

$renderRows = static function (?int $parentId, int $depth) use (&$renderRows, $childrenByParent, $rowsById): string {
    $html = '';
    foreach ($childrenByParent[$parentId === null ? 'root' : (string) $parentId] ?? [] as $rowId) {
        $row = $rowsById[$rowId];
        $deleteForm = '<form method="post" action="dd-varia.php?list=' . rawurlencode((string) $row['list_key'])
            . '" class="d-inline">' . csrf_field()
            . '<input type="hidden" name="action" value="deactivate">'
            . '<input type="hidden" name="id" value="' . $rowId . '">'
            . '<input type="hidden" name="list_key" value="' . e((string) $row['list_key']) . '">'
            . '<button class="btn btn-link btn-sm text-danger p-0" type="submit">' . e(fn_t('dd_varia_remove')) . '</button>'
            . '</form>';
        $html .= fn_templateRender('dd_varia_row.html', [
            'dd_varia_key' => (string) $row['key'],
            'dd_varia_parent' => $row['parent_id'] === null ? '—' : (string) $rowsById[$row['parent_id']]['key'],
            'dd_varia_label' => fn_t(substr((string) $row['content'], 1, -1)),
            'dd_varia_info' => (string) $row['info'],
            'dd_varia_order' => (string) $row['sort_order'],
            'dd_varia_status' => $row['is_active'] === 't' ? fn_t('active') : fn_t('inactive'),
            'dd_varia_indent' => str_repeat('— ', min($depth, 12)),
            '_dd_varia_edit_href' => 'dd-varia.php?list=' . rawurlencode((string) $row['list_key']) . '&edit=' . $rowId,
            '_dd_varia_remove_form_html' => $row['is_active'] === 't' ? $deleteForm : '',
        ]);
        $html .= $renderRows($rowId, $depth + 1);
    }
    return $html;
};
$rowsHtml = $renderRows(null, 0);
if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="7" class="text-secondary">' . e(fn_t('dd_varia_no_rows')) . '</td></tr>';
}

$editingLabel = $editingId > 0 ? fn_t('dd_varia_edit_heading') : fn_t('dd_varia_add_heading');
$formListKey = $formValues['list_key'];
$formKey = $formValues['key'];
if ($editingId === 0 && $formKey === '' && $rowsById === []) {
    $formKey = '_root';
}
$message = $messageKey === '' ? '' : '<div class="alert alert-' . e($messageType)
    . '" role="status">' . e(fn_t($messageKey)) . '</div>';

page_start(fn_t('dd_varia_title'), $currentUser);
echo fn_templateRender('dd_varia.html', [
    'dd_varia_title' => fn_t('dd_varia_title'),
    'dd_varia_intro' => fn_t('dd_varia_intro'),
    'dd_varia_list_label' => fn_t('dd_varia_list_label'),
    'dd_varia_list_choose' => fn_t('dd_varia_list_choose'),
    'dd_varia_add_heading' => $editingLabel,
    'dd_varia_key_label' => fn_t('dd_varia_key_label'),
    'dd_varia_parent_label' => fn_t('dd_varia_parent_label'),
    'dd_varia_parent_none' => fn_t('dd_varia_parent_none'),
    'dd_varia_info_label' => fn_t('dd_varia_info_label'),
    'dd_varia_order_label' => fn_t('dd_varia_order_label'),
    'dd_varia_active_label' => fn_t('dd_varia_active_label'),
    'dd_varia_translation_help' => fn_t('dd_varia_translation_help'),
    'dd_varia_translation_label' => fn_t('dd_varia_translation_label'),
    'dd_varia_save' => fn_t('save'),
    'dd_varia_cancel' => fn_t('cancel'),
    'dd_varia_table_key' => fn_t('dd_varia_key_label'),
    'dd_varia_table_parent' => fn_t('dd_varia_parent_label'),
    'dd_varia_table_label' => fn_t('dd_varia_translation_label'),
    'dd_varia_table_info' => fn_t('dd_varia_info_label'),
    'dd_varia_table_order' => fn_t('dd_varia_order_label'),
    'dd_varia_table_status' => fn_t('dd_varia_table_status'),
    'dd_varia_table_actions' => fn_t('dd_varia_table_actions'),
    'dd_varia_remove' => fn_t('dd_varia_remove'),
    'dd_varia_no_rows' => fn_t('dd_varia_no_rows'),
    'dd_varia_key_value' => $formKey,
    'dd_varia_list_value' => $formListKey,
    'dd_varia_info_value' => $formValues['info'],
    'dd_varia_sort_value' => $formValues['sort_order'],
    '_dd_varia_is_active_checked' => $formValues['is_active'] ? 'checked' : '',
    '_dd_varia_edit_id_html' => $editingId > 0 ? '<input type="hidden" name="id" value="' . $editingId . '">' : '',
    '_dd_varia_key_readonly' => $editingId > 0 ? 'readonly' : '',
    '_dd_varia_list_readonly' => $editingId > 0 ? 'readonly' : '',
    '_dd_varia_parent_options_html' => $parentOptionsHtml,
    '_dd_varia_list_options_html' => $listOptionsHtml,
    '_dd_varia_rows_html' => $rowsHtml,
    '_dd_varia_message_html' => $message,
    '_dd_varia_csrf_field_html' => csrf_field(),
    '_dd_varia_action' => $editingId > 0 ? 'update' : 'add',
    '_dd_varia_cancel_html' => $editingId > 0
        ? '<a class="btn btn-outline-secondary" href="dd-varia.php?list=' . rawurlencode($selectedList) . '">'
            . e(fn_t('cancel')) . '</a>'
        : '',
    'dd_varia_translation_nl' => fn_t('dd_varia_language_nl'),
    'dd_varia_translation_fr' => fn_t('dd_varia_language_fr'),
    'dd_varia_translation_en' => fn_t('dd_varia_language_en'),
    'dd_varia_translation_de' => fn_t('dd_varia_language_de'),
    'dd_varia_translation_nl_value' => $formValues['translations']['NL'],
    'dd_varia_translation_fr_value' => $formValues['translations']['FR'],
    'dd_varia_translation_en_value' => $formValues['translations']['EN'],
    'dd_varia_translation_de_value' => $formValues['translations']['DE'],
    '_dd_varia_list_choose_selected' => $selectedList === '' ? 'selected' : '',
]);
page_end();
