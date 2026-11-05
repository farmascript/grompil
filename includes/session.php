<?php
/**
 *
 * @date_cre: Thu 2026-11-05
 * @date_mut: Thu 2026-11-05
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Starts the shared PHP session and enforces its configured inactivity timeout.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/credentials_config.php';

/** Return the configured inactivity limit, defaulting safely to 30 minutes. */
function grompil_session_idle_timeout_seconds(): int
{
    static $seconds = null;
    if ($seconds !== null) {
        return $seconds;
    }

    $settings = grompil_deployment_settings(defined('GROMPIL_ROOT') ? GROMPIL_ROOT : dirname(__DIR__));
    $minutes = filter_var($settings['session_idle_timeout_minutes'] ?? 30, FILTER_VALIDATE_INT);
    if ($minutes === false || $minutes < 1 || $minutes > 43200) {
        error_log('Invalid session_idle_timeout_minutes in settings_local.php; using 30 minutes.');
        $minutes = 30;
    }

    $seconds = $minutes * 60;
    return $seconds;
}

/** Start the session with persistent-cookie settings and expire idle logins. */
function grompil_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $cookieLifetime = 60 * 60 * 24 * 30;
    $storageLifetime = max($cookieLifetime, grompil_session_idle_timeout_seconds());

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) $storageLifetime);
    session_name('grompil_session');
    session_set_cookie_params([
        'lifetime' => $cookieLifetime,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    grompil_enforce_session_idle_timeout();
}

/** Expire an authenticated session after inactivity, preserving language for the login screen. */
function grompil_enforce_session_idle_timeout(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    $lastActivity = filter_var($_SESSION['last_activity_at'] ?? null, FILTER_VALIDATE_INT);
    if ($lastActivity === false || $lastActivity < 1 || $lastActivity > $now) {
        $lastActivity = $now;
    }

    if ($now - $lastActivity >= grompil_session_idle_timeout_seconds()) {
        $language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
        if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
            $language = 'NL';
        }
        $languagePreferenceSet = !empty($_SESSION['language_preference_set']);
        $_SESSION = [
            'language' => $language,
            'language_preference_set' => $languagePreferenceSet,
            'session_expired' => true,
        ];
        session_regenerate_id(true);
        return;
    }

    $_SESSION['last_activity_at'] = $now;
}
