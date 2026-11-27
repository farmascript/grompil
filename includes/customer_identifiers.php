<?php
/**
 * @date_cre: Fri 2026-11-27 13:19:15
 * @date_mut: Fri 2026-11-27 13:19:15
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Stores and retrieves encrypted customer identifiers.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:customer_identifiers
 */

declare(strict_types=1);

/**
 * Represents a customer identifier storage or validation error.
 */
class GrompilCustomerIdentifierException extends RuntimeException
{
}

/**
 * Represents an identifier already assigned to another customer in the company.
 */
class GrompilDuplicateCustomerIdentifierException extends GrompilCustomerIdentifierException
{
}

/**
 * Validate a Belgian National Register number and derive its date of birth.
 *
 * @param string $number The entered number, optionally formatted with dots or hyphens.
 * @return ?array{number: string, date_of_birth: string} The normalized number and ISO birth date, or null if invalid.
 */
function grompil_parse_national_register_number(string $number): ?array
{
    if (preg_match('/^[0-9.\s-]+$/u', $number) !== 1) {
        return null;
    }
    $digits = preg_replace('/[.\s-]+/u', '', $number);
    if (!is_string($digits) || preg_match('/^[0-9]{11}$/D', $digits) !== 1) {
        return null;
    }

    $firstNine = substr($digits, 0, 9);
    $checkDigits = (int) substr($digits, 9, 2);
    $birthYearShort = (int) substr($digits, 0, 2);
    $birthMonth = (int) substr($digits, 2, 2);
    $birthDay = (int) substr($digits, 4, 2);
    if ($checkDigits === 97 - ((int) $firstNine % 97)) {
        $birthYear = 1900 + $birthYearShort;
    } elseif ($checkDigits === 97 - ((int) ('2' . $firstNine) % 97)) {
        $birthYear = 2000 + $birthYearShort;
    } else {
        return null;
    }

    if (!checkdate($birthMonth, $birthDay, $birthYear) || $birthYear > (int) date('Y')) {
        return null;
    }

    return [
        'number' => $digits,
        'date_of_birth' => sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay),
    ];
}

/**
 * Read the deployment-specific encryption key for customer identifiers.
 *
 * @return string The validated secretbox key.
 */
function grompil_customer_identifier_encryption_key(): string
{
    if (!extension_loaded('sodium')) {
        throw new GrompilCustomerIdentifierException('The sodium extension is unavailable.');
    }
    $settings = grompil_deployment_settings(GROMPIL_ROOT);
    $encoded = $settings['customer_identifier_encryption_key'] ?? '';
    $key = is_string($encoded) ? base64_decode($encoded, true) : false;
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new GrompilCustomerIdentifierException('Customer identifier encryption is not configured.');
    }
    return $key;
}

/**
 * Encrypt a customer identifier for database storage.
 *
 * @param string $value The normalized identifier.
 * @return string The nonce and ciphertext encoded as Base64.
 */
function grompil_customer_identifier_encrypt(string $value): string
{
    $key = grompil_customer_identifier_encryption_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, $key));
}

/**
 * Decrypt a customer identifier from database storage.
 *
 * @param string $encrypted The encoded nonce and ciphertext.
 * @return string The decrypted identifier.
 */
function grompil_customer_identifier_decrypt(string $encrypted): string
{
    $payload = base64_decode($encrypted, true);
    if (!is_string($payload) || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new GrompilCustomerIdentifierException('Stored customer identifier data is invalid.');
    }
    $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $value = sodium_crypto_secretbox_open($ciphertext, $nonce, grompil_customer_identifier_encryption_key());
    if (!is_string($value)) {
        throw new GrompilCustomerIdentifierException('Stored customer identifier could not be decrypted.');
    }
    return $value;
}

/**
 * Create a keyed lookup fingerprint without storing the plaintext identifier.
 *
 * @param string $value The normalized identifier.
 * @return string A hexadecimal HMAC-SHA-256 fingerprint.
 */
function grompil_customer_identifier_fingerprint(string $value): string
{
    $key = grompil_customer_identifier_encryption_key();
    $lookupKey = hash_hmac('sha256', 'grompil-customer-identifier-lookup-v1', $key, true);
    return hash_hmac('sha256', $value, $lookupKey);
}

/**
 * Return a customer's active Belgian National Register number, if one is stored.
 *
 * @param int $companyId The owning company ID.
 * @param string $customerCode The customer code.
 * @return ?string The decrypted identifier or null when absent.
 */
function grompil_customer_national_register_number_get(int $companyId, string $customerCode): ?string
{
    $result = db_query_params(
        'customers.identifier_national_register_get',
        "SELECT encrypted_value FROM dt_customer_identifiers
         WHERE company_id=$1 AND customer_code=$2 AND identifier_type_key='national_register' AND is_active
         LIMIT 1",
        [$companyId, $customerCode]
    );
    if (pg_num_rows($result) === 0) {
        return null;
    }
    return grompil_customer_identifier_decrypt((string) pg_fetch_result($result, 0, 'encrypted_value'));
}

/**
 * Save or clear a customer's Belgian National Register number.
 *
 * @param int $companyId The owning company ID.
 * @param string $customerCode The customer code.
 * @param string $number The normalized number, or an empty value to clear it.
 * @param string $actorCode The authenticated user code.
 * @return void
 */
function grompil_customer_national_register_number_save(
    int $companyId,
    string $customerCode,
    string $number,
    string $actorCode
): void {
    if ($number === '') {
        db_query_params(
            'customers.identifier_national_register_clear',
            "UPDATE dt_customer_identifiers SET is_active=FALSE, updated_by_user_code=$1, updated_at=now()
             WHERE company_id=$2 AND customer_code=$3 AND identifier_type_key='national_register' AND is_active",
            [$actorCode, $companyId, $customerCode]
        );
        return;
    }

    $encrypted = grompil_customer_identifier_encrypt($number);
    $fingerprint = grompil_customer_identifier_fingerprint($number);
    $duplicate = db_query_params(
        'customers.identifier_national_register_duplicate_check',
        "SELECT 1 FROM dt_customer_identifiers
         WHERE company_id=$1 AND identifier_type_key='national_register' AND lookup_hash=$2
           AND customer_code<>$3 AND is_active LIMIT 1",
        [$companyId, $fingerprint, $customerCode]
    );
    if (pg_num_rows($duplicate) > 0) {
        throw new GrompilDuplicateCustomerIdentifierException('National Register number already belongs to another customer.');
    }

    $result = db_query_params(
        'customers.identifier_national_register_save',
        "INSERT INTO dt_customer_identifiers (
            company_id, customer_code, identifier_type_key, encrypted_value, lookup_hash,
            created_by_user_code, updated_by_user_code
         ) VALUES ($1,$2,'national_register',$3,$4,$5,$5)
         ON CONFLICT (company_id, customer_code, identifier_type_key) WHERE is_active
         DO UPDATE SET encrypted_value=EXCLUDED.encrypted_value, lookup_hash=EXCLUDED.lookup_hash,
            updated_by_user_code=EXCLUDED.updated_by_user_code, updated_at=now()",
        [$companyId, $customerCode, $encrypted, $fingerprint, $actorCode]
    );
    if (pg_affected_rows($result) !== 1) {
        throw new GrompilCustomerIdentifierException('Customer identifier could not be saved.');
    }
}
