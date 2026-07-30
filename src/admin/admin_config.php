<?

/**
 *
 * @file        admin_config.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:01:29
 * @dateLastMod Thu 2026-07-30 19:21:22
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }
$inc = get_included_files(); if(basename(__FILE__) == basename($inc[0])) die('<pre>nice try</pre>');

if( !defined('LOCAL_HOST') ) {
    $serverRA = $_SERVER['REMOTE_ADDR'];
    match ( $serverRA ) {
        '127.0.0.1' => define("LOCAL_HOST", TRUE),
        '::1'       => define("LOCAL_HOST", TRUE),
        default     => define("LOCAL_HOST", FALSE),
    };
}

$config = [];

$config += [
'baseDir'           => 'grompil',
'prefixSession'     => "grompil",
'prefixCookies'     => "grompil_",
'cookiesExpireTime' => 60*60*24,                  # 60*60*24 = 1 day
];

if( LOCAL_HOST ) {
    $config += [
	'hostname'    => "localhost",
    'username'    => "progres",
    'password'    => "apo110",
    'dbname'      => "grompil",
    'port'        => 3306,
    'prefixTable' => "grm_",
	];

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
	
    error_reporting(E_ALL);
} else {
    $config += [
	'hostname'    => "localhost",
    'username'    => "grompil_1_lm",
    'password'    => "Retie?110!274",
    'dbname'      => "grompil_1_grompil",
    'port'        => 5432,
    'prefixTable' => "grm_",
	];
}

define ('TABLE_PREFIX', $config['prefixTable']);