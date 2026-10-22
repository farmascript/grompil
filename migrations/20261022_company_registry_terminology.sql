BEGIN;

-- The KBO lookup can return businesses from every sector. Use company
-- terminology for its registry search, even though the current source table
-- also stores pharmacy registry entries.
WITH key_map(old_key, new_key) AS (
    VALUES
        ('pharmacy_directory_help', 'company_registry_help'),
        ('pharmacy_directory_title', 'company_registry_title'),
        ('pharmacy_directory_unavailable', 'company_registry_unavailable'),
        ('pharmacy_next', 'company_registry_next'),
        ('pharmacy_no_results', 'company_registry_no_results'),
        ('pharmacy_not_found', 'company_registry_not_found'),
        ('pharmacy_pagination', 'company_registry_pagination'),
        ('pharmacy_previous', 'company_registry_previous'),
        ('pharmacy_result_count', 'company_registry_result_count'),
        ('pharmacy_search_button', 'company_registry_search_button'),
        ('pharmacy_search_label', 'company_registry_search_label'),
        ('pharmacy_search_placeholder', 'company_registry_search_placeholder')
)
INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
SELECT s.company_id, m.new_key, s."value", s.code, now()
FROM substitutions AS s
JOIN key_map AS m ON m.old_key = s."key"
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

DELETE FROM substitutions
WHERE "key" IN (
    'pharmacy_directory_help',
    'pharmacy_directory_title',
    'pharmacy_directory_unavailable',
    'pharmacy_next',
    'pharmacy_no_results',
    'pharmacy_not_found',
    'pharmacy_pagination',
    'pharmacy_previous',
    'pharmacy_result_count',
    'pharmacy_search_button',
    'pharmacy_search_label',
    'pharmacy_search_placeholder'
);

UPDATE substitutions AS s
SET "value" = t."value", updated_at = now()
FROM (VALUES
    ('company_registry_help', 'NL', 'Zoek in het ondernemingsregister. Controleer de gegevens bij de KBO voordat je een bedrijf aanmaakt.'),
    ('company_registry_title', 'NL', 'Bedrijf zoeken in het register'),
    ('company_registry_unavailable', 'NL', 'Het ondernemingsregister is niet beschikbaar. Controleer of de tabel bestaat en leesbaar is voor de applicatiegebruiker.'),
    ('company_registry_no_results', 'NL', 'Geen bedrijven gevonden.'),
    ('company_registry_not_found', 'NL', 'Het gekozen bedrijf kon niet worden gevonden.'),
    ('company_registry_pagination', 'NL', 'Pagina’s met bedrijven'),
    ('company_registry_search_label', 'NL', 'Zoek bedrijf'),
    ('company_registry_search_placeholder', 'NL', 'Naam, adres, postcode, gemeente of KBO-nummer'),
    ('company_registry_help', 'FR', 'Recherchez dans le registre des entreprises. Vérifiez les données auprès de la BCE avant de créer une entreprise.'),
    ('company_registry_title', 'FR', 'Rechercher une entreprise dans le registre'),
    ('company_registry_unavailable', 'FR', 'Le registre des entreprises est indisponible. Vérifiez que la table existe et que l’utilisateur applicatif peut la lire.'),
    ('company_registry_no_results', 'FR', 'Aucune entreprise trouvée.'),
    ('company_registry_not_found', 'FR', 'L’entreprise sélectionnée est introuvable.'),
    ('company_registry_pagination', 'FR', 'Pages des entreprises'),
    ('company_registry_search_label', 'FR', 'Rechercher une entreprise'),
    ('company_registry_search_placeholder', 'FR', 'Nom, adresse, code postal, commune ou numéro BCE'),
    ('company_registry_help', 'EN', 'Search the business register. Check the details with the CBE before creating a company.'),
    ('company_registry_title', 'EN', 'Find a company in the register'),
    ('company_registry_unavailable', 'EN', 'The business register is unavailable. Check that the table exists and the application user can read it.'),
    ('company_registry_no_results', 'EN', 'No companies found.'),
    ('company_registry_not_found', 'EN', 'The selected company could not be found.'),
    ('company_registry_pagination', 'EN', 'Company pages'),
    ('company_registry_search_label', 'EN', 'Search company'),
    ('company_registry_search_placeholder', 'EN', 'Name, address, postcode, municipality or CBE number'),
    ('company_registry_help', 'DE', 'Durchsuchen Sie das Unternehmensregister. Prüfen Sie die Angaben bei der ZDU, bevor Sie ein Unternehmen anlegen.'),
    ('company_registry_title', 'DE', 'Unternehmen im Register suchen'),
    ('company_registry_unavailable', 'DE', 'Das Unternehmensregister ist nicht verfügbar. Prüfen Sie, ob die Tabelle vorhanden und für den Anwendungsbenutzer lesbar ist.'),
    ('company_registry_no_results', 'DE', 'Keine Unternehmen gefunden.'),
    ('company_registry_not_found', 'DE', 'Das ausgewählte Unternehmen wurde nicht gefunden.'),
    ('company_registry_pagination', 'DE', 'Unternehmensseiten'),
    ('company_registry_search_label', 'DE', 'Unternehmen suchen'),
    ('company_registry_search_placeholder', 'DE', 'Name, Adresse, Postleitzahl, Gemeinde oder ZDU-Nummer')
) AS t("key", code, "value")
WHERE s.company_id = 0
  AND s."key" = t."key"
  AND upper(s.code) = t.code;

COMMIT;
