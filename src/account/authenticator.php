<?php
/**
 * Completes the platform administrator password plus TOTP login.
 * @date_mut: Sun 2026-11-08 13:33:54
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:account:authenticator
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$authenticatorQuery = static function (string $operation, string $sql, array $params = []): PgSql\Result|false {
    try {
        return db_query_params($operation, $sql, $params);
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
};

if (current_user() !== null) {
    redirect('admin.php');
}

$userCode = (string) ($_SESSION['mfa_pending_user_code'] ?? '');
$authVersion = (int) ($_SESSION['mfa_pending_auth_version'] ?? -1);
$email = (string) ($_SESSION['mfa_pending_email'] ?? '');
$attemptEmail = (string) ($_SESSION['mfa_pending_attempt_email'] ?? '');
$loginIp = (string) ($_SESSION['mfa_pending_ip'] ?? '');
$currentIp = grompil_client_ip_address();
if ($userCode === '' || $email === '' || $attemptEmail === '' || $loginIp === '' || $currentIp !== $loginIp) {
    unset($_SESSION['mfa_pending_user_code'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
    redirect('login.php');
}

set_database_tenant_context(0, $userCode, true);
$accountResult = $authenticatorQuery(
    'account_authenticator.load_platform_account',
    'SELECT u.is_active, u.approval_status,
            COALESCE((to_jsonb(u)->>\'auth_version\')::bigint, 0) AS auth_version,
            r.name AS role_name, r.level AS role_level, r.permissions
     FROM bt_users AS u JOIN ft_roles AS r ON r.id = u.role_id
     WHERE u.user_code = $1 AND u.company_id IS NULL',
    [$userCode]
);
$account = $accountResult ? pg_fetch_assoc($accountResult) : false;
$accountIsPlatform = $account && $account['is_active'] === 't'
    && $account['approval_status'] === 'approved'
    && (int) $account['auth_version'] === $authVersion
    && is_platform_operator([
        'company_id' => null,
        'role_level' => (int) $account['role_level'],
        'role_name' => (string) $account['role_name'],
        'permissions' => json_decode((string) $account['permissions'], true) ?: [],
    ]);
if (!$accountIsPlatform || !platform_management_ip_allowed($currentIp, $userCode)) {
    unset($_SESSION['mfa_pending_user_code'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
    redirect('login.php');
}

$errorHtml = '';
$setupSecret = (string) ($_SESSION['mfa_enroll_secret'] ?? '');
$isVerifiedSetup = ($_SESSION['mfa_pending_verified'] ?? false) === true;
$encryptedSecret = null;
try {
    grompil_totp_encryption_key();
    grompil_totp_library_ready();
    if (!$isVerifiedSetup) {
        $encryptedSecret = grompil_platform_totp_ciphertext($userCode);
        if ($encryptedSecret === null) {
            if ($setupSecret === '') {
                $setup = OTPHP\TOTP::generate(new OTPHP\InternalClock(), 20)
                    ->withPeriod(30)
                    ->withDigits(6)
                    ->withDigest('sha1')
                    ->withIssuer('gromPil')
                    ->withLabel('Platformbeheerder ' . $userCode);
                $setupSecret = $setup->getSecret();
                $_SESSION['mfa_enroll_secret'] = $setupSecret;
            }
        } else {
            unset($_SESSION['mfa_enroll_secret']);
        }
    }
} catch (Throwable $error) {
    error_log('Platform authenticator could not be initialized: ' . $error->getMessage());
    $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_mfa_unavailable')) . '</div>';
}

$attemptCount = static function () use ($authenticatorQuery, $attemptEmail, $loginIp): int {
    $result = $authenticatorQuery(
        'account_authenticator.count_attempts',
        "SELECT count(*) AS total FROM st_login_attempts
         WHERE company_id = 0 AND email = $1 AND ip_address = $2
           AND attempted_at > now() - interval '15 minutes'",
        [$attemptEmail, $loginIp]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ? (int) $row['total'] : 0;
};

$completeLogin = static function () use ($authenticatorQuery, $userCode, $authVersion, $attemptEmail, $loginIp): never {
    $authenticatorQuery('account_authenticator.clear_attempts', 'DELETE FROM st_login_attempts WHERE company_id = 0 AND email = $1 AND ip_address = $2', [$attemptEmail, $loginIp]);
    session_regenerate_id(true);
    unset($_SESSION['mfa_pending_user_code'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
    unset($_SESSION['active_company_id']);
    $_SESSION['user_code'] = $userCode;
    $_SESSION['auth_version'] = $authVersion;
    $_SESSION['platform_mfa_verified'] = true;
    $_SESSION['last_activity_at'] = time();
    $signedInUser = current_user();
    if ($signedInUser === null) {
        redirect('login.php');
    }
    $saved = $authenticatorQuery('account_authenticator.update_last_login', 'UPDATE bt_users SET last_login_at = now() WHERE user_code = $1', [$userCode]);
    if (!$saved) {
        error_log('Successful platform login timestamp could not be saved.');
    }
    redirect_after_login($signedInUser);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify_code');
    if ($action === 'continue' && $isVerifiedSetup) {
        $completeLogin();
    }
    if ($attemptCount() >= 5) {
        $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_mfa_locked')) . '</div>';
    } elseif (!$isVerifiedSetup && in_array($action, ['verify_code', 'complete_setup'], true)) {
        $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['code'] ?? ''))));
        $validCode = false;
        try {
            $secret = $encryptedSecret === null
                ? $setupSecret
                : grompil_totp_decrypt_secret($encryptedSecret);
            if (preg_match('/^\d{6}$/', $code)) {
                $validCode = grompil_totp($secret, $email)->verify($code, null, 5);
            }
            if (!$validCode && $encryptedSecret !== null) {
                $validCode = grompil_consume_totp_recovery_code($userCode, $code);
            }
        } catch (Throwable $error) {
            error_log('Platform authenticator code verification failed: ' . $error->getMessage());
        }

        if ($validCode && $encryptedSecret === null) {
            $started = (bool) $authenticatorQuery('account_authenticator.enroll.begin', 'BEGIN');
            try {
                $stored = $started ? $authenticatorQuery(
                    'account_authenticator.enroll.store',
                    'INSERT INTO st_platform_totp_credentials (user_code, secret_ciphertext, enabled_at)
                     VALUES ($1, $2, now())
                     ON CONFLICT (user_code) DO UPDATE
                     SET secret_ciphertext = EXCLUDED.secret_ciphertext, enabled_at = now(), created_at = now()',
                    [$userCode, grompil_totp_encrypt_secret($setupSecret)]
                ) : false;
                if (!$stored) {
                    throw new RuntimeException('Authenticator credentials could not be stored.');
                }
                $recoveryCodes = grompil_create_totp_recovery_codes($userCode);
                if (!$authenticatorQuery('account_authenticator.enroll.commit', 'COMMIT')) {
                    throw new RuntimeException('Authenticator enrollment could not be committed.');
                }
                $_SESSION['mfa_pending_verified'] = true;
                $_SESSION['mfa_recovery_codes_display'] = $recoveryCodes;
                unset($_SESSION['mfa_enroll_secret']);
                $isVerifiedSetup = true;
            } catch (Throwable $error) {
                if ($started) {
                    $authenticatorQuery('account_authenticator.enroll.rollback', 'ROLLBACK');
                }
                error_log('Platform authenticator enrollment failed: ' . $error->getMessage());
                $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_mfa_unavailable')) . '</div>';
            }
        } elseif ($validCode) {
            $completeLogin();
        } else {
            $authenticatorQuery('account_authenticator.record_attempt', 'INSERT INTO st_login_attempts (company_id, email, ip_address) VALUES (0, $1, $2)', [$attemptEmail, $loginIp]);
            $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_mfa_invalid')) . '</div>';
        }
    }
}

$recoveryCodesHtml = '';
if ($isVerifiedSetup) {
    $items = '';
    foreach ((array) ($_SESSION['mfa_recovery_codes_display'] ?? []) as $recoveryCode) {
        $items .= '<li><code>' . e((string) $recoveryCode) . '</code></li>';
    }
    $recoveryCodesHtml = '<div class="alert alert-warning"><strong>' . e(fn_t('platform_mfa_recovery_title'))
        . '</strong><p class="mb-2">' . e(fn_t('platform_mfa_recovery_help')) . '</p><ul class="mb-0 columns-2">' . $items . '</ul></div>';
}

$setupHtml = '';
$qrScriptsHtml = '';
if (!$isVerifiedSetup && $encryptedSecret === null && $setupSecret !== '') {
    $qrHtml = '';
    try {
        $provisioningUri = grompil_totp($setupSecret, 'Platformbeheerder ' . $userCode)->getProvisioningUri();
        $qrLabel = fn_t('platform_mfa_qr_label');
        $qrHtml = '<div class="text-center my-3 d-none" data-authenticator-qr-wrapper><div class="d-inline-block border rounded p-2 bg-white"'
            . ' data-authenticator-qr data-otpauth-uri="' . e($provisioningUri) . '"'
            . ' data-qr-label="' . e($qrLabel) . '"></div></div>';
        $assetBaseHref = e(fn_appBaseHref());
        $qrScriptsHtml = '<script src="' . $assetBaseHref . 'assets/qrcode-generator.js?v=1" defer></script>'
            . '<script src="' . $assetBaseHref . 'assets/authenticator-qr.js?v=1" defer></script>';
    } catch (Throwable $error) {
        error_log('Platform authenticator QR could not be prepared: ' . $error->getMessage());
    }
    $setupHtml = '<div class="alert alert-info"><p>' . e(fn_t('platform_mfa_qr_instructions'))
        . '</p>' . $qrHtml . '<p class="small">' . e(fn_t('platform_mfa_manual_instructions'))
        . '</p><p class="mb-0"><strong>' . e(fn_t('platform_mfa_secret_label')) . '</strong><br><code class="user-select-all fs-5">'
        . e($setupSecret) . '</code></p></div>';
}

echo fn_templateRender('login_authenticator.html', [
    'html_language' => strtolower((string) ($_SESSION['language'] ?? 'NL')),
    'platform_mfa_title' => fn_t($isVerifiedSetup ? 'platform_mfa_recovery_title' : 'platform_mfa_title'),
    'platform_mfa_instructions' => fn_t($isVerifiedSetup ? 'platform_mfa_recovery_help' : 'platform_mfa_instructions'),
    'platform_mfa_code_label' => fn_t('platform_mfa_code_label'),
    'platform_mfa_verify' => fn_t($isVerifiedSetup ? 'platform_mfa_continue' : 'platform_mfa_verify'),
    'platform_mfa_login_link' => fn_t('platform_mfa_login_link'),
    'platform_mfa_action' => e($isVerifiedSetup ? 'continue' : 'verify_code'),
    '_code_input_html' => $isVerifiedSetup
        ? ''
        : '<div class="mb-3"><label class="form-label" for="authenticator-code">' . e(fn_t('platform_mfa_code_label'))
                . '</label><input class="form-control form-control-lg" id="authenticator-code" name="code" type="text" inputmode="text" autocapitalize="characters" autocomplete="one-time-code" pattern="[0-9A-Fa-f ]{6,16}" maxlength="16" required autofocus></div>',
    '_csrf_field_html' => csrf_field(),
    '_error_html' => $errorHtml,
    '_setup_html' => $setupHtml,
    '_qr_scripts_html' => $qrScriptsHtml,
    '_recovery_codes_html' => $recoveryCodesHtml,
    '_language_selector_html' => fn_languageSelector('form-select form-select-sm w-auto'),
    '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
]);
