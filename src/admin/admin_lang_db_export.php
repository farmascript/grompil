<?

/**
 *
 * @file        admin_lang_db_export.php
 * @author      lm
 * @dateCreated Sat 2026-08-01 12:43:16
 * @dateLastMod Wed 2026-08-05 20:16:34
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

// Verbinding maken met de PostgreSQL database
$host    = 'localhost';
$port    = '5432'; // Standaard PostgreSQL poort
$db      = 'grompil';
$user    = 'postgres';
$pass    = 'apo110';

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
    die("PostgreSQL verbinding mislukt: " . $e->getMessage());
}

/**
 * Functie om vertalingen te exporteren naar een PHP-bestand
 */
function exportLanguageFiles($pdo, $langCode) {
    // Haal alle vertalingen op voor de gekozen taal
    $stmt = $pdo->prepare("SELECT key, value FROM substitutions WHERE code = :lang OR code = 'all' ORDER BY key");
    $stmt->execute(['lang' => $langCode]);
    $translations = $stmt->fetchAll();

    $fileContent = "<?php\n";
    $fileContent .= "// Automatisch gegenereerd op: " . date('Y-m-d H:i:s') . "\n";
    $fileContent .= "\$arrSubst" . " = [\n";

    foreach ($translations as $row) {
        // var_export zorgt dat speciale tekens en aanhalingstekens veilig worden ontsnapt
        $key   = var_export($row['key'], true);
        $value = var_export($row['value'], true);
        
        $fileContent .= "    $key => $value,\n";
    }

    $fileContent .= "];\n";

    $fileName = "lang_" . $langCode . ".php";
    
    if (file_put_contents('../lang/' . $fileName, $fileContent) !== false) {
        echo "Succesvol geëxporteerd naar: " . $fileName . "<br>";
    } else {
        echo "Fout bij het schrijven naar: " . $fileName . "<br>";
    }
}

exportLanguageFiles($pdo, 'nl');
exportLanguageFiles($pdo, 'fr');
exportLanguageFiles($pdo, 'en');
exportLanguageFiles($pdo, 'de');
exportLanguageFiles($pdo, 'all');
?>
