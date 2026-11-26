<?php
/**
 * @date_cre: Thu 2026-11-19 10:52:55
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Shared, allow-listed dispensing function-key actions and configuration.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:dispensing_shortcuts
 */

declare(strict_types=1);

/**
 * Load the configured dispensing shortcut slots.
 *
 * @return array The resulting data.
 */
function fn_dispensingShortcutSlots(): array
{
    $slots = [];
    foreach (['', 'CTRL_', 'SHIFT_'] as $modifier) {
        for ($number = 1; $number <= 12; $number++) {
            $keyCode = $modifier . 'F' . $number;
            $slots[] = [
                'key_code' => $keyCode,
                'key_label' => ($modifier === 'CTRL_' ? 'Ctrl+' : ($modifier === 'SHIFT_' ? 'Shift+' : '')) . 'F' . $number,
                'layer' => $modifier === 'CTRL_' ? 'ctrl' : ($modifier === 'SHIFT_' ? 'shift' : 'normal'),
                'number' => $number,
            ];
        }
    }
    return $slots;
}

/**
 * Load the available dispensing shortcut actions.
 *
 * @return array The resulting data.
 */
function fn_dispensingShortcutActions(): array
{
    return [
        'none' => ['title' => 'dispensing_shortcut_unassigned', 'tooltip' => 'dispensing_shortcut_unassigned_help'],
        'focus_customer_search' => ['title' => 'dispensing_shortcut_customer_search', 'tooltip' => 'dispensing_shortcut_customer_search_help'],
        'focus_product_search' => ['title' => 'dispensing_shortcut_product_search', 'tooltip' => 'dispensing_shortcut_product_search_help'],
        'open_customer_modal' => ['title' => 'dispensing_shortcut_add_customer', 'tooltip' => 'dispensing_shortcut_add_customer_help'],
        'toggle_customer_regex' => ['title' => 'dispensing_shortcut_toggle_regex', 'tooltip' => 'dispensing_shortcut_toggle_regex_help'],
        'clear_product_search' => ['title' => 'dispensing_shortcut_clear_product_search', 'tooltip' => 'dispensing_shortcut_clear_product_search_help'],
        'set_status_free_sale' => ['title' => 'dispensing_shortcut_status_free_sale', 'tooltip' => 'dispensing_shortcut_status_free_sale_help'],
        'set_status_prescription' => ['title' => 'dispensing_shortcut_status_prescription', 'tooltip' => 'dispensing_shortcut_status_prescription_help'],
        'close_dispensing' => ['title' => 'dispensing_shortcut_close_dispensing', 'tooltip' => 'dispensing_shortcut_close_dispensing_help'],
        'discard_dispensing' => ['title' => 'dispensing_shortcut_discard_dispensing', 'tooltip' => 'dispensing_shortcut_discard_dispensing_help'],
        'open_patient_history' => ['title' => 'dispensing_shortcut_patient_history', 'tooltip' => 'dispensing_shortcut_patient_history_help'],
        'open_module' => ['title' => 'dispensing_shortcut_open_module', 'tooltip' => 'dispensing_shortcut_open_module_help'],
        'open_url' => ['title' => 'dispensing_shortcut_open_url', 'tooltip' => 'dispensing_shortcut_open_url_help'],
        'open_company_website' => ['title' => 'dispensing_shortcut_company_website', 'tooltip' => 'dispensing_shortcut_company_website_help'],
    ];
}

/**
 * Determine whether safe dispensing shortcut url.
 *
 * @param string $url The url.
 *
 * @return bool Whether the operation succeeded.
 */
function fn_isSafeDispensingShortcutUrl(string $url): bool
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== '') {
        return in_array($scheme, ['http', 'https'], true)
            && isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    return !str_starts_with($url, '//')
        && trim((string) ($parts['path'] ?? '')) !== ''
        && !preg_match('~(^|/)\.\.?(/|$)~', (string) ($parts['path'] ?? ''));
}

/**
 * Registered application pages that may be assigned as function-key targets.
 * Access control remains enforced by each target page when it is opened.
 *
 * @return list<string>
 */
function fn_dispensingShortcutModuleTargets(): array
{
    if (!function_exists('grompil_module_access_rules')) {
        return [];
    }

    $root = defined('GROMPIL_ROOT') ? GROMPIL_ROOT : dirname(__DIR__);
    $excludedTargets = ['index.php', 'account.php', 'select-company.php'];
    $targets = [];
    foreach (array_keys(grompil_module_access_rules()) as $module) {
        if (!in_array($module, $excludedTargets, true)
            && preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.php$~', $module) === 1
            && strlen('open_page:' . $module) <= 80
            && is_file($root . '/' . $module)) {
            $targets[] = $module;
        }
    }

    sort($targets, SORT_NATURAL | SORT_FLAG_CASE);
    return $targets;
}

/**
 * Resolve and validate a module path for a dispensing shortcut.
 *
 * @param string $actionKey The action key.
 *
 * @return ?string The resulting text.
 */
function fn_dispensingShortcutModulePath(string $actionKey): ?string
{
    if (!str_starts_with($actionKey, 'open_page:')) {
        return null;
    }

    $module = substr($actionKey, strlen('open_page:'));
    return in_array($module, fn_dispensingShortcutModuleTargets(), true) ? $module : null;
}

/**
 * Load dispensing shortcut configuration.
 *
 * @return array<string, array{action_key:string, action_config:array, labels:array}>|false The resulting data.
 */
function fn_loadDispensingShortcutConfiguration(): array|false
{
    try {
        $result = db_query_params(
            'dispensing.shortcuts.load',
            'SELECT key_code, action_key, action_config, labels
             FROM ft_platform_dispensing_function_keys'
        );
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
    if (!$result) {
        return false;
    }

    $configuration = [];
    while ($row = pg_fetch_assoc($result)) {
        $labels = json_decode((string) ($row['labels'] ?? '{}'), true);
        $actionConfig = json_decode((string) ($row['action_config'] ?? '{}'), true);
        $configuration[(string) $row['key_code']] = [
            'action_key' => (string) $row['action_key'],
            'action_config' => is_array($actionConfig) ? $actionConfig : [],
            'labels' => is_array($labels) ? $labels : [],
        ];
    }
    return $configuration;
}
