BEGIN;

ALTER TABLE st_dispensing_history
    ADD COLUMN IF NOT EXISTS prescription_number BIGINT;

CREATE UNIQUE INDEX IF NOT EXISTS st_dispensing_history_prescription_number_uidx
    ON st_dispensing_history (company_id, prescription_number)
    WHERE prescription_number IS NOT NULL;

CREATE TABLE IF NOT EXISTS dt_company_prescription_counters (
    company_id BIGINT PRIMARY KEY REFERENCES bt_companies(id) ON DELETE CASCADE,
    last_number BIGINT NOT NULL DEFAULT 0 CHECK (last_number >= 0)
);

ALTER TABLE dt_company_prescription_counters ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS dt_company_prescription_counters_read ON dt_company_prescription_counters;
DROP POLICY IF EXISTS dt_company_prescription_counters_insert ON dt_company_prescription_counters;
DROP POLICY IF EXISTS dt_company_prescription_counters_update ON dt_company_prescription_counters;
CREATE POLICY dt_company_prescription_counters_read ON dt_company_prescription_counters FOR SELECT
    USING (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT);
CREATE POLICY dt_company_prescription_counters_insert ON dt_company_prescription_counters FOR INSERT
    WITH CHECK (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT);
CREATE POLICY dt_company_prescription_counters_update ON dt_company_prescription_counters FOR UPDATE
    USING (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT)
    WITH CHECK (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT);

DO $$
DECLARE
    app_role TEXT;
BEGIN
    FOR app_role IN
        SELECT rolname FROM pg_roles
        WHERE rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app')
    LOOP
        EXECUTE format('GRANT SELECT, INSERT, UPDATE ON dt_company_prescription_counters TO %I', app_role);
        EXECUTE format('GRANT SELECT ON st_dispensing_history TO %I', app_role);
    END LOOP;
END;
$$;

COMMIT;
