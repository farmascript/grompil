<?

  /**
 *
 * @file        nl.php
 * @author      lm
 * @dateCreated Fri 2026-07-31 10: 17: 23
 * @dateLastMod Fri 2026-07-31 22:09:42
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

$arrLang = [

  # header
		"title"       => "Welkom op ons platform",
		"title_url"   => "gromPil saas",
		"description" => "Snel, veilig en volledig gebouwd met de modernste technieken van Bootstrap 5.3.8 en PHP backend-architectuur.",
		"home"        => "Home",
		"services"    => "Diensten",
		"about_us"    => "Over Ons",
		"contact"     => "Contact",

  # cookies_consent
		"cookie_accept"     => "Accepteren",
		"cookie_all"        => "Alles Accepteren",
		"cookie_decline"    => "Weigeren",
		"cookie_essential"  => "Alleen Essentieel",
		"cookie_functional" => "Functioneel",
		"cookie_more_info"  => "Meer informatie",
		"cookie_settings"   => "Cookie-instellingen",
		"cookie_text"       => "We gebruiken cookies om uw ervaring te verbeteren. Door verder te gaan, gaat u akkoord met ons gebruik van cookies.",
		"cookie_title"      => "Cookies",

  # body
		"bootstrap"              => "Snel, veilig en volledig gebouwd met de modernste technieken van Bootstrap en PHP backend-architectuur.",
		"feature_cookie_text"    => "Eenvoudig instemmen of weigeren via onze geïntegreerde consent-functionaliteit.",
		"feature_cookie_title"   => "Cookie Beheer",
		"feature_security_text"  => "Optimale databeveiliging dankzij ingebouwde CSRF-bescherming en HttpOnly cookies.",
		"feature_security_title" => "Beveiliging",
		"feature_speed_text"     => "Geoptimaliseerde code zorgt voor bliksemsnelle laadtijden op elk mobiel apparaat.",
		"feature_speed_title"    => "Snelheid",
		"more_info"              => "Meer informatie",
		"start"                  => "Aan de slag",
		"welcome"                => "Welkom op ons platform",

  # footer
		"contact_email"        => "info@grompil.com",
		"cookie_banner"        => Lang::render(DIR::TMPL_BASIC->value . '/cookies_consent.html'),
		"cookie_reset"         => "Reset Cookie Keuzes",
		"copyright"            => "&copy; 1981-" . date('Y') . " - lm ",

  # bootstrap

			  # placed in the header
			  # do not touch unless you know what you are doing
		"bt_link_stylesheet"     => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
		"bt_styleSheetIntegrity" => 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',

			  # it is important to place the script at the end of the body for optimal page load performance.
			  # do not touch unless you know what you are doing"
		"bt_link_script"     => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
		"bt_scriptIntegrity" => 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI',

   # varia

		"missing_translation"  => "Ontbrekende vertalingen",
		"img_work_in_progress" => DFLT::ICON_WIP->value,
		"gromPilSaas_alt"      => "GrompilSaaS beeld",
		"gromPilSaas_img"      => DFLT::IMG_gromPilSaas->value,
		"gromPil_logo"         => DFLT::IMG_gromPilLogo->value,
		"gromPil_logo_alt"     => "Grompil logo",

];

