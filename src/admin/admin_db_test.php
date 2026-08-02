<?

/**
 *
 * @file        admin_db_test.php
 * @author      lm
 * @dateCreated Sun 2026-08-02 11:35:36
 * @dateLastMod Sun 2026-08-02 11:47:58
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
$db = "grompil_1_grompil";


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

# print "end";
# exit;

// 1. Define the SQL query to get public tables
$query = "SELECT table_name 
          FROM information_schema.tables 
          WHERE table_schema = 'public' 
            AND table_type = 'BASE TABLE'
          ORDER BY table_name;";

// 2. Execute the query using your $link connection
$result = pg_query($link, $query);

// 3. Check for errors and loop through the results
if ($result) {
    echo "<h3>Tables in Database:</h3>";
    echo "<ul>";
    
    while ($row = pg_fetch_assoc($result)) {
        echo "<li>" . htmlspecialchars($row['table_name']) . "</li>";
    }
    
    echo "</ul>";
    
    // 4. Free the result memory
    pg_free_result($result);
} else {
    echo "Error executing query: " . pg_last_error($link);
}

print "end";
