-- Add platform-managed status choices for enterprise and VAT numbers.
BEGIN;

ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS enterprise_number_status VARCHAR(80) NOT NULL DEFAULT 'unknown';
ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS vat_number_status VARCHAR(80) NOT NULL DEFAULT 'unknown';

UPDATE companies
SET enterprise_number_status = 'known'
WHERE enterprise_number <> '' AND enterprise_number_status = 'unknown';
UPDATE companies
SET vat_number_status = 'known'
WHERE vat_number <> '' AND vat_number_status = 'unknown';

INSERT INTO dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES (
    'company_number_status', '_root', '@company_number_status_root@', NULL,
    'Shared status choices for enterprise and VAT numbers.', 0
)
ON CONFLICT (list_key, "key") DO NOTHING;

WITH root AS (
    SELECT id FROM dd_varia WHERE list_key = 'company_number_status' AND "key" = '_root'
)
INSERT INTO dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT 'company_number_status', item.status_key, '@' || item.translation_key || '@', root.id,
       'Shared status choice for company identification numbers.', item.sort_order
FROM root
CROSS JOIN (VALUES
    ('known', 'company_number_status_known', 10),
    ('pending', 'company_number_status_pending', 20),
    ('not_applicable', 'company_number_status_not_applicable', 30),
    ('unknown', 'company_number_status_unknown', 40)
) AS item(status_key, translation_key, sort_order)
ON CONFLICT (list_key, "key") DO NOTHING;

COMMIT;
