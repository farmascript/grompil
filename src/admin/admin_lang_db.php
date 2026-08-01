<?

/**
 *
 * @file        admin_lang_db.php
 * @author      lm
 * @dateCreated Sat 2026-08-01 12:20:34
 * @dateLastMod Sat 2026-08-01 12:57:07
 *
 * @copyright   Copyright 1981-present - Lieven Maus <info@grompil.com>
 *
 * @wiki
 *
**/

// 1. Verbinding maken met de PostgreSQL database
$host    = 'localhost';
$port    = '5432';
$db      = 'grompil';
$user    = 'postgres';
$pass    = 'apo110';

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
 * Functie om een PHP-array te importeren in PostgreSQL
 */
function importLanguageArray($pdo, $filePath, $langCode, $arrayVariableName) {
    // Controleer of het bestand bestaat
    if (!file_exists($filePath)) {
        echo "Bestand niet gevonden: $filePath<br>";
        return;
    }

    // Laad het bestand in (dit maakt de array beschikbaar)
    include $filePath;

    // Haal de array dynamisch op via de variabelenaam (bijv. $arr_nl of $arr1)
    $translations = $$arrayVariableName;

    if (!is_array($translations)) {
        echo "Variabele \$$arrayVariableName is geen geldige array in $filePath<br>";
        return;
    }

    // Bereid de SQL UPSERT-query voor PostgreSQL voor
    $sql = "INSERT INTO translations (translation_key, language_code, translation_value) 
            VALUES (:key, :lang, :value) 
            ON CONFLICT (translation_key, language_code) 
            DO UPDATE SET translation_value = EXCLUDED.translation_value";
            
    $stmt = $pdo->prepare($sql);

    $count = 0;
    
    // Loop door de array en voer de query uit
    foreach ($translations as $key => $value) {
        $stmt->execute([
            'key'   => $key,
            'lang'  => $langCode,
            'value' => $value
        ]);
        $count++;
    }

    echo "Succesvol $count regels geïmporteerd/bijgewerkt voor taal: <strong>$langCode</strong> uit $filePath.<br>";
}

// 2. Voer de import uit voor jouw bestanden
// Pas de bestandsnaam en de exacte naam van de array-variabele aan naar jouw situatie:
importLanguageArray($pdo, '../lang/lang_nl.php', 'nl', 'arrLang_nl');
importLanguageArray($pdo, '../lang/lang_fr.php', 'fr', 'arrLang_fr');
importLanguageArray($pdo, '../lang/lang_de.php', 'de', 'arrLang_de');
importLanguageArray($pdo, '../lang/lang_en.php', 'en', 'arrLang_en');
importLanguageArray($pdo, '../lang/lang_all.php', 'all', 'arrLang_all');

?>
