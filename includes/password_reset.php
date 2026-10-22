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

/** Returns mail delivery data, or null for an unknown account or a limited request. */
function password_reset_issue(PgSql\Connection $connection, ?int $companyId, string $email, string $ip): ?array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        return null;
    }
    $scope = $companyId ?? 0;
    $identifier = hash('sha256', $email);
    password_reset_query($connection, 'BEGIN');
    try {
        // Serialize both limits so concurrent requests cannot exceed them.
        password_reset_query($connection, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['reset-ip:' . $ip]);
        password_reset_query($connection, 'SELECT pg_advisory_xact_lock(hashtext($1))', ['reset-email:' . $scope . ':' . $identifier]);
        $limits = pg_fetch_assoc(password_reset_query($connection,
            "SELECT
                count(*) FILTER (WHERE ip_address = $1 AND attempted_at > now() - interval '1 hour') AS ip_total,
                count(*) FILTER (WHERE company_id = $2 AND identifier_hash = $3 AND attempted_at > now() - interval '15 minutes') AS email_total
             FROM password_reset_attempts
             WHERE attempted_at > now() - interval '1 hour'
               AND (ip_address = $1 OR (company_id = $2 AND identifier_hash = $3))",
            [$ip, $scope, $identifier]
        ));
        if ((int) $limits['ip_total'] >= 20 || (int) $limits['email_total'] >= 3) {
            password_reset_query($connection, 'COMMIT');
            return null;
        }
        password_reset_query($connection,
            'INSERT INTO password_reset_attempts (company_id, identifier_hash, ip_address) VALUES ($1, $2, $3)',
            [$scope, $identifier, $ip]
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
