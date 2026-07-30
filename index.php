<?

/**
 *
 * @file        index.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 17:37:35
 * @dateLastMod Thu 2026-07-30 17:39:17
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

	print "DIR_ROOT: " . DIR_ROOT . "<br>";
	
# use in other modules to prevent direct execution of a module that has to be included
	define('BASIC_INDEX_SEEN', TRUE);

# check php version
	$versionNeeded = '8.1';
	if (version_compare(PHP_VERSION, $versionNeeded) < 0)
	{
		# debug function not available
		print "<pre>Fatal error #715994: your PHP installation is not compatible. You need at least PHP $versionNeeded. Found: " . PHP_VERSION . "</pre>";
		exit -1;
	} else {
		print "PHP version is compatible.<br>";
	}

# set this or php gives a warning
	$voidBool = date_default_timezone_set('Europe/Brussels');
