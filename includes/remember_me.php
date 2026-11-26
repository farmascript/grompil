<?php
/**
 * @date_cre: Sat 2026-11-21 17:44:47
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages remember me.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:remember_me
 */
declare(strict_types=1);

/**
 * Configured absolute lifetime for an opted-in remembered browser.
 *
 * @return int The resulting identifier.
 */
function grompil_remember_me_days(): int
{
    static $days = null;
    if ($days !== null) {
        return $days;
    }

    try {
        $settings = grompil_deployment_settings(defined('GROMPIL_ROOT') ? GROMPIL_ROOT : dirname(__DIR__));
    } catch (Throwable $error) {
        error_log('Could not read remember_me_days setting; using one day.');
        $settings = [];
    }
    $configured = filter_var($settings['remember_me_days'] ?? 1, FILTER_VALIDATE_INT);
    if ($configured === false || $configured < 1 || $configured > 30) {
        error_log('Invalid remember_me_days setting; using one day.');
        $configured = 1;
    }
    $days = $configured;
    return $days;
}

/**
 * Determine whether remember-me cookies require the secure flag.
 *
 * @return bool Whether the operation succeeded.
 */
function grompil_remember_me_secure_cookie(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/**
 * Set the remember-me cookie with its expiration time.
 *
 * @param string $value The value.
 * @param int $expiresAt The expires at.
 *
 * @return void No value is returned.
 */
function grompil_remember_me_set_cookie(string $value, int $expiresAt): void
{
    setcookie('grompil_remember', $value, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => grompil_remember_me_secure_cookie(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($value === '') {
        unset($_COOKIE['grompil_remember']);
    } else {
        $_COOKIE['grompil_remember'] = $value;
    }
}

/**
 * Revoke the token for this browser, if present, and remove its cookie.
 *
 * @return void No value is returned.
 */
function grompil_remember_me_revoke_current(): void
{
    $cookie = (string) ($_COOKIE['grompil_remember'] ?? '');
    if (preg_match('/^([a-f0-9]{32}):[a-f0-9]{64}$/D', $cookie, $matches) === 1) {
        try {
            db_query_params('remember_me.revoke', 'DELETE FROM st_remember_me_tokens WHERE selector = $1', [$matches[1]]);
        } catch (Throwable $error) {
            error_log('Remember-me token could not be revoked: ' . get_class($error));
        }
    }
    grompil_remember_me_set_cookie('', time() - 3600);
}

/**
 * Issue a hashed, single-browser token after the complete login flow succeeds.
 *
 * @param bool $remember The remember.
 *
 * @return bool Whether the operation succeeded.
 */
function grompil_remember_me_finish_login(bool $remember): bool
{
    grompil_remember_me_revoke_current();
    unset($_SESSION['remember_me_expires_at']);
    if (!$remember || !isset($_SESSION['user_code'], $_SESSION['auth_version'])) {
        return true;
    }

    $selector = bin2hex(random_bytes(16));
    $verifier = bin2hex(random_bytes(32));
    $expiresAt = time() + (grompil_remember_me_days() * 86400);
    try {
        db_query_params('remember_me.cleanup_expired', 'DELETE FROM st_remember_me_tokens WHERE expires_at <= now()');
        db_query_params(
            'remember_me.issue',
            'INSERT INTO st_remember_me_tokens (
                selector, token_hash, user_code, auth_version, active_company_id,
                platform_mfa_verified, company_mfa_verified, company_mfa_company_id, expires_at
             ) VALUES ($1, $2, $3, $4::BIGINT, $5::BIGINT, $6::BOOLEAN, $7::BOOLEAN, $8::BIGINT, to_timestamp($9::DOUBLE PRECISION))',
            [
                $selector, hash('sha256', $verifier), (string) $_SESSION['user_code'],
                (string) $_SESSION['auth_version'], isset($_SESSION['active_company_id']) ? (string) $_SESSION['active_company_id'] : null,
                !empty($_SESSION['platform_mfa_verified']) ? 'true' : 'false',
                !empty($_SESSION['company_mfa_verified']) ? 'true' : 'false',
                isset($_SESSION['company_mfa_company_id']) ? (string) $_SESSION['company_mfa_company_id'] : null,
                (string) $expiresAt,
            ]
        );
        $_SESSION['remember_me_expires_at'] = $expiresAt;
        grompil_remember_me_set_cookie($selector . ':' . $verifier, $expiresAt);
        return true;
    } catch (Throwable $error) {
        error_log('Remember-me token could not be issued: ' . get_class($error));
        return false;
    }
}

/**
 * Restore an authenticated session from a valid browser token.
 *
 * @return bool Whether the operation succeeded.
 */
function grompil_remember_me_restore(): bool
{
    if (isset($_SESSION['user_code'])) {
        return true;
    }
    $cookie = (string) ($_COOKIE['grompil_remember'] ?? '');
    if (preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/D', $cookie, $matches) !== 1) {
        if ($cookie !== '') {
            grompil_remember_me_set_cookie('', time() - 3600);
        }
        return false;
    }

    try {
        $result = db_query_params(
            'remember_me.load',
            'SELECT selector, token_hash, user_code, auth_version, active_company_id,
                    platform_mfa_verified, company_mfa_verified, company_mfa_company_id,
                    extract(epoch FROM expires_at)::BIGINT AS expires_epoch
             FROM st_remember_me_tokens WHERE selector = $1 AND expires_at > now()',
            [$matches[1]]
        );
        $token = pg_fetch_assoc($result) ?: false;
    } catch (Throwable $error) {
        error_log('Remember-me token lookup failed: ' . get_class($error));
        return false;
    }
    if (!$token || !hash_equals(trim((string) $token['token_hash']), hash('sha256', $matches[2]))) {
        grompil_remember_me_revoke_current();
        return false;
    }

    $userCode = (string) $token['user_code'];
    $activeCompanyId = $token['active_company_id'] === null ? 0 : (int) $token['active_company_id'];
    $platformOperator = $token['platform_mfa_verified'] === 't';
    set_database_tenant_context($activeCompanyId, $userCode, $platformOperator);
    try {
        $userResult = db_query_params(
            'remember_me.validate_user',
            'SELECT is_active, approval_status,
                    COALESCE((to_jsonb(u)->>\'auth_version\')::BIGINT, 0) AS auth_version
             FROM bt_users u WHERE user_code = $1',
            [$userCode]
        );
        $user = pg_fetch_assoc($userResult) ?: false;
    } catch (Throwable $error) {
        error_log('Remember-me account validation failed: ' . get_class($error));
        return false;
    }
    if (!$user || $user['is_active'] !== 't' || $user['approval_status'] !== 'approved'
        || (int) $user['auth_version'] !== (int) $token['auth_version']) {
        grompil_remember_me_revoke_current();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_code'] = $userCode;
    $_SESSION['auth_version'] = (int) $token['auth_version'];
    $_SESSION['last_activity_at'] = time();
    $_SESSION['remember_me_expires_at'] = (int) $token['expires_epoch'];
    if ($activeCompanyId > 0) {
        $_SESSION['active_company_id'] = $activeCompanyId;
    }
    if ($token['platform_mfa_verified'] === 't') {
        $_SESSION['platform_mfa_verified'] = true;
    }
    if ($token['company_mfa_verified'] === 't') {
        $_SESSION['company_mfa_verified'] = true;
        $_SESSION['company_mfa_company_id'] = (int) ($token['company_mfa_company_id'] ?? $activeCompanyId);
        $_SESSION['company_mfa_user_code'] = $userCode;
    }
    $_SESSION['bookmark_sync_nonce'] = bin2hex(random_bytes(16));
    return true;
}
