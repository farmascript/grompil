-- Validate the user-code format constraint after scripts/backfill_user_codes.php repairs existing values.
BEGIN;

ALTER TABLE users VALIDATE CONSTRAINT users_user_code_format_check;

COMMIT;
