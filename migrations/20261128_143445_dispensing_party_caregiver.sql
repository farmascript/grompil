BEGIN;

ALTER TABLE dt_dispensing_parties
    ADD COLUMN IF NOT EXISTS caregiver_code VARCHAR(6);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'dt_dispensing_parties_caregiver_code_fkey'
          AND conrelid = 'dt_dispensing_parties'::regclass
    ) THEN
        ALTER TABLE dt_dispensing_parties
            ADD CONSTRAINT dt_dispensing_parties_caregiver_code_fkey
            FOREIGN KEY (caregiver_code)
            REFERENCES bt_caregivers(caregiver_code)
            ON DELETE RESTRICT;
    END IF;
END;
$$;

CREATE INDEX IF NOT EXISTS dt_dispensing_parties_caregiver_idx
    ON dt_dispensing_parties (caregiver_code)
    WHERE caregiver_code IS NOT NULL;

COMMENT ON COLUMN dt_dispensing_parties.caregiver_code IS
    'Default physician or dentist for product lines assigned to this dispensing party.';

COMMIT;
