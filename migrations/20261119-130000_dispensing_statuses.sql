BEGIN;

INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES ('dispensing_statuses', '_root', '@dd_varia_root_dispensing_statuses@', NULL,
        'Root for dispensing line statuses.', 0)
ON CONFLICT (list_key, "key") DO NOTHING;

WITH root AS (
    SELECT id FROM ft_dd_varia
    WHERE list_key = 'dispensing_statuses' AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT 'dispensing_statuses', seed.status_key, '@' || seed.translation_key || '@', root.id,
       seed.info, seed.sort_order
FROM root
CROSS JOIN (VALUES
    ('z', 'dispense_status_free', 'Free dispensing; no patient is required.', 10),
    ('v', 'dispense_status_prescription', 'Prescription dispensing.', 20),
    ('o', 'dispense_status_incomplete_prescription', 'Incomplete prescription.', 30),
    ('a', 'dispense_status_incomplete_attestation', 'Incomplete attestation.', 40),
    ('s', 'dispense_status_written_request', 'Written request.', 50),
    ('e', 'dispense_status_veterinary', 'Veterinary dispensing.', 60)
) AS seed(status_key, translation_key, info, sort_order)
ON CONFLICT (list_key, "key") DO NOTHING;

COMMIT;
