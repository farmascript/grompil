<?

/**
 *
 * @file        establish_environment.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 11:04:38
 * @dateLastMod Wed 2026-08-05 15:42:51
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 * name of this file does not start with fn_ because it was called before global define routine
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region establish_environment */

function fn_establish_environment(): string {

	$isWindows = (PHP_OS_FAMILY === 'Windows');

	$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
	$isLiteSpeed    = (str_contains(strtolower($serverSoftware), 'litespeed'));
	$environment       = '';

	if ($isWindows && !$isLiteSpeed) {
		$environment = 'localhost_windows';
	} elseif ($isLiteSpeed) {
		$environment = 'production_litespeed';
		
	} else {
		die( "395146 - $environment = 'unknown'");
	}

	if( !defined('ENVIRONMENT') ) {
		define('ENVIRONMENT', $environment);
	}
	
	if( !defined('LOCAL_HOST') ) {
    $serverRA = $_SERVER['REMOTE_ADDR'];
    match ( $serverRA ) {
        '127.0.0.1' => define("LOCAL_HOST", TRUE),
        '::1'       => define("LOCAL_HOST", TRUE),
        default     => define("LOCAL_HOST", FALSE),
    };
}

	# print "<pre><b>#868138</b>\n";
	# print "remote_adress: " . $_SERVER['REMOTE_ADDR'] . "\n";
	# print "local_host: " . (LOCAL_HOST ? 'TRUE' : 'FALSE') . "\n";
	# print "envorinment: " . ENVIRONMENT . "\n";
	# print "</pre>";

return $environment;
}

/* #endregion establish_environment */