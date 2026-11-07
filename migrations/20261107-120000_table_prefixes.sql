-- Rename current application tables to the agreed table-family prefixes.
-- Safe to re-run: already-renamed tables are left alone. Data and constraints
-- stay attached to each table through PostgreSQL's ALTER TABLE RENAME.
BEGIN;

DO $$
DECLARE
    item RECORD;
    old_exists BOOLEAN;
    new_exists BOOLEAN;
BEGIN
    FOR item IN
        SELECT * FROM (VALUES
            ('companies', 'bt_companies'),
            ('users', 'bt_users'),
            ('roles', 'ft_roles'),
            ('company_roles', 'ct_company_roles'),
            ('company_users', 'ct_company_users'),
            ('company_domains', 'ct_company_domains'),
            ('company_pages', 'ct_company_pages'),
            ('company_navigation_preferences', 'ct_company_navigation_preferences'),
            ('platform_navigation_preferences', 'ft_platform_navigation_preferences'),
            ('platform_system_settings', 'ft_platform_system_settings'),
            ('dd_varia', 'ft_dd_varia'),
            ('substitutions', 'ft_substitutions'),
            ('access_ip_allowlist', 'ft_access_ip_allowlist'),
            ('registration_attempts', 'st_registration_attempts'),
            ('login_attempts', 'st_login_attempts'),
            ('kbo_lookup_attempts', 'st_kbo_lookup_attempts'),
            ('password_reset_tokens', 'st_password_reset_tokens'),
            ('password_reset_attempts', 'st_password_reset_attempts'),
            ('platform_totp_credentials', 'st_platform_totp_credentials'),
            ('platform_totp_recovery_codes', 'st_platform_totp_recovery_codes'),
            ('sam_public_products', 'xt_sam_public_products'),
            ('sam_atc_classifications', 'xt_sam_atc_classifications'),
            ('pharmacies', 'xt_pharmacies')
        ) AS names(old_name, new_name)
    LOOP
        SELECT to_regclass(format('public.%I', item.old_name)) IS NOT NULL
          INTO old_exists;
        SELECT to_regclass(format('public.%I', item.new_name)) IS NOT NULL
          INTO new_exists;

        IF old_exists AND new_exists THEN
            RAISE EXCEPTION 'Both public.% and public.% exist; resolve this table-name collision before continuing.',
                item.old_name, item.new_name;
        ELSIF old_exists THEN
            EXECUTE format('ALTER TABLE public.%I RENAME TO %I', item.old_name, item.new_name);
        END IF;
    END LOOP;
END;
$$;

COMMIT;
