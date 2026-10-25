<?php
/**
 *
 * @date_cre: Fri 2026-10-23
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Tests PostgreSQL using the credentials selected for this installation.
 *
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
$password = '';
$connectionString = '';

try {
    if (!function_exists('pg_connect')) {
        throw new RuntimeException('The PostgreSQL extension (pgsql) is not enabled in PHP.');
    }
    if (!defined('GROMPIL_ROOT')) {
        define('GROMPIL_ROOT', dirname(__DIR__));
    }
    require_once dirname(__DIR__) . '/includes/database_config.php';
    $database = grompil_database_config();
    $password = (string) ($database['password'] ?? '');
    $connectionString = grompil_database_connection_string();
    if ($connectionString === null) {
        throw new RuntimeException('The database password is missing from the selected credentials file.');
    }
    error_clear_last();
    $connection = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
    if ($connection === false) {
        throw new RuntimeException(error_get_last()['message'] ?? 'The database connection failed.');
    }
    $result = @pg_query($connection, 'SELECT 1');
    if ($result === false) {
        throw new RuntimeException('Connected, but the test query failed: ' . pg_last_error($connection));
    }
    pg_free_result($result);
    pg_close($connection);
    echo "OK: database connection succeeded and SELECT 1 completed successfully.\n";
} catch (Throwable $error) {
    http_response_code(500);
    $message = $error->getMessage();
    if ($connectionString !== '') $message = str_replace($connectionString, '[connection details hidden]', $message);
    if ($password !== '') $message = str_replace([$password, addcslashes($password, "\\'")], '[password hidden]', $message);
    echo 'ERROR: ' . $message . "\n";
    exit(1);
}
