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
 * @description: Tests password recovery against isolated PostgreSQL temporary tables without sending email.
 *
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/includes/database_config.php';
require_once dirname(__DIR__) . '/includes/templates.php';
require_once dirname(__DIR__) . '/includes/password_reset.php';

/**
 * Assert a condition in the password reset check.
 *
 * @param bool $condition The condition expected to be true.
 * @param string $message The assertion message used when the condition fails.
 * @return void
 */
function reset_test_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Test failed: ' . $message);
    }
}

$connectionString = grompil_database_connection_string();
$connection = $connectionString ? @pg_connect($connectionString) : false;
if (!$connection) {
    fwrite(STDERR, "Could not connect to PostgreSQL.\n");
    exit(1);
}
try {
    $readiness = pg_fetch_assoc(password_reset_query($connection,
        "SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'auth_version') AS migrated,
                current_user = pg_get_userbyid(c.relowner) AS owns_users
         FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
         WHERE n.nspname = 'public' AND c.relname = 'users'"
    ));
    // Temporary tables shadow public names only for this connection. No real
    // account or stored reset link is modified, and SMTP is never called.
    password_reset_query($connection, 'CREATE TEMP TABLE companies (id bigint PRIMARY KEY)');
    password_reset_query($connection,
        "CREATE TEMP TABLE users (
            id bigint PRIMARY KEY, company_id bigint, email text, password_hash varchar(255),
            is_active boolean DEFAULT TRUE, approval_status text DEFAULT 'approved',
            must_change_password boolean DEFAULT TRUE, updated_at timestamptz DEFAULT now()
        )"
    );
    $migration = file_get_contents(dirname(__DIR__) . '/migrations/20261021-183938_password_reset.sql');
    $migration = preg_replace('/GRANT SELECT, INSERT, UPDATE, DELETE ON password_reset_tokens, password_reset_attempts TO grompil_app;/', '', $migration);
    $migration = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMP TABLE IF NOT EXISTS', $migration);
    reset_test_expect((bool) @pg_query($connection, $migration), 'migration applies to temporary fixtures');
    reset_test_expect((bool) @pg_query($connection, $migration), 'migration can be repeated');
    password_reset_query($connection,
        "CREATE TEMP TABLE platform_system_settings (
            "key" text PRIMARY KEY, value_integer integer NOT NULL, updated_at timestamptz DEFAULT now()
        )"
    );
    password_reset_query($connection,
        "INSERT INTO platform_system_settings (\"key\", value_integer)
         VALUES ('password_reset_max_requests', 3), ('password_reset_window_minutes', 15)"
    );
    password_reset_query($connection, 'INSERT INTO companies VALUES (11), (12)');
    $original = password_hash('Original-Password-123', PASSWORD_DEFAULT);
    foreach ([[1, null, 'shared@example.test', true, 'approved'], [2, 11, 'shared@example.test', true, 'approved'],
              [3, 12, 'shared@example.test', true, 'approved'], [4, 11, 'inactive@example.test', false, 'approved'],
              [5, 11, 'pending@example.test', true, 'pending'], [6, 11, 'expiry@example.test', true, 'approved']] as $fixture) {
        password_reset_query($connection,
            'INSERT INTO users (id, company_id, email, is_active, approval_status, password_hash) VALUES ($1, $2, $3, $4, $5, $6)',
            [$fixture[0], $fixture[1], $fixture[2], $fixture[3] ? 'true' : 'false', $fixture[4], $original]
        );
    }
    $delivery = password_reset_issue($connection, 11, 'SHARED@example.test', '192.0.2.1');
    $hash = hash('sha256', $delivery['token']);
    reset_test_expect(password_reset_valid($connection, 11, $hash), 'issued company token works');
    reset_test_expect(!password_reset_valid($connection, 12, $hash) && !password_reset_valid($connection, null, $hash), 'token cannot cross company or platform scope');
    reset_test_expect(!password_reset_consume($connection, 12, $hash, 'Replacement-Password-456'), 'wrong company cannot reset the account');
    $stored = pg_fetch_assoc(password_reset_query($connection, 'SELECT token_hash FROM password_reset_tokens WHERE user_id = 2'));
    reset_test_expect($stored['token_hash'] === $hash && $stored['token_hash'] !== $delivery['token'], 'only a token hash is stored');
    $replacement = password_reset_issue($connection, 11, 'shared@example.test', '192.0.2.1');
    reset_test_expect(!password_reset_valid($connection, 11, $hash), 'a new request revokes the earlier link');
    $hash = hash('sha256', $replacement['token']);
    reset_test_expect(!password_reset_consume($connection, 11, $hash, 'short'), 'short password is refused');
    reset_test_expect(password_reset_consume($connection, 11, $hash, 'Replacement-Password-456'), 'valid token changes the password');
    reset_test_expect(!password_reset_consume($connection, 11, $hash, 'Another-Password-789'), 'token replay fails');
    $user = pg_fetch_assoc(password_reset_query($connection, 'SELECT * FROM users WHERE id = 2'));
    reset_test_expect(password_verify('Replacement-Password-456', $user['password_hash']) && $user['must_change_password'] === 'f' && (int) $user['auth_version'] === 1, 'password changes and existing sessions are revoked');
    $others = password_reset_query($connection, 'SELECT password_hash FROM users WHERE id IN (1, 3)');
    while ($other = pg_fetch_assoc($others)) {
        reset_test_expect(password_verify('Original-Password-123', $other['password_hash']), 'same email in other scopes is unchanged');
    }
    foreach ([null, 12] as $scope) {
        $other = password_reset_issue($connection, $scope, 'shared@example.test', '192.0.2.2');
        reset_test_expect(password_reset_valid($connection, $scope, hash('sha256', $other['token'])), 'global and second company accounts have their own valid tokens');
    }
    $expiry = password_reset_issue($connection, 11, 'expiry@example.test', '192.0.2.3');
    password_reset_query($connection, "UPDATE password_reset_tokens SET expires_at = now() - interval '1 second' WHERE user_id = 6");
    reset_test_expect(!password_reset_valid($connection, 11, hash('sha256', $expiry['token'])), 'expired link fails');
    $changed = password_reset_issue($connection, 11, 'expiry@example.test', '192.0.2.3');
    password_reset_query($connection, 'UPDATE users SET password_hash = $1 WHERE id = 6', [password_hash('Changed-Elsewhere-123', PASSWORD_DEFAULT)]);
    reset_test_expect(!password_reset_valid($connection, 11, hash('sha256', $changed['token'])), 'changing the password elsewhere revokes the link');
    reset_test_expect(password_reset_issue($connection, 11, 'inactive@example.test', '192.0.2.4') === null, 'inactive account gets no token');
    reset_test_expect(password_reset_issue($connection, 11, 'pending@example.test', '192.0.2.4') === null, 'pending account gets no token');
    for ($attempt = 0; $attempt < 4; $attempt++) {
        reset_test_expect(password_reset_issue($connection, 11, 'missing@example.test', '192.0.2.5') === null, 'unknown email gets no token');
    }
    $count = pg_fetch_assoc(password_reset_query($connection, "SELECT count(*) AS total FROM password_reset_attempts WHERE ip_address = '192.0.2.5'"));
    reset_test_expect((int) $count['total'] === 3, 'email request limit is enforced');
    password_reset_query($connection, "INSERT INTO password_reset_attempts (company_id, identifier_hash, ip_address) SELECT 0, repeat('a', 64), '192.0.2.6' FROM generate_series(1, 20)");
    reset_test_expect(password_reset_issue($connection, null, 'shared@example.test', '192.0.2.6') === null, 'IP request limit is enforced');

    $previousUrl = getenv('GROMPIL_PUBLIC_URL');
    putenv('GROMPIL_PUBLIC_URL');
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = 8181;
    $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/forgot-password.php';
    $_SERVER['SCRIPT_NAME'] = '/grompil/forgot-password.php';
    $_SERVER['HTTP_HOST'] = 'attacker.invalid';
    reset_test_expect(password_reset_base_url() === 'http://localhost:8181/grompil/', 'untrusted Host header does not enter the email link');
    $_SERVER['SERVER_NAME'] = 'attacker.invalid';
    $rejected = false;
    try { password_reset_base_url(); } catch (RuntimeException $exception) { $rejected = true; }
    reset_test_expect($rejected, 'production requires an explicit public URL');
    putenv('GROMPIL_PUBLIC_URL=https://example.test/grompil/');
    reset_test_expect(password_reset_base_url() === 'https://example.test/grompil/', 'configured HTTPS application URL works');
    $previousUrl === false ? putenv('GROMPIL_PUBLIC_URL') : putenv('GROMPIL_PUBLIC_URL=' . $previousUrl);
    fwrite(STDOUT, "Passed: scope isolation, token hashing, expiry, replacement, single use, password changes, session version, inactive/pending accounts, request limits, migration repeatability and trusted URLs. No email sent.\n");
    fwrite(STDOUT, 'Local database migration applied: ' . (($readiness['migrated'] ?? 'f') === 't' ? 'yes' : 'no') . "\n");
    fwrite(STDOUT, 'Configured database role owns users: ' . (($readiness['owns_users'] ?? 'f') === 't' ? 'yes' : 'no') . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    pg_close($connection);
}
