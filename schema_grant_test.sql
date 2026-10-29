BEGIN

GRANT CONNECT ON DATABASE grompilc_test TO grompilc_test_app;
GRANT USAGE ON SCHEMA public TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE
    ON ALL TABLES IN SCHEMA public TO grompilc_test_app;
GRANT USAGE, SELECT
    ON ALL SEQUENCES IN SCHEMA public TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON substitutions TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON access_ip_allowlist TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON kbo_lookup_attempts TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON companies, company_users, company_pages TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON company_navigation_preferences TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON platform_navigation_preferences TO grompilc_test_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON platform_system_settings TO grompilc_test_app;
DO $$
BEGIN
    IF to_regclass('public.pharmacies') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.pharmacies TO grompilc_test_app';
    END IF;
END;
$$;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO grompilc_test_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO grompilc_test_app;

GRANT SELECT, INSERT, UPDATE, DELETE ON password_reset_tokens, password_reset_attempts TO grompilc_test_app;

COMMIT