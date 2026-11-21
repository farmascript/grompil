BEGIN;

CREATE INDEX IF NOT EXISTS st_dispensing_history_lines_customer_idx
    ON st_dispensing_history_lines (customer_code, history_id)
    WHERE customer_code IS NOT NULL;

DROP POLICY IF EXISTS st_dispensing_history_company_scope ON st_dispensing_history;
DROP POLICY IF EXISTS st_dispensing_history_company_read ON st_dispensing_history;
DROP POLICY IF EXISTS st_dispensing_history_actor_insert ON st_dispensing_history;
CREATE POLICY st_dispensing_history_company_read ON st_dispensing_history FOR SELECT
    USING (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT);
CREATE POLICY st_dispensing_history_actor_insert ON st_dispensing_history FOR INSERT
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );

DROP POLICY IF EXISTS st_dispensing_history_lines_company_scope ON st_dispensing_history_lines;
DROP POLICY IF EXISTS st_dispensing_history_lines_company_read ON st_dispensing_history_lines;
DROP POLICY IF EXISTS st_dispensing_history_lines_company_insert ON st_dispensing_history_lines;
CREATE POLICY st_dispensing_history_lines_company_read ON st_dispensing_history_lines FOR SELECT
    USING (EXISTS (
        SELECT 1 FROM st_dispensing_history history
        WHERE history.id = st_dispensing_history_lines.history_id
          AND history.company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
    ));
CREATE POLICY st_dispensing_history_lines_company_insert ON st_dispensing_history_lines FOR INSERT
    WITH CHECK (EXISTS (
        SELECT 1 FROM st_dispensing_history history
        WHERE history.id = st_dispensing_history_lines.history_id
          AND history.company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
          AND history.created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    ));

ALTER TABLE ft_platform_dispensing_function_keys
    DROP CONSTRAINT IF EXISTS ft_platform_dispensing_function_keys_action_key_check;
ALTER TABLE ft_platform_dispensing_function_keys
    ADD CONSTRAINT ft_platform_dispensing_function_keys_action_key_check
    CHECK (
        action_key IN (
            'none', 'focus_customer_search', 'focus_product_search', 'open_customer_modal',
            'toggle_customer_regex', 'clear_product_search', 'open_url', 'open_company_website',
            'close_dispensing', 'discard_dispensing', 'open_patient_history'
        ) OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'
    );
UPDATE ft_platform_dispensing_function_keys
SET action_key = 'open_patient_history', action_config = '{}'::JSONB, labels = '{}'::JSONB, updated_at = now()
WHERE key_code = 'F6' AND action_key = 'none';

COMMIT;
