/**
 *
 * @date_cre: Sun 2026-11-15 18:42:41
 * @date_mut: Sun 2026-11-15 18:58:44
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Extends dt_user_contacts to store contacts directly for company customers.
 *
 */
BEGIN;

ALTER TABLE dt_user_contacts ALTER COLUMN user_code DROP NOT NULL;
ALTER TABLE dt_user_contacts ADD COLUMN IF NOT EXISTS customer_code VARCHAR(6);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'dt_user_contacts_owner_check'
            AND conrelid = 'dt_user_contacts'::regclass
    ) THEN
        ALTER TABLE dt_user_contacts
            ADD CONSTRAINT dt_user_contacts_owner_check CHECK (
                (user_code IS NOT NULL AND customer_code IS NULL)
                OR (user_code IS NULL AND customer_code IS NOT NULL AND company_id IS NOT NULL AND NOT is_login_email)
            );
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'dt_user_contacts_customer_owner_fk'
            AND conrelid = 'dt_user_contacts'::regclass
    ) THEN
        ALTER TABLE dt_user_contacts
            ADD CONSTRAINT dt_user_contacts_customer_owner_fk
            FOREIGN KEY (company_id, customer_code)
            REFERENCES bt_customers(company_id, customer_code) ON DELETE CASCADE;
    END IF;
END;
$$;

CREATE INDEX IF NOT EXISTS dt_user_contacts_customer_lookup_idx
    ON dt_user_contacts(company_id, customer_code, is_active, sort_order, id)
    WHERE customer_code IS NOT NULL;


INSERT INTO ft_substitutions (company_id, "key", "value", code)
VALUES
    (0, 'contact_manager_add', 'Contact toevoegen', 'NL'),
    (0, 'contact_manager_add_title', 'Nieuw contactgegeven', 'NL'),
    (0, 'contact_manager_choose_type', 'Kies een contactsoort', 'NL'),
    (0, 'contact_manager_count', 'Contactgegevens', 'NL'),
    (0, 'contact_manager_customer_title', 'Contactgegevens van de patiënt', 'NL'),
    (0, 'contact_manager_deleted', 'Contactgegeven verwijderd.', 'NL'),
    (0, 'contact_manager_empty', 'Er zijn nog geen contactgegevens opgeslagen.', 'NL'),
    (0, 'contact_manager_intro', 'Voeg contactgegevens toe, pas ze aan of verwijder ze. Verwijderen deactiveert het contactgegeven.', 'NL'),
    (0, 'contact_manager_invalid', 'Controleer de contactsoort en de ingevulde gegevens.', 'NL'),
    (0, 'contact_manager_label', 'Herkenningsnaam', 'NL'),
    (0, 'contact_manager_not_found', 'De patiënt of het contactgegeven werd niet gevonden.', 'NL'),
    (0, 'contact_manager_primary', 'Primair contactgegeven', 'NL'),
    (0, 'contact_manager_public', 'Publiek tonen', 'NL'),
    (0, 'contact_manager_saved', 'Contactgegeven opgeslagen.', 'NL'),
    (0, 'contact_manager_type', 'Contactsoort', 'NL'),
    (0, 'contact_manager_unavailable', 'Het contactgegeven kon niet worden opgeslagen. Probeer het later opnieuw.', 'NL'),
    (0, 'contact_manager_user_title', 'Contactgegevens van de gebruiker', 'NL'),
    (0, 'contact_manager_value', 'Contactgegeven', 'NL'),
    (0, 'user_code_label', 'Gebruikerscode', 'NL'),
    (0, 'contact_manager_add', 'Ajouter un contact', 'FR'),
    (0, 'contact_manager_add_title', 'Nouveau moyen de contact', 'FR'),
    (0, 'contact_manager_choose_type', 'Choisissez un type de contact', 'FR'),
    (0, 'contact_manager_count', 'Coordonnées', 'FR'),
    (0, 'contact_manager_customer_title', 'Coordonnées du patient', 'FR'),
    (0, 'contact_manager_deleted', 'Le contact a été supprimé.', 'FR'),
    (0, 'contact_manager_empty', 'Aucune coordonnée n’est encore enregistrée.', 'FR'),
    (0, 'contact_manager_intro', 'Ajoutez, modifiez ou supprimez des coordonnées. La suppression désactive la coordonnée.', 'FR'),
    (0, 'contact_manager_invalid', 'Vérifiez le type de contact et les informations saisies.', 'FR'),
    (0, 'contact_manager_label', 'Libellé', 'FR'),
    (0, 'contact_manager_not_found', 'Le patient ou le contact est introuvable.', 'FR'),
    (0, 'contact_manager_primary', 'Contact principal', 'FR'),
    (0, 'contact_manager_public', 'Afficher publiquement', 'FR'),
    (0, 'contact_manager_saved', 'Le contact a été enregistré.', 'FR'),
    (0, 'contact_manager_type', 'Type de contact', 'FR'),
    (0, 'contact_manager_unavailable', 'Impossible d’enregistrer le contact. Veuillez réessayer plus tard.', 'FR'),
    (0, 'contact_manager_user_title', 'Coordonnées de l’utilisateur', 'FR'),
    (0, 'contact_manager_value', 'Coordonnée', 'FR'),
    (0, 'user_code_label', 'Code utilisateur', 'FR'),
    (0, 'contact_manager_add', 'Add contact', 'EN'),
    (0, 'contact_manager_add_title', 'New contact detail', 'EN'),
    (0, 'contact_manager_choose_type', 'Choose a contact type', 'EN'),
    (0, 'contact_manager_count', 'Contact details', 'EN'),
    (0, 'contact_manager_customer_title', 'Patient contact details', 'EN'),
    (0, 'contact_manager_deleted', 'Contact detail removed.', 'EN'),
    (0, 'contact_manager_empty', 'No contact details have been saved yet.', 'EN'),
    (0, 'contact_manager_intro', 'Add, edit, or remove contact details. Removing a detail deactivates it.', 'EN'),
    (0, 'contact_manager_invalid', 'Check the contact type and entered information.', 'EN'),
    (0, 'contact_manager_label', 'Label', 'EN'),
    (0, 'contact_manager_not_found', 'The patient or contact detail could not be found.', 'EN'),
    (0, 'contact_manager_primary', 'Primary contact detail', 'EN'),
    (0, 'contact_manager_public', 'Show publicly', 'EN'),
    (0, 'contact_manager_saved', 'Contact detail saved.', 'EN'),
    (0, 'contact_manager_type', 'Contact type', 'EN'),
    (0, 'contact_manager_unavailable', 'The contact detail could not be saved. Please try again later.', 'EN'),
    (0, 'contact_manager_user_title', 'User contact details', 'EN'),
    (0, 'contact_manager_value', 'Contact detail', 'EN'),
    (0, 'user_code_label', 'User code', 'EN'),
    (0, 'contact_manager_add', 'Kontakt hinzufügen', 'DE'),
    (0, 'contact_manager_add_title', 'Neuer Kontakteintrag', 'DE'),
    (0, 'contact_manager_choose_type', 'Kontaktart auswählen', 'DE'),
    (0, 'contact_manager_count', 'Kontaktdaten', 'DE'),
    (0, 'contact_manager_customer_title', 'Kontaktdaten des Patienten', 'DE'),
    (0, 'contact_manager_deleted', 'Kontakteintrag entfernt.', 'DE'),
    (0, 'contact_manager_empty', 'Es wurden noch keine Kontaktdaten gespeichert.', 'DE'),
    (0, 'contact_manager_intro', 'Kontaktdaten hinzufügen, ändern oder entfernen. Beim Entfernen wird der Eintrag deaktiviert.', 'DE'),
    (0, 'contact_manager_invalid', 'Kontaktart und eingegebene Daten prüfen.', 'DE'),
    (0, 'contact_manager_label', 'Bezeichnung', 'DE'),
    (0, 'contact_manager_not_found', 'Patient oder Kontakteintrag nicht gefunden.', 'DE'),
    (0, 'contact_manager_primary', 'Primärer Kontakt', 'DE'),
    (0, 'contact_manager_public', 'Öffentlich anzeigen', 'DE'),
    (0, 'contact_manager_saved', 'Kontakteintrag gespeichert.', 'DE'),
    (0, 'contact_manager_type', 'Kontaktart', 'DE'),
    (0, 'contact_manager_unavailable', 'Der Kontakteintrag konnte nicht gespeichert werden. Bitte später erneut versuchen.', 'DE'),
    (0, 'contact_manager_user_title', 'Kontaktdaten des Benutzers', 'DE'),
    (0, 'contact_manager_value', 'Kontaktdaten', 'DE'),
    (0, 'user_code_label', 'Benutzercode', 'DE')
ON CONFLICT (company_id, "key", code) DO NOTHING;
COMMIT;