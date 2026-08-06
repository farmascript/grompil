<?

/**
 *
 * @file        fn_createAlert.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 17:57:07
 * @dateLastMod Wed 2026-08-05 18:06:22
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }


/* #region fn_createAlert */

	
function fn_createAlert(string $type, string $message): string
{
    if ($message === '') {
        return '';
    }

    return '
        <div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
            ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
}

/* #endregion fn_createAlert */