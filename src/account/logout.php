<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:56
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Ends the session and removes the sign-in cookie.
 *
 * https://dw.grompil.com/!saas/doku.php?id=src:src:account:logout
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    grompil_show_error(405, 21607, 'Gebruik de uitlogknop in de applicatie om af te melden.', 'Aanvraag niet toegestaan');
}

verify_csrf();

$companySlug = trim((string) ($_POST['c'] ?? ''));
$company = $companySlug === '' ? null : resolve_company_slug($companySlug, false);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => 'Lax',
        ]
    );
}

session_destroy();

redirect($company ? 'login.php?c=' . rawurlencode($company['slug']) : 'login.php');

