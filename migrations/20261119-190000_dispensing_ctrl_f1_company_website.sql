BEGIN;

ALTER TABLE ft_platform_dispensing_function_keys
    DROP CONSTRAINT IF EXISTS ft_platform_dispensing_function_keys_action_key_check;

ALTER TABLE ft_platform_dispensing_function_keys
    ADD CONSTRAINT ft_platform_dispensing_function_keys_action_key_check
    CHECK (
        action_key IN (
            'none',
            'focus_customer_search',
            'focus_product_search',
            'open_customer_modal',
            'toggle_customer_regex',
            'clear_product_search',
            'open_url',
            'open_company_website'
        )
        OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'
    );

INSERT INTO ft_platform_dispensing_function_keys
    (key_code, action_key, action_config, labels, updated_by_user_code, updated_at)
VALUES
    ('CTRL_F1', 'open_company_website', '{}'::jsonb, '{}'::jsonb, NULL, now())
ON CONFLICT (key_code) DO UPDATE
SET action_key = EXCLUDED.action_key,
    action_config = EXCLUDED.action_config,
    labels = EXCLUDED.labels,
    updated_by_user_code = NULL,
    updated_at = now();

COMMIT;
