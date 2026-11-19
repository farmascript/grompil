<?php
/**
 * Platform-wide function-key assignments for the dispensing module.
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/dispensing_shortcuts.php';

$currentUser = grompil_require_module_access('platform-dispensing-keys.php');
if (current_company_context($currentUser) !== null || !is_platform_operator($currentUser)) {
    grompil_show_error(403, 86143, fn_t('access_denied'), fn_t('access_denied'));
}

$language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
    $language = 'NL';
}
$actions = fn_dispensingShortcutActions();
$slots = fn_dispensingShortcutSlots();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postedShortcuts = $_POST['shortcuts'] ?? null;
    $validated = [];
    $valid = is_array($postedShortcuts);
    foreach ($slots as $slot) {
        $keyCode = $slot['key_code'];
        $posted = is_array($postedShortcuts) && isset($postedShortcuts[$keyCode]) && is_array($postedShortcuts[$keyCode])
            ? $postedShortcuts[$keyCode]
            : [];
        $actionKey = is_scalar($posted['action_key'] ?? null) ? (string) $posted['action_key'] : '';
        $title = is_scalar($posted['title'] ?? null) ? trim((string) $posted['title']) : '';
        $tooltip = is_scalar($posted['tooltip'] ?? null) ? trim((string) $posted['tooltip']) : '';
        $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
        $tooltipLength = function_exists('mb_strlen') ? mb_strlen($tooltip) : strlen($tooltip);
        if (!array_key_exists($actionKey, $actions) || $titleLength > 80 || $tooltipLength > 250) {
            $valid = false;
        }
        $validated[$keyCode] = [
            'action_key' => $actionKey,
            'title' => $title,
            'tooltip' => $tooltip,
        ];
    }

    if (!$valid) {
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_dispensing_shortcuts_invalid')) . '</div>';
    } else {
        $configuration = fn_loadDispensingShortcutConfiguration();
        $saved = $configuration !== false;
        $transactionStarted = false;
        if ($saved) {
            try {
                $transactionStarted = (bool) db_query_params('platform_dispensing_shortcuts.begin', 'BEGIN');
                $saved = $transactionStarted;
            } catch (GrompilDatabaseQueryException) {
                $saved = false;
            }
        }
        foreach ($slots as $slot) {
            if (!$saved) {
                break;
            }
            $keyCode = $slot['key_code'];
            $currentLabels = $configuration[$keyCode]['labels'] ?? [];
            if (!is_array($currentLabels)) {
                $currentLabels = [];
            }
            $currentLabels[$language] = [
                'title' => $validated[$keyCode]['title'],
                'tooltip' => $validated[$keyCode]['tooltip'],
            ];
            $labelsJson = json_encode($currentLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($labelsJson)) {
                $saved = false;
                break;
            }
            try {
                $result = db_query_params(
                    'platform_dispensing_shortcuts.save',
                    'INSERT INTO ft_platform_dispensing_function_keys
                        (key_code, action_key, labels, updated_by_user_code, updated_at)
                     VALUES ($1, $2, $3::jsonb, $4, now())
                     ON CONFLICT (key_code) DO UPDATE
                     SET action_key = EXCLUDED.action_key,
                         labels = EXCLUDED.labels,
                         updated_by_user_code = EXCLUDED.updated_by_user_code,
                         updated_at = now()',
                    [$keyCode, $validated[$keyCode]['action_key'], $labelsJson, (string) $currentUser['user_code']]
                );
            } catch (GrompilDatabaseQueryException) {
                $result = false;
            }
            if (!$result) {
                $saved = false;
            }
        }
        if ($transactionStarted) {
            try {
                db_query_params($saved ? 'platform_dispensing_shortcuts.commit' : 'platform_dispensing_shortcuts.rollback', $saved ? 'COMMIT' : 'ROLLBACK');
            } catch (GrompilDatabaseQueryException) {
                $saved = false;
            }
        }
        $message = $saved
            ? '<div class="alert alert-success" role="status">' . e(fn_t('platform_dispensing_shortcuts_saved')) . '</div>'
            : '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_settings_unavailable')) . '</div>';
    }
}

$configuration = fn_loadDispensingShortcutConfiguration();
if ($configuration === false) {
    $configuration = [];
    $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_settings_unavailable')) . '</div>';
}

$actionOptions = '';
foreach ($actions as $actionKey => $action) {
    $actionOptions .= '<option value="' . e($actionKey) . '">' . e(fn_t($action['title'])) . '</option>';
}
$rowsByLayer = ['normal' => '', 'ctrl' => '', 'shift' => ''];
foreach ($slots as $slot) {
    $keyCode = $slot['key_code'];
    $config = $configuration[$keyCode] ?? ['action_key' => 'none', 'labels' => []];
    $actionKey = array_key_exists($config['action_key'] ?? '', $actions)
        ? (string) $config['action_key']
        : 'none';
    $labels = $config['labels'][$language] ?? [];
    $labels = is_array($labels) ? $labels : [];
    $selectedOptions = str_replace(
        'value="' . e($actionKey) . '">',
        'value="' . e($actionKey) . '" selected>',
        $actionOptions
    );
    $rowsByLayer[$slot['layer']] .= fn_templateRender('platform_dispensing_shortcut_row.html', [
        'shortcut_key_label' => e($slot['key_label']),
        'shortcut_key_code' => e($keyCode),
        '_shortcut_action_options_html' => $selectedOptions,
        'shortcut_title_value' => e((string) ($labels['title'] ?? '')),
        'shortcut_tooltip_value' => e((string) ($labels['tooltip'] ?? '')),
    ]);
}

page_start(fn_t('platform_dispensing_shortcuts_title'), $currentUser);
echo fn_templateRender('platform_dispensing_shortcuts.html', [
    'platform_dispensing_shortcuts_title' => fn_t('platform_dispensing_shortcuts_title'),
    'platform_dispensing_shortcuts_intro' => fn_t('platform_dispensing_shortcuts_intro'),
    'platform_dispensing_shortcuts_language' => fn_t('platform_dispensing_shortcuts_language'),
    'platform_dispensing_shortcuts_normal' => fn_t('platform_dispensing_shortcuts_normal'),
    'platform_dispensing_shortcuts_ctrl' => fn_t('platform_dispensing_shortcuts_ctrl'),
    'platform_dispensing_shortcuts_shift' => fn_t('platform_dispensing_shortcuts_shift'),
    'platform_dispensing_shortcuts_key' => fn_t('platform_dispensing_shortcuts_key'),
    'platform_dispensing_shortcuts_action' => fn_t('platform_dispensing_shortcuts_action'),
    'platform_dispensing_shortcuts_title_label' => fn_t('platform_dispensing_shortcuts_title_label'),
    'platform_dispensing_shortcuts_tooltip' => fn_t('platform_dispensing_shortcuts_tooltip'),
    'platform_settings_save' => fn_t('save'),
    'shortcut_label_language' => e($language),
    '_csrf_field_html' => csrf_field(),
    '_message_html' => $message,
    '_normal_rows_html' => $rowsByLayer['normal'],
    '_ctrl_rows_html' => $rowsByLayer['ctrl'],
    '_shift_rows_html' => $rowsByLayer['shift'],
]);
page_end();
