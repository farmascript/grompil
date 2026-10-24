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
 * @description: Adds password reset tokens, request limits and login session revocation.
 *
 */

BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_version BIGINT NOT NULL DEFAULT 0 CHECK (auth_version >= 0);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_hash CHAR(64) PRIMARY KEY CHECK (token_hash ~ '^[a-f0-9]{64}$'),
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    company_id BIGINT REFERENCES companies(id) ON DELETE CASCADE,
    password_hash_at_issue VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_reset_tokens_expiry_idx ON password_reset_tokens (expires_at);

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    identifier_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_reset_attempts_ip_idx ON password_reset_attempts (ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS password_reset_attempts_identifier_idx ON password_reset_attempts (company_id, identifier_hash, attempted_at);

GRANT SELECT, INSERT, UPDATE, DELETE ON password_reset_tokens, password_reset_attempts TO grompil_app;

COMMIT;
