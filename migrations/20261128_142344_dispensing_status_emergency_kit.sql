BEGIN;

WITH root AS (
    SELECT id
    FROM ft_dd_varia
    WHERE list_key = 'dispensing_statuses' AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order, is_mutable, short_codes)
SELECT 'dispensing_statuses', 'emergency_kit', '@dispense_status_emergency_kit@', root.id,
       'Urgentietrousse dispensing status.', 70, FALSE,
       '{"NL":"u","FR":"u","EN":"u","DE":"u"}'::JSONB
FROM root
ON CONFLICT (list_key, "key") DO NOTHING;

COMMIT;
