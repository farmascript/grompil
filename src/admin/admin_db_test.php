<?

/**
 *
 * @file        admin_db_test.php
 * @author      lm
 * @dateCreated Sun 2026-08-02 11:35:36
 * @dateLastMod Wed 2026-08-05 15:42:51
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/


declare(strict_types=1);
if (!defined('BASIC_INDEX_SEEN')) {	require $_SERVER['DOCUMENT_ROOT'] . '/not_allowed.php'; }

print "\n<pre>659007\n"; 
print "var_dump myPDO :"; var_dump($myPDO); print "\n</pre>\n";

try {

// 2. Define the SQL query
    $query = "SELECT table_name 
              FROM information_schema.tables 
              WHERE table_schema = 'public'
              AND   table_type   = 'BASE TABLE'
              ORDER BY table_name;";

    // 3. Execute the query using PDO
    $stmt = $myPDO->query($query);
    
    // 4. Fetch and display the results
    echo "<h3>Tables in Database:</h3>";
    echo "<ul>";
    while ($row = $stmt->fetch()) {
        echo "<li>" . htmlspecialchars($row['table_name']) . "</li>";
    }
    echo "</ul>";

} catch (\PDOException $e) {
    // This will now capture connection errors and query errors
    echo "Database Error: " . $e->getMessage();
} catch (\Throwable $th) {
    // This captures any other unexpected PHP errors
    echo "General Error: " . $th->getMessage();
}

print "end3";
