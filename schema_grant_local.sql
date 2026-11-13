BEGIN;

GRANT CONNECT ON DATABASE grompil TO grompil_app;
GRANT USAGE ON SCHEMA public TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE
    ON ALL TABLES IN SCHEMA public TO grompil_app;
GRANT USAGE, SELECT
    ON ALL SEQUENCES IN SCHEMA public TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_substitutions TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_access_ip_allowlist TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON st_kbo_lookup_attempts TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON bt_companies, ct_company_users, ct_company_pages TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ct_company_navigation_preferences TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ct_company_preferences TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_platform_navigation_preferences TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_platform_system_settings TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON st_platform_totp_credentials, st_platform_totp_recovery_codes TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ft_dd_varia TO grompil_app;
DO $$
BEGIN
    IF to_regclass('public.xt_sam_public_products') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_sam_public_products TO grompil_app';
    END IF;
    IF to_regclass('public.xt_sam_atc_classifications') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_sam_atc_classifications TO grompil_app';
    END IF;
    IF to_regclass('public.xt_sam_compounding_catalog') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT, INSERT, DELETE ON public.xt_sam_compounding_catalog TO grompil_app';
    END IF;
END;
$$;
DO $$
BEGIN
    IF to_regclass('public.xt_pharmacies') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_pharmacies TO grompil_app';
    END IF;
END;
$$;
DO $$
BEGIN
    IF to_regclass('public.xt_compounding_raw_materials') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_compounding_raw_materials TO grompil_app';
        EXECUTE 'REVOKE INSERT, UPDATE, DELETE ON public.xt_compounding_raw_materials FROM grompil_app';
    END IF;
    IF to_regclass('public.xt_compounding_raw_material_sources') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_compounding_raw_material_sources TO grompil_app';
        EXECUTE 'REVOKE INSERT, UPDATE, DELETE ON public.xt_compounding_raw_material_sources FROM grompil_app';
    END IF;
    IF to_regclass('public.xt_compounding_reimbursement_codes') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.xt_compounding_reimbursement_codes TO grompil_app';
        EXECUTE 'REVOKE INSERT, UPDATE, DELETE ON public.xt_compounding_reimbursement_codes FROM grompil_app';
    END IF;
END;
$$;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO grompil_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO grompil_app;

GRANT SELECT, INSERT, UPDATE, DELETE ON st_password_reset_tokens, st_password_reset_attempts TO grompil_app;
GRANT SELECT, INSERT, UPDATE ON bt_customers, dt_customer_locations TO grompil_app;
GRANT SELECT, INSERT ON st_customer_audit_log TO grompil_app;
GRANT USAGE, SELECT ON SEQUENCE bt_customers_id_seq, dt_customer_locations_id_seq, st_customer_audit_log_id_seq TO grompil_app;
REVOKE DELETE ON bt_customers, dt_customer_locations FROM grompil_app;
REVOKE UPDATE, DELETE ON st_customer_audit_log FROM grompil_app;

COMMIT;
