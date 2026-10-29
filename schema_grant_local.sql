BEGIN

GRANT CONNECT ON DATABASE grompil TO grompil_app;
GRANT USAGE ON SCHEMA public TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE
    ON ALL TABLES IN SCHEMA public TO grompil_app;
GRANT USAGE, SELECT
    ON ALL SEQUENCES IN SCHEMA public TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON substitutions TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON access_ip_allowlist TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON kbo_lookup_attempts TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON companies, company_users, company_pages TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON company_navigation_preferences TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON platform_navigation_preferences TO grompil_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON platform_system_settings TO grompil_app;
DO $$
BEGIN
    IF to_regclass('public.pharmacies') IS NOT NULL THEN
        EXECUTE 'GRANT SELECT ON public.pharmacies TO grompil_app';
    END IF;
END;
$$;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO grompil_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO grompil_app;

GRANT SELECT, INSERT, UPDATE, DELETE ON password_reset_tokens, password_reset_attempts TO grompil_app;

COMMIT