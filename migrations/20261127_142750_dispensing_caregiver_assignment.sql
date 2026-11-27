BEGIN;

ALTER TABLE dt_dispensing_lines
    ADD COLUMN IF NOT EXISTS caregiver_code VARCHAR(6);
ALTER TABLE dt_dispensing_lines
    DROP CONSTRAINT IF EXISTS dt_dispensing_lines_caregiver_code_fkey;
ALTER TABLE dt_dispensing_lines
    ADD CONSTRAINT dt_dispensing_lines_caregiver_code_fkey
    FOREIGN KEY (caregiver_code) REFERENCES bt_caregivers(caregiver_code) ON DELETE RESTRICT;

DROP INDEX IF EXISTS dt_dispensing_lines_product_uidx;
DROP INDEX IF EXISTS dt_dispensing_lines_product_no_caregiver_uidx;
DROP INDEX IF EXISTS dt_dispensing_lines_product_caregiver_uidx;
CREATE UNIQUE INDEX IF NOT EXISTS dt_dispensing_lines_product_caregiver_uidx
    ON dt_dispensing_lines (
        company_id, dispensing_session_id, party_id, cnk, amp_code, ampp_code, status_key,
        (COALESCE(caregiver_code, ''::VARCHAR))
    );

ALTER TABLE st_dispensing_history_lines
    ADD COLUMN IF NOT EXISTS caregiver_code VARCHAR(6);
ALTER TABLE st_dispensing_history_lines
    ADD COLUMN IF NOT EXISTS caregiver_name VARCHAR(280) NOT NULL DEFAULT '';

COMMIT;
