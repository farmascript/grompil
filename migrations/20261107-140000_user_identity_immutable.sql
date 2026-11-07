-- Preserve user identity and account history: deactivate accounts instead of deleting them.
BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM users
        WHERE user_code IS NULL
           OR user_code !~ '^[A-Z0-9]{6}$'
           OR length(regexp_replace(user_code, '[A-Z]', '', 'g')) <> 3
           OR length(regexp_replace(user_code, '[0-9]', '', 'g')) <> 3
    ) THEN
        RAISE EXCEPTION 'Cannot protect user identity: backfill and validate all users.user_code values first.';
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION grompil_protect_user_identity()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'User accounts cannot be deleted; set is_active = false instead.'
            USING ERRCODE = '55000';
    END IF;
    IF NEW.user_code IS DISTINCT FROM OLD.user_code THEN
        RAISE EXCEPTION 'users.user_code is immutable after account creation.'
            USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS users_user_code_immutable ON users;
CREATE TRIGGER users_user_code_immutable
    BEFORE UPDATE OF user_code ON users
    FOR EACH ROW EXECUTE FUNCTION grompil_protect_user_identity();

DROP TRIGGER IF EXISTS users_delete_forbidden ON users;
CREATE TRIGGER users_delete_forbidden
    BEFORE DELETE ON users
    FOR EACH ROW EXECUTE FUNCTION grompil_protect_user_identity();

COMMIT;
