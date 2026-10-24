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
 * @description: Removes the obsolete global role reference from company memberships.
 *
 */

-- Remove the obsolete global role link from company memberships.
-- company_role_id is the authoritative company-specific role reference.
BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'company_users'
          AND column_name = 'role_id'
    ) THEN
        EXECUTE $migration$
            UPDATE company_users AS cu
            SET company_role_id = cr.id
            FROM roles AS r, company_roles AS cr
            WHERE cu.company_role_id IS NULL
              AND cu.role_id = r.id
              AND cr.company_id = cu.company_id
              AND cr.name = r.name
        $migration$;
    END IF;

    IF EXISTS (
        SELECT 1 FROM company_users WHERE company_role_id IS NULL
    ) THEN
        RAISE EXCEPTION
            'Migratie afgebroken: minstens één company_users-record heeft geen geldige bedrijfsrol.';
    END IF;
END
$$;

ALTER TABLE company_users ALTER COLUMN company_role_id SET NOT NULL;
ALTER TABLE company_users DROP COLUMN IF EXISTS role_id;

COMMIT;

