<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:02:56
 * @date_mut: Wed 2026-10-21 09:10:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Imports missing translation keys into the PostgreSQL table.
 *
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/templates.php';
require_once dirname(__DIR__) . '/includes/database_config.php';

$connectionString = grompil_database_connection_string();
if ($connectionString === null) {
    fwrite(STDERR, "Database password is missing from credentials.php or the environment variables.\n");
    exit(1);
}
$connection = @pg_connect($connectionString);
if (!$connection) {
    fwrite(STDERR, "Could not connect to PostgreSQL.\n");
    exit(1);
}
pg_query($connection, "SELECT set_config('grompil.platform_operator', 'true', false)");

if (!pg_query($connection, 'BEGIN')) {
    fwrite(STDERR, "Could not start the import transaction.\n");
    exit(1);
}

$normalizeCodes = pg_query(
    $connection,
    'INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
     SELECT company_id, "key", "value", upper(code), updated_at
     FROM substitutions
     WHERE code <> upper(code)
     ON CONFLICT (company_id, "key", code) DO UPDATE
     SET "value" = EXCLUDED."value", updated_at = EXCLUDED.updated_at'
);
if (!$normalizeCodes || !pg_query($connection, 'DELETE FROM substitutions WHERE code <> upper(code)')) {
    pg_query($connection, 'ROLLBACK');
    fwrite(STDERR, "Failed to normalize language codes.\n");
    exit(1);
}

foreach (['NL', 'FR', 'EN', 'DE'] as $language) {
    $arrSubst = fn_translationSeed($language);
    foreach ($arrSubst as $key => $value) {
        $result = pg_query_params(
            $connection,
            'INSERT INTO substitutions (company_id, "key", "value", code)
             VALUES (0, $1, $2, $3)
             ON CONFLICT (company_id, "key", code) DO NOTHING',
            [(string) $key, (string) $value, $language]
        );
        if (!$result) {
            pg_query($connection, 'ROLLBACK');
            fwrite(STDERR, "Import failed for key {$key} ({$language}).\n");
            exit(1);
        }
    }
    fwrite(STDOUT, sprintf("%s: added %d translations.\n", $language, count($arrSubst)));
}

if (!pg_query($connection, 'COMMIT')) {
    pg_query($connection, 'ROLLBACK');
    fwrite(STDERR, "Could not commit the import transaction.\n");
    exit(1);
}

