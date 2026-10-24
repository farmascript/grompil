BEGIN;

INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
VALUES
    (0, 'company_registry_help', 'Zoek een onderneming op naam of ondernemingsnummer in de KBO. Naamzoekingen doorzoeken ook vestigingseenheden.', 'NL', now()),
    (0, 'company_kbo_establishment_unit', 'Vestigingseenheid', 'NL', now()),
    (0, 'company_registry_help', 'Recherchez une entreprise dans la BCE par nom ou numéro d’entreprise. La recherche par nom inclut aussi les unités d’établissement.', 'FR', now()),
    (0, 'company_kbo_establishment_unit', 'Unité d’établissement', 'FR', now()),
    (0, 'company_registry_help', 'Search the CBE by company name or enterprise number. Name searches also include establishment units.', 'EN', now()),
    (0, 'company_kbo_establishment_unit', 'Establishment unit', 'EN', now()),
    (0, 'company_registry_help', 'Suchen Sie in der ZDU nach Unternehmensname oder Unternehmensnummer. Die Namenssuche berücksichtigt auch Niederlassungseinheiten.', 'DE', now()),
    (0, 'company_kbo_establishment_unit', 'Niederlassungseinheit', 'DE', now())
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

COMMIT;
