<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:02:45
 * @date_mut: Wed 2026-11-04
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Exports translations from PostgreSQL to PHP language files.
 *
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/database_config.php';
require_once dirname(__DIR__) . '/includes/database_queries.php';

$languages = array_slice($argv, 1) ?: ['NL', 'FR', 'EN', 'DE'];
$connectionString = grompil_database_connection_string();
if ($connectionString === null) {
    fwrite(STDERR, "The database password is missing from the selected credentials file.\n");
    exit(1);
}
$connection = @pg_connect($connectionString);

if (!$connection) {
    fwrite(STDERR, "Could not connect to PostgreSQL.\n");
    exit(1);
}

foreach ($languages as $language) {
    $language = strtoupper($language);
    if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
        fwrite(STDERR, "Invalid language code: {$language}\n");
        exit(1);
    }
    try {
        $result = db_query_params(
            'translations_export.read_language',
            'SELECT "key", "value" FROM substitutions WHERE company_id = 0 AND code = $1 ORDER BY "key"',
            [$language],
            $connection
        );
    } catch (GrompilDatabaseQueryException) {
        fwrite(STDERR, "Failed to fetch translations for {$language}.\n");
        exit(1);
    }
    $arrSubst = [];
    while ($row = pg_fetch_assoc($result)) {
        $arrSubst[$row['key']] = $row['value'];
    }
    $content = "<?php\n\n\$arrSubst = " . var_export($arrSubst, true) . ";\n";
    $languageDirectory = getenv('GROMPIL_LANGUAGE_DIR') ?: dirname(__DIR__) . '/src/lang';
    $target = rtrim($languageDirectory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'lang_' . strtolower($language) . '.php';
    if (file_put_contents($target, $content, LOCK_EX) === false) {
        fwrite(STDERR, "Failed to write: {$target}\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf("%s: exported %d translations.\n", $language, count($arrSubst)));
}

