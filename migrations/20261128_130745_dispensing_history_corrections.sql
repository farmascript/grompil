BEGIN;

ALTER TABLE dt_dispensing_sessions
    ADD COLUMN IF NOT EXISTS source_history_id BIGINT;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'dt_dispensing_sessions_source_history_fk'
          AND conrelid = 'dt_dispensing_sessions'::regclass
    ) THEN
        ALTER TABLE dt_dispensing_sessions
            ADD CONSTRAINT dt_dispensing_sessions_source_history_fk
            FOREIGN KEY (company_id, source_history_id)
            REFERENCES st_dispensing_history(company_id, id)
            ON DELETE RESTRICT;
    END IF;
END;
$$;

CREATE UNIQUE INDEX IF NOT EXISTS dt_dispensing_sessions_open_history_correction_uidx
    ON dt_dispensing_sessions (company_id, source_history_id)
    WHERE source_history_id IS NOT NULL AND state = 'open';

ALTER TABLE st_dispensing_history
    ADD COLUMN IF NOT EXISTS corrected_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS corrected_by_user_code VARCHAR(6)
        REFERENCES bt_users(user_code) ON DELETE RESTRICT;

COMMENT ON COLUMN dt_dispensing_sessions.source_history_id IS
    'History record being corrected in this open dispensing session.';
COMMENT ON COLUMN st_dispensing_history.corrected_at IS
    'Most recent time the prescription history content was corrected.';
COMMENT ON COLUMN st_dispensing_history.corrected_by_user_code IS
    'User who most recently corrected the prescription history content.';

COMMIT;
