<?

/**
 *
 * @file        admin_cookies_consetn.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 21:29:52
 * @dateLastMod Thu 2026-07-30 21:31:20
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

# 1. Definieer de consent-profielen
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

# 2. Verwerk URL parameters (Mutaties)
if (isset($_GET['ck'])) {
    $action = $_GET['ck'];
    $shouldRedirect = false;

    if ($action === 'a') {
        # Alle cookies accepteren
        fn_cookies(strategy: 'delete', name: 'cookieConsent', trace: '627283');
        fn_cookies(strategy: 'set', name: 'cookieConsent', value: json_encode($arrCookieConsentAll), trace: '800327');
        $shouldRedirect = true;
    } 
    elseif ($action === 'e') {
        # Alleen essentiële cookies accepteren (OPGELOST: miste in oude script)
        fn_cookies(strategy: 'delete', name: 'cookieConsent', trace: '627283');
        fn_cookies(strategy: 'set', name: 'cookieConsent', value: json_encode($arrCookieConsentEss), trace: '800328');
        $shouldRedirect = true;
    } 
    elseif ($action === 'n') {
        # Reset / Weigeren
        fn_cookies(strategy: 'delete', name: 'cookieConsent', trace: '857296');
        $shouldRedirect = true;
    }

    # Schoon de URL op om herhalingen/loops te voorkomen
    if ($shouldRedirect) {
        $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: " . $cleanUrl, true, 303);
        exit;
    }
}

# 3. Controleer huidige status en toon banner indien nodig
$__cookieConsentFooter = '';
$cookieConsentRaw = fn_cookies(strategy: 'get', name: 'cookieConsent', trace: '931432');

# Controleer of de cookie leeg is (beide checks vangen zowel '' als false op)
if (empty($cookieConsentRaw)) {
    require DIR::TEMPLATES_STD->value . '/357270_cookies_consent.php';
    $__cookieConsentFooter = $arrBlock['content'];
} else {
    # Optioneel: Maak de actieve cookie-instellingen direct beschikbaar als array in uw app
    $arrActiveConsent = json_decode($cookieConsentRaw, true);
}
