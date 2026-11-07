/**
 *
 * @date_cre: Sun 2026-10-18 20:35:50
 * @date_mut: Sat 2026-11-07
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Deactivates test accounts and companies while preserving their records.
 *
 */

-- Soft reset for the local Grompil test database.
-- Preserves user accounts, memberships, companies, translations and related history.
-- Keeps exactly one global platform administrator active.
BEGIN;

CREATE TEMP TABLE grompil_kept_platform_admin ON COMMIT DROP AS
SELECT u.user_code
FROM bt_users AS u
JOIN ft_roles AS r ON r.id = u.role_id
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

-- Preserve memberships and account records for audit/history; revoke access instead.
UPDATE ct_company_users SET is_active = FALSE, updated_at = now();

UPDATE bt_users SET is_active = FALSE, updated_at = now()
WHERE user_code NOT IN (SELECT user_code FROM grompil_kept_platform_admin);
UPDATE bt_users SET is_active = TRUE, updated_at = now()
WHERE user_code IN (SELECT user_code FROM grompil_kept_platform_admin);

-- Keep companies and their content, but hide and disable their websites.
UPDATE bt_companies SET is_active = FALSE, is_public = FALSE, updated_at = now();

-- Clear test-only request history.
DELETE FROM st_login_attempts;
DELETE FROM st_registration_attempts;

COMMIT;

-- Expected after reset: one active global platform administrator; all other
-- accounts, memberships and companies remain stored but are inactive/private.

