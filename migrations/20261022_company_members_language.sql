BEGIN;

INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
VALUES
    (0, 'company_members_existing_heading', 'Bestaande medewerker toewijzen', 'NL', now()),
    (0, 'company_members_add_help', 'Deze lijst bevat algemene medewerkersaccounts die nog niet aan een bedrijf zijn gekoppeld. Aanvragen via deze bedrijfspagina verschijnen hieronder met de status In afwachting.', 'NL', now()),
    (0, 'company_members_no_unassigned_users', 'Geen niet-toegewezen medewerkers gevonden.', 'NL', now()),
    (0, 'company_members_choose_role', 'Kies een rol', 'NL', now()),
    (0, 'company_members_workflow_help', 'Aanvragen van medewerkers die zich via deze bedrijfspagina registreren, verschijnen hieronder met de status In afwachting. Wijs daar een rol toe en keur de aanvraag goed. De lijst met bestaande gebruikers is alleen beschikbaar voor platformbeheerders.', 'NL', now()),
    (0, 'company_members_existing_heading', 'Associer un membre existant', 'FR', now()),
    (0, 'company_members_add_help', 'Cette liste contient les comptes de personnel qui ne sont associés à aucune société. Les inscriptions via cette page apparaissent dans le tableau ci-dessous avec le statut En attente.', 'FR', now()),
    (0, 'company_members_no_unassigned_users', 'Aucun membre du personnel non associé n’a été trouvé.', 'FR', now()),
    (0, 'company_members_choose_role', 'Choisir un rôle', 'FR', now()),
    (0, 'company_members_workflow_help', 'Les demandes du personnel inscrit via cette page apparaissent ci-dessous avec le statut En attente. Attribuez un rôle et approuvez-les. La liste des utilisateurs existants est réservée à l’administration de la plateforme.', 'FR', now()),
    (0, 'company_members_existing_heading', 'Assign an existing staff member', 'EN', now()),
    (0, 'company_members_add_help', 'This list contains general staff accounts that are not linked to a company. Requests submitted through this company page appear below with the status Pending.', 'EN', now()),
    (0, 'company_members_no_unassigned_users', 'No unassigned staff members found.', 'EN', now()),
    (0, 'company_members_choose_role', 'Choose a role', 'EN', now()),
    (0, 'company_members_workflow_help', 'Requests from staff who register through this company page appear below as Pending. Assign a role there and approve the request. The existing-user list is only available to platform administrators.', 'EN', now()),
    (0, 'company_members_existing_heading', 'Bestehenden Mitarbeiter zuordnen', 'DE', now()),
    (0, 'company_members_add_help', 'Diese Liste enthält allgemeine Mitarbeiterkonten, die noch keinem Unternehmen zugeordnet sind. Anträge über diese Unternehmensseite erscheinen unten mit dem Status Ausstehend.', 'DE', now()),
    (0, 'company_members_no_unassigned_users', 'Keine nicht zugeordneten Mitarbeiter gefunden.', 'DE', now()),
    (0, 'company_members_choose_role', 'Rolle auswählen', 'DE', now()),
    (0, 'company_members_workflow_help', 'Anträge von Mitarbeitern, die sich über diese Unternehmensseite registrieren, erscheinen unten mit dem Status Ausstehend. Weisen Sie dort eine Rolle zu und genehmigen Sie den Antrag. Die Liste bestehender Benutzer ist nur für Plattformadministratoren verfügbar.', 'DE', now())
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

COMMIT;
