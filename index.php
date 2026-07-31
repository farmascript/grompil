<?

/**
 *
 * @file        index.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 17:37:35
 * @dateLastMod Fri 2026-07-31 12:55:30
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

/* #region cd basics */

# check if we are on the server or on localhost
	$serverRA = $_SERVER['REMOTE_ADDR'];
	match ($serverRA) {
		'127.0.0.1' => define("LOCAL_HOST", TRUE),
		'::1'       => define("LOCAL_HOST", TRUE),
		default     => define("LOCAL_HOST", FALSE),
	};
	define("DIR_ROOT", LOCAL_HOST ? '' : "/public_html");

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
		# $txt = '';
		print "\n<!-- b 642108  -->\n<pre>\n<div class='container'>\n$txt</div>\n</pre>\n<!-- b 642108  -->";

	}
	register_shutdown_function('shutdown');
	
/* #endregion basics */


/* #region cd startSomeThings */

define('DIR_ADMIN', 'src/admin');

	require DIR_ADMIN . '/admin_config.php';
	require DIR_ADMIN . '/admin_defines_0.php';
	require DIR_ADMIN . '/admin_load_functions_classes_0.php';

	# require DIR::LANG->value . '/nl.php';
	Lang::load(require DIR::LANG->value . '/nl.php');

	# test
	print Lang::get('cookie_settings') . BRNL;
	print Lang::render(DIR::TMPL_BASIC->value . '/cookies_consent.html') . BRNL;

/* #endregion startSomeThings */

print DFLT::ICON_WIP->value;

/*
 * end of script
 */
	
	exit(0);