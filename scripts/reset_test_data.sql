/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Deletes or restores explicitly selected local test data.
 *
 */

-- Destructive reset for the local Grompil test database.
-- Run in Adminer only after taking and checking a full backup.
-- Keeps exactly one global platform administrator, global system roles,
-- public.pharmacies, company_id = 0 translations and platform navigation.
BEGIN;

CREATE TEMP TABLE grompil_kept_platform_admin ON COMMIT DROP AS
SELECT u.id
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
WHERE u.company_id IS NULL
  AND lower(r.name) = 'beheerder'
  AND r.level < 100;

DO $$
BEGIN
    IF (SELECT count(*) FROM grompil_kept_platform_admin) <> 1 THEN
        RAISE EXCEPTION
            'Reset afgebroken: verwacht precies één globale platformbeheerder met rol Beheerder en level < 100.';
    END IF;
END
$$;

-- Remove memberships first because they reference both companies and roles.
DELETE FROM company_users;

-- Remove all users except the verified global platform administrator.
DELETE FROM users
WHERE id NOT IN (SELECT id FROM grompil_kept_platform_admin);

-- Remove company-specific translations; preserve the shared language set.
DELETE FROM substitutions WHERE company_id <> 0;

-- Remove companies. Company-owned pages, domains, roles, navigation and IP
-- rules are removed by their declared ON DELETE CASCADE foreign keys.
DELETE FROM companies;

-- Clear test-only request history.
DELETE FROM login_attempts;
DELETE FROM registration_attempts;

COMMIT;

-- Expected after reset: one platform administrator, no companies or tenant
-- users, only company_id = 0 substitutions. roles, pharmacies and platform
-- navigation preferences remain in place.

