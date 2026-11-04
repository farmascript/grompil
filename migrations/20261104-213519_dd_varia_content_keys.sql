-- Make dd_varia content tokens descriptive and consistent without dots.
BEGIN;

UPDATE dd_varia
SET content = '@dd_varia_root_' || list_key || '@', updated_at = now()
WHERE "key" = '_root'
  AND content IN ('@dd_varia_root@', '@company_number_status_root@');

WITH token_map AS (
    SELECT id,
           substring(content FROM 2 FOR length(content) - 2) AS old_key,
           CASE
               WHEN length('ddv_' || list_key || '_' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END) <= 120
                   THEN 'ddv_' || list_key || '_' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END
               ELSE 'ddv_' || left(list_key, 40) || '_'
                   || left(CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END, 40) || '_'
                   || left(md5(list_key || ':' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END), 16)
           END AS new_key
    FROM dd_varia
    WHERE content ~ '^@ddv[._][A-Za-z0-9_.-]+@$'
)
INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
SELECT old.company_id, token_map.new_key, old."value", old.code, now()
FROM substitutions AS old
JOIN token_map ON old."key" = token_map.old_key
WHERE old.company_id = 0 AND token_map.old_key <> token_map.new_key
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

WITH token_map AS (
    SELECT id,
           substring(content FROM 2 FOR length(content) - 2) AS old_key,
           CASE
               WHEN length('ddv_' || list_key || '_' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END) <= 120
                   THEN 'ddv_' || list_key || '_' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END
               ELSE 'ddv_' || left(list_key, 40) || '_'
                   || left(CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END, 40) || '_'
                   || left(md5(list_key || ':' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END), 16)
           END AS new_key
    FROM dd_varia
    WHERE content ~ '^@ddv[._][A-Za-z0-9_.-]+@$'
)
DELETE FROM substitutions AS old
USING token_map
WHERE old.company_id = 0
  AND old."key" = token_map.old_key
  AND token_map.old_key <> token_map.new_key;

WITH token_map AS (
    SELECT id,
           substring(content FROM 2 FOR length(content) - 2) AS old_key,
           CASE
               WHEN length('ddv_' || list_key || '_' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END) <= 120
                   THEN 'ddv_' || list_key || '_' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END
               ELSE 'ddv_' || left(list_key, 40) || '_'
                   || left(CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END, 40) || '_'
                   || left(md5(list_key || ':' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END), 16)
           END AS new_key
    FROM dd_varia
    WHERE content ~ '^@ddv[._][A-Za-z0-9_.-]+@$'
)
UPDATE dd_varia AS item
SET content = '@' || token_map.new_key || '@', updated_at = now()
FROM token_map
WHERE item.id = token_map.id
  AND token_map.old_key <> token_map.new_key;

COMMIT;
