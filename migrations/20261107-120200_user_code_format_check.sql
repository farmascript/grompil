-- Require user codes to contain exactly six characters: three uppercase letters and three digits.
BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'users'::regclass AND conname = 'users_user_code_format_check'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT users_user_code_format_check CHECK (
            user_code ~ '^[A-Z0-9]{6}$'
            AND length(regexp_replace(user_code, '[A-Z]', '', 'g')) = 3
            AND length(regexp_replace(user_code, '[0-9]', '', 'g')) = 3
        ) NOT VALID;
    END IF;
END;
$$;

COMMIT;
