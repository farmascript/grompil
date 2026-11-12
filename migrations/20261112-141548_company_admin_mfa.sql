BEGIN;

-- Allow each signed-in account to read and maintain only its own TOTP secret
-- and recovery codes. Platform operators retain the existing global access.
DROP POLICY IF EXISTS platform_totp_credentials_platform_only ON st_platform_totp_credentials;
CREATE POLICY platform_totp_credentials_platform_only ON st_platform_totp_credentials
    FOR ALL
    USING (
        current_setting('grompil.platform_operator', true) = 'true'
        OR user_code = NULLIF(current_setting('grompil.user_code', true), '')
    )
    WITH CHECK (
        current_setting('grompil.platform_operator', true) = 'true'
        OR user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );

DROP POLICY IF EXISTS platform_totp_recovery_codes_platform_only ON st_platform_totp_recovery_codes;
CREATE POLICY platform_totp_recovery_codes_platform_only ON st_platform_totp_recovery_codes
    FOR ALL
    USING (
        current_setting('grompil.platform_operator', true) = 'true'
        OR user_code = NULLIF(current_setting('grompil.user_code', true), '')
    )
    WITH CHECK (
        current_setting('grompil.platform_operator', true) = 'true'
        OR user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );

COMMIT;
