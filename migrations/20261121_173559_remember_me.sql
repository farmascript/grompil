BEGIN;

ALTER TABLE bt_users
    ADD COLUMN IF NOT EXISTS auth_version BIGINT NOT NULL DEFAULT 0 CHECK (auth_version >= 0);

CREATE TABLE IF NOT EXISTS st_remember_me_tokens (
    selector CHAR(32) PRIMARY KEY CHECK (selector ~ '^[a-f0-9]{32}$'),
    token_hash CHAR(64) NOT NULL CHECK (token_hash ~ '^[a-f0-9]{64}$'),
    user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE CASCADE,
    auth_version BIGINT NOT NULL CHECK (auth_version >= 0),
    active_company_id BIGINT REFERENCES bt_companies(id) ON DELETE CASCADE,
    platform_mfa_verified BOOLEAN NOT NULL DEFAULT FALSE,
    company_mfa_verified BOOLEAN NOT NULL DEFAULT FALSE,
    company_mfa_company_id BIGINT REFERENCES bt_companies(id) ON DELETE CASCADE,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS st_remember_me_tokens_user_idx ON st_remember_me_tokens (user_code);
CREATE INDEX IF NOT EXISTS st_remember_me_tokens_expiry_idx ON st_remember_me_tokens (expires_at);

DO $$
DECLARE
    app_role TEXT;
BEGIN
    FOR app_role IN
        SELECT rolname FROM pg_roles
        WHERE rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app')
    LOOP
        EXECUTE format('GRANT SELECT, INSERT, DELETE ON st_remember_me_tokens TO %I', app_role);
    END LOOP;
END;
$$;

COMMIT;
