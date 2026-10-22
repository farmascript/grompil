BEGIN;

-- KBO-resultaten beschrijven ondernemingen, ook wanneer de gekozen
-- registervermelding uit de apothekentabel komt.
WITH key_map(old_key, new_key) AS (
    VALUES
        ('pharmacy_already_created', 'company_already_created'),
        ('pharmacy_create_button', 'company_create_button'),
        ('pharmacy_create_failed', 'company_create_failed'),
        ('pharmacy_missing_kbo', 'company_kbo_number_missing')
)
INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
SELECT s.company_id, m.new_key, s."value", s.code, now()
FROM substitutions AS s
JOIN key_map AS m ON m.old_key = s."key"
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

DELETE FROM substitutions
WHERE "key" IN (
    'pharmacy_already_created',
    'pharmacy_create_button',
    'pharmacy_create_failed',
    'pharmacy_missing_kbo'
);

-- Generalize the shared translations while preserving any company-specific
-- overrides copied above.
UPDATE substitutions AS s
SET "value" = t."value", updated_at = now()
FROM (VALUES
    ('company_already_created', 'NL', 'Dit bedrijf is al aangemaakt.'),
    ('company_create_button', 'NL', 'Dit bedrijf aanmaken'),
    ('company_create_failed', 'NL', 'Het bedrijf kon niet worden aangemaakt. Controleer of het databaseschema is bijgewerkt.'),
    ('company_kbo_number_missing', 'NL', 'Voor dit bedrijf staat geen KBO-nummer in het register.'),
    ('company_already_created', 'FR', 'Cette entreprise a déjà été créée.'),
    ('company_create_button', 'FR', 'Créer cette entreprise'),
    ('company_create_failed', 'FR', 'L’entreprise n’a pas pu être créée. Vérifiez que le schéma de la base de données est à jour.'),
    ('company_kbo_number_missing', 'FR', 'Le registre ne contient pas de numéro BCE pour cette entreprise.'),
    ('company_already_created', 'EN', 'This company has already been created.'),
    ('company_create_button', 'EN', 'Create this company'),
    ('company_create_failed', 'EN', 'The company could not be created. Check that the database schema is up to date.'),
    ('company_kbo_number_missing', 'EN', 'The register does not contain a CBE number for this company.'),
    ('company_already_created', 'DE', 'Dieses Unternehmen wurde bereits angelegt.'),
    ('company_create_button', 'DE', 'Dieses Unternehmen anlegen'),
    ('company_create_failed', 'DE', 'Das Unternehmen konnte nicht angelegt werden. Prüfen Sie, ob das Datenbankschema aktuell ist.'),
    ('company_kbo_number_missing', 'DE', 'Das Register enthält keine ZDU-Nummer für dieses Unternehmen.')
) AS t("key", code, "value")
WHERE s.company_id = 0
  AND s."key" = t."key"
  AND upper(s.code) = t.code;

COMMIT;
