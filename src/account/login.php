<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:47
 * @date_mut: Wed 2026-10-21 18:39:38
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Processes sign-in and checks account and company context.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';

$signedInUser = current_user();
if ($signedInUser !== null) {
    redirect_after_login($signedInUser);
}

$companySlug = trim((string) ($_GET['c'] ?? ''));
$loginCompany = $companySlug === '' ? null : resolve_company_slug($companySlug, false);
if ($companySlug !== '' && $loginCompany === null) {
    grompil_show_error(404, 18403, 'Het bedrijfsinlogscherm is niet beschikbaar. Controleer de bedrijfslink.', 'Aanmelden niet mogelijk');
}
if ($loginCompany !== null) {
    set_database_tenant_context((int) $loginCompany['id'], 0, false);
    if (!company_management_ip_allowed((int) $loginCompany['id'])) {
        grompil_show_error(403, 57314, 'Aanmelden bij het bedrijfsbeheer is vanaf dit IP-adres niet toegestaan. Neem contact op met de platformbeheerder.', 'Toegang vanaf dit IP-adres geweigerd');
    }
}

$introHtml = '<span class="eyebrow">' . e(fn_t('login_eyebrow')) . '</span>'
    . '<h1>' . e(fn_t('login_headline')) . '<br><span>' . e(fn_t('login_headline_second')) . '</span></h1>'
    . '<p>' . e(fn_t('login_intro')) . '</p>';
if ($loginCompany !== null) {
    set_database_tenant_context((int) $loginCompany['id'], 0, false);
    $language = strtoupper((string) ($_SESSION['language'] ?? $loginCompany['default_language'] ?? 'NL'));
    $allowedLanguages = ['NL', 'FR', 'EN', 'DE'];
    if (!in_array($language, $allowedLanguages, true)) {
        $language = 'NL';
    }
    $homeDefaults = [
        'NL' => ['Welkom bij ' . $loginCompany['name'], 'Welkom op de website van ' . $loginCompany['name'] . '. Hier vind je informatie over onze diensten en contactgegevens.'],
        'FR' => ['Bienvenue chez ' . $loginCompany['name'], 'Bienvenue sur le site de ' . $loginCompany['name'] . '. Vous trouverez ici des informations sur nos services et nos coordonnées.'],
        'EN' => ['Welcome to ' . $loginCompany['name'], 'Welcome to the ' . $loginCompany['name'] . ' website. Find information about our services and contact details here.'],
        'DE' => ['Willkommen bei ' . $loginCompany['name'], 'Willkommen auf der Website von ' . $loginCompany['name'] . '. Hier finden Sie Informationen zu unseren Dienstleistungen und Kontaktdaten.'],
    ];
    [$companyTitle, $companyBody] = $homeDefaults[$language];
    $pageResult = pg_query_params(
        db(),
        'SELECT title, body FROM company_pages WHERE company_id = $1 AND page_key = $2 AND language_code = $3 AND is_published',
        [(int) $loginCompany['id'], 'home', $language]
    );
    $companyPage = $pageResult ? pg_fetch_assoc($pageResult) : false;
    if (!$companyPage && $language !== $loginCompany['default_language']) {
        $pageResult = pg_query_params(
            db(),
            'SELECT title, body FROM company_pages WHERE company_id = $1 AND page_key = $2 AND language_code = $3 AND is_published',
            [(int) $loginCompany['id'], 'home', (string) $loginCompany['default_language']]
        );
        $companyPage = $pageResult ? pg_fetch_assoc($pageResult) : false;
    }
    if ($companyPage) {
        if (trim((string) $companyPage['title']) !== '') {
            $companyTitle = (string) $companyPage['title'];
        }
        $companyBody = (string) $companyPage['body'];
    }
    $introHtml = '<span class="eyebrow">' . e((string) $loginCompany['name']) . '</span>'
        . '<h1>' . e($companyTitle) . '</h1>'
        . '<p>' . nl2br(e($companyBody)) . '</p>';
}

$errorHtml = '';
$email = (string) ($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($email);
    $password = (string) ($_POST['password'] ?? '');
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $attemptCompanyId = $loginCompany ? (int) $loginCompany['id'] : 0;
    $attemptResult = pg_query_params(
        db(),
        "SELECT count(*) AS total, max(attempted_at) AS latest
         FROM login_attempts
         WHERE company_id = $1 AND email = $2 AND ip_address = $3
           AND attempted_at > now() - interval '15 minutes'",
        [$attemptCompanyId, $email, $ipAddress]
    );
    $attempt = $attemptResult ? pg_fetch_assoc($attemptResult) : false;
    $isLocked = $attempt
        && (int) $attempt['total'] >= 5
        && strtotime((string) $attempt['latest']) > time() - 900;
    $userResult = false;
    if (!$isLocked) {
        $userResult = $loginCompany
            ? pg_query_params(db(), 'SELECT id, password_hash, is_active, COALESCE((to_jsonb(users)->>\'auth_version\')::bigint, 0) AS auth_version FROM users WHERE company_id = $2 AND (lower(email) = lower($1) OR lower(acronym) = lower($1)) LIMIT 2', [$email, (int) $loginCompany['id']])
            : pg_query_params(db(), 'SELECT id, password_hash, is_active, COALESCE((to_jsonb(users)->>\'auth_version\')::bigint, 0) AS auth_version FROM users WHERE company_id IS NULL AND (lower(email) = lower($1) OR lower(acronym) = lower($1)) LIMIT 2', [$email]);
    }
    $user = $userResult && pg_num_rows($userResult) === 1 ? pg_fetch_assoc($userResult) : false;

    if ($user && $user['is_active'] === 't' && password_verify($password, $user['password_hash'])) {
        pg_query_params(
            db(),
            'DELETE FROM login_attempts WHERE company_id = $1 AND email = $2 AND ip_address = $3',
            [$attemptCompanyId, $email, $ipAddress]
        );
        session_regenerate_id(true);
        unset($_SESSION['active_company_id']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['auth_version'] = (int) $user['auth_version'];
        if ($loginCompany) {
            $_SESSION['active_company_id'] = (int) $loginCompany['id'];
        }
        $signedInUser = current_user();
        if ($signedInUser !== null) {
            redirect_after_login($signedInUser);
        }
    }
    if (!$isLocked) {
        pg_query_params(
            db(),
            'INSERT INTO login_attempts (company_id, email, ip_address) VALUES ($1, $2, $3)',
            [$attemptCompanyId, $email, $ipAddress]
        );
    }
    $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t('login_error')) . '</div>';
}

echo fn_templateRender('login.html', [
    '_login_intro_html' => $introHtml,
    'email_value' => $email,
    '_error_html' => $errorHtml,
    '_csrf_field_html' => csrf_field(),
    'login_form_action' => $loginCompany ? 'login.php?c=' . rawurlencode($companySlug) : 'login.php',
    'register_link' => $loginCompany ? 'register.php?c=' . rawurlencode($companySlug) : 'register.php',
    'forgot_password_link' => $loginCompany ? 'forgot-password.php?c=' . rawurlencode($companySlug) : 'forgot-password.php',
    '_language_selector_html' => fn_languageSelector('form-select form-select-sm w-auto'),
    '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
]);

