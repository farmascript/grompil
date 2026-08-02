<?

/**
 *
 * @file        admin_db_connect.php
 * @author      lm
 * @dateCreated Sat 2026-08-01 21:19:58
 * @dateLastMod Sun 2026-08-02 13:09:45
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

# require_once  DIR_ADMIN . 'admin_config.php';

// Verbinding maken met de PostgreSQL database
$hostName     = $config['hostname'];
$port     = $config['port'];
$dbName   = $config['dbname'];
$userName = $config['username'];
$pass     = $config['password'];

// Aangepaste DSN string voor PostgreSQL
# $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
# $db_connection = pg_connect("host=localhost dbname=$dbName user=$userName password=$pass");


$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
	$myPDO = new PDO('pgsql:host=' . $hostName . ';dbname=' . $dbName, $userName, $pass, $options);

} catch (\PDOException $e) {
    die("<pre>596881 - PostgreSQL verbinding mislukt op '$omgeving', using host: '$hostName', db: '$dbName', user: '$userName' " . NLBR . $e->getMessage() . "</pre>");
}

require DIR_ADMIN . '/admin_db_test.php';