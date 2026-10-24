<?php
/**
 *
 * @date_cre: Fri 2026-10-23
 * @date_mut: Fri 2026-10-23
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Tests PostgreSQL from public_html using credentials stored one directory above.
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
        throw new RuntimeException('De PostgreSQL-extensie (pgsql) is niet ingeschakeld in PHP.');
    }
    $path = dirname(__DIR__) . '/credentials.php';
    if (!is_readable($path)) {
        throw new RuntimeException('credentials.php is niet leesbaar in de map boven public_html.');
    }
    $credentials = require $path;
    $database = is_array($credentials) ? ($credentials['database'] ?? []) : [];
    $parts = [];
    foreach (['host' => 'host', 'port' => 'port', 'name' => 'dbname', 'user' => 'user', 'password' => 'password'] as $key => $parameter) {
        if (!is_array($database) || !is_scalar($database[$key] ?? null) || (string) $database[$key] === '') {
            throw new RuntimeException('Database-instelling ontbreekt of is ongeldig: ' . $key . '.');
        }
        $value = (string) $database[$key];
        if ($key === 'password') $password = $value;
        $parts[] = $parameter . "='" . addcslashes($value, "\\'") . "'";
    }
    $connectionString = implode(' ', $parts) . ' connect_timeout=5';
    error_clear_last();
    $connection = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
    if ($connection === false) {
        throw new RuntimeException(error_get_last()['message'] ?? 'De databaseverbinding is mislukt.');
    }
    $result = @pg_query($connection, 'SELECT 1');
    if ($result === false) {
        throw new RuntimeException('Verbonden, maar de testquery is mislukt: ' . pg_last_error($connection));
    }
    pg_free_result($result);
    pg_close($connection);
    echo "OK: databaseverbinding geslaagd en SELECT 1 succesvol uitgevoerd.\n";
} catch (Throwable $error) {
    http_response_code(500);
    $message = $error->getMessage();
    if ($connectionString !== '') $message = str_replace($connectionString, '[verbindingsgegevens verborgen]', $message);
    if ($password !== '') $message = str_replace([$password, addcslashes($password, "\\'")], '[wachtwoord verborgen]', $message);
    echo 'FOUT: ' . $message . "\n";
    exit(1);
}
