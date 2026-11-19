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
            'clear_product_search'
        )
        OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'
    );

COMMENT ON COLUMN ft_platform_dispensing_function_keys.action_key IS
    'Allow-listed function-key action. open_page targets a registered PHP module; its own access checks remain authoritative.';

COMMIT;
