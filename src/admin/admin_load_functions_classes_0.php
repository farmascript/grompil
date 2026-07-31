<?

/**
 *
 * @file        admin_load_functions_classes_0.php
 * @author      lm
 * @dateCreated Thu 2026-07-30 19:35:04
 * @dateLastMod Fri 2026-07-31 12:21:19
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region cd functions */

	foreach (glob(DIR::FN->value . '/fn_*') as $filename) {
		require $filename;
	}

/* #endregion functions */

/* #region cd classes */

	foreach (glob(DIR::CLASSES->value . '/class_*') as $filename) {
		require $filename;
	}

/* #endregion classes */