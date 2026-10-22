/**
 *
 * @date_cre: Sun 2026-10-18 20:35:58
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Adds storage and policies for platform-wide navigation preferences.
 *
 */

BEGIN;

CREATE TABLE IF NOT EXISTS platform_navigation_preferences (
    application VARCHAR(80) PRIMARY KEY
        CHECK (application ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
    configuration JSONB NOT NULL DEFAULT '{"groups": [], "assignments": {}}'::jsonb
        CHECK (jsonb_typeof(configuration) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE platform_navigation_preferences ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_navigation_preferences_read ON platform_navigation_preferences;
CREATE POLICY platform_navigation_preferences_read ON platform_navigation_preferences
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_navigation_preferences_admin_write ON platform_navigation_preferences;
CREATE POLICY platform_navigation_preferences_admin_write ON platform_navigation_preferences
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

GRANT SELECT, INSERT, UPDATE, DELETE ON platform_navigation_preferences TO grompil_app;

COMMIT;

