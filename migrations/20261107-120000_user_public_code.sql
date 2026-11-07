-- Add an independent, unique public identifier to each user account.
BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS user_code VARCHAR(6);
CREATE UNIQUE INDEX IF NOT EXISTS users_user_code_uidx ON users (user_code);

COMMIT;
