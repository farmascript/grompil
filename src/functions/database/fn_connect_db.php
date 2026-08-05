<?

/**
 *
 * @file        fn_connect_db.php
 * @author      lm
 * @dateCreated Wed 2026-08-05 10:44:28
 * @dateLastMod Wed 2026-08-05 15:42:51
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

/* #region fn_connect_db */

function fn_connect_db($config): PDO {

    static $pdo = null;

	$port     = $config['port'];
	$dbName   = $config['dbname'];
	$userName = $config['username'];
	$pass     = $config['password'];
	$hostName = $config['hostname'];

	$options = [
    	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
   	 	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
   	 	PDO::ATTR_EMULATE_PREPARES   => false,
	];	

	if ($pdo === null) {

		try {	
			$myPDO = new PDO('pgsql:host=' . $hostName . ';dbname=' . $dbName, $userName, $pass, $options);

		} catch (\PDOException $e) {
		    die("<pre>596881 - PostgreSQL connection failed for '$omgeving', using host: '$hostName', db: '$dbName', user: '$userName' " . NLBR . $e->getMessage() . "</pre>");
		}

	return $myPDO;
    }

}

/* #endregion fn_connect_db */