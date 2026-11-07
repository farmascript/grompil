<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:02:56
 * @date_mut: Sat 2026-11-07 14:16:49
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Imports missing translation keys into the PostgreSQL table.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:translations_import
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/templates.php';
require_once dirname(__DIR__) . '/includes/database_config.php';
require_once dirname(__DIR__) . '/includes/database_queries.php';

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
$translationImportQuery = static function (string $operation, string $sql, array $params = []) use ($connection): PgSql\Result|false {
    try {
        return db_query_params($operation, $sql, $params, $connection);
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
};
$translationImportQuery('translations_import.set_platform_context', "SELECT set_config('grompil.platform_operator', 'true', false)");

if (!$translationImportQuery('translations_import.begin', 'BEGIN')) {
    fwrite(STDERR, "Could not start the import transaction.\n");
    exit(1);
}

$normalizeCodes = $translationImportQuery(
    'translations_import.normalize_codes',
    'INSERT INTO ft_substitutions (company_id, "key", "value", code, updated_at)
     SELECT company_id, "key", "value", upper(code), updated_at
     FROM ft_substitutions
     WHERE code <> upper(code)
     ON CONFLICT (company_id, "key", code) DO UPDATE
     SET "value" = EXCLUDED."value", updated_at = EXCLUDED.updated_at'
);
if (!$normalizeCodes || !$translationImportQuery('translations_import.delete_old_codes', 'DELETE FROM ft_substitutions WHERE code <> upper(code)')) {
    $translationImportQuery('translations_import.rollback_normalize', 'ROLLBACK');
    fwrite(STDERR, "Failed to normalize language codes.\n");
    exit(1);
}

foreach (['NL', 'FR', 'EN', 'DE'] as $language) {
    $arrSubst = fn_translationSeed($language);
    foreach ($arrSubst as $key => $value) {
        $result = $translationImportQuery(
            'translations_import.insert_missing',
            'INSERT INTO ft_substitutions (company_id, "key", "value", code)
             VALUES (0, $1, $2, $3)
             ON CONFLICT (company_id, "key", code) DO NOTHING',
            [(string) $key, (string) $value, $language]
        );
        if (!$result) {
            $translationImportQuery('translations_import.rollback_insert', 'ROLLBACK');
            fwrite(STDERR, "Import failed for key {$key} ({$language}).\n");
            exit(1);
        }
    }
    fwrite(STDOUT, sprintf("%s: added %d translations.\n", $language, count($arrSubst)));
}

if (!$translationImportQuery('translations_import.commit', 'COMMIT')) {
    $translationImportQuery('translations_import.rollback_commit', 'ROLLBACK');
    fwrite(STDERR, "Could not commit the import transaction.\n");
    exit(1);
}

