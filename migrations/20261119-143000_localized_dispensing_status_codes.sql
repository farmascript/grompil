BEGIN;

ALTER TABLE ft_dd_varia
    ADD COLUMN IF NOT EXISTS is_mutable BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS short_codes JSONB NOT NULL DEFAULT '{}'::JSONB;

DROP TRIGGER IF EXISTS dd_varia_protect_immutable ON ft_dd_varia;

CREATE UNIQUE INDEX IF NOT EXISTS dd_varia_short_code_nl_uidx
    ON ft_dd_varia (list_key, lower(btrim(short_codes->>'NL')))
    WHERE list_key = 'dispensing_statuses'
      AND short_codes ? 'NL' AND btrim(short_codes->>'NL') <> '';
CREATE UNIQUE INDEX IF NOT EXISTS dd_varia_short_code_fr_uidx
    ON ft_dd_varia (list_key, lower(btrim(short_codes->>'FR')))
    WHERE list_key = 'dispensing_statuses'
      AND short_codes ? 'FR' AND btrim(short_codes->>'FR') <> '';
CREATE UNIQUE INDEX IF NOT EXISTS dd_varia_short_code_en_uidx
    ON ft_dd_varia (list_key, lower(btrim(short_codes->>'EN')))
    WHERE list_key = 'dispensing_statuses'
      AND short_codes ? 'EN' AND btrim(short_codes->>'EN') <> '';
CREATE UNIQUE INDEX IF NOT EXISTS dd_varia_short_code_de_uidx
    ON ft_dd_varia (list_key, lower(btrim(short_codes->>'DE')))
    WHERE list_key = 'dispensing_statuses'
      AND short_codes ? 'DE' AND btrim(short_codes->>'DE') <> '';

-- Preserve row IDs while replacing the old language-specific display keys with
-- stable internal keys. Short labels are now stored separately per language.
UPDATE ft_dd_varia
SET "key" = CASE "key"
        WHEN 'z' THEN 'free_sale'
        WHEN 'v' THEN 'prescription'
        WHEN 'o' THEN 'incomplete_prescription'
        WHEN 'a' THEN 'incomplete_attestation'
        WHEN 's' THEN 'written_request'
        WHEN 'e' THEN 'veterinary'
        ELSE "key"
    END,
    short_codes = CASE "key"
        WHEN 'z' THEN '{"NL":"z","FR":"l","EN":"f","DE":"f"}'::JSONB
        WHEN 'v' THEN '{"NL":"v","FR":"o","EN":"p","DE":"r"}'::JSONB
        WHEN 'o' THEN '{"NL":"o","FR":"i","EN":"ip","DE":"ur"}'::JSONB
        WHEN 'a' THEN '{"NL":"a","FR":"a","EN":"ia","DE":"ub"}'::JSONB
        WHEN 's' THEN '{"NL":"s","FR":"d","EN":"w","DE":"s"}'::JSONB
        WHEN 'e' THEN '{"NL":"e","FR":"v","EN":"v","DE":"t"}'::JSONB
        ELSE short_codes
    END,
    is_mutable = FALSE,
    updated_at = now()
WHERE list_key = 'dispensing_statuses'
  AND "key" IN ('z', 'v', 'o', 'a', 's', 'e');

INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES ('dispensing_statuses', '_root', '@dd_varia_root_dispensing_statuses@', NULL,
        'Root for dispensing line statuses.', 0)
ON CONFLICT (list_key, "key") DO NOTHING;

WITH root AS (
    SELECT id FROM ft_dd_varia
    WHERE list_key = 'dispensing_statuses' AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order, is_mutable, short_codes)
SELECT 'dispensing_statuses', seed.status_key, '@' || seed.translation_key || '@', root.id,
       seed.info, seed.sort_order, FALSE, seed.short_codes::JSONB
FROM root
CROSS JOIN (VALUES
    ('free_sale', 'dispense_status_free', 'Free dispensing; no patient is required.', 10, '{"NL":"z","FR":"l","EN":"f","DE":"f"}'),
    ('prescription', 'dispense_status_prescription', 'Prescription dispensing.', 20, '{"NL":"v","FR":"o","EN":"p","DE":"r"}'),
    ('incomplete_prescription', 'dispense_status_incomplete_prescription', 'Incomplete prescription.', 30, '{"NL":"o","FR":"i","EN":"ip","DE":"ur"}'),
    ('incomplete_attestation', 'dispense_status_incomplete_attestation', 'Incomplete attestation.', 40, '{"NL":"a","FR":"a","EN":"ia","DE":"ub"}'),
    ('written_request', 'dispense_status_written_request', 'Written request.', 50, '{"NL":"s","FR":"d","EN":"w","DE":"s"}'),
    ('veterinary', 'dispense_status_veterinary', 'Veterinary dispensing.', 60, '{"NL":"e","FR":"v","EN":"v","DE":"t"}')
) AS seed(status_key, translation_key, info, sort_order, short_codes)
ON CONFLICT (list_key, "key") DO NOTHING;

UPDATE ft_dd_varia SET is_mutable = FALSE
WHERE list_key = 'dispensing_statuses';

CREATE OR REPLACE FUNCTION fn_dd_varia_protect_immutable() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.is_mutable = FALSE THEN
            RAISE EXCEPTION 'This lookup value is immutable.' USING ERRCODE = '55000';
        END IF;
        RETURN OLD;
    END IF;

    IF TG_OP = 'INSERT' THEN
        IF NEW.list_key = 'dispensing_statuses' THEN
            NEW.is_mutable := FALSE;
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.is_mutable = FALSE THEN
        RAISE EXCEPTION 'This lookup value is immutable.' USING ERRCODE = '55000';
    END IF;
    IF NEW.list_key = 'dispensing_statuses' THEN
        NEW.is_mutable := FALSE;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER dd_varia_protect_immutable
    BEFORE INSERT OR UPDATE OR DELETE ON ft_dd_varia
    FOR EACH ROW EXECUTE FUNCTION fn_dd_varia_protect_immutable();

COMMIT;
