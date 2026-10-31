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
$loginErrorKey = 'login_error';
$email = (string) ($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($email);
    $password = (string) ($_POST['password'] ?? '');
    $ipAddress = grompil_client_ip_address() ?: 'unknown';
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
            ? pg_query_params(db(), 'SELECT id, password_hash, is_active, company_id, COALESCE((to_jsonb(users)->>\'auth_version\')::bigint, 0) AS auth_version FROM users WHERE company_id = $2 AND (lower(email) = lower($1) OR lower(acronym) = lower($1)) LIMIT 2', [$email, (int) $loginCompany['id']])
            : pg_query_params(db(), 'SELECT u.id, u.email, u.password_hash, u.is_active, u.company_id, r.name AS role_name, r.level AS role_level, r.permissions, COALESCE((to_jsonb(u)->>\'auth_version\')::bigint, 0) AS auth_version FROM users AS u JOIN roles AS r ON r.id = u.role_id WHERE u.company_id IS NULL AND (lower(u.email) = lower($1) OR lower(u.acronym) = lower($1)) LIMIT 2', [$email]);
    }
    $user = $userResult && pg_num_rows($userResult) === 1 ? pg_fetch_assoc($userResult) : false;

    if ($user && $user['is_active'] === 't' && password_verify($password, $user['password_hash'])) {
        $account = [
            'company_id' => $user['company_id'] === null ? null : (int) $user['company_id'],
            'role_level' => isset($user['role_level']) ? (int) $user['role_level'] : 0,
            'role_name' => (string) ($user['role_name'] ?? ''),
            'permissions' => json_decode((string) ($user['permissions'] ?? '[]'), true) ?: [],
        ];
        $isPlatformLogin = !$loginCompany && is_platform_operator($account);
        $ipAllowed = true;

        if ($isPlatformLogin) {
            $ipAllowed = platform_management_ip_allowed($ipAddress);
            if ($ipAllowed) {
                try {
                    grompil_totp_encryption_key();
                    grompil_totp_library_ready();
                } catch (Throwable $error) {
                    error_log('Platform authenticator configuration is unavailable: ' . $error->getMessage());
                    $ipAllowed = false;
                    $loginErrorKey = 'platform_mfa_unavailable';
                }
            }
            if ($ipAllowed) {
                session_regenerate_id(true);
                unset($_SESSION['user_id'], $_SESSION['auth_version'], $_SESSION['active_company_id']);
                unset($_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
                $_SESSION['mfa_pending_user_id'] = (int) $user['id'];
                $_SESSION['mfa_pending_auth_version'] = (int) $user['auth_version'];
                $_SESSION['mfa_pending_email'] = (string) ($user['email'] ?? $email);
                $_SESSION['mfa_pending_attempt_email'] = $email;
                $_SESSION['mfa_pending_ip'] = $ipAddress;
                redirect('authenticator.php');
            }
        } elseif ($loginCompany !== null) {
            set_database_tenant_context((int) $loginCompany['id'], (int) $user['id'], false);
            $membershipResult = pg_query_params(
                db(),
                'SELECT cr.level
                 FROM company_users AS cu
                 JOIN company_roles AS cr ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                 WHERE cu.company_id = $1 AND cu.user_id = $2
                   AND cu.is_active AND cu.approval_status = \'approved\'',
                [(int) $loginCompany['id'], (int) $user['id']]
            );
            $membership = $membershipResult ? pg_fetch_assoc($membershipResult) : false;
            if ($membership && (int) $membership['level'] > 0 && (int) $membership['level'] < 200
                && !company_management_ip_allowed((int) $loginCompany['id'], $ipAddress)) {
                $ipAllowed = false;
            }
        }

        if ($ipAllowed) {
            pg_query_params(
                db(),
                'DELETE FROM login_attempts WHERE company_id = $1 AND email = $2 AND ip_address = $3',
                [$attemptCompanyId, $email, $ipAddress]
            );
            session_regenerate_id(true);
            unset($_SESSION['active_company_id']);
            unset($_SESSION['platform_mfa_verified']);
            unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['auth_version'] = (int) $user['auth_version'];
            if ($loginCompany) {
                $_SESSION['active_company_id'] = (int) $loginCompany['id'];
            }
            $signedInUser = current_user();
            if ($signedInUser !== null) {
                $lastLoginResult = pg_query_params(
                    db(),
                    'UPDATE users SET last_login_at = now() WHERE id = $1',
                    [(int) $signedInUser['id']]
                );
                if (!$lastLoginResult) {
                    error_log('Successful login timestamp could not be saved.');
                }
                redirect_after_login($signedInUser);
            }
        }
    }
    if (!$isLocked) {
        pg_query_params(
            db(),
            'INSERT INTO login_attempts (company_id, email, ip_address) VALUES ($1, $2, $3)',
            [$attemptCompanyId, $email, $ipAddress]
        );
    }
    $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t($loginErrorKey)) . '</div>';
}

echo fn_templateRender('login.html', [
    '_login_intro_html' => $introHtml,
    'email_value' => $email,
    '_error_html' => $errorHtml,
    '_csrf_field_html' => csrf_field(),
    '_registration_link_hidden' => $loginCompany ? '' : 'hidden',
    'login_form_action' => $loginCompany ? 'login.php?c=' . rawurlencode($companySlug) : 'login.php',
    'register_link' => $loginCompany ? 'register.php?c=' . rawurlencode($companySlug) : 'register.php',
    'forgot_password_link' => $loginCompany ? 'forgot-password.php?c=' . rawurlencode($companySlug) : 'forgot-password.php',
    '_language_selector_html' => fn_languageSelector('form-select form-select-sm w-auto'),
    '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
]);

