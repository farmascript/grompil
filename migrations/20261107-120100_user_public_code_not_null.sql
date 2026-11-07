-- Finalize the user code migration after scripts/backfill_user_codes.php succeeds.
BEGIN;

ALTER TABLE users ALTER COLUMN user_code SET NOT NULL;

COMMIT;
