<?

/**
 *
 * @file        incl_establish_environment.php
 * @author      lm
 * @dateCreated Sun 2026-08-02 14:19:21
 * @dateLastMod Sun 2026-08-02 14:42:49
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

$isWindows = (PHP_OS_FAMILY === 'Windows');

$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
$isLiteSpeed    = (str_contains(strtolower($serverSoftware), 'litespeed'));
$omgeving       = '';

if ($isWindows && !$isLiteSpeed) {
    $omgeving = 'localhost_windows';
} elseif ($isLiteSpeed) {
    $omgeving = 'productie_litespeed';
	
} else {
    die( "395146 - $omgeving = 'onbekend'");
}

if( !defined('LOCAL_HOST') ) {
    $serverRA = $_SERVER['REMOTE_ADDR'];
    match ( $serverRA ) {
        '127.0.0.1' => define("LOCAL_HOST", TRUE),
        '::1'       => define("LOCAL_HOST", TRUE),
        default     => define("LOCAL_HOST", FALSE),
    };
}

	print '<pre><b>#868138</b>\n';
	print "remote_adress: " . $_SERVER['REMOTE_ADDR'] . "\n";
	print "local_host: " . (LOCAL_HOST ? 'TRUE' : 'FALSE') . "\n";
	print '</pre>';
