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
 * @description: Requests scoped password recovery emails without revealing account existence.
 *
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/module_guard.php';
require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/password_reset.php';
require_once GROMPIL_ROOT . '/includes/mailer.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$slug = trim((string) ($_GET['c'] ?? ''));
$company = $slug === '' ? null : resolve_company_slug($slug, false);
if ($slug !== '' && $company === null) {
    grompil_show_error(404, 18403, fn_t('recovery_company_unavailable'), fn_t('forgot_password_link_text'));
}
$companyId = $company ? (int) $company['id'] : null;
set_database_tenant_context($companyId ?? 0, 0, false);
$query = $company ? '?c=' . rawurlencode($slug) : '';
$email = trim((string) ($_POST['email'] ?? ''));
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $baseUrl = password_reset_base_url();
        $delivery = password_reset_issue(db(), $companyId, $email, grompil_client_ip_address() ?: 'unknown');
        if ($delivery !== null) {
            $parameters = ['token' => $delivery['token'], 'lang' => (string) ($_SESSION['language'] ?? 'NL')];
            if ($company) {
                $parameters['c'] = $slug;
            }
            $url = $baseUrl . 'reset-password.php?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
            $context = $company ? (string) $company['name'] : 'Grompil';
            $text = fn_t('recovery_mail_intro') . "\n\n" . $context . "\n" . $url . "\n\n" . fn_t('recovery_mail_expiry') . "\n" . fn_t('recovery_mail_ignore');
            $html = '<p>' . e(fn_t('recovery_mail_intro')) . '</p><p><strong>' . e($context) . '</strong></p>'
                . '<p><a href="' . e($url) . '">' . e(fn_t('recovery_reset_title')) . '</a></p>'
                . '<p>' . e(fn_t('recovery_mail_expiry')) . '</p><p>' . e(fn_t('recovery_mail_ignore')) . '</p>';
            try {
                grompil_send_mail($delivery['email'], fn_t('recovery_mail_subject'), $html, $text);
            } catch (Throwable $exception) {
                // Do not log addresses, reset URLs, tokens, or SMTP credentials.
                error_log('Password reset email could not be sent.');
            }
        }
        $message = '<div class="alert alert-info" role="status">' . e(fn_t('recovery_requested')) . '</div>';
    } catch (Throwable $exception) {
        error_log('Password reset request could not be processed. Check the migration and public URL configuration.');
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('recovery_unavailable')) . '</div>';
    }
}
echo fn_templateRender('password_recovery.html', [
    'recovery_heading' => fn_t('forgot_password_link_text'),
    'recovery_description' => fn_t('recovery_request_intro'),
    'recovery_company_name' => $company ? (string) $company['name'] : 'Grompil',
    '_message_html' => $message,
    '_recovery_form_html' => fn_templateRender('forgot_password_form.html', [
        'recovery_form_action' => 'forgot-password.php' . $query,
        'email_value' => $email,
        '_csrf_field_html' => csrf_field(),
    ]),
    'recovery_login_link' => 'login.php' . $query,
    '_language_selector_html' => fn_languageSelector(),
]);
