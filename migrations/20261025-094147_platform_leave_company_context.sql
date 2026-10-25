BEGIN;

INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
VALUES
    (0, 'platform_leave_company', 'Terug naar platformbeheer', 'NL', now()),
    (0, 'platform_leave_company', 'Retour à l’administration de la plateforme', 'FR', now()),
    (0, 'platform_leave_company', 'Back to platform administration', 'EN', now()),
    (0, 'platform_leave_company', 'Zurück zur Plattformverwaltung', 'DE', now()),
    (0, 'platform_leave_company_warning', 'Je blijft ingelogd als platformbeheerder. De actieve company wordt ook in andere tabbladen van deze browser verlaten. Doorgaan?', 'NL', now()),
    (0, 'platform_leave_company_warning', 'Vous resterez connecté en tant qu’administrateur de la plateforme. Le contexte de société sera également quitté dans les autres onglets de ce navigateur. Continuer ?', 'FR', now()),
    (0, 'platform_leave_company_warning', 'You will remain signed in as a platform administrator. The active company context will also be cleared in other tabs in this browser. Continue?', 'EN', now()),
    (0, 'platform_leave_company_warning', 'Sie bleiben als Plattformadministrator angemeldet. Der aktive Unternehmenskontext wird auch in den anderen Tabs dieses Browsers beendet. Fortfahren?', 'DE', now())
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

COMMIT;
