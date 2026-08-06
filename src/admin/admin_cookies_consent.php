<?

/**
 *
 * @file        admin_cookies_consent.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 21:29:52
 * @dateLastMod Wed 2026-08-05 20:12:40
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

# -------------------------------------------------------------------------
# FASE 1: LOGICA & MUTATIES (De PHP Controller)
# -------------------------------------------------------------------------
$arrCookieConsentAll = [
    'cookiesAll'        => true,
    'cookiesAnalytic'   => true,
    'cookiesStatistics' => true,
    'cookiesPreference' => true,
    'cookiesMarketing'  => true,
    'cookiesAds'        => true,
];

$arrCookieConsentEss = [
    'cookiesAll'        => false,
    'cookiesAnalytic'   => false,
    'cookiesStatistics' => false,
    'cookiesPreference' => true,
    'cookiesMarketing'  => false,
    'cookiesAds'        => false,
];

# Verwerk URL parameters (Indien op een cookieknop is geklikt)
if (isset($_GET['ck'])) {
    $action = $_GET['ck'];
    $shouldRedirect = false;

    if ($action === 'a') {
        fn_cookies(strategy: 'delete', name: 'cookieConsent' );
        fn_cookies(strategy: 'set', name: 'cookieConsent', value: json_encode($arrCookieConsentAll));
        $shouldRedirect = true;
    } 
    elseif ($action === 'e') {
        fn_cookies(strategy: 'delete', name: 'cookieConsent');
        fn_cookies(strategy: 'set', name: 'cookieConsent', value: json_encode($arrCookieConsentEss));
        $shouldRedirect = true;
    } 
    elseif ($action === 'n') {
        fn_cookies(strategy: 'delete', name: 'cookieConsent');
        $shouldRedirect = true;
    }

    # URL opschonen na mutatie (Voorkomt oneindige loops)
    if ($shouldRedirect) {

        # $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
		# NIEUWE METHODE (Werkt gegarandeerd op Windows én Linux productieomgevingen)
		$cleanUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        header("Location: " . $cleanUrl, true, 303);
        exit;
    }
}

# -------------------------------------------------------------------------
# FASE 2: STATUS CONTROLEREN & BANNER BUFFEREN
# -------------------------------------------------------------------------
$cookiesConsentFooter = '';
$cookieConsentRaw = fn_cookies(strategy: 'get', name: 'cookieConsent');

# Als er geen cookie is ingesteld, laden we de banner in het geheugen
if (empty($cookieConsentRaw)) {

	$cookiesConsentFooter = fn_renderTemplate(DIR_TMPL_DEFAULT . '/cookies_consent.html', $arrSubst);

} else {
    # Optioneel: De actieve instellingen zijn nu direct bruikbaar in de rest van uw app
    $arrActiveConsent = json_decode($cookieConsentRaw, true);
}
