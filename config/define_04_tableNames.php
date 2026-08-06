<?

/**
 *
 * @file        defien_tableNames.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 12:27:02
 * @dateLastMod Wed 2026-08-05 20:57:48
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

define('TABLE_USERS',      PREFIX_TABLE . 'users');
define('TABLE_VAR',		   PREFIX_TABLE . 'var');
define('TABLE_SUBST',      PREFIX_TABLE . 'substitutions');
define('TABLE_PHARMACIES', PREFIX_TABLE . 'pharmacies');