BEGIN;

-- Dispensing status rows are immutable in normal administration, but their
-- internal metadata needs this one-time backfill from plain text to JSON.
ALTER TABLE ft_dd_varia DISABLE TRIGGER dd_varia_protect_immutable;

DO $$
DECLARE
    status_row RECORD;
    parsed_info JSONB;
    info_text TEXT;
BEGIN
    FOR status_row IN
        SELECT id, "key", info
        FROM ft_dd_varia
        WHERE list_key = 'dispensing_statuses'
          AND "key" <> '_root'
    LOOP
        BEGIN
            parsed_info := status_row.info::JSONB;
        EXCEPTION WHEN invalid_text_representation THEN
            parsed_info := NULL;
        END;

        IF jsonb_typeof(parsed_info) = 'object'
           AND jsonb_typeof(parsed_info->'text') = 'string'
           AND jsonb_typeof(parsed_info->'need_prescription') = 'boolean' THEN
            CONTINUE;
        END IF;

        info_text := status_row.info;
        UPDATE ft_dd_varia
        SET info = jsonb_build_object(
                'text', info_text,
                'need_prescription', status_row."key" IN ('prescription', 'v')
            )::TEXT,
            updated_at = now()
        WHERE id = status_row.id;
    END LOOP;
END;
$$;

ALTER TABLE ft_dd_varia ENABLE TRIGGER dd_varia_protect_immutable;

COMMIT;
