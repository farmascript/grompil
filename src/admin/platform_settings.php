<?php
/**
 *
 * @date_cre: Sat 2026-10-24 00:00:00
 * @date_mut: Sat 2026-10-24 00:00:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Allows platform administrators to change global system parameters.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('platform-settings.php');
if (current_company_context($currentUser) !== null) {
    grompil_show_error(403, 86142, 'Deze keuze is niet mogelijk zolang je voor een bedrijf werkt. Keer terug naar platformbeheer en probeer het opnieuw.', 'Keuze niet mogelijk');
}
$message = '';
$values = ['password_reset_max_requests' => 3, 'password_reset_window_minutes' => 15];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $maxRequestsInput = $_POST['password_reset_max_requests'] ?? null;
    $windowMinutesInput = $_POST['password_reset_window_minutes'] ?? null;
    $values['password_reset_max_requests'] = is_scalar($maxRequestsInput)
        ? filter_var($maxRequestsInput, FILTER_VALIDATE_INT)
        : false;
    $values['password_reset_window_minutes'] = is_scalar($windowMinutesInput)
        ? filter_var($windowMinutesInput, FILTER_VALIDATE_INT)
        : false;
    $valid = is_int($values['password_reset_max_requests'])
        && $values['password_reset_max_requests'] >= 1
        && $values['password_reset_max_requests'] <= 10
        && is_int($values['password_reset_window_minutes'])
        && $values['password_reset_window_minutes'] >= 1
        && $values['password_reset_window_minutes'] <= 1440;
    if (!$valid) {
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_settings_invalid')) . '</div>';
    } else {
        $saved = pg_query_params(db(),
            "INSERT INTO platform_system_settings (\"key\", value_integer, updated_at)
             VALUES ('password_reset_max_requests', $1, now()), ('password_reset_window_minutes', $2, now())
             ON CONFLICT (\"key\") DO UPDATE
                SET value_integer = EXCLUDED.value_integer, updated_at = now()",
            [$values['password_reset_max_requests'], $values['password_reset_window_minutes']]
        );
        if ($saved) {
            $message = '<div class="alert alert-success" role="status">' . e(fn_t('platform_settings_saved')) . '</div>';
        } else {
            error_log('Platform system parameters could not be saved.');
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_settings_unavailable')) . '</div>';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $message === '') {
    $result = pg_query_params(db(),
        'SELECT "key", value_integer FROM platform_system_settings
         WHERE "key" IN (\'password_reset_max_requests\', \'password_reset_window_minutes\')',
        []
    );
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $values[$row['key']] = (int) $row['value_integer'];
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log('Platform system parameters could not be loaded.');
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_settings_unavailable')) . '</div>';
    }
}

page_start(fn_t('platform_settings_title'), $currentUser);
echo fn_templateRender('platform_settings.html', [
    'platform_settings_title' => fn_t('platform_settings_title'),
    'platform_settings_intro' => fn_t('platform_settings_intro'),
    'platform_settings_password_reset' => fn_t('platform_settings_password_reset'),
    'platform_settings_max_requests' => fn_t('platform_settings_max_requests'),
    'platform_settings_max_requests_help' => fn_t('platform_settings_max_requests_help'),
    'platform_settings_window_minutes' => fn_t('platform_settings_window_minutes'),
    'platform_settings_window_minutes_help' => fn_t('platform_settings_window_minutes_help'),
    'platform_settings_save' => fn_t('save'),
    'max_requests_value' => e((string) $values['password_reset_max_requests']),
    'window_minutes_value' => e((string) $values['password_reset_window_minutes']),
    '_csrf_field_html' => csrf_field(),
    '_message_html' => $message,
]);
page_end();
