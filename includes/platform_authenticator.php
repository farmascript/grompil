<?php
/**
 * Platform administrator TOTP helpers.
 */

declare(strict_types=1);

use OTPHP\InternalClock;
use OTPHP\TOTP;

/**
 * Load the OTP implementation through Composer only when MFA is used.
 */
function grompil_totp_library_ready(): void
{
    $autoload = GROMPIL_ROOT . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('The Composer dependencies are not installed.');
    }
    require_once $autoload;
    if (!class_exists(TOTP::class) || !extension_loaded('sodium')) {
        throw new RuntimeException('The platform authenticator requirements are unavailable.');
    }
}

/**
 * Read the deployment-specific secret encryption key.
 */
function grompil_totp_encryption_key(): string
{
    $settings = grompil_deployment_settings(GROMPIL_ROOT);
    $encoded = $settings['platform_totp_encryption_key'] ?? '';
    $key = is_string($encoded) ? base64_decode($encoded, true) : false;
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('The platform authenticator encryption key is missing or invalid.');
    }
    return $key;
}

/** Encrypt a Base32 TOTP secret for storage. */
function grompil_totp_encrypt_secret(string $secret): string
{
    grompil_totp_library_ready();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($secret, $nonce, grompil_totp_encryption_key());
    return base64_encode($nonce . $ciphertext);
}

/** Decrypt a stored TOTP secret. */
function grompil_totp_decrypt_secret(string $encrypted): string
{
    grompil_totp_library_ready();
    $payload = base64_decode($encrypted, true);
    if (!is_string($payload) || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('The stored platform authenticator secret is invalid.');
    }
    $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $secret = sodium_crypto_secretbox_open($ciphertext, $nonce, grompil_totp_encryption_key());
    if (!is_string($secret)) {
        throw new RuntimeException('The platform authenticator secret could not be decrypted.');
    }
    return $secret;
}

/** Create a standards-based 6 digit, 30 second authenticator object. */
function grompil_totp(string $secret, string $label): TOTP
{
    grompil_totp_library_ready();
    return TOTP::createFromSecret($secret, new InternalClock())
        ->withPeriod(30)
        ->withDigits(6)
        ->withDigest('sha1')
        ->withIssuer('gromPil')
        ->withLabel($label);
}

/** Check the deployment-wide platform management IP rules. Empty means unrestricted. */
function platform_management_ip_allowed(?string $address = null, int $contextUserId = 0): bool
{
    $address ??= grompil_client_ip_address();
    if (filter_var($address, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    // The RLS context is elevated only for this narrowly scoped global-rule lookup.
    $contextCompanyId = $contextUserId > 0 ? (int) ($_SESSION['active_company_id'] ?? 0) : 0;
    set_database_tenant_context($contextCompanyId, $contextUserId, true);
    $result = @pg_query_params(
        db(),
        "SELECT count(*) AS rule_count,
                COALESCE(bool_or(network >>= $1::inet), FALSE) AS address_allowed
         FROM access_ip_allowlist
         WHERE company_id IS NULL AND scope = 'admin' AND is_active",
        [$address]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row !== false
        && ((int) $row['rule_count'] === 0 || $row['address_allowed'] === 't');
}

/** Return the active encrypted TOTP secret for a platform account, if configured. */
function grompil_platform_totp_ciphertext(int $userId): ?string
{
    set_database_tenant_context(0, $userId, true);
    $result = pg_query_params(
        db(),
        'SELECT secret_ciphertext FROM platform_totp_credentials WHERE user_id = $1 AND enabled_at IS NOT NULL',
        [$userId]
    );
    if (!$result) {
        throw new RuntimeException('The platform authenticator table is not available.');
    }
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ? (string) $row['secret_ciphertext'] : null;
}

/** Generate single-use recovery codes and persist only password hashes. */
function grompil_create_totp_recovery_codes(int $userId): array
{
    set_database_tenant_context(0, $userId, true);
    $codes = [];
    for ($index = 0; $index < 10; $index++) {
        $codes[] = strtoupper(bin2hex(random_bytes(8)));
    }
    $deleted = pg_query_params(db(), 'DELETE FROM platform_totp_recovery_codes WHERE user_id = $1', [$userId]);
    if (!$deleted) {
        throw new RuntimeException('Recovery codes could not be prepared.');
    }
    foreach ($codes as $code) {
        $inserted = pg_query_params(
            db(),
            'INSERT INTO platform_totp_recovery_codes (user_id, code_hash) VALUES ($1, $2)',
            [$userId, password_hash($code, PASSWORD_DEFAULT)]
        );
        if (!$inserted) {
            throw new RuntimeException('Recovery codes could not be stored.');
        }
    }
    return $codes;
}

/** Verify and consume one single-use recovery code. */
function grompil_consume_totp_recovery_code(int $userId, string $code): bool
{
    if (!preg_match('/^[A-F0-9]{16}$/i', $code)) {
        return false;
    }
    set_database_tenant_context(0, $userId, true);
    $result = pg_query_params(
        db(),
        'SELECT code_hash FROM platform_totp_recovery_codes WHERE user_id = $1 AND used_at IS NULL',
        [$userId]
    );
    while ($result && $row = pg_fetch_assoc($result)) {
        if (!password_verify(strtoupper($code), (string) $row['code_hash'])) {
            continue;
        }
        $consumed = pg_query_params(
            db(),
            'UPDATE platform_totp_recovery_codes SET used_at = now() WHERE user_id = $1 AND code_hash = $2 AND used_at IS NULL',
            [$userId, $row['code_hash']]
        );
        return $consumed && pg_affected_rows($consumed) === 1;
    }
    return false;
}
