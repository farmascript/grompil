/**
 *
 * @date_cre: Sun 2026-10-18 20:35:56
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Updates the database structure for company-specific sign-in.
 *
 */

-- Allows an active company to be resolved briefly for its company-scoped login page.
-- The application sets grompil.company_login_lookup only around that lookup.
DROP POLICY IF EXISTS companies_visible_to_public_members_or_platform ON companies;
CREATE POLICY companies_visible_to_public_members_or_platform ON companies
    FOR SELECT USING (
        (is_active AND is_public)
        OR id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR id IN (
            SELECT cu.company_id FROM company_users AS cu
            WHERE cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR (is_active AND current_setting('grompil.company_login_lookup', true) = 'true')
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

