<?

/**
 *
 * @file        fn_arrayDump.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 17:37:45
 * @dateLastMod Wed 2026-08-05 17:42:29
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_arrayDump */

function fn_arrayDump(array $array): void {
	
	print "\n<pre>\n";
    foreach ($array as $key => $value) {

        print $key . ': ';

        if (is_array($value)) {
            echo PHP_EOL;
            fn_arrayDump($value);
        } else {
            print $value . PHP_EOL;
        }
    }
	print "\n</pre>\n";
}

/* #endregion fn_arrayDump */