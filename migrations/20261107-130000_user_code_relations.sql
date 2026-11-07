-- Replace every cross-table user-id relationship with the stable user_code.
-- Keep users.id as the primary key used only inside users.
BEGIN;

CREATE UNIQUE INDEX IF NOT EXISTS users_company_user_code_uidx
    ON users (company_id, user_code);

ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by_user_code VARCHAR(6);
UPDATE users AS target
SET approved_by_user_code = approver.user_code
FROM users AS approver
WHERE target.approved_by = approver.id
  AND target.approved_by_user_code IS NULL;

ALTER TABLE company_users ADD COLUMN IF NOT EXISTS user_code VARCHAR(6);
ALTER TABLE company_users ADD COLUMN IF NOT EXISTS approved_by_user_code VARCHAR(6);
UPDATE company_users AS membership
SET user_code = account.user_code
FROM users AS account
WHERE membership.user_id = account.id AND membership.user_code IS NULL;
UPDATE company_users AS membership
SET approved_by_user_code = approver.user_code
FROM users AS approver
WHERE membership.approved_by = approver.id AND membership.approved_by_user_code IS NULL;

ALTER TABLE kbo_lookup_attempts ADD COLUMN IF NOT EXISTS requested_by_user_code VARCHAR(6);
UPDATE kbo_lookup_attempts AS attempt
SET requested_by_user_code = account.user_code
FROM users AS account
WHERE attempt.requested_by = account.id AND attempt.requested_by_user_code IS NULL;

ALTER TABLE password_reset_tokens ADD COLUMN IF NOT EXISTS user_code VARCHAR(6);
UPDATE password_reset_tokens AS token
SET user_code = account.user_code
FROM users AS account
WHERE token.user_id = account.id AND token.user_code IS NULL;

ALTER TABLE platform_totp_credentials ADD COLUMN IF NOT EXISTS user_code VARCHAR(6);
UPDATE platform_totp_credentials AS credential
SET user_code = account.user_code
FROM users AS account
WHERE credential.user_id = account.id AND credential.user_code IS NULL;

ALTER TABLE platform_totp_recovery_codes ADD COLUMN IF NOT EXISTS user_code VARCHAR(6);
UPDATE platform_totp_recovery_codes AS recovery
SET user_code = account.user_code
FROM users AS account
WHERE recovery.user_id = account.id AND recovery.user_code IS NULL;

ALTER TABLE company_users ALTER COLUMN user_code SET NOT NULL;
ALTER TABLE password_reset_tokens ALTER COLUMN user_code SET NOT NULL;
ALTER TABLE platform_totp_credentials ALTER COLUMN user_code SET NOT NULL;
ALTER TABLE platform_totp_recovery_codes ALTER COLUMN user_code SET NOT NULL;

ALTER TABLE users DROP CONSTRAINT IF EXISTS users_approved_by_fkey;
ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_user_id_fkey;
ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_user_same_company_fk;
ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_approved_by_fkey;
ALTER TABLE kbo_lookup_attempts DROP CONSTRAINT IF EXISTS kbo_lookup_attempts_requested_by_fkey;
ALTER TABLE password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_user_id_fkey;
ALTER TABLE password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_user_id_key;
ALTER TABLE platform_totp_credentials DROP CONSTRAINT IF EXISTS platform_totp_credentials_user_id_fkey;
ALTER TABLE platform_totp_credentials DROP CONSTRAINT IF EXISTS platform_totp_credentials_pkey;
ALTER TABLE platform_totp_recovery_codes DROP CONSTRAINT IF EXISTS platform_totp_recovery_codes_user_id_fkey;
ALTER TABLE platform_totp_recovery_codes DROP CONSTRAINT IF EXISTS platform_totp_recovery_codes_pkey;
DROP INDEX IF EXISTS company_users_user_lookup_idx;
DROP INDEX IF EXISTS users_company_id_id_unique_idx;

ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_pkey;
ALTER TABLE company_users ADD CONSTRAINT company_users_pkey PRIMARY KEY (company_id, user_code);
ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_user_code_key UNIQUE (user_code);
ALTER TABLE platform_totp_credentials ADD CONSTRAINT platform_totp_credentials_pkey PRIMARY KEY (user_code);
ALTER TABLE platform_totp_recovery_codes ADD CONSTRAINT platform_totp_recovery_codes_pkey PRIMARY KEY (user_code, code_hash);

ALTER TABLE users ADD CONSTRAINT users_approved_by_user_code_fkey
    FOREIGN KEY (approved_by_user_code) REFERENCES users (user_code) ON DELETE SET NULL;
ALTER TABLE company_users ADD CONSTRAINT company_users_user_same_company_fk
    FOREIGN KEY (company_id, user_code) REFERENCES users (company_id, user_code) ON DELETE CASCADE;
ALTER TABLE company_users ADD CONSTRAINT company_users_approved_by_user_code_fkey
    FOREIGN KEY (approved_by_user_code) REFERENCES users (user_code) ON DELETE SET NULL;
ALTER TABLE kbo_lookup_attempts ADD CONSTRAINT kbo_lookup_attempts_requested_by_user_code_fkey
    FOREIGN KEY (requested_by_user_code) REFERENCES users (user_code) ON DELETE SET NULL;
ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_user_code_fkey
    FOREIGN KEY (user_code) REFERENCES users (user_code) ON DELETE CASCADE;
ALTER TABLE platform_totp_credentials ADD CONSTRAINT platform_totp_credentials_user_code_fkey
    FOREIGN KEY (user_code) REFERENCES users (user_code) ON DELETE CASCADE;
ALTER TABLE platform_totp_recovery_codes ADD CONSTRAINT platform_totp_recovery_codes_user_code_fkey
    FOREIGN KEY (user_code) REFERENCES users (user_code) ON DELETE CASCADE;
CREATE INDEX company_users_user_lookup_idx ON company_users (user_code, company_id);

DROP POLICY IF EXISTS companies_visible_to_public_members_or_platform ON companies;
CREATE POLICY companies_visible_to_public_members_or_platform ON companies
    FOR SELECT USING (
        (is_active AND is_public)
        OR id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR id IN (
            SELECT cu.company_id FROM company_users AS cu
            WHERE cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR (is_active AND current_setting('grompil.company_login_lookup', true) = 'true')
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_users_self_read ON company_users;
CREATE POLICY company_users_self_read ON company_users
    FOR SELECT USING (
        user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );

DROP POLICY IF EXISTS company_roles_visible_to_member_or_platform ON company_roles;
CREATE POLICY company_roles_visible_to_member_or_platform ON company_roles
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR company_id IN (
            SELECT cu.company_id FROM company_users AS cu
            WHERE cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_navigation_preferences_read ON company_navigation_preferences;
CREATE POLICY company_navigation_preferences_read ON company_navigation_preferences
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
            )
        )
    );
DROP POLICY IF EXISTS company_navigation_preferences_admin_write ON company_navigation_preferences;
CREATE POLICY company_navigation_preferences_admin_write ON company_navigation_preferences
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                JOIN company_roles AS cr ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                JOIN company_roles AS cr ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    );

ALTER TABLE users DROP COLUMN approved_by;
ALTER TABLE company_users DROP COLUMN user_id;
ALTER TABLE company_users DROP COLUMN approved_by;
ALTER TABLE kbo_lookup_attempts DROP COLUMN requested_by;
ALTER TABLE password_reset_tokens DROP COLUMN user_id;
ALTER TABLE platform_totp_credentials DROP COLUMN user_id;
ALTER TABLE platform_totp_recovery_codes DROP COLUMN user_id;

COMMIT;
