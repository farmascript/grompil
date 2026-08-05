<?

/**
 *
 * @file        fn_load_defines.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 12:52:52
 * @dateLastMod Wed 2026-08-05 15:42:51
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_load_defines */

function fn_load_defines(): void {
	
	foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/config/define*.php') as $file) {
	    require_once $file;
	}

}

/* #endregion fn_load_defines */