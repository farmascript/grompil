/**
 *
 * @date_cre: Sun 2026-10-18 20:36:03
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Adds storage and security policies for company navigation preferences.
 *
 */

BEGIN;

CREATE TABLE IF NOT EXISTS company_navigation_preferences (
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    application VARCHAR(80) NOT NULL
        CHECK (application ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
    configuration JSONB NOT NULL DEFAULT '{}'::jsonb
        CHECK (jsonb_typeof(configuration) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (company_id, application)
);

ALTER TABLE company_navigation_preferences ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS company_navigation_preferences_read ON company_navigation_preferences;
CREATE POLICY company_navigation_preferences_read ON company_navigation_preferences
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
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
                JOIN company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
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
                JOIN company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    );

GRANT SELECT, INSERT, UPDATE, DELETE ON company_navigation_preferences TO grompil_app;

COMMIT;

