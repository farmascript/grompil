<?

/**
 *
 * @file        admin_db_connect.php
 * @author      lm
 * @dateCreated Sat 2026-08-01 21:19:58
 * @dateLastMod Sat 2026-08-01 22:36:36
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

# require_once  DIR_ADMIN . 'admin_config.php';

// Verbinding maken met de PostgreSQL database
$host    = $config['hostname'];
$port    = $config['port'];
$db      = $config['dbname'];
$user    = $config['username'];
$pass    = $config['password'];

// Aangepaste DSN string voor PostgreSQL
$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("PostgreSQL verbinding mislukt op '$omgeving': " . $e->getMessage());
}
