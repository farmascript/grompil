/**
 *
 * @date_cre: Sun 2026-10-18 20:35:54
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Adds storage and usage tracking for company register lookup limits.
 *
 */

-- Record KBO lookups so the platform operator can track the rolling weekly quota.
BEGIN;

CREATE TABLE IF NOT EXISTS kbo_lookup_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    enterprise_number VARCHAR(16) NOT NULL,
    request_type VARCHAR(40) NOT NULL DEFAULT 'read_enterprise',
    requested_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS kbo_lookup_attempts_requested_at_idx
    ON kbo_lookup_attempts (requested_at DESC);
ALTER TABLE kbo_lookup_attempts ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS kbo_lookup_attempts_platform_only ON kbo_lookup_attempts;
CREATE POLICY kbo_lookup_attempts_platform_only ON kbo_lookup_attempts
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');
GRANT SELECT, INSERT, UPDATE, DELETE ON kbo_lookup_attempts TO grompil_app;
GRANT USAGE, SELECT ON SEQUENCE kbo_lookup_attempts_id_seq TO grompil_app;

COMMIT;

