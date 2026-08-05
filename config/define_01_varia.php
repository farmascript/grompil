<?

/**
 *
 * @file        define_varia.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 12:21:01
 * @dateLastMod Wed 2026-08-05 15:42:51
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

# $config is set in admin_config.php

	define('PREFIX_SESSION', $config['prefixSession']);
	define('PREFIX_COOKIES', $config['prefixCookies']);
	define('PREFIX_TABLE',   $config['prefixTable']);
	define('DB_NAME',        $config['dbname']);

	define('DIR_BASE',       $config['baseDir']);
	

define('BRNL', "<br />\n");
define('BR',   "<br />");
define('NL',   "\n");

define('ARROW_RIGHT', '→');
define('HTML_TARGET', '_blank');

define('COMPANY_DEFAULT', 'basic');
define('COMPANY_START',   'start');

define('DATE_TIME',        date("Y-m-d H:i:s"));

define ('BASE_NAME', $baseName);
define('URL_BASE',       htmlspecialchars($_SERVER["PHP_SELF"]));
