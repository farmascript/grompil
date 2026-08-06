<?

/**
 *
 * @file        define_directorys.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 12:17:41
 * @dateLastMod Wed 2026-08-05 21:00:45
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

	define('DIR_ADMIN',       'src/admin');
	define('DIR_CLASSES',     'src/classes');
	define('DIR_FAVICONS',    'media/favicons');
	define('DIR_FUNCTIONS',   'src/fn');
	define('DIR_IMAGES',      'media');
	define('DIR_INCL',        'src/incl');
	define('DIR_LANG',        'src/lang');
	define('DIR_TEMP',        'tmp');
	define('DIR_TMPL_DEFAULT','tmpl/default');
	define('DIR_JS',          'js');

