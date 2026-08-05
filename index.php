<?
declare(strict_types=1);

# ob_start(); # Buffert eventuele perongelukke spaties of vroege output

/**
 *
 * @file        index.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 17:37:35
 * @dateLastMod Wed 2026-08-05 14:51:28
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

/* #region basics */

# check if we are on the server or on localhost
	$serverRA = $_SERVER['REMOTE_ADDR'];
	match ($serverRA) {
		'127.0.0.1' => define("LOCAL_HOST", TRUE),
		'::1'       => define("LOCAL_HOST", TRUE),
		default     => define("LOCAL_HOST", FALSE),
	};
	define("DIR_ROOT", LOCAL_HOST ? '' : "/public_html");
	
	# on the production server or not?
	LOCAL_HOST ? $baseName = 'grompil' : $baseName = '';
	define('BASENAME', $baseName);

	

# use in other modules to prevent direct execution of a module that has to be included
	define('BASIC_INDEX_SEEN', TRUE);

# check php version
	$versionNeeded = '8.1';
	if (version_compare(PHP_VERSION, $versionNeeded) < 0)
	{
		# debug function not available
		print "<pre>Fatal error #715994: your PHP installation is not compatible. You need at least PHP $versionNeeded. Found: " . PHP_VERSION . "</pre>";
		exit -1;
	} 

# set this or php gives a warning
	$voidBool = date_default_timezone_set('Europe/Brussels');

# gracefully exit
	function shutdown()
	{
		$txt = "#213376 - program end.\n";
		$txt = ''; 	# leaves a trace in the source that the script ended gracefully, but does not show it to the user
		print "\n<!-- b 642108  -->\n<pre>\n<div class='container'>\n$txt</div>\n</pre>\n<!-- b 642108  -->";

	}
	register_shutdown_function('shutdown');
	
/* #endregion basics */


/* #region startSomeThings */

require 'config/config.php';

# read all of the function_exists functions from the src/functions directory
	$iterator = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator('src/functions')
	);
	
	foreach ($iterator as $file) {
	
	    if (!$file->isFile()) {
	        continue;
	    }
	
	    $filename = strtolower($file->getFilename());
	
	    if (
	        str_starts_with($filename, 'fn_') &&
	        str_ends_with($filename, '.php')
	    ) {
	        require_once $file->getPathname();
	    }
	}

	$pdo = fn_connect_db($config);

# read all of the define functions from the src/defines directory
	foreach (glob(__DIR__ . '/config/define_*.php') as $file) {
	    require_once $file;
	}

/* #endregion startSomeThings */

/* #region doSomeLangBusiness */

	$langCode = fn_establish_language($config);
	$langFile = DIR_LANG . '/lang_' . $langCode . '.php';
	require $langFile;
	
	# fully qualified, trying to prevent cache problems
	$arrLang['favicon_saas_ico']     = DIR_FAVICONS . '/saas.ico';
	$arrLang['favicon_saas_png']     = DIR_FAVICONS . '/saas.png';
	$arrLang['gromPil_logo']         = DIR_IMAGES . '/gromPilLogo.png';
	$arrLang['gromPilSaas_img']      = DIR_IMAGES . '/gromPilSaas.png';
	
	$arrLang['img_work_in_progress'] = DIR_IMAGES . '/work_in_progress1.png';
	$arrLang['version'] = '0.1';
	$arrLang['phpVersion'] = PHP_VERSION;
	
/* #endregion doSomeLangBusiness */


# check cookies. Now you have the value of $cookiesConsentFooter, which is either empty or contains the HTML of the banner
require DIR_ADMIN . '/admin_cookies_consent.php';

$arrRender = [
	'header' => DIR_TMPL_DEFAULT . '/header.html',
	'body'   => DIR_TMPL_DEFAULT . '/body.html',
	'footer' => DIR_TMPL_DEFAULT . '/footer.html',
];

$missing = [];
$html    = '';

foreach ($arrRender as $key => $file) {
	$html .= fn_renderTemplate($file, $arrLang);
	$missing = array_merge($missing, fn_checkTemplate($file, $arrLang));
}


if (!empty($missing)) {
	print BRNL . Lang::get('missing_translation') . BRNL;
    print implode(BRNL, $missing);
	}
	
	print $html;
	print $cookiesConsentFooter;
	
/*
 * end of script
 */
	
exit(0);