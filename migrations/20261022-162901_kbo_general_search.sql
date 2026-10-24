BEGIN;

ALTER TABLE kbo_lookup_attempts
    ADD COLUMN IF NOT EXISTS request_type VARCHAR(40) NOT NULL DEFAULT 'read_enterprise';

-- Keep the earlier pharmacy-register terminology migration safe to skip or
-- rerun: move those UI keys to the company registry namespace here as well.
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
        ('pharmacy_search_placeholder', 'company_registry_fagg_search_placeholder')
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

INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
VALUES
    (0, 'company_registry_fagg_help', 'Zoek een apotheek in het FAGG-register. Controleer daarna de onderneming bij de KBO.', 'NL', now()),
    (0, 'company_registry_fagg_only', 'Exclusief opzoeken via het FAGG-apothekenregister', 'NL', now()),
    (0, 'company_registry_fagg_search_placeholder', 'Naam, adres, postcode, gemeente of KBO-nummer', 'NL', now()),
    (0, 'company_registry_fagg_title', 'Apotheek zoeken in het FAGG-register', 'NL', now()),
    (0, 'company_registry_help', 'Zoek een onderneming op naam of ondernemingsnummer in de KBO.', 'NL', now()),
    (0, 'company_registry_title', 'Bedrijf zoeken in de KBO', 'NL', now()),
    (0, 'company_registry_unavailable', 'De KBO-zoekdienst is niet beschikbaar. Controleer de verbinding en probeer later opnieuw.', 'NL', now()),
    (0, 'company_registry_next', 'Volgende', 'NL', now()),
    (0, 'company_registry_no_results', 'Geen bedrijven gevonden.', 'NL', now()),
    (0, 'company_registry_not_found', 'Het gekozen bedrijf kon niet worden gevonden.', 'NL', now()),
    (0, 'company_registry_pagination', 'KBO-zoekresultaten', 'NL', now()),
    (0, 'company_registry_previous', 'Vorige', 'NL', now()),
    (0, 'company_registry_result_count', '%s resultaten · pagina %s van %s', 'NL', now()),
    (0, 'company_registry_search_button', 'Zoeken in KBO', 'NL', now()),
    (0, 'company_registry_search_label', 'Zoek een bedrijf', 'NL', now()),
    (0, 'company_registry_search_placeholder', 'Bedrijfsnaam of ondernemingsnummer', 'NL', now()),
    (0, 'company_kbo_search_query_invalid', 'Vul minstens 2 en maximaal 120 tekens in.', 'NL', now()),
    (0, 'company_kbo_establishment_unit', 'Vestigingseenheid', 'NL', now()),
    (0, 'company_kbo_service_quota_exceeded', 'Het gebruiksbudget of de daglimiet van de KBO-webservice is bereikt.', 'NL', now()),

    (0, 'company_registry_fagg_help', 'Recherchez une pharmacie dans le registre de l’AFMPS. Vérifiez ensuite l’entreprise auprès de la BCE.', 'FR', now()),
    (0, 'company_registry_fagg_only', 'Rechercher exclusivement dans le registre des pharmacies de l’AFMPS', 'FR', now()),
    (0, 'company_registry_fagg_search_placeholder', 'Nom, adresse, code postal, commune ou numéro BCE', 'FR', now()),
    (0, 'company_registry_fagg_title', 'Rechercher une pharmacie dans le registre de l’AFMPS', 'FR', now()),
    (0, 'company_registry_help', 'Recherchez une entreprise dans la BCE par nom ou numéro d’entreprise.', 'FR', now()),
    (0, 'company_registry_title', 'Rechercher une entreprise dans la BCE', 'FR', now()),
    (0, 'company_registry_unavailable', 'Le service de recherche BCE est indisponible. Vérifiez la connexion et réessayez plus tard.', 'FR', now()),
    (0, 'company_registry_next', 'Suivant', 'FR', now()),
    (0, 'company_registry_no_results', 'Aucune entreprise trouvée.', 'FR', now()),
    (0, 'company_registry_not_found', 'L’entreprise sélectionnée est introuvable.', 'FR', now()),
    (0, 'company_registry_pagination', 'Résultats de recherche BCE', 'FR', now()),
    (0, 'company_registry_previous', 'Précédent', 'FR', now()),
    (0, 'company_registry_result_count', '%s résultats · page %s sur %s', 'FR', now()),
    (0, 'company_registry_search_button', 'Rechercher dans la BCE', 'FR', now()),
    (0, 'company_registry_search_label', 'Rechercher une entreprise', 'FR', now()),
    (0, 'company_registry_search_placeholder', 'Nom de l’entreprise ou numéro d’entreprise', 'FR', now()),
    (0, 'company_kbo_search_query_invalid', 'Saisissez entre 2 et 120 caractères.', 'FR', now()),
    (0, 'company_kbo_establishment_unit', 'Unité d’établissement', 'FR', now()),
    (0, 'company_kbo_service_quota_exceeded', 'Le quota ou le solde journalier du service web BCE est épuisé.', 'FR', now()),

    (0, 'company_registry_fagg_help', 'Search for a pharmacy in the FAGG register. Then verify the company details with the CBE.', 'EN', now()),
    (0, 'company_registry_fagg_only', 'Search exclusively in the FAGG pharmacy register', 'EN', now()),
    (0, 'company_registry_fagg_search_placeholder', 'Name, address, postcode, municipality or CBE number', 'EN', now()),
    (0, 'company_registry_fagg_title', 'Find a pharmacy in the FAGG register', 'EN', now()),
    (0, 'company_registry_help', 'Search the CBE by company name or enterprise number.', 'EN', now()),
    (0, 'company_registry_title', 'Search for a company in the CBE', 'EN', now()),
    (0, 'company_registry_unavailable', 'The CBE search service is unavailable. Check the connection and try again later.', 'EN', now()),
    (0, 'company_registry_next', 'Next', 'EN', now()),
    (0, 'company_registry_no_results', 'No companies found.', 'EN', now()),
    (0, 'company_registry_not_found', 'The selected company could not be found.', 'EN', now()),
    (0, 'company_registry_pagination', 'CBE search results', 'EN', now()),
    (0, 'company_registry_previous', 'Previous', 'EN', now()),
    (0, 'company_registry_result_count', '%s results · page %s of %s', 'EN', now()),
    (0, 'company_registry_search_button', 'Search the CBE', 'EN', now()),
    (0, 'company_registry_search_label', 'Search for a company', 'EN', now()),
    (0, 'company_registry_search_placeholder', 'Company name or enterprise number', 'EN', now()),
    (0, 'company_kbo_search_query_invalid', 'Enter between 2 and 120 characters.', 'EN', now()),
    (0, 'company_kbo_establishment_unit', 'Establishment unit', 'EN', now()),
    (0, 'company_kbo_service_quota_exceeded', 'The CBE web service quota or daily balance has been exhausted.', 'EN', now()),

    (0, 'company_registry_fagg_help', 'Suchen Sie eine Apotheke im FAGG-Register. Prüfen Sie anschließend das Unternehmen bei der ZDU.', 'DE', now()),
    (0, 'company_registry_fagg_only', 'Ausschließlich im FAGG-Apothekenregister suchen', 'DE', now()),
    (0, 'company_registry_fagg_search_placeholder', 'Name, Adresse, Postleitzahl, Gemeinde oder ZDU-Nummer', 'DE', now()),
    (0, 'company_registry_fagg_title', 'Apotheke im FAGG-Register suchen', 'DE', now()),
    (0, 'company_registry_help', 'Suchen Sie in der ZDU nach Unternehmensname oder Unternehmensnummer.', 'DE', now()),
    (0, 'company_registry_title', 'Unternehmen in der ZDU suchen', 'DE', now()),
    (0, 'company_registry_unavailable', 'Der ZDU-Suchdienst ist nicht verfügbar. Prüfen Sie die Verbindung und versuchen Sie es später erneut.', 'DE', now()),
    (0, 'company_registry_next', 'Weiter', 'DE', now()),
    (0, 'company_registry_no_results', 'Keine Unternehmen gefunden.', 'DE', now()),
    (0, 'company_registry_not_found', 'Das ausgewählte Unternehmen wurde nicht gefunden.', 'DE', now()),
    (0, 'company_registry_pagination', 'ZDU-Suchergebnisse', 'DE', now()),
    (0, 'company_registry_previous', 'Zurück', 'DE', now()),
    (0, 'company_registry_result_count', '%s Ergebnisse · Seite %s von %s', 'DE', now()),
    (0, 'company_registry_search_button', 'In der ZDU suchen', 'DE', now()),
    (0, 'company_registry_search_label', 'Unternehmen suchen', 'DE', now()),
    (0, 'company_registry_search_placeholder', 'Unternehmensname oder Unternehmensnummer', 'DE', now()),
    (0, 'company_kbo_search_query_invalid', 'Geben Sie mindestens 2 und höchstens 120 Zeichen ein.', 'DE', now()),
    (0, 'company_kbo_establishment_unit', 'Niederlassungseinheit', 'DE', now()),
    (0, 'company_kbo_service_quota_exceeded', 'Das Kontingent oder Tagesguthaben des ZDU-Webdienstes ist aufgebraucht.', 'DE', now())
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

COMMIT;
