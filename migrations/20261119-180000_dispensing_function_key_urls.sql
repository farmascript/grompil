BEGIN;

ALTER TABLE ft_platform_dispensing_function_keys
    ADD COLUMN IF NOT EXISTS action_config JSONB NOT NULL DEFAULT '{}'::jsonb;

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
            'open_url'
        )
        OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'
    );

ALTER TABLE ft_platform_dispensing_function_keys
    DROP CONSTRAINT IF EXISTS ft_platform_dispensing_function_keys_action_config_check;

ALTER TABLE ft_platform_dispensing_function_keys
    ADD CONSTRAINT ft_platform_dispensing_function_keys_action_config_check
    CHECK (jsonb_typeof(action_config) = 'object');

COMMENT ON COLUMN ft_platform_dispensing_function_keys.action_config IS
    'Structured settings for actions such as opening a validated HTTP(S) or internal URL in the same or a new tab.';

COMMIT;
