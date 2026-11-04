<?php
/**
 *
 * @date_cre: Wed 2026-11-04
 * @date_mut: Wed 2026-11-04
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Runs parameterized PostgreSQL queries with consistent failure handling.
 *
 */

declare(strict_types=1);

/**
 * Represents a failed database query after its technical details were logged.
 */
class GrompilDatabaseQueryException extends RuntimeException
{
}

/**
 * Execute a parameterized query using the application's shared PostgreSQL connection.
 *
 * @param string $operation A stable identifier for the operation, used in server logs.
 * @param string $sql The parameterized SQL statement.
 * @param array<int, mixed> $params Positional query parameters.
 * @return PgSql\Result
 * @throws GrompilDatabaseQueryException
 */
function db_query_params(string $operation, string $sql, array $params = []): PgSql\Result
{
    if (!preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $operation)) {
        throw new InvalidArgumentException('Invalid database operation identifier.');
    }

    $connection = db();

    try {
        $result = @pg_query_params($connection, $sql, $params);
    } catch (Throwable $error) {
        error_log(sprintf(
            'Database query failed [%s] (PHP error: %s).',
            $operation,
            get_class($error)
        ));
        throw new GrompilDatabaseQueryException(
            'Database query failed: ' . $operation,
            0,
            $error
        );
    }

    if (!$result) {
        error_log(sprintf(
            'Database query failed [%s] (PostgreSQL error; SQLSTATE unavailable).',
            $operation
        ));
        throw new GrompilDatabaseQueryException('Database query failed: ' . $operation);
    }

    $status = pg_result_status($result);
    if (!in_array($status, [PGSQL_TUPLES_OK, PGSQL_COMMAND_OK], true)) {
        $sqlState = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE) ?: 'unknown';
        pg_free_result($result);
        error_log(sprintf('Database query failed [%s] (SQLSTATE %s).', $operation, $sqlState));
        throw new GrompilDatabaseQueryException('Database query failed: ' . $operation);
    }

    return $result;
}
