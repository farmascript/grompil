BEGIN;

ALTER TABLE ft_platform_dispensing_function_keys
    DROP CONSTRAINT IF EXISTS ft_platform_dispensing_function_keys_action_key_check;
ALTER TABLE ft_platform_dispensing_function_keys
    ADD CONSTRAINT ft_platform_dispensing_function_keys_action_key_check
    CHECK (
        action_key IN (
            'none', 'focus_customer_search', 'focus_product_search', 'open_customer_modal',
            'toggle_customer_regex', 'clear_product_search', 'open_url', 'open_company_website',
            'close_dispensing', 'discard_dispensing'
        ) OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'
    );

UPDATE ft_platform_dispensing_function_keys
SET action_key = 'discard_dispensing', action_config = '{}'::jsonb, labels = '{}'::jsonb, updated_at = now()
WHERE key_code = 'F12' AND action_key = 'none';

COMMIT;
