<?

/**
 *
 * @file        fn_load_functions.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 11:33:37
 * @dateLastMod Wed 2026-08-05 11:34:25
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_load_functions */

function fn_load_functions(string $directory): void {
	
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }
}

}

/* #endregion fn_load_functions */