<?

/**
 *
 * @file        admin_db_test2.php
 * @author      lm
 * @dateCreated Sun 2026-08-02 13:16:20
 * @dateLastMod Sun 2026-08-02 13:18:03
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

$user = 'grompil_1_lm';
$password = 'Retie?110!274';
$dbname = 'grompil_1_grompil';

// Op Linux/LiteSpeed servers is dit vaak het standaard pad:
$socket_dir = '/var/run/postgresql'; 

try {
    // We laten 'host' weg en definiëren de socket map via 'hostaddr' of direct in de host string
    $dsn = "pgsql:host=$socket_dir;dbname=$dbname";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Gooi exceptions bij fouten
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Geef arrays terug met kolomnamen
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Gebruik native PostgreSQL prepared statements
    ];

    $pdo = new PDO($dsn, $user, $password, $options);
    echo "Succesvol verbonden via Unix-socket!";

} catch (\PDOException $e) {
    echo "Verbindingsfout: " . $e->getMessage();
}

