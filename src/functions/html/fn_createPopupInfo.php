<?

/**
 *
 * @file        fn_createPopupInfo.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 21:26:37
 * @dateLastMod Wed 2026-08-05 21:42:04
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_createPopupInfo */

function fn_createPopupInfo(string $message, array $arrSubst): string {
	
    if ($message === '') {
        return '';
    }

	$arrSubst['MESSAGE'] = $message; 
	$html = fn_renderTemplate( DIR_TMPL_DEFAULT . "/popupInfo.html", $arrSubst);

return $html;
}

/* #endregion fn_createPopupInfo */