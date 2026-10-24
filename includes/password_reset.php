<?php
/**
 *
 * @date_cre: Wed 2026-10-21 17:30:00
 * @date_mut: Wed 2026-10-21 18:39:38
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Issues and consumes scoped, expiring, single-use password reset tokens.
 *
 */

declare(strict_types=1);

/** Runs a reset query without exposing SQL parameters in the application log. */
function password_reset_query(PgSql\Connection $connection, string $sql, array $parameters = []): PgSql\Result
{
    $result = @pg_query_params($connection, $sql, $parameters);
    if (!$result) {
        throw new RuntimeException('Password reset database operation failed.');
    }
    return $result;
}

/** Returns the platform-configured request limit and rolling window. */
function password_reset_request_limits(PgSql\Connection $connection): array
{
    $result = password_reset_query($connection,
        'SELECT "key", value_integer FROM platform_system_settings
         WHERE "key" IN (\'password_reset_max_requests\', \'password_reset_window_minutes\')'
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

/** Returns current email/IP request totals and the wait until an attempt expires. */
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
         FROM password_reset_attempts
         WHERE (ip_address = $1 AND attempted_at > now() - interval '1 hour')
            OR (company_id = $2 AND identifier_hash = $3 AND attempted_at > now() - make_interval(mins => $4))",
        [$ip, $scope, $identifier, $windowMinutes]
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

/** Returns mail delivery data, or null for an unknown account or a limited request. */
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
    password_reset_query($connection, 'BEGIN');
    try {
        // Serialize both limits so concurrent requests cannot exceed them.
        password_reset_query($connection, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['reset-ip:' . $ip]);
        password_reset_query($connection, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['reset-email:' . $scope . ':' . $identifier]);
        $requestStatus = password_reset_attempt_status(
            $connection,
            $scope,
            $identifier,
            $ip,
            $limits['window_minutes'],
            $limits['max_requests']
        );
        if ($requestStatus['remaining'] === 0) {
            password_reset_query($connection, 'COMMIT');
            return null;
        }
        password_reset_query($connection,
            'INSERT INTO password_reset_attempts (company_id, identifier_hash, ip_address) VALUES ($1, $2, $3)',
            [$scope, $identifier, $ip]
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
            "SELECT id, email, password_hash FROM users
             WHERE company_id IS NOT DISTINCT FROM $1::bigint AND lower(email) = $2
               AND is_active AND approval_status = 'approved'
             FOR UPDATE",
            [$companyId, $email]
        ));
        $delivery = null;
        if ($user) {
            $token = bin2hex(random_bytes(32));
            password_reset_query($connection, 'DELETE FROM password_reset_tokens WHERE user_id = $1', [$user['id']]);
            password_reset_query($connection,
                "INSERT INTO password_reset_tokens (token_hash, user_id, company_id, password_hash_at_issue, expires_at)
                 VALUES ($1, $2, $3, $4, now() + interval '30 minutes')",
                [hash('sha256', $token), $user['id'], $companyId, $user['password_hash']]
            );
            $delivery = ['email' => $user['email'], 'token' => $token];
        }
        password_reset_query($connection, "DELETE FROM password_reset_tokens WHERE expires_at <= now()");
        password_reset_query($connection, "DELETE FROM password_reset_attempts WHERE attempted_at < now() - interval '1 day'");
        password_reset_query($connection, 'COMMIT');
        return $delivery;
    } catch (Throwable $exception) {
        @pg_query($connection, 'ROLLBACK');
        throw $exception;
    }
}

/** Checks the token's scope, expiry and account state without consuming it. */
function password_reset_valid(PgSql\Connection $connection, ?int $companyId, string $tokenHash): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
        return false;
    }
    $result = password_reset_query($connection,
        "SELECT 1 FROM password_reset_tokens AS t JOIN users AS u ON u.id = t.user_id
         WHERE t.token_hash = $1 AND t.company_id IS NOT DISTINCT FROM $2::bigint
           AND u.company_id IS NOT DISTINCT FROM $2::bigint
           AND t.expires_at > now() AND t.password_hash_at_issue = u.password_hash
           AND u.is_active AND u.approval_status = 'approved'",
        [$tokenHash, $companyId]
    );
    return pg_num_rows($result) === 1;
}

/** Atomically changes the password and revokes tokens and existing login sessions. */
function password_reset_consume(PgSql\Connection $connection, ?int $companyId, string $tokenHash, string $password): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash) || strlen($password) < 12 || strlen($password) > 4096) {
        return false;
    }
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    password_reset_query($connection, 'BEGIN');
    try {
        // Lock the account first, as issuance does, to avoid lock-order inversions.
        $user = pg_fetch_assoc(password_reset_query($connection,
            'SELECT u.id FROM users AS u JOIN password_reset_tokens AS t ON t.user_id = u.id
             WHERE t.token_hash = $1 AND u.company_id IS NOT DISTINCT FROM $2::bigint
             FOR UPDATE OF u', [$tokenHash, $companyId]
        ));
        if (!$user || !password_reset_valid($connection, $companyId, $tokenHash)) {
            password_reset_query($connection, 'ROLLBACK');
            return false;
        }
        password_reset_query($connection,
            'UPDATE users SET password_hash = $1, must_change_password = FALSE,
                auth_version = auth_version + 1, updated_at = now() WHERE id = $2',
            [$newHash, $user['id']]
        );
        password_reset_query($connection, 'DELETE FROM password_reset_tokens WHERE user_id = $1', [$user['id']]);
        password_reset_query($connection, 'COMMIT');
        return true;
    } catch (Throwable $exception) {
        @pg_query($connection, 'ROLLBACK');
        throw $exception;
    }
}

/** Uses an explicitly configured public URL; only loopback has an automatic fallback. */
function password_reset_base_url(): string
{
    $configured = trim((string) getenv('GROMPIL_PUBLIC_URL'));
    if ($configured !== '') {
        $parts = parse_url($configured);
        if (!$parts || !isset($parts['host'], $parts['scheme']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || !filter_var($configured, FILTER_VALIDATE_URL)
            || ($parts['scheme'] !== 'https' && !($parts['scheme'] === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true)))) {
            throw new RuntimeException('GROMPIL_PUBLIC_URL must be an HTTPS application URL, or an HTTP loopback URL.');
        }
        return rtrim($configured, '/') . '/';
    }
    $host = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    if (!in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || $port < 1 || $port > 65535) {
        throw new RuntimeException('Set GROMPIL_PUBLIC_URL before sending password reset emails.');
    }
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $host === '::1' ? '[::1]' : $host;
    return $scheme . '://' . $host . (in_array([$scheme, $port], [['http', 80], ['https', 443]], true) ? '' : ':' . $port) . fn_appBaseHref();
}
