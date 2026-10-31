BEGIN;

-- TOTP shared secrets are encrypted by the application with a key from settings_local.php.
CREATE TABLE IF NOT EXISTS platform_totp_credentials (
    user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    secret_ciphertext TEXT NOT NULL,
    enabled_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (secret_ciphertext <> '')
);

CREATE TABLE IF NOT EXISTS platform_totp_recovery_codes (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    used_at TIMESTAMPTZ,
    PRIMARY KEY (user_id, code_hash)
);

ALTER TABLE platform_totp_credentials ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_totp_credentials_platform_only ON platform_totp_credentials;
CREATE POLICY platform_totp_credentials_platform_only ON platform_totp_credentials
    FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

ALTER TABLE platform_totp_recovery_codes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_totp_recovery_codes_platform_only ON platform_totp_recovery_codes;
CREATE POLICY platform_totp_recovery_codes_platform_only ON platform_totp_recovery_codes
    FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

COMMIT;
