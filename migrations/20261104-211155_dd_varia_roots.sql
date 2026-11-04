-- Ensure each dd_varia list has one root and attach its existing top-level values.
BEGIN;

INSERT INTO dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT DISTINCT item.list_key, '_root', '@dd_varia_root@', NULL::BIGINT,
       'Root entry for this lookup list.', 0
FROM dd_varia AS item
ON CONFLICT (list_key, "key") DO NOTHING;

UPDATE dd_varia AS item
SET parent_id = root.id, updated_at = now()
FROM dd_varia AS root
WHERE item.list_key = root.list_key
  AND root."key" = '_root'
  AND root.parent_id IS NULL
  AND item.id <> root.id
  AND item.parent_id IS NULL;

WITH token_map AS (
    SELECT id,
           'ddv_' || id::TEXT AS old_key,
           CASE
               WHEN length('ddv.' || list_key || '.' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END) <= 120
                   THEN 'ddv.' || list_key || '.' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END
               ELSE 'ddv.' || left(list_key, 40) || '.'
                   || left(CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END, 40) || '.'
                   || left(md5(list_key || ':' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END), 16)
           END AS new_key
    FROM dd_varia
    WHERE content ~ '^@ddv_[0-9]+@$'
)
INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
SELECT old.company_id, token_map.new_key, old."value", old.code, now()
FROM substitutions AS old
JOIN token_map ON old."key" = token_map.old_key
WHERE old.company_id = 0
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

WITH token_map AS (
    SELECT id,
           'ddv_' || id::TEXT AS old_key,
           CASE
               WHEN length('ddv.' || list_key || '.' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END) <= 120
                   THEN 'ddv.' || list_key || '.' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END
               ELSE 'ddv.' || left(list_key, 40) || '.'
                   || left(CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END, 40) || '.'
                   || left(md5(list_key || ':' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END), 16)
           END AS new_key
    FROM dd_varia
    WHERE content ~ '^@ddv_[0-9]+@$'
)
DELETE FROM substitutions AS old
USING token_map
WHERE old.company_id = 0 AND old."key" = token_map.old_key;

WITH token_map AS (
    SELECT id,
           CASE
               WHEN length('ddv.' || list_key || '.' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END) <= 120
                   THEN 'ddv.' || list_key || '.' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END
               ELSE 'ddv.' || left(list_key, 40) || '.'
                   || left(CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END, 40) || '.'
                   || left(md5(list_key || ':' || CASE WHEN "key" = '_root' THEN 'root' ELSE "key" END), 16)
           END AS new_key
    FROM dd_varia
    WHERE content ~ '^@ddv_[0-9]+@$'
)
UPDATE dd_varia AS item
SET content = '@' || token_map.new_key || '@', updated_at = now()
FROM token_map
WHERE item.id = token_map.id;

COMMIT;
