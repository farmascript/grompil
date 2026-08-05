<?

/**
 *
 * @file        define_media.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 12:23:36
 * @dateLastMod Wed 2026-08-05 16:33:53
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

define('ICON_VSC',    "<img src='" . DIR_IMAGES . "/vscode.svg'  width='15' height='15'>");
define('ICON_MANUAL', "<img src='" . DIR_IMAGES . "/info.svg'  width='20' height='20'>");
define('ICON_WIP',    "<img src='" . DIR_IMAGES . "/work_in_progress1.png'  width='120'>");		
define('IMG_gromPilSaas', DIR_IMAGES . "/gromPilSaas.png");
define('IMG_gromPilLogo', DIR_IMAGES . "/gromPilLogo.png");

define('HOME_LINK',     "<a href='" . URL_BASE . "'><i class='bi bi-house-door'></i></a>");
