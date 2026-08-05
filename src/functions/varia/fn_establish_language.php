<?

/**
 *
 * @file        fn_establish_language.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 11:51:52
 * @dateLastMod Wed 2026-08-05 15:42:51
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_establish_language */

function fn_establish_language(array $config): string {
	
	$allowed_languages = ['nl', 'fr', 'en', 'de'];
	$default_language = 'nl';
	$cookieSiteLang = $config['prefixCookies'] . 'site_lang';

	// 1. establish the language (URL has priority, then cookie, otherwise default)
	if (isset($_GET['lang']) && in_array(strtolower($_GET['lang']), $allowed_languages, true)) {

	    $langCode = strtolower($_GET['lang']);

	} elseif (isset($_COOKIE[$cookieSiteLang]) && in_array($_COOKIE[$cookieSiteLang], $allowed_languages, true)) {

	    $langCode = $_COOKIE[$cookieSiteLang];

	} else {

	    $langCode = $default_language;

	}

	// 2. Store the language in a cookie for 30 days if it has been changed or is new
	if (!isset($_COOKIE[$cookieSiteLang]) || $_COOKIE[$cookieSiteLang] !== $langCode) {
	    setcookie($cookieSiteLang, $langCode, [
	        'expires' => time() + (86400 * 30), // 30 dagen geldig
	        'path' => '/',                      // Beschikbaar op de hele website
	        'secure' => true,                   // Alleen via HTTPS (veilig)
	        'httponly' => true,                 // Beschermt tegen XSS-hacks
	        'samesite' => 'Lax'                 // Goed voor privacy en security
	    ]);
	}

	// $langCode is now safe to use everywhere

return $langCode;
}

/* #endregion fn_establish_language */