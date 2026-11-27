-- Reserve prescription numbers when the dispensing receipt preview is created.

BEGIN;

ALTER TABLE dt_dispensing_sessions
    ADD COLUMN IF NOT EXISTS prescription_number BIGINT;

CREATE UNIQUE INDEX IF NOT EXISTS dt_dispensing_sessions_prescription_number_uidx
    ON dt_dispensing_sessions (company_id, prescription_number)
    WHERE prescription_number IS NOT NULL;

COMMIT;
