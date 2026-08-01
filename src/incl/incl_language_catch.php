<?

/**
 *
 * @file        incl_language_catch.php
 * @author      lm
 * @dateCreated Sat 2026-08-01 16:06:03
 * @dateLastMod Sat 2026-08-01 21:17:30
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

$allowed_languages = ['nl', 'fr', 'en', 'de'];
$default_language = 'nl';
$cookieSiteLang = $config['prefixCookies'] . 'site_lang';

// 1. Bepaal de taal (URL heeft voorrang, daarna cookie, anders standaard)
if (isset($_GET['lang']) && in_array(strtolower($_GET['lang']), $allowed_languages, true)) {

    $langCode = strtolower($_GET['lang']);

} elseif (isset($_COOKIE[$cookieSiteLang]) && in_array($_COOKIE[$cookieSiteLang], $allowed_languages, true)) {

    $langCode = $_COOKIE[$cookieSiteLang];

} else {

    $langCode = $default_language;
	
}

// 2. Sla de taal op in een cookie voor 30 dagen als deze gewijzigd of nieuw is
if (!isset($_COOKIE[$cookieSiteLang]) || $_COOKIE[$cookieSiteLang] !== $langCode) {
    setcookie($cookieSiteLang, $langCode, [
        'expires' => time() + (86400 * 30), // 30 dagen geldig
        'path' => '/',                      // Beschikbaar op de hele website
        'secure' => true,                   // Alleen via HTTPS (veilig)
        'httponly' => true,                 // Beschermt tegen XSS-hacks
        'samesite' => 'Lax'                 // Goed voor privacy en security
    ]);
}

// $langCode is nu overal veilig te gebruiken
