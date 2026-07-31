<?

/**
 *
 * @file        admin_defines_0.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:22:05
 * @dateLastMod Fri 2026-07-31 20:21:40
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

define('DATE_TIME',        date("Y-m-d H:i:s"));

# $config is set in admin_config.php

define('PREFIX_SESSION', $config['prefixSession']);
define('PREFIX_COOKIES', $config['prefixCookies']);
define('PREFIX_TABLE',   $config['prefixTable']);
define('DB_NAME',        $config['dbname']);

define('DIR_BASE',       $config['baseDir']);
define('DIR_IMAGES',     'media');

define('URL_BASE',       htmlspecialchars($_SERVER["PHP_SELF"]));
define('HOME_LINK',     "<a href='" . URL_BASE . "'><i class='bi bi-house-door'></i></a>");

define('BRNL', "<br />\n");
define('BR',   "<br />");
define('NL',   "\n");

# Enum case value must be compile-time evaluable, so 'grm_' is hardcoded
enum TABLE_NAME: string {

	case USERS    = 'grm_users';
	case VAR      = 'grm_var';

}

# DEFAULT is keyword, so DFLT
enum DFLT: string {

	case BASE_NAME       = 'grompil';
	case COMPANY_DEFAULT = 'basic';
	case COMPANY_START   = 'start';

	case ICON_VSC         = "<img src='" . DIR_IMAGES . "/vscode.svg'  width='15' height='15'>";
	case ICON_MANUAL      = "<img src='" . DIR_IMAGES . "/info.svg'  width='20' height='20'>";
	case ICON_WIP         = "<img src='" . DIR_IMAGES . "/work_in_progress.png'  width='120'>";

	case IMG_gromPilSaas = DIR_IMAGES . "/gromPilSaas.png"; 
	case IMG_gromPilLogo = DIR_IMAGES . "/gromPilLogo.png";
	
	case ARROW_RIGHT = '→';
	case HTML_TARGET = '_blank';

}

enum DIR: string {
	

	case FN               = 'src/fn';
	case CLASSES		  = 'src/classes';
	case LANG			  = 'src/lang';
	case TMPL_BASIC		  = 'tmpl/basic';

}
