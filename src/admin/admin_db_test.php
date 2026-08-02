<?

/**
 *
 * @file        admin_db_test.php
 * @author      lm
 * @dateCreated Sun 2026-08-02 11:35:36
 * @dateLastMod Sun 2026-08-02 11:37:12
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

$host = "localhost";
$port = "5432";
$username = "grompil_1_lm";
$password = "Retie?110!274";


// 1. Build the connection string from form inputs
$string = "host='$host'" . ($port ? " port='$port'" : "") . " user='" . addcslashes($username, "'\\") . "' password='" . addcslashes($password, "'\\") . "'";

if (isset($ssl["mode"])) {
    $string .= " sslmode='" . $ssl["mode"] . "'";
}

// 2. Open the connection using the PGSQL_CONNECT_FORCE_NEW flag
$link = @pg_connect("$string dbname='" . ($db != "" ? addcslashes($db, "'\\") : "postgres") . "'", PGSQL_CONNECT_FORCE_NEW);

// 3. Fallback to default 'postgres' database if the target database fails performance checks
if (!$link && $db != "") {
    $link = @pg_connect("$string dbname='postgres'", PGSQL_CONNECT_FORCE_NEW);
}

// 4. Set the client encoding
if ($link) {
    pg_set_client_encoding($link, "UTF8");
}
