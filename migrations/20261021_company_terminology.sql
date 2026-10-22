BEGIN;

-- KBO-gegevens beschrijven ondernemingen uit alle sectoren. Verplaats de
-- KBO-vertalingen naar algemene company_kbo-keys. De apotheekregisterkeys
-- blijven onder pharmacy_* staan.
WITH key_map(old_key, new_key) AS (
    VALUES
        ('pharmacy_kbo_address', 'company_kbo_address'),
        ('pharmacy_kbo_authentication_failed', 'company_kbo_authentication_failed'),
        ('pharmacy_kbo_details', 'company_kbo_details'),
        ('pharmacy_kbo_failed', 'company_kbo_failed'),
        ('pharmacy_kbo_lookup', 'company_kbo_lookup'),
        ('pharmacy_kbo_not_configured', 'company_kbo_not_configured'),
        ('pharmacy_kbo_quota_label', 'company_kbo_quota_label'),
        ('pharmacy_kbo_quota_period', 'company_kbo_quota_period'),
        ('pharmacy_kbo_service_unavailable', 'company_kbo_service_unavailable'),
        ('pharmacy_kbo_status', 'company_kbo_status'),
        ('pharmacy_kbo_terms_not_accepted', 'company_kbo_terms_not_accepted'),
        ('pharmacy_kbo_unavailable', 'company_kbo_unavailable'),
        ('pharmacy_kbo_weekly_limit', 'company_kbo_weekly_limit'),
        ('pharmacy_lookup_first', 'company_kbo_lookup_first')
)
INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
SELECT s.company_id, m.new_key, s."value", s.code, now()
FROM substitutions AS s
JOIN key_map AS m ON m.old_key = s."key"
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

DELETE FROM substitutions
WHERE "key" IN (
    'pharmacy_kbo_address',
    'pharmacy_kbo_authentication_failed',
    'pharmacy_kbo_details',
    'pharmacy_kbo_failed',
    'pharmacy_kbo_lookup',
    'pharmacy_kbo_not_configured',
    'pharmacy_kbo_quota_label',
    'pharmacy_kbo_quota_period',
    'pharmacy_kbo_service_unavailable',
    'pharmacy_kbo_status',
    'pharmacy_kbo_terms_not_accepted',
    'pharmacy_kbo_unavailable',
    'pharmacy_kbo_weekly_limit',
    'pharmacy_lookup_first'
);

-- Generalize terminology in every language and company override, while
-- leaving all pharmacy-register labels and messages unchanged.
UPDATE substitutions
SET "value" = CASE upper(code)
    WHEN 'NL' THEN
        regexp_replace(
            regexp_replace(
                regexp_replace(
                    regexp_replace(
                        regexp_replace("value", 'apotheekwebsite', 'bedrijfswebsite', 'gi'),
                        'apotheekrol', 'bedrijfsrol', 'gi'
                    ),
                    'apotheekbeheer', 'bedrijfsbeheer', 'gi'
                ),
                'apotheken', 'bedrijven', 'gi'
            ),
            'apotheek', 'bedrijf', 'gi'
        )
    WHEN 'FR' THEN
        regexp_replace(
            regexp_replace(
                regexp_replace(
                    regexp_replace("value", 'de la pharmacie', 'de l’entreprise', 'gi'),
                    'à la pharmacie', 'à l’entreprise', 'gi'
                ),
                'pharmacies', 'entreprises', 'gi'
            ),
            'pharmacie', 'entreprise', 'gi'
        )
    WHEN 'EN' THEN regexp_replace(
        regexp_replace("value", 'pharmacies', 'companies', 'gi'),
        'pharmacy', 'company', 'gi'
    )
    WHEN 'DE' THEN
        regexp_replace(
            regexp_replace(
                regexp_replace(
                    regexp_replace(
                        regexp_replace("value", 'Apothekenwebsite', 'Unternehmenswebsite', 'gi'),
                        'Apothekenverwaltung', 'Unternehmensverwaltung', 'gi'
                    ),
                    'Apothekenumgebung', 'Unternehmensumgebung', 'gi'
                ),
                'Apotheken', 'Unternehmen', 'gi'
            ),
            'Apotheke', 'Unternehmen', 'gi'
        )
    ELSE "value"
END,
updated_at = now()
WHERE left("key", 9) <> 'pharmacy_'
  AND (
      (upper(code) = 'NL' AND "value" ~* 'apotheek')
      OR (upper(code) = 'FR' AND "value" ~* 'pharmacie')
      OR (upper(code) = 'EN' AND "value" ~* 'pharmacy')
      OR (upper(code) = 'DE' AND "value" ~* 'apotheke')
  );

-- Repair common compounds and articles after the terminology replacement.
UPDATE substitutions
SET "value" = CASE upper(code)
    WHEN 'NL' THEN
        regexp_replace(
            regexp_replace(
                regexp_replace(
                    regexp_replace(
                        regexp_replace(
                            regexp_replace(
                                regexp_replace(
                                    regexp_replace(
                                        regexp_replace("value", 'bedrijftoegang', 'bedrijfstoegang', 'gi'),
                                        'bedrijfrol', 'bedrijfsrol', 'gi'
                                    ),
                                    'bedrijfteam', 'bedrijfsteam', 'gi'
                                ),
                                'deze bedrijf', 'dit bedrijf', 'gi'
                            ),
                            'de bedrijf', 'het bedrijf', 'gi'
                        ),
                        'actieve bedrijf', 'actief bedrijf', 'gi'
                    ),
                    'Ontdek de bedrijf', 'Ontdek het bedrijf', 'gi'
                ),
                'de bedrijf,', 'het bedrijf,', 'gi'
            ),
            'van de online bedrijf', 'van de online bedrijfsomgeving', 'gi'
        )
    WHEN 'FR' THEN
        regexp_replace(
            regexp_replace(
                regexp_replace(
                    regexp_replace(
                        regexp_replace(
                            regexp_replace("value", 'Personnel de entreprise', 'Personnel de l’entreprise', 'gi'),
                            'de entreprise', 'd’entreprise', 'gi'
                        ),
                        'la entreprise', 'l’entreprise', 'gi'
                    ),
                    'Découvrir la entreprise', 'Découvrir l’entreprise', 'gi'
                ),
                'site public de entreprise', 'site public d’entreprise', 'gi'
            ),
            'équipe de entreprise', 'équipe d’entreprise', 'gi'
        )
    WHEN 'EN' THEN "value"
    WHEN 'DE' THEN
        regexp_replace(
            regexp_replace(
                regexp_replace(
                    regexp_replace(
                        regexp_replace(
                            regexp_replace(
                                regexp_replace(
                                    regexp_replace(
                                        regexp_replace(
                                            regexp_replace("value", 'des Unternehmensnumgebung', 'Unternehmensumgebung', 'gi'),
                                            'Unternehmenpersonal', 'Unternehmenspersonal', 'gi'
                                        ),
                                        'Unternehmenzugriff', 'Unternehmenszugriff', 'gi'
                                    ),
                                    'Unternehmenrolle', 'Unternehmensrolle', 'gi'
                                ),
                                'Unternehmeneinstellungen', 'Unternehmenseinstellungen', 'gi'
                            ),
                            'Unternehmenumgebung', 'Unternehmensumgebung', 'gi'
                        ),
                        'Unternehmenteam', 'Unternehmensteam', 'gi'
                    ),
                    'diese Unternehmen', 'dieses Unternehmen', 'gi'
                ),
                'Ihre Unternehmen', 'Ihr Unternehmen', 'gi'
            ),
            'einer Unternehmen', 'einem Unternehmen', 'gi'
        )
    ELSE "value"
END,
updated_at = now()
WHERE left("key", 9) <> 'pharmacy_';

UPDATE substitutions
SET "value" = CASE upper(code)
    WHEN 'NL' THEN regexp_replace("value", 'het bedrijfswebsite', 'de bedrijfswebsite', 'gi')
    WHEN 'FR' THEN regexp_replace("value", 'équipe d’entreprise', 'équipe de l’entreprise', 'gi')
    ELSE "value"
END,
updated_at = now()
WHERE left("key", 9) <> 'pharmacy_';


-- Publish curated general-purpose values from the four language files.
UPDATE substitutions AS s
SET "value" = v.translation, updated_at = now()
FROM (VALUES
        ('acronym_help', 'NL', 'Kies een unieke loginnaam binnen dit bedrijf. Je kunt daarna met je acroniem of e-mailadres inloggen.'),
        ('acronym_taken', 'NL', 'Dit acroniem is al in gebruik of valt samen met een ander loginadres binnen dit bedrijf.'),
        ('admin_intro', 'NL', 'Kies een onderdeel om de bedrijfsomgeving te beheren.'),
        ('company_access_pending', 'NL', 'Dit account heeft nog geen toegang tot dit bedrijf. Vraag het bedrijfsbeheerder om toegang.'),
        ('company_edit', 'NL', 'bedrijf  aanpassen'),
        ('company_invalid', 'NL', 'Controleer het bedrijfsnaam, unieke code, landcode, taal en facturatie-e-mailadres.'),
        ('company_member_save_failed', 'NL', 'Toegang tot het bedrijf kon niet worden bijgewerkt.'),
        ('company_member_saved', 'NL', 'Toegang tot het bedrijf bijgewerkt.'),
        ('company_members_empty', 'NL', 'Er zijn nog geen medewerkers aan dit bedrijf gekoppeld.'),
        ('company_members_heading', 'NL', 'bedrijfsmedewerkers'),
        ('company_members_intro', 'NL', 'Koppel bestaande gebruikers aan een bedrijf en wijs hun een rol toe.'),
        ('company_name_label', 'NL', 'Bedrijfsnaam'),
        ('company_new', 'NL', 'Nieuw bedrijf'),
        ('company_not_found', 'NL', 'Bedrijf niet gevonden.'),
        ('company_other_session', 'NL', 'Je bent aangemeld bij een ander bedrijf. Meld je af om met een account voor dit bedrijf in te loggen.'),
        ('company_permission_roles', 'NL', 'bedrijfsrollen beheren'),
        ('company_permission_settings', 'NL', 'bedrijfsinstellingen beheren'),
        ('company_permission_translations', 'NL', 'Bedrijfsteksten beheren'),
        ('company_permission_users', 'NL', 'bedrijfsmedewerkers beheren'),
        ('company_public_disabled', 'NL', 'De bedrijfswebsite is nu verborgen.'),
        ('company_public_enabled', 'NL', 'De bedrijfswebsite is nu openbaar.'),
        ('company_public_label', 'NL', 'Publieke bedrijfswebsite tonen'),
        ('company_public_update_failed', 'NL', 'De zichtbaarheid van de bedrijfswebsite kon niet worden aangepast.'),
        ('company_role_save_failed', 'NL', 'bedrijfsrol kon niet worden opgeslagen. Controleer of de naam uniek is binnen dit bedrijf.'),
        ('company_role_saved', 'NL', 'Bedrijfsrol opgeslagen.'),
        ('company_roles_edit', 'NL', 'bedrijfsrol aanpassen'),
        ('company_roles_heading', 'NL', 'Bedrijfsrollen'),
        ('company_roles_intro', 'NL', 'Deze rollen en hun rechten gelden alleen binnen dit bedrijf. Systeemrollen blijven vast staan.'),
        ('company_roles_new', 'NL', 'Nieuwe bedrijfsrol'),
        ('company_save_failed', 'NL', 'Bedrijf kon niet worden opgeslagen. Controleer de unieke code en ondernemingsnummer.'),
        ('company_saved', 'NL', 'Bedrijf opgeslagen.'),
        ('company_select_intro', 'NL', 'Selecteer een bedrijf of voeg het toe aan het platform.'),
        ('company_store_media_help', 'NL', 'Plaats de afbeeldingsbestanden in de mediamap van dit bedrijf en vul alleen de bestandsnaam in.'),
        ('company_translation_saved', 'NL', 'Bedrijfsteksten opgeslagen en gepubliceerd.'),
        ('company_translations_heading', 'NL', 'Bedrijfsspecifieke vertalingen'),
        ('login_intro', 'NL', 'Log in op de beveiligde werkomgeving van jouw bedrijf.'),
        ('manager_add_company_group', 'NL', '＋ Bedrijfsblok'),
        ('manager_company', 'NL', 'Bedrijf'),
        ('manager_company_group_title', 'NL', 'Bedrijfsblok toevoegen'),
        ('manager_drop_company', 'NL', 'Sleep bedrijfslinks hiernaartoe'),
        ('manager_empty_page', 'NL', 'Dit blad is leeg. Voeg een bedrijfsblok toe en plaats er je links in.'),
        ('manager_first_group', 'NL', 'Maak eerst een bedrijfsblok op dit blad aan.'),
        ('marketing_companies_empty', 'NL', 'Er zijn nog geen publieke bedrijfspagina’s beschikbaar.'),
        ('marketing_services_intro', 'NL', 'Een flexibel platform voor een duidelijke online aanwezigheid, bedrijfsbeheer en een veilige omgeving voor je klanten.'),
        ('marketing_services_title', 'NL', 'Diensten voor jouw bedrijf'),
        ('nav_store', 'NL', 'Publieke bedrijfsite'),
        ('company_kbo_address', 'NL', 'Maatschappelijke zetel volgens KBO'),
        ('company_kbo_authentication_failed', 'NL', 'De KBO heeft de aanmelding geweigerd. Controleer de gebruikersnaam en het wachtwoord in credentials.php.'),
        ('company_kbo_details', 'NL', 'Gegevens uit de Kruispuntbank van Ondernemingen'),
        ('company_kbo_failed', 'NL', 'De KBO-opvraging is mislukt. Controleer de KBO-toegang en probeer opnieuw.'),
        ('company_kbo_lookup', 'NL', 'KBO-gegevens ophalen'),
        ('company_kbo_not_configured', 'NL', 'De KBO-webservice is nog niet ingesteld. Voeg username en password toe onder de sleutel kbo in het private credentials.php-bestand.'),
        ('company_kbo_quota_label', 'NL', 'KBO-opvragingen in de afgelopen 7 dagen'),
        ('company_kbo_quota_period', 'NL', 'maximum 100; mislukte aanvragen tellen mee'),
        ('company_kbo_service_unavailable', 'NL', 'De KBO-dienst is niet bereikbaar. Controleer de serververbinding en probeer later opnieuw.'),
        ('company_kbo_status', 'NL', 'Status in KBO'),
        ('company_kbo_terms_not_accepted', 'NL', 'Aanvaard eerst de actuele gebruiksvoorwaarden van de KBO-webservice via het KBO-portaal en probeer daarna opnieuw.'),
        ('company_kbo_unavailable', 'NL', 'niet beschikbaar'),
        ('company_kbo_weekly_limit', 'NL', 'De limiet van 100 KBO-opvragingen in de afgelopen 7 dagen is bereikt. Probeer het later opnieuw.'),
        ('company_kbo_lookup_first', 'NL', 'Haal eerst de KBO-gegevens op voordat je dit bedrijf aanmaakt.'),
        ('recovery_company_unavailable', 'NL', 'Het bedrijf is niet beschikbaar. Controleer het bedrijfslink.'),
        ('store_benefit_one_text', 'NL', 'Persoonlijke begeleiding door het team van jouw bedrijf.'),
        ('store_benefit_three_text', 'NL', 'Jouw bedrijf blijft het vertrouwde aanspreekpunt, ook online.'),
        ('store_brand_caption', 'NL', 'bedrijf · zorg · dichtbij'),
        ('store_browse', 'NL', 'Ontdek het bedrijf'),
        ('store_catalog_empty', 'NL', 'Het online aanbod van jouw bedrijf wordt hier klaargezet.'),
        ('store_catalog_title', 'NL', 'Het bedrijf, altijd binnen handbereik'),
        ('store_demo_name', 'NL', 'Jouw bedrijf'),
        ('store_directory_empty', 'NL', 'Er zijn nog geen publieke bedrijfswebsites beschikbaar.'),
        ('store_directory_heading', 'NL', 'Kies je bedrijf'),
        ('store_directory_intro', 'NL', 'Bekijk de publieke website van een aangesloten bedrijf.'),
        ('store_heading', 'NL', 'Jouw bedrijf. Online én dichtbij.'),
        ('store_intro', 'NL', 'Vertrouwde zorg, persoonlijke service en een zorgvuldig aanbod — van jouw bedrijf.'),
        ('store_note_text', 'NL', 'Van het bedrijfsteam dat je kent'),
        ('store_section_intro', 'NL', 'Ontdek de service van jouw bedrijf en vind straks ook online wat je nodig hebt.'),
        ('store_staff_workspace', 'NL', 'Naar bedrijfsbeheer'),
        ('store_title', 'NL', 'Bedrijf'),
        ('store_trustline', 'NL', 'De service van jouw bedrijf, nu ook online.'),
        ('store_visual_alt', 'NL', 'Grafische illustratie van de online bedrijfsomgeving'),
        ('store_visual_label', 'NL', 'JOUW BEDRIJF ONLINE'),
        ('tenant_none_available', 'NL', 'Je account is nog niet aan een actief bedrijf gekoppeld. Vraag de beheerder om toegang.'),
        ('tenant_select_help', 'NL', 'Selecteer het bedrijf waarvoor je wilt werken.'),
        ('tenant_select_title', 'NL', 'Kies een bedrijf'),
        ('user_add_help', 'NL', 'Dit maakt een algemeen account aan. Koppel een medewerker daarna via Medewerkers beheren aan een bedrijf.'),
        ('workspace_label', 'NL', 'Werkruimte van het bedrijf'),
        ('account_staff', 'FR', 'Personnel de l’entreprise — demande soumise à approbation'),
        ('acronym_help', 'FR', 'Choisissez un identifiant unique dans cette entreprise. Vous pourrez ensuite vous connecter avec cet identifiant ou votre adresse e-mail.'),
        ('acronym_taken', 'FR', 'Cet acronyme est déjà utilisé ou correspond à un autre identifiant de connexion dans cette entreprise.'),
        ('admin_companies_description', 'FR', 'Gérer les sociétés, les coordonnées et les informations de facturation.'),
        ('admin_intro', 'FR', 'Choisissez une rubrique pour gérer l’environnement de l’entreprise.'),
        ('companies_heading', 'FR', 'Sociétés'),
        ('companies_intro', 'FR', 'Gérez les sociétés qui utilisent Grompil. Les abonnements et les factures pourront ensuite être associés à ces fiches.'),
        ('company_access_pending', 'FR', 'Ce compte n’a pas encore accès à cette entreprise. Demandez l’accès à son administrateur.'),
        ('company_edit', 'FR', 'Modifier la société'),
        ('company_enterprise_number', 'FR', 'Numéro d’entreprise'),
        ('company_member_already_exists', 'FR', 'Cette adresse e-mail ou cet acronyme possède déjà un compte distinct dans cette société. Traitez la demande ou le compte existant dans la liste ci-dessous.'),
        ('company_member_save_failed', 'FR', 'Impossible de mettre à jour l’accès à l’entreprise.'),
        ('company_member_saved', 'FR', 'Accès à l’entreprise mis à jour.'),
        ('company_members_add_help', 'FR', 'Cette liste contient uniquement les comptes de personnel généraux qui ne sont associés à aucune société. Les inscriptions via cette page apparaissent comme demandes dans le tableau ci-dessous.'),
        ('company_members_empty', 'FR', 'Aucun membre du personnel n’est encore associé à cette entreprise.'),
        ('company_members_heading', 'FR', 'Personnel de l’entreprise'),
        ('company_members_intro', 'FR', 'Associez des utilisateurs existants à une entreprise et attribuez-leur un rôle propre à celle-ci.'),
        ('company_members_role', 'FR', 'Rôle dans l’entreprise'),
        ('company_name_label', 'FR', 'Nom de la société'),
        ('company_new', 'FR', 'Nouvelle société'),
        ('company_not_found', 'FR', 'entreprise introuvable.'),
        ('company_other_session', 'FR', 'Vous êtes connecté à une autre entreprise. Déconnectez-vous pour vous connecter avec un compte de cette entreprise.'),
        ('company_permission_roles', 'FR', 'Gérer les rôles de l’entreprise'),
        ('company_permission_settings', 'FR', 'Gérer les paramètres de l’entreprise'),
        ('company_permission_translations', 'FR', 'Gérer les textes de la société'),
        ('company_permission_users', 'FR', 'Gérer le personnel de l’entreprise'),
        ('company_public_disabled', 'FR', 'Le site de l’entreprise est maintenant masqué.'),
        ('company_public_enabled', 'FR', 'Le site de l’entreprise est maintenant public.'),
        ('company_public_label', 'FR', 'Afficher le site public de l’entreprise'),
        ('company_public_update_failed', 'FR', 'Impossible de modifier la visibilité du site de l’entreprise.'),
        ('company_role_save_failed', 'FR', 'Impossible d’enregistrer le rôle. Vérifiez que son nom est unique dans cette entreprise.'),
        ('company_role_saved', 'FR', 'Rôle d’entreprise enregistré.'),
        ('company_roles_edit', 'FR', 'Modifier le rôle d’entreprise'),
        ('company_roles_heading', 'FR', 'Rôles de l’entreprise'),
        ('company_roles_intro', 'FR', 'Ces rôles et autorisations ne s’appliquent qu’à cette entreprise. Les rôles système restent fixes.'),
        ('company_roles_new', 'FR', 'Nouveau rôle d’entreprise'),
        ('company_save_failed', 'FR', 'Impossible d’enregistrer la société. Vérifiez le code unique et le numéro d’entreprise.'),
        ('company_saved', 'FR', 'Société enregistrée.'),
        ('company_select', 'FR', 'Sélectionner une entreprise'),
        ('company_select_intro', 'FR', 'Sélectionnez une entreprise ou ajoutez-la à la plateforme.'),
        ('company_store_media_help', 'FR', 'Placez les images dans le dossier média de cette entreprise et indiquez uniquement le nom du fichier.'),
        ('company_translation_saved', 'FR', 'Textes de la société enregistrés et publiés.'),
        ('company_translations_heading', 'FR', 'Traductions propres à la société'),
        ('login_eyebrow', 'FR', 'Logiciel pour entreprises'),
        ('login_intro', 'FR', 'Connectez-vous à l’espace de travail sécurisé de votre entreprise.'),
        ('manager_add_company_group', 'FR', '＋ Bloc entreprise'),
        ('manager_company', 'FR', 'Entreprise'),
        ('manager_company_group_title', 'FR', 'Ajouter un bloc entreprise'),
        ('manager_drop_company', 'FR', 'Déposez les liens de l’entreprise ici'),
        ('manager_empty_page', 'FR', 'Cette feuille est vide. Ajoutez un bloc entreprise et vos liens.'),
        ('manager_first_group', 'FR', 'Créez d’abord un bloc entreprise sur cette feuille.'),
        ('marketing_about_intro', 'FR', 'Grompil crée des logiciels en ligne accessibles pour des entreprises de différents secteurs. Nous développons une plateforme solide qui évolue avec votre organisation.'),
        ('marketing_companies_empty', 'FR', 'Aucune page d’entreprise publique n’est encore disponible.'),
        ('marketing_companies_title', 'FR', 'Entreprises sur Grompil'),
        ('marketing_cta_companies', 'FR', 'Voir les entreprises'),
        ('marketing_home_intro', 'FR', 'Grompil aide les entreprises à présenter leurs services en ligne. Une base sobre, rapide et sécurisée, construite avec PHP et Bootstrap.'),
        ('marketing_services_intro', 'FR', 'Une plateforme flexible pour une présence en ligne claire, la gestion de votre entreprise et un environnement sécurisé pour vos clients.'),
        ('marketing_services_title', 'FR', 'Des services pour votre entreprise'),
        ('nav_companies', 'FR', 'Sociétés'),
        ('nav_store', 'FR', 'Site public de l’entreprise'),
        ('company_kbo_address', 'FR', 'Siège social selon la BCE'),
        ('company_kbo_authentication_failed', 'FR', 'La BCE a refusé l’authentification. Vérifiez le nom d’utilisateur et le mot de passe dans credentials.php.'),
        ('company_kbo_details', 'FR', 'Données de la Banque-Carrefour des Entreprises'),
        ('company_kbo_failed', 'FR', 'La recherche BCE a échoué. Vérifiez l’accès à la BCE et réessayez.'),
        ('company_kbo_lookup', 'FR', 'Récupérer les données BCE'),
        ('company_kbo_not_configured', 'FR', 'Le service web BCE n’est pas configuré. Ajoutez username et password sous la clé kbo dans le fichier privé credentials.php.'),
        ('company_kbo_quota_label', 'FR', 'Recherches BCE des 7 derniers jours'),
        ('company_kbo_quota_period', 'FR', 'maximum 100 ; les demandes échouées sont comptées'),
        ('company_kbo_service_unavailable', 'FR', 'Le service BCE est inaccessible. Vérifiez la connexion du serveur et réessayez plus tard.'),
        ('company_kbo_status', 'FR', 'Statut BCE'),
        ('company_kbo_terms_not_accepted', 'FR', 'Acceptez d’abord les conditions d’utilisation actuelles du service web BCE dans le portail BCE, puis réessayez.'),
        ('company_kbo_unavailable', 'FR', 'indisponible'),
        ('company_kbo_weekly_limit', 'FR', 'La limite de 100 recherches BCE sur les 7 derniers jours est atteinte. Réessayez plus tard.'),
        ('company_kbo_lookup_first', 'FR', 'Récupérez d’abord les données BCE avant de créer cette entreprise.'),
        ('recovery_company_unavailable', 'FR', 'L’entreprise est indisponible. Vérifiez le lien.'),
        ('store_benefit_one_text', 'FR', 'Accompagnement personnalisé par l’équipe de votre entreprise.'),
        ('store_benefit_three_text', 'FR', 'Votre entreprise reste votre interlocuteur habituel, même en ligne.'),
        ('store_brand_caption', 'FR', 'entreprise · soins · proximité'),
        ('store_browse', 'FR', 'Découvrir l’entreprise'),
        ('store_catalog_empty', 'FR', 'L’offre en ligne de votre entreprise sera bientôt disponible ici.'),
        ('store_catalog_title', 'FR', 'Votre entreprise, toujours à portée de main'),
        ('store_demo_name', 'FR', 'Votre entreprise'),
        ('store_directory_empty', 'FR', 'Aucun site public d’entreprise n’est encore disponible.'),
        ('store_directory_heading', 'FR', 'Choisissez votre entreprise'),
        ('store_directory_intro', 'FR', 'Visitez le site public d’une entreprise participante.'),
        ('store_heading', 'FR', 'Votre entreprise. En ligne et près de chez vous.'),
        ('store_intro', 'FR', 'Des soins de confiance, un service personnalisé et une offre soigneusement sélectionnée — par votre entreprise.'),
        ('store_note_text', 'FR', 'Par l’équipe de l’entreprise que vous connaissez'),
        ('store_section_intro', 'FR', 'Découvrez les services de votre entreprise et trouvez bientôt en ligne ce dont vous avez besoin.'),
        ('store_staff_workspace', 'FR', 'Aller à la gestion de l’entreprise'),
        ('store_title', 'FR', 'Entreprise'),
        ('store_trustline', 'FR', 'Le service de votre entreprise, désormais aussi en ligne.'),
        ('store_visual_alt', 'FR', 'Illustration graphique de l’entreprise en ligne'),
        ('store_visual_label', 'FR', 'VOTRE ENTREPRISE EN LIGNE'),
        ('tenant_none_available', 'FR', 'Votre compte n’est pas encore associé à une entreprise active. Demandez un accès à un administrateur.'),
        ('tenant_select_help', 'FR', 'Sélectionnez l’entreprise avec laquelle vous souhaitez travailler.'),
        ('tenant_select_title', 'FR', 'Choisir une entreprise'),
        ('user_add_help', 'FR', 'Cela crée un compte général. Associez ensuite le membre du personnel à une entreprise via Gérer le personnel.'),
        ('workspace_label', 'FR', 'Espace de travail de l’entreprise'),
        ('account_staff', 'EN', 'company staff — submit for approval'),
        ('acronym_help', 'EN', 'Choose a login name that is unique within this company. You can then sign in with it or your email address.'),
        ('acronym_taken', 'EN', 'This acronym is already in use or matches another login identifier within this company.'),
        ('admin_intro', 'EN', 'Choose an area to manage the company workspace.'),
        ('company_access_pending', 'EN', 'This account does not have access to this company yet. Ask the company administrator for access.'),
        ('company_edit', 'EN', 'Edit company'),
        ('company_invalid', 'EN', 'Check the company name, unique code, country code, language and billing email.'),
        ('company_member_already_exists', 'EN', 'This email address or acronym already has a separate account at this company. Handle the existing request or user in the list below.'),
        ('company_member_save_failed', 'EN', 'Could not update company access.'),
        ('company_member_saved', 'EN', 'Company access updated.'),
        ('company_members_add_help', 'EN', 'This list contains only general staff accounts that are not linked to any company. Registrations through this company page appear as requests in the table below.'),
        ('company_members_empty', 'EN', 'No staff members are assigned to this company yet.'),
        ('company_members_heading', 'EN', 'Company staff'),
        ('company_members_intro', 'EN', 'Assign existing users to a company and give them a company-specific role.'),
        ('company_members_role', 'EN', 'Company role'),
        ('company_members_workflow_help', 'EN', 'Staff who register through this company page appear below as Pending. Assign a role there and approve the request. The existing-user list is only for platform administrators.'),
        ('company_name_label', 'EN', 'Company name'),
        ('company_new', 'EN', 'New company'),
        ('company_not_found', 'EN', 'company not found.'),
        ('company_other_session', 'EN', 'You are signed in to another company. Sign out to sign in with an account for this company.'),
        ('company_permission_roles', 'EN', 'Manage Company roles'),
        ('company_permission_settings', 'EN', 'Manage company settings'),
        ('company_permission_translations', 'EN', 'Manage company text'),
        ('company_permission_users', 'EN', 'Manage company staff'),
        ('company_public_disabled', 'EN', 'The company website is now hidden.'),
        ('company_public_enabled', 'EN', 'The company website is now public.'),
        ('company_public_label', 'EN', 'Show public company website'),
        ('company_public_update_failed', 'EN', 'Could not change the company website visibility.'),
        ('company_role_save_failed', 'EN', 'The company role could not be saved. Check that its name is unique within this company.'),
        ('company_role_saved', 'EN', 'Company role saved.'),
        ('company_roles_edit', 'EN', 'Edit company role'),
        ('company_roles_heading', 'EN', 'Company roles'),
        ('company_roles_intro', 'EN', 'These roles and permissions apply only within this company. System roles stay fixed.'),
        ('company_roles_new', 'EN', 'New company role'),
        ('company_save_failed', 'EN', 'Could not save the company. Check the unique code and enterprise number.'),
        ('company_saved', 'EN', 'Company saved.'),
        ('company_select', 'EN', 'Select a company'),
        ('company_select_intro', 'EN', 'Select a company or add it to the platform.'),
        ('company_store_media_help', 'EN', 'Place image files in this company’s media folder and enter only the file name.'),
        ('company_translation_saved', 'EN', 'Company text saved and published.'),
        ('company_translations_heading', 'EN', 'Company-specific translations'),
        ('login_intro', 'EN', 'Sign in to your company’s secure workspace.'),
        ('manager_add_company_group', 'EN', '＋ Company block'),
        ('manager_company', 'EN', 'Company'),
        ('manager_company_group_title', 'EN', 'Add company block'),
        ('manager_drop_company', 'EN', 'Drag company links here'),
        ('manager_empty_page', 'EN', 'This sheet is empty. Add a company block and place your links in it.'),
        ('manager_first_group', 'EN', 'Create a company block on this sheet first.'),
        ('marketing_about_intro', 'EN', 'Grompil builds accessible online software for businesses across different sectors. We are developing a solid platform that can grow with your organisation.'),
        ('marketing_companies_empty', 'EN', 'No public business pages are available yet.'),
        ('marketing_companies_title', 'EN', 'Businesses on Grompil'),
        ('marketing_home_intro', 'EN', 'Grompil helps businesses present their services online. A clean, fast and secure foundation, built with PHP and Bootstrap.'),
        ('marketing_services_intro', 'EN', 'A flexible platform for a clear online presence, business administration and a secure environment for your customers.'),
        ('marketing_services_title', 'EN', 'Services for your business'),
        ('nav_store', 'EN', 'Company storefront'),
        ('company_kbo_address', 'EN', 'Registered office according to CBE'),
        ('company_kbo_authentication_failed', 'EN', 'The CBE rejected the login. Check the username and password in credentials.php.'),
        ('company_kbo_details', 'EN', 'Crossroads Bank for Enterprises details'),
        ('company_kbo_failed', 'EN', 'The CBE lookup failed. Check the CBE access and try again.'),
        ('company_kbo_lookup', 'EN', 'Retrieve CBE details'),
        ('company_kbo_not_configured', 'EN', 'The CBE web service is not configured. Add username and password under the kbo key in the private credentials.php file.'),
        ('company_kbo_quota_label', 'EN', 'CBE lookups in the last 7 days'),
        ('company_kbo_quota_period', 'EN', 'maximum 100; failed requests count'),
        ('company_kbo_service_unavailable', 'EN', 'The CBE service is unavailable. Check the server connection and try again later.'),
        ('company_kbo_status', 'EN', 'CBE status'),
        ('company_kbo_terms_not_accepted', 'EN', 'First accept the current CBE web service terms in the CBE portal, then try again.'),
        ('company_kbo_unavailable', 'EN', 'unavailable'),
        ('company_kbo_weekly_limit', 'EN', 'The limit of 100 CBE lookups in the last 7 days has been reached. Try again later.'),
        ('company_kbo_lookup_first', 'EN', 'Retrieve CBE details before creating this company.'),
        ('recovery_company_unavailable', 'EN', 'The company is unavailable. Check the company link.'),
        ('store_benefit_one_text', 'EN', 'Personal guidance from the team at your company.'),
        ('store_benefit_three_text', 'EN', 'Your company remains your trusted point of contact, online too.'),
        ('store_brand_caption', 'EN', 'Company · service · close by'),
        ('store_browse', 'EN', 'Explore the company'),
        ('store_catalog_empty', 'EN', 'Your company’s online range will appear here.'),
        ('store_catalog_title', 'EN', 'Your company, always within reach'),
        ('store_demo_name', 'EN', 'Your company'),
        ('store_directory_empty', 'EN', 'No public company websites are available yet.'),
        ('store_directory_heading', 'EN', 'Choose your company'),
        ('store_directory_intro', 'EN', 'Visit the public website of a participating company.'),
        ('store_heading', 'EN', 'Your company. Online and nearby.'),
        ('store_intro', 'EN', 'Trusted care, personal service and a thoughtfully selected range — from your company.'),
        ('store_note_text', 'EN', 'From the company team you know'),
        ('store_section_intro', 'EN', 'Discover your company’s service and soon find what you need online too.'),
        ('store_staff_workspace', 'EN', 'Go to company management'),
        ('store_title', 'EN', 'Company'),
        ('store_trustline', 'EN', 'The service of your company, now online too.'),
        ('store_visual_alt', 'EN', 'Graphic illustration of the online company'),
        ('store_visual_label', 'EN', 'YOUR COMPANY ONLINE'),
        ('tenant_none_available', 'EN', 'Your account is not linked to an active company yet. Ask an administrator for access.'),
        ('tenant_select_help', 'EN', 'Select the company you want to work with.'),
        ('tenant_select_title', 'EN', 'Choose a company'),
        ('user_add_help', 'EN', 'This creates a general account. Link a staff member to a company afterwards using Manage staff.'),
        ('workspace_label', 'EN', 'Company workspace'),
        ('acronym_help', 'DE', 'Wählen Sie einen eindeutigen Anmeldenamen für dieses Unternehmen. Sie können sich danach mit diesem Namen oder Ihrer E-Mail-Adresse anmelden.'),
        ('acronym_taken', 'DE', 'Dieses Akronym wird bereits verwendet oder stimmt mit einem anderen Anmeldenamen in diesem Unternehmen überein.'),
        ('admin_companies_description', 'DE', 'Unternehmen, Kontaktdaten und Rechnungsinformationen verwalten.'),
        ('admin_intro', 'DE', 'Wählen Sie einen Bereich zur Verwaltung der Unternehmensumgebung.'),
        ('companies_heading', 'DE', 'Unternehmen'),
        ('companies_intro', 'DE', 'Verwalten Sie die Unternehmen, die Grompil nutzen. Abonnements und Rechnungen können später mit diesen Einträgen verknüpft werden.'),
        ('company_access_pending', 'DE', 'Dieses Konto hat noch keinen Zugang zu diesem Unternehmen. Bitten Sie die Unternehmensverwaltung um Zugang.'),
        ('company_edit', 'DE', 'Unternehmen bearbeiten'),
        ('company_enterprise_number', 'DE', 'Unternehmensnummer'),
        ('company_invalid', 'DE', 'Prüfen Sie Unternehmensname, eindeutigen Code, Ländercode, Sprache und Rechnungs-E-Mail.'),
        ('company_member_already_exists', 'DE', 'Für diese E-Mail-Adresse oder dieses Kürzel gibt es bereits ein eigenes Konto in diesem Unternehmen. Bearbeiten Sie den vorhandenen Antrag oder Benutzer in der Liste unten.'),
        ('company_member_save_failed', 'DE', 'Unternehmenszugriff konnte nicht aktualisiert werden.'),
        ('company_member_saved', 'DE', 'Unternehmenszugriff aktualisiert.'),
        ('company_members_add_help', 'DE', 'Diese Liste enthält nur allgemeine Mitarbeiterkonten, die noch keinem Unternehmen zugeordnet sind. Registrierungen über dieses Unternehmensseite erscheinen als Anträge in der Tabelle unten.'),
        ('company_members_empty', 'DE', 'diesem Unternehmen sind noch keine Mitarbeiter zugeordnet.'),
        ('company_members_heading', 'DE', 'Unternehmenspersonal'),
        ('company_members_intro', 'DE', 'Weisen Sie bestehende Benutzer einem Unternehmen zu und geben Sie ihnen eine unternehmensspezifische Rolle.'),
        ('company_members_role', 'DE', 'Unternehmensrolle'),
        ('company_members_workflow_help', 'DE', 'Anträge von Mitarbeitern, die sich über dieses Unternehmensseite registrieren, erscheinen unten mit dem Status Ausstehend. Weisen Sie dort eine Rolle zu und genehmigen Sie den Antrag. Die Liste bestehender Benutzer ist nur für die Plattformverwaltung verfügbar.'),
        ('company_name_label', 'DE', 'Unternehmensname'),
        ('company_new', 'DE', 'Neues Unternehmen'),
        ('company_not_found', 'DE', 'Unternehmen nicht gefunden.'),
        ('company_other_session', 'DE', 'Sie sind bei einem anderen Unternehmen angemeldet. Melden Sie sich ab, um sich mit einem Konto dieses Unternehmens anzumelden.'),
        ('company_permission_roles', 'DE', 'Unternehmensrollen verwalten'),
        ('company_permission_settings', 'DE', 'Unternehmenseinstellungen verwalten'),
        ('company_permission_translations', 'DE', 'Unternehmenstexte verwalten'),
        ('company_permission_users', 'DE', 'Unternehmenspersonal verwalten'),
        ('company_public_disabled', 'DE', 'Die Unternehmenswebsite ist jetzt verborgen.'),
        ('company_public_enabled', 'DE', 'Die Unternehmenswebsite ist jetzt öffentlich.'),
        ('company_public_label', 'DE', 'Öffentliche Unternehmenswebsite anzeigen'),
        ('company_public_update_failed', 'DE', 'Die Sichtbarkeit der Unternehmenswebsite konnte nicht geändert werden.'),
        ('company_role_save_failed', 'DE', 'Die Rolle konnte nicht gespeichert werden. Der Name muss in diesem Unternehmen eindeutig sein.'),
        ('company_role_saved', 'DE', 'Unternehmensrolle gespeichert.'),
        ('company_roles_edit', 'DE', 'Unternehmensrolle bearbeiten'),
        ('company_roles_heading', 'DE', 'Unternehmensrollen'),
        ('company_roles_intro', 'DE', 'Diese Rollen und Berechtigungen gelten nur für dieses Unternehmen. Systemrollen bleiben fest.'),
        ('company_roles_new', 'DE', 'Neue Unternehmensrolle'),
        ('company_save_failed', 'DE', 'Unternehmen konnte nicht gespeichert werden. Prüfen Sie eindeutigen Code und Unternehmensnummer.'),
        ('company_saved', 'DE', 'Unternehmen gespeichert.'),
        ('company_select', 'DE', 'Unternehmen auswählen'),
        ('company_select_intro', 'DE', 'Wählen Sie ein Unternehmen aus oder fügen Sie es der Plattform hinzu.'),
        ('company_store_media_help', 'DE', 'Legen Sie die Bilddateien im Medienordner dieses Unternehmens ab und geben Sie nur den Dateinamen ein.'),
        ('company_translation_saved', 'DE', 'Unternehmenstexte gespeichert und veröffentlicht.'),
        ('company_translations_heading', 'DE', 'Unternehmenseigene Übersetzungen'),
        ('login_eyebrow', 'DE', 'Software für Unternehmen'),
        ('login_intro', 'DE', 'Melden Sie sich in der geschützten Arbeitsumgebung Ihres Unternehmens an.'),
        ('manager_add_company_group', 'DE', '＋ Unternehmensblock'),
        ('manager_company', 'DE', 'Unternehmen'),
        ('manager_company_group_title', 'DE', 'Unternehmensblock hinzufügen'),
        ('manager_drop_company', 'DE', 'Unternehmenslinks hierher ziehen'),
        ('manager_empty_page', 'DE', 'Dieses Blatt ist leer. Fügen Sie einen Unternehmensblock und Ihre Links hinzu.'),
        ('manager_first_group', 'DE', 'Erstellen Sie zuerst einen Unternehmensblock auf diesem Blatt.'),
        ('marketing_about_intro', 'DE', 'Grompil entwickelt zugängliche Online-Software für Unternehmen aus verschiedenen Branchen. Wir bauen eine solide Plattform, die mit Ihrer Organisation wachsen kann.'),
        ('marketing_companies_empty', 'DE', 'Es sind noch keine öffentlichen Unternehmensseiten verfügbar.'),
        ('marketing_companies_title', 'DE', 'Unternehmen auf Grompil'),
        ('marketing_cta_companies', 'DE', 'Unternehmen ansehen'),
        ('marketing_home_intro', 'DE', 'Grompil hilft Unternehmen, ihre Dienstleistungen online zu präsentieren. Eine klare, schnelle und sichere Basis, entwickelt mit PHP und Bootstrap.'),
        ('marketing_services_intro', 'DE', 'Eine flexible Plattform für einen klaren Online-Auftritt, die Unternehmensverwaltung und eine sichere Umgebung für Ihre Kunden.'),
        ('marketing_services_title', 'DE', 'Dienste für Ihr Unternehmen'),
        ('nav_companies', 'DE', 'Unternehmen'),
        ('nav_store', 'DE', 'Öffentliche Unternehmenswebsite'),
        ('company_kbo_address', 'DE', 'Sitz laut ZDU'),
        ('company_kbo_authentication_failed', 'DE', 'Die ZDU hat die Anmeldung abgelehnt. Prüfen Sie Benutzername und Passwort in credentials.php.'),
        ('company_kbo_details', 'DE', 'Angaben der Zentralen Datenbank der Unternehmen'),
        ('company_kbo_failed', 'DE', 'Die ZDU-Abfrage ist fehlgeschlagen. Prüfen Sie den Zugang und versuchen Sie es erneut.'),
        ('company_kbo_lookup', 'DE', 'ZDU-Angaben abrufen'),
        ('company_kbo_not_configured', 'DE', 'Der ZDU-Webdienst ist nicht eingerichtet. Fügen Sie username und password unter dem Schlüssel kbo in der privaten Datei credentials.php hinzu.'),
        ('company_kbo_quota_label', 'DE', 'ZDU-Abfragen der letzten 7 Tage'),
        ('company_kbo_quota_period', 'DE', 'maximal 100; fehlgeschlagene Anfragen zählen mit'),
        ('company_kbo_service_unavailable', 'DE', 'Der ZDU-Dienst ist nicht erreichbar. Prüfen Sie die Serververbindung und versuchen Sie es später erneut.'),
        ('company_kbo_status', 'DE', 'ZDU-Status'),
        ('company_kbo_terms_not_accepted', 'DE', 'Akzeptieren Sie zuerst die aktuellen Nutzungsbedingungen des ZDU-Webdienstes im ZDU-Portal und versuchen Sie es erneut.'),
        ('company_kbo_unavailable', 'DE', 'nicht verfügbar'),
        ('company_kbo_weekly_limit', 'DE', 'Das Limit von 100 ZDU-Abfragen in den letzten 7 Tagen ist erreicht. Bitte versuchen Sie es später erneut.'),
        ('company_kbo_lookup_first', 'DE', 'Rufen Sie zuerst die ZDU-Angaben ab, bevor Sie dieses Unternehmen anlegen.'),
        ('recovery_company_unavailable', 'DE', 'Das Unternehmen ist nicht verfügbar. Prüfen Sie den Unternehmenslink.'),
        ('store_benefit_one_text', 'DE', 'Persönliche Beratung durch das Team Ihres Unternehmens.'),
        ('store_benefit_three_text', 'DE', 'Ihr Unternehmen bleibt auch online Ihre vertraute Anlaufstelle.'),
        ('store_brand_caption', 'DE', 'Unternehmen · Beratung · Nähe'),
        ('store_browse', 'DE', 'Unternehmen entdecken'),
        ('store_catalog_empty', 'DE', 'Das Online-Angebot Ihres Unternehmens wird hier bereitgestellt.'),
        ('store_catalog_title', 'DE', 'Ihr Unternehmen immer griffbereit'),
        ('store_demo_name', 'DE', 'Ihr Unternehmen'),
        ('store_directory_empty', 'DE', 'Es sind noch keine öffentlichen Unternehmenswebsites verfügbar.'),
        ('store_directory_heading', 'DE', 'Wählen Sie Ihr Unternehmen'),
        ('store_directory_intro', 'DE', 'Besuchen Sie die öffentliche Website eines teilnehmenden Unternehmens.'),
        ('store_heading', 'DE', 'Ihr Unternehmen. Online und in Ihrer Nähe.'),
        ('store_intro', 'DE', 'Vertraute Beratung, persönlicher Service und ein sorgfältig ausgewähltes Angebot Ihres Unternehmens.'),
        ('store_note_text', 'DE', 'Vom Unternehmensteam, das Sie kennen'),
        ('store_section_intro', 'DE', 'Entdecken Sie den Service Ihres Unternehmens und finden Sie bald auch online, was Sie brauchen.'),
        ('store_staff_workspace', 'DE', 'Zur Unternehmensverwaltung'),
        ('store_title', 'DE', 'Unternehmen'),
        ('store_trustline', 'DE', 'Der Service Ihres Unternehmens, jetzt auch online.'),
        ('store_visual_alt', 'DE', 'Grafische Darstellung des Online-Angebots des Unternehmens'),
        ('store_visual_label', 'DE', 'IHR UNTERNEHMEN ONLINE'),
        ('tenant_none_available', 'DE', 'Ihr Konto ist noch keinem aktiven Unternehmen zugeordnet. Bitten Sie einen Administrator um Zugriff.'),
        ('tenant_select_help', 'DE', 'Wählen Sie das Unternehmen aus, mit dem Sie arbeiten möchten.'),
        ('tenant_select_title', 'DE', 'Unternehmen auswählen'),
        ('user_add_help', 'DE', 'Dies erstellt ein allgemeines Konto. Ordnen Sie Mitarbeitende anschließend über Personal verwalten einem Unternehmen zu.'),
        ('workspace_label', 'DE', 'Arbeitsbereich des Unternehmens')
) AS v("key", code, translation)
WHERE s.company_id = 0 AND s."key" = v."key" AND upper(s.code) = v.code;

INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
VALUES
    (0, 'company_select', 'Bedrijven kiezen', 'NL', now()),
    (0, 'company_select_intro', 'Selecteer een bedrijf of voeg het toe aan het platform.', 'NL', now()),
    (0, 'company_select', 'Sélectionner une entreprise', 'FR', now()),
    (0, 'company_select_intro', 'Sélectionnez une entreprise ou ajoutez-la à la plateforme.', 'FR', now()),
    (0, 'company_select', 'Select a company', 'EN', now()),
    (0, 'company_select_intro', 'Select a company or add it to the platform.', 'EN', now()),
    (0, 'company_select', 'Unternehmen auswählen', 'DE', now()),
    (0, 'company_select_intro', 'Wählen Sie ein Unternehmen aus oder fügen Sie es der Plattform hinzu.', 'DE', now())
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

COMMIT;
