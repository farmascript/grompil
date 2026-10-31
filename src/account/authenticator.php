<?php
/** Completes the platform administrator password plus TOTP login. */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('admin.php');
}

$userId = (int) ($_SESSION['mfa_pending_user_id'] ?? 0);
$authVersion = (int) ($_SESSION['mfa_pending_auth_version'] ?? -1);
$email = (string) ($_SESSION['mfa_pending_email'] ?? '');
$attemptEmail = (string) ($_SESSION['mfa_pending_attempt_email'] ?? '');
$loginIp = (string) ($_SESSION['mfa_pending_ip'] ?? '');
$currentIp = grompil_client_ip_address();
if ($userId < 1 || $email === '' || $attemptEmail === '' || $loginIp === '' || $currentIp !== $loginIp) {
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
    redirect('login.php');
}

set_database_tenant_context(0, $userId, true);
$accountResult = pg_query_params(
    db(),
    'SELECT u.is_active, u.approval_status,
            COALESCE((to_jsonb(u)->>\'auth_version\')::bigint, 0) AS auth_version,
            r.name AS role_name, r.level AS role_level, r.permissions
     FROM users AS u JOIN roles AS r ON r.id = u.role_id
     WHERE u.id = $1 AND u.company_id IS NULL',
    [$userId]
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
if (!$accountIsPlatform || !platform_management_ip_allowed($currentIp, $userId)) {
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
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
        $encryptedSecret = grompil_platform_totp_ciphertext($userId);
        if ($encryptedSecret === null) {
            if ($setupSecret === '') {
                $setup = OTPHP\TOTP::generate(new OTPHP\InternalClock(), 20)
                    ->withPeriod(30)
                    ->withDigits(6)
                    ->withDigest('sha1')
                    ->withIssuer('gromPil')
                    ->withLabel($email);
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

$attemptCount = static function () use ($attemptEmail, $loginIp): int {
    $result = pg_query_params(
        db(),
        "SELECT count(*) AS total FROM login_attempts
         WHERE company_id = 0 AND email = $1 AND ip_address = $2
           AND attempted_at > now() - interval '15 minutes'",
        [$attemptEmail, $loginIp]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ? (int) $row['total'] : 0;
};

$completeLogin = static function () use ($userId, $authVersion, $attemptEmail, $loginIp): never {
    pg_query_params(db(), 'DELETE FROM login_attempts WHERE company_id = 0 AND email = $1 AND ip_address = $2', [$attemptEmail, $loginIp]);
    session_regenerate_id(true);
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_auth_version'], $_SESSION['mfa_pending_email'], $_SESSION['mfa_pending_attempt_email'], $_SESSION['mfa_pending_ip'], $_SESSION['mfa_pending_verified'], $_SESSION['mfa_enroll_secret'], $_SESSION['mfa_recovery_codes_display']);
    unset($_SESSION['active_company_id']);
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_version'] = $authVersion;
    $_SESSION['platform_mfa_verified'] = true;
    $signedInUser = current_user();
    if ($signedInUser === null) {
        redirect('login.php');
    }
    $saved = pg_query_params(db(), 'UPDATE users SET last_login_at = now() WHERE id = $1', [$userId]);
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
                $validCode = grompil_consume_totp_recovery_code($userId, $code);
            }
        } catch (Throwable $error) {
            error_log('Platform authenticator code verification failed: ' . $error->getMessage());
        }

        if ($validCode && $encryptedSecret === null) {
            $started = (bool) pg_query(db(), 'BEGIN');
            try {
                $stored = $started ? pg_query_params(
                    db(),
                    'INSERT INTO platform_totp_credentials (user_id, secret_ciphertext, enabled_at)
                     VALUES ($1, $2, now())
                     ON CONFLICT (user_id) DO UPDATE
                     SET secret_ciphertext = EXCLUDED.secret_ciphertext, enabled_at = now(), created_at = now()',
                    [$userId, grompil_totp_encrypt_secret($setupSecret)]
                ) : false;
                if (!$stored) {
                    throw new RuntimeException('Authenticator credentials could not be stored.');
                }
                $recoveryCodes = grompil_create_totp_recovery_codes($userId);
                if (!pg_query(db(), 'COMMIT')) {
                    throw new RuntimeException('Authenticator enrollment could not be committed.');
                }
                $_SESSION['mfa_pending_verified'] = true;
                $_SESSION['mfa_recovery_codes_display'] = $recoveryCodes;
                unset($_SESSION['mfa_enroll_secret']);
                $isVerifiedSetup = true;
            } catch (Throwable $error) {
                if ($started) {
                    pg_query(db(), 'ROLLBACK');
                }
                error_log('Platform authenticator enrollment failed: ' . $error->getMessage());
                $errorHtml = '<div class="alert alert-danger" role="alert">' . e(fn_t('platform_mfa_unavailable')) . '</div>';
            }
        } elseif ($validCode) {
            $completeLogin();
        } else {
            pg_query_params(db(), 'INSERT INTO login_attempts (company_id, email, ip_address) VALUES (0, $1, $2)', [$attemptEmail, $loginIp]);
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
if (!$isVerifiedSetup && $encryptedSecret === null && $setupSecret !== '') {
    $setupHtml = '<div class="alert alert-info"><p>' . e(fn_t('platform_mfa_setup_instructions'))
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
    '_recovery_codes_html' => $recoveryCodesHtml,
    '_language_selector_html' => fn_languageSelector('form-select form-select-sm w-auto'),
    '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
]);
