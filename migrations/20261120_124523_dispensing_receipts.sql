BEGIN;

ALTER TABLE ct_company_preferences
    ADD COLUMN IF NOT EXISTS dispensing_receipt_thank_you TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS st_dispensing_history (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL,
    dispensing_session_id UUID NOT NULL,
    created_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    status_key VARCHAR(80) NOT NULL,
    receipt_thank_you TEXT NOT NULL DEFAULT '',
    receipt_snapshot JSONB NOT NULL CHECK (jsonb_typeof(receipt_snapshot) = 'object'),
    completed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, dispensing_session_id),
    UNIQUE (company_id, id),
    FOREIGN KEY (company_id, dispensing_session_id)
        REFERENCES dt_dispensing_sessions(company_id, id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS st_dispensing_history_lines (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    history_id BIGINT NOT NULL,
    party_type VARCHAR(16) NOT NULL CHECK (party_type IN ('customer', 'anonymous')),
    customer_code VARCHAR(6),
    party_name VARCHAR(260) NOT NULL,
    status_key VARCHAR(80) NOT NULL,
    cnk VARCHAR(7) NOT NULL,
    amp_code VARCHAR(80) NOT NULL DEFAULT '',
    ampp_code VARCHAR(80) NOT NULL DEFAULT '',
    product_name_nl VARCHAR(500) NOT NULL,
    product_name_fr VARCHAR(500) NOT NULL DEFAULT '',
    package_display_nl VARCHAR(250) NOT NULL DEFAULT '',
    package_display_fr VARCHAR(250) NOT NULL DEFAULT '',
    quantity INTEGER NOT NULL CHECK (quantity BETWEEN 1 AND 999),
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (history_id) REFERENCES st_dispensing_history(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS st_dispensing_history_company_date_idx
    ON st_dispensing_history (company_id, completed_at DESC);
CREATE INDEX IF NOT EXISTS st_dispensing_history_lines_order_idx
    ON st_dispensing_history_lines (history_id, sort_order, id);

ALTER TABLE st_dispensing_history ENABLE ROW LEVEL SECURITY;
ALTER TABLE st_dispensing_history_lines ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS st_dispensing_history_company_scope ON st_dispensing_history;
CREATE POLICY st_dispensing_history_company_scope ON st_dispensing_history FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );
DROP POLICY IF EXISTS st_dispensing_history_lines_company_scope ON st_dispensing_history_lines;
CREATE POLICY st_dispensing_history_lines_company_scope ON st_dispensing_history_lines FOR ALL
    USING (EXISTS (
        SELECT 1 FROM st_dispensing_history history
        WHERE history.id = st_dispensing_history_lines.history_id
          AND history.company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
          AND history.created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    ))
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
            'close_dispensing'
        ) OR action_key ~ '^open_page:[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.]php$'
    );
UPDATE ft_platform_dispensing_function_keys
SET action_key = 'close_dispensing', action_config = '{}'::jsonb, labels = '{}'::jsonb, updated_at = now()
WHERE key_code = 'F5' AND action_key = 'none';

DO $$
DECLARE
    app_role TEXT;
BEGIN
    FOR app_role IN
        SELECT rolname FROM pg_roles
        WHERE rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app')
    LOOP
        EXECUTE format(
            'GRANT SELECT, INSERT, UPDATE, DELETE ON st_dispensing_history, st_dispensing_history_lines TO %I',
            app_role
        );
        EXECUTE format(
            'GRANT USAGE, SELECT ON SEQUENCE st_dispensing_history_id_seq, st_dispensing_history_lines_id_seq TO %I',
            app_role
        );
    END LOOP;
END;
$$;

COMMENT ON TABLE st_dispensing_history IS
    'Finalized dispensing sessions and immutable receipt snapshot.';
COMMENT ON TABLE st_dispensing_history_lines IS
    'Product-line snapshots copied from dispensing work sessions when a receipt is confirmed.';

COMMIT;
