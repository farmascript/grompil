-- 1. Hernummer alle bestaande ID's opeenvolgend vanaf 1
WITH gehernummerd AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS nieuw_id
    FROM translations
)
UPDATE translations t
SET id = g.nieuw_id
FROM gehernummerd g
WHERE t.id = g.id;

-- 2. Zet de automatische teller synchroon met het hoogste nieuwe ID
SELECT setval(pg_get_serial_sequence('translations', 'id'), COALESCE(MAX(id), 1)) FROM translations;
