BEGIN;

GRANT CONNECT ON DATABASE grompilc TO grompilc_app;
GRANT USAGE ON SCHEMA public TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE
    ON ALL TABLES IN SCHEMA public TO grompilc_app;
GRANT USAGE, SELECT
    ON ALL SEQUENCES IN SCHEMA public TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_substitutions TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_access_ip_allowlist TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON st_kbo_lookup_attempts TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON bt_companies, ct_company_users, ct_company_pages TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ct_company_navigation_preferences TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_platform_navigation_preferences TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_platform_system_settings TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON st_platform_totp_credentials, st_platform_totp_recovery_codes TO grompilc_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_dd_varia TO grompilc_app;
DO $$
BEGIN
    IF to_regclass('public.xt_sam_public_products') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_sam_public_products TO grompilc_app';
    END IF;
    IF to_regclass('public.xt_sam_atc_classifications') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_sam_atc_classifications TO grompilc_app';
    END IF;
END;
$$;
DO $$
BEGIN
    IF to_regclass('public.xt_pharmacies') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_pharmacies TO grompilc_app';
    END IF;
END;
$$;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO grompilc_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO grompilc_app;

GRANT SELECT, INSERT, UPDATE, DELETE ON st_password_reset_tokens, st_password_reset_attempts TO grompilc_app;

COMMIT;
