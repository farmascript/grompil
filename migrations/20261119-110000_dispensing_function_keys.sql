BEGIN;

CREATE TABLE IF NOT EXISTS ft_platform_dispensing_function_keys (
    key_code VARCHAR(16) PRIMARY KEY
        CHECK (key_code ~ '^(F([1-9]|1[0-2])|CTRL_F([1-9]|1[0-2])|SHIFT_F([1-9]|1[0-2]))$'),
    action_key VARCHAR(80) NOT NULL DEFAULT 'none'
        CHECK (action_key IN (
            'none',
            'focus_customer_search',
            'focus_product_search',
            'open_customer_modal',
            'toggle_customer_regex',
            'clear_product_search',
            'open_url',
            'open_company_website'
        ) OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'),
    action_config JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(action_config) = 'object'),
    labels JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(labels) = 'object'),
    updated_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO ft_platform_dispensing_function_keys (key_code, action_key)
SELECT CASE modifier
           WHEN 'CTRL' THEN 'CTRL_F' || key_number::text
           WHEN 'SHIFT' THEN 'SHIFT_F' || key_number::text
           ELSE 'F' || key_number::text
       END,
       CASE
           WHEN modifier = '' AND key_number = 1 THEN 'focus_customer_search'
           WHEN modifier = '' AND key_number = 2 THEN 'focus_product_search'
           WHEN modifier = '' AND key_number = 3 THEN 'open_customer_modal'
           WHEN modifier = '' AND key_number = 4 THEN 'toggle_customer_regex'
           ELSE 'none'
       END
FROM (VALUES (''), ('CTRL'), ('SHIFT')) AS modifiers(modifier)
CROSS JOIN generate_series(1, 12) AS series(key_number)
ON CONFLICT (key_code) DO NOTHING;

ALTER TABLE ft_platform_dispensing_function_keys ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_dispensing_function_keys_read ON ft_platform_dispensing_function_keys;
CREATE POLICY platform_dispensing_function_keys_read ON ft_platform_dispensing_function_keys
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_dispensing_function_keys_admin_write ON ft_platform_dispensing_function_keys;
CREATE POLICY platform_dispensing_function_keys_admin_write ON ft_platform_dispensing_function_keys
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DO $$
DECLARE
    application_role NAME;
BEGIN
    SELECT rolname INTO application_role
    FROM pg_roles
    WHERE rolname::text = current_database() || '_app'
       OR rolname = 'grompil_app'
    ORDER BY CASE WHEN rolname::text = current_database() || '_app' THEN 0 ELSE 1 END
    LIMIT 1;

    IF application_role IS NULL THEN
        RAISE EXCEPTION 'Application database role not found (expected %_app or grompil_app)', current_database();
    END IF;

    EXECUTE format(
        'GRANT SELECT, INSERT, UPDATE, DELETE ON ft_platform_dispensing_function_keys TO %I',
        application_role
    );
END;
$$;

COMMENT ON TABLE ft_platform_dispensing_function_keys IS
    'Platform-managed allow-listed actions and multilingual labels for dispensing function-key shortcuts.';

COMMIT;
