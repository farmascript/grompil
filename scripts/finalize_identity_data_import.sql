-- Run this against the target database after importing data with explicit IDs.
-- It restores GENERATED ALWAYS and advances each identity sequence to the
-- maximum imported ID, so future inserts continue without collisions.

BEGIN;

DO $$
DECLARE
    identity_column RECORD;
    identity_sequence TEXT;
    maximum_id BIGINT;
BEGIN
    IF current_database() <> 'grompilc_test' THEN
        RAISE EXCEPTION 'This script is intended only for database grompilc_test; current database is %.',
            current_database();
    END IF;

    FOR identity_column IN
        SELECT table_schema, table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND is_identity = 'YES'
    LOOP
        EXECUTE format(
            'SELECT max(%I) FROM %I.%I',
            identity_column.column_name,
            identity_column.table_schema,
            identity_column.table_name
        ) INTO maximum_id;

        identity_sequence := pg_get_serial_sequence(
            format('%I.%I', identity_column.table_schema, identity_column.table_name),
            identity_column.column_name
        );

        EXECUTE format(
            'ALTER TABLE %I.%I ALTER COLUMN %I SET GENERATED ALWAYS',
            identity_column.table_schema,
            identity_column.table_name,
            identity_column.column_name
        );

        IF identity_sequence IS NULL THEN
            RAISE EXCEPTION 'No identity sequence found for %.%.%',
                identity_column.table_schema,
                identity_column.table_name,
                identity_column.column_name;
        ELSIF maximum_id IS NULL THEN
            PERFORM setval(identity_sequence::regclass, 1, FALSE);
        ELSE
            PERFORM setval(identity_sequence::regclass, maximum_id, TRUE);
        END IF;
    END LOOP;
END;
$$;

COMMIT;
