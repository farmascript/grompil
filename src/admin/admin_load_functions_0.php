<?

/**
 *
 * @file        admin_load_functions_0.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:35:04
 * @dateLastMod Thu 2026-07-30 19:36:00
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region load functions, part #0 */

	foreach (glob(DIR::FN->value . '/fn_*') as $filename) {
		require $filename;
	}

/* #endregion load functions, part #0 */