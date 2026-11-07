<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:30:00
 * @date_mut: Sat 2026-11-07 14:16:49
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Issues and consumes scoped, expiring, single-use password reset tokens.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:password_reset
 */

declare(strict_types=1);

require_once __DIR__ . '/credentials_config.php';
require_once __DIR__ . '/database_queries.php';

/**
 *  Runs a reset query without exposing SQL parameters in the application log.
 *
 * @param PgSql\Connection $connection The PostgreSQL connection.
 * @param string $sql The parameterized SQL statement.
 * @param array $parameters The values to bind to the SQL placeholders.
 * @param string $operation Stable operation identifier for server-side diagnostics.
 * @return PgSql\Result
 */
function password_reset_query(
    PgSql\Connection $connection,
    string $sql,
    array $parameters = [],
    string $operation = 'password_reset.query'
): PgSql\Result
{
    try {
        return db_query_params($operation, $sql, $parameters, $connection);
    } catch (GrompilDatabaseQueryException $exception) {
        throw new RuntimeException('Password reset database operation failed.', 0, $exception);
    }
}

/** Preserve the old best-effort rollback behavior while logging failures centrally. */
function password_reset_rollback(PgSql\Connection $connection, string $operation): void
{
    try {
        db_query_params($operation, 'ROLLBACK', [], $connection);
    } catch (GrompilDatabaseQueryException) {
        // The original operation is already failing; rollback remains best-effort.
    }
}

/**
 *  Returns the platform-configured request limit and rolling window.
 *
 * @param PgSql\Connection $connection The PostgreSQL connection.
 * @return array
 */
function password_reset_request_limits(PgSql\Connection $connection): array
{
    $result = password_reset_query($connection,
        'SELECT "key", value_integer FROM ft_platform_system_settings
         WHERE "key" IN (\'password_reset_max_requests\', \'password_reset_window_minutes\')',
        [],
        'password_reset.request_limits'
    );
    $limits = ['max_requests' => 3, 'window_minutes' => 15];
    while ($row = pg_fetch_assoc($result)) {
        if ($row['key'] === 'password_reset_max_requests') {
            $limits['max_requests'] = (int) $row['value_integer'];
        } elseif ($row['key'] === 'password_reset_window_minutes') {
            $limits['window_minutes'] = (int) $row['value_integer'];
        }
    }
    return $limits;
}

/**
 *  Returns current email/IP request totals and the wait until an attempt expires.
 *
 * @param PgSql\Connection $connection The PostgreSQL connection.
 * @param int $scope The reset-request company scope.
 * @param string $identifier The hashed account identifier.
 * @param string $ip The client IP address.
 * @param int $windowMinutes The rate-limit window in minutes.
 * @param int $maxRequests The maximum requests allowed in the window.
 * @return array
 */
function password_reset_attempt_status(
    PgSql\Connection $connection,
    int $scope,
    string $identifier,
    string $ip,
    int $windowMinutes,
    int $maxRequests
): array {
    $row = pg_fetch_assoc(password_reset_query($connection,
        "SELECT
            count(*) FILTER (WHERE ip_address = $1) AS ip_total,
            count(*) FILTER (WHERE company_id = $2 AND identifier_hash = $3) AS email_total,
            COALESCE(CEIL(EXTRACT(EPOCH FROM (MIN(attempted_at) FILTER (WHERE ip_address = $1) + interval '1 hour' - now()))), 0)::integer AS ip_wait,
            COALESCE(CEIL(EXTRACT(EPOCH FROM (MIN(attempted_at) FILTER (WHERE company_id = $2 AND identifier_hash = $3) + make_interval(mins => $4) - now()))), 0)::integer AS email_wait
         FROM st_password_reset_attempts
         WHERE (ip_address = $1 AND attempted_at > now() - interval '1 hour')
            OR (company_id = $2 AND identifier_hash = $3 AND attempted_at > now() - make_interval(mins => $4))",
        [$ip, $scope, $identifier, $windowMinutes],
        'password_reset.attempt_status'
    ));
    $ipTotal = (int) ($row['ip_total'] ?? 0);
    $emailTotal = (int) ($row['email_total'] ?? 0);
    $limited = $ipTotal >= 20 || $emailTotal >= $maxRequests;
    return [
        'remaining' => $limited ? 0 : max(0, $maxRequests - $emailTotal),
        'retry_after_seconds' => $limited
            ? max(1, ($ipTotal >= 20 ? (int) $row['ip_wait'] : 0), ($emailTotal >= $maxRequests ? (int) $row['email_wait'] : 0))
            : 0,
        'max_requests' => $maxRequests,
        'window_minutes' => $windowMinutes,
    ];
}

/**
 *  Returns mail delivery data, or null for an unknown account or a limited request.
 *
 * @param PgSql\Connection $connection The PostgreSQL connection.
 * @param ?int $companyId The company database ID.
 * @param string $email The account email address.
 * @param string $ip The client IP address.
 * @param ?array $requestStatus The resulting request-limit status, passed by reference.
 * @return ?array
 */
function password_reset_issue(
    PgSql\Connection $connection,
    ?int $companyId,
    string $email,
    string $ip,
    ?array &$requestStatus = null
): ?array
{
    $requestStatus = ['remaining' => 0, 'retry_after_seconds' => 0, 'max_requests' => 3, 'window_minutes' => 15];
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        return null;
    }
    $scope = $companyId ?? 0;
    $identifier = hash('sha256', $email);
    $limits = password_reset_request_limits($connection);
    $requestStatus['max_requests'] = $limits['max_requests'];
    $requestStatus['window_minutes'] = $limits['window_minutes'];
    password_reset_query($connection, 'BEGIN', [], 'password_reset.issue.begin');
    try {
        // Serialize both limits so concurrent requests cannot exceed them.
        password_reset_query($connection, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['reset-ip:' . $ip], 'password_reset.issue.lock_ip');
        password_reset_query($connection, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['reset-email:' . $scope . ':' . $identifier], 'password_reset.issue.lock_email');
        $requestStatus = password_reset_attempt_status(
            $connection,
            $scope,
            $identifier,
            $ip,
            $limits['window_minutes'],
            $limits['max_requests']
        );
        if ($requestStatus['remaining'] === 0) {
            password_reset_query($connection, 'COMMIT', [], 'password_reset.issue.commit_limited');
            return null;
        }
        password_reset_query($connection,
            'INSERT INTO st_password_reset_attempts (company_id, identifier_hash, ip_address) VALUES ($1, $2, $3)',
            [$scope, $identifier, $ip],
            'password_reset.issue.record_attempt'
        );
        $requestStatus = password_reset_attempt_status(
            $connection,
            $scope,
            $identifier,
            $ip,
            $limits['window_minutes'],
            $limits['max_requests']
        );
        $user = pg_fetch_assoc(password_reset_query($connection,
            "SELECT user_code, email, password_hash FROM bt_users
             WHERE company_id IS NOT DISTINCT FROM $1::bigint AND lower(email) = $2
               AND is_active AND approval_status = 'approved'
             FOR UPDATE",
            [$companyId, $email],
            'password_reset.issue.find_account'
        ));
        $delivery = null;
        if ($user) {
            $token = bin2hex(random_bytes(32));
            password_reset_query($connection, 'DELETE FROM st_password_reset_tokens WHERE user_code = $1', [$user['user_code']], 'password_reset.issue.revoke_old_token');
            password_reset_query($connection,
                "INSERT INTO st_password_reset_tokens (token_hash, user_code, company_id, password_hash_at_issue, expires_at)
                 VALUES ($1, $2, $3, $4, now() + interval '30 minutes')",
                [hash('sha256', $token), $user['user_code'], $companyId, $user['password_hash']],
                'password_reset.issue.create_token'
            );
            $delivery = ['email' => $user['email'], 'token' => $token];
        }
        password_reset_query($connection, "DELETE FROM st_password_reset_tokens WHERE expires_at <= now()", [], 'password_reset.issue.cleanup_tokens');
        password_reset_query($connection, "DELETE FROM st_password_reset_attempts WHERE attempted_at < now() - interval '1 day'", [], 'password_reset.issue.cleanup_attempts');
        password_reset_query($connection, 'COMMIT', [], 'password_reset.issue.commit');
        return $delivery;
    } catch (Throwable $exception) {
        password_reset_rollback($connection, 'password_reset.issue.rollback');
        throw $exception;
    }
}

/**
 *  Checks the token's scope, expiry and account state without consuming it.
 *
 * @param PgSql\Connection $connection The PostgreSQL connection.
 * @param ?int $companyId The company database ID.
 * @param string $tokenHash The SHA-256 hash of the reset token.
 * @return bool
 */
function password_reset_valid(PgSql\Connection $connection, ?int $companyId, string $tokenHash): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
        return false;
    }
    $result = password_reset_query($connection,
        "SELECT 1 FROM st_password_reset_tokens AS t JOIN bt_users AS u ON u.user_code = t.user_code
         WHERE t.token_hash = $1 AND t.company_id IS NOT DISTINCT FROM $2::bigint
           AND u.company_id IS NOT DISTINCT FROM $2::bigint
           AND t.expires_at > now() AND t.password_hash_at_issue = u.password_hash
           AND u.is_active AND u.approval_status = 'approved'",
        [$tokenHash, $companyId],
        'password_reset.validate_token'
    );
    return pg_num_rows($result) === 1;
}

/**
 *  Atomically changes the password and revokes tokens and existing login sessions.
 *
 * @param PgSql\Connection $connection The PostgreSQL connection.
 * @param ?int $companyId The company database ID.
 * @param string $tokenHash The SHA-256 hash of the reset token.
 * @param string $password The new account password.
 * @return bool
 */
function password_reset_consume(PgSql\Connection $connection, ?int $companyId, string $tokenHash, string $password): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash) || strlen($password) < 12 || strlen($password) > 4096) {
        return false;
    }
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    password_reset_query($connection, 'BEGIN', [], 'password_reset.consume.begin');
    try {
        // Lock the account first, as issuance does, to avoid lock-order inversions.
        $user = pg_fetch_assoc(password_reset_query($connection,
            'SELECT u.user_code FROM bt_users AS u JOIN st_password_reset_tokens AS t ON t.user_code = u.user_code
             WHERE t.token_hash = $1 AND u.company_id IS NOT DISTINCT FROM $2::bigint
             FOR UPDATE OF u', [$tokenHash, $companyId],
            'password_reset.consume.lock_account'
        ));
        if (!$user || !password_reset_valid($connection, $companyId, $tokenHash)) {
            password_reset_query($connection, 'ROLLBACK', [], 'password_reset.consume.invalid_rollback');
            return false;
        }
        password_reset_query($connection,
            'UPDATE bt_users SET password_hash = $1, must_change_password = FALSE,
                auth_version = auth_version + 1, updated_at = now() WHERE user_code = $2',
            [$newHash, $user['user_code']],
            'password_reset.consume.change_password'
        );
        password_reset_query($connection, 'DELETE FROM st_password_reset_tokens WHERE user_code = $1', [$user['user_code']], 'password_reset.consume.revoke_tokens');
        password_reset_query($connection, 'COMMIT', [], 'password_reset.consume.commit');
        return true;
    } catch (Throwable $exception) {
        password_reset_rollback($connection, 'password_reset.consume.rollback');
        throw $exception;
    }
}

/**
 * Uses a configured public URL; only loopback has an automatic fallback.
 *
 * @return string
 */
function password_reset_base_url(): string
{
    $configured = trim((string) getenv('GROMPIL_PUBLIC_URL'));
    if ($configured === '') {
        $projectRoot = defined('GROMPIL_ROOT') ? GROMPIL_ROOT : dirname(__DIR__);
        $settings = grompil_deployment_settings($projectRoot);
        $publicUrl = $settings['public_url'] ?? '';
        if (!is_string($publicUrl)) {
            throw new RuntimeException('The public_url deployment setting must be a string.');
        }
        $configured = trim($publicUrl);
    }
    if ($configured !== '') {
        $parts = parse_url($configured);
        if (!$parts || !isset($parts['host'], $parts['scheme']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || !filter_var($configured, FILTER_VALIDATE_URL)
            || ($parts['scheme'] !== 'https' && !($parts['scheme'] === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true)))) {
            throw new RuntimeException('The public application URL must be HTTPS, or HTTP on loopback.');
        }
        return rtrim($configured, '/') . '/';
    }
    $host = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if (!in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || $port < 1 || $port > 65535) {
        throw new RuntimeException('Set public_url in settings_local.php before sending password reset emails.');
    }
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $host === '::1' ? '[::1]' : $host;
    return $scheme . '://' . $host . (in_array([$scheme, $port], [['http', 80], ['https', 443]], true) ? '' : ':' . $port) . fn_appBaseHref();
}
