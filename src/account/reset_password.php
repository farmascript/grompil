<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:30:00
 * @date_mut: Wed 2026-10-21 18:39:38
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Validates reset links and accepts a new account password.
 *
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/module_guard.php';
require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/password_reset.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$slug = trim((string) ($_GET['c'] ?? ''));
$company = $slug === '' ? null : resolve_company_slug($slug, false);
if ($slug !== '' && $company === null) {
    grompil_show_error(404, 18403, fn_t('recovery_company_unavailable'), fn_t('recovery_reset_title'));
}
$companyId = $company ? (int) $company['id'] : null;
$scope = $companyId ?? 0;
set_database_tenant_context($scope, 0, false);
$query = $company ? '?c=' . rawurlencode($slug) : '';
$message = '';
$form = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
        $token = (string) $_GET['token'];
        $tokenHash = preg_match('/^[a-f0-9]{64}$/', $token) ? hash('sha256', $token) : '';
        if (password_reset_valid(db(), $companyId, $tokenHash)) {
            $_SESSION['password_reset_grants'][$scope] = $tokenHash;
            // Remove the bearer token from the visible URL before loading assets.
            redirect('reset-password.php' . $query);
        }
        unset($_SESSION['password_reset_grants'][$scope]);
    }
    $done = !empty($_SESSION['password_reset_done'][$scope]);
    unset($_SESSION['password_reset_done'][$scope]);
    $tokenHash = (string) ($_SESSION['password_reset_grants'][$scope] ?? '');
    $valid = !$done && password_reset_valid(db(), $companyId, $tokenHash);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($valid) {
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirm'] ?? '');
            if (strlen($password) < 12 || strlen($password) > 4096 || str_contains($password, "\0")) {
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('password_too_short')) . '</div>';
            } elseif (!hash_equals($password, $confirmation)) {
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('password_confirmation_mismatch')) . '</div>';
            } elseif (password_reset_consume(db(), $companyId, $tokenHash, $password)) {
                unset($_SESSION['password_reset_grants'][$scope], $_SESSION['user_id'], $_SESSION['active_company_id'], $_SESSION['auth_version']);
                session_regenerate_id(true);
                $_SESSION['password_reset_done'][$scope] = true;
                redirect('reset-password.php' . $query);
            } else {
                $valid = false;
            }
        }
    }
    if ($done) {
        $message = '<div class="alert alert-success" role="status">' . e(fn_t('recovery_success')) . '</div>';
    } elseif (!$valid) {
        $message = '<div class="alert alert-warning" role="status">' . e(fn_t('recovery_invalid')) . '</div>';
        $form = '<a class="btn btn-grompil" href="forgot-password.php' . e($query) . '">' . e(fn_t('recovery_send_link')) . '</a>';
    } else {
        $form = fn_templateRender('reset_password_form.html', [
            'recovery_form_action' => 'reset-password.php' . $query,
            '_csrf_field_html' => csrf_field(),
        ]);
    }
} catch (Throwable $exception) {
    error_log('Password reset could not be processed. Check the password reset migration.');
    $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('recovery_unavailable')) . '</div>';
}
echo fn_templateRender('password_recovery.html', [
    'recovery_heading' => fn_t('recovery_reset_title'),
    'recovery_description' => fn_t('recovery_reset_intro'),
    'recovery_company_name' => $company ? (string) $company['name'] : 'Grompil',
    '_message_html' => $message,
    '_recovery_form_html' => $form,
    'recovery_login_link' => 'login.php' . $query,
    '_language_selector_html' => fn_languageSelector(),
]);
