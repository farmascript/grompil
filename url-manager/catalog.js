window.GROMPIL_NAVIGATION = {
    "schemaVersion": 1,
    "application": "grompil",
    "applicationVersion": "0.4.6",
    "defaultLanguage": "nl",
    "languages": [
        "nl",
        "fr",
        "en",
        "de"
    ],
    "groups": [
        {
            "id": "company",
            "i18n": {
                "nl": {
                    "title": "Werkruimte van het bedrijf"
                },
                "fr": {
                    "title": "Espace de travail de l’entreprise"
                },
                "en": {
                    "title": "Company workspace"
                },
                "de": {
                    "title": "Arbeitsbereich des Unternehmens"
                }
            }
        },
        {
            "id": "platform",
            "i18n": {
                "nl": {
                    "title": "Beheer"
                },
                "fr": {
                    "title": "Administration"
                },
                "en": {
                    "title": "Admin"
                },
                "de": {
                    "title": "Verwaltung"
                }
            }
        },
        {
            "id": "public",
            "i18n": {
                "nl": {
                    "title": "home"
                },
                "fr": {
                    "title": "accueil"
                },
                "en": {
                    "title": "home"
                },
                "de": {
                    "title": "Startseite"
                }
            }
        }
    ],
    "items": [
        {
            "id": 18472,
            "module": "companies.php",
            "group": "platform",
            "public_visibility": false,
            "i18n": {
                "nl": {
                    "title": "Companies",
                    "description": "Bedrijven, contactgegevens en facturatiegegevens beheren.",
                    "keywords": []
                },
                "fr": {
                    "title": "Sociétés",
                    "description": "Gérer les sociétés, les coordonnées et les informations de facturation.",
                    "keywords": []
                },
                "en": {
                    "title": "Companies",
                    "description": "Manage companies, contact details and billing information.",
                    "keywords": []
                },
                "de": {
                    "title": "Unternehmen",
                    "description": "Unternehmen, Kontaktdaten und Rechnungsinformationen verwalten.",
                    "keywords": []
                }
            },
            "sequence_number": 1,
            "action": "manage"
        },
        {
            "id": 18473,
            "module": "users.php",
            "group": "platform",
            "public_visibility": false,
            "i18n": {
                "nl": {
                    "title": "Gebruikers en toegang",
                    "description": "Accounts goedkeuren en toegang beheren.",
                    "keywords": []
                },
                "fr": {
                    "title": "Utilisateurs et accès",
                    "description": "Approuver les comptes et gérer les accès.",
                    "keywords": []
                },
                "en": {
                    "title": "Users and access",
                    "description": "Approve accounts and manage access.",
                    "keywords": []
                },
                "de": {
                    "title": "Benutzer und Zugänge",
                    "description": "Konten genehmigen und Zugänge verwalten.",
                    "keywords": []
                }
            },
            "sequence_number": 2,
            "action": "manage"
        },
        {
            "id": 18474,
            "module": "company_members.php",
            "group": "company",
            "public_visibility": true,
            "i18n": {
                "nl": {
                    "title": "Medewerkers beheren",
                    "description": "Koppel bestaande gebruikers aan een bedrijf en wijs hun een rol toe.",
                    "keywords": []
                },
                "fr": {
                    "title": "Gérer le personnel",
                    "description": "Associez des utilisateurs existants à une entreprise et attribuez-leur un rôle propre à celle-ci.",
                    "keywords": []
                },
                "en": {
                    "title": "Manage staff",
                    "description": "Assign existing users to a company and give them a company-specific role.",
                    "keywords": []
                },
                "de": {
                    "title": "Personal verwalten",
                    "description": "Weisen Sie bestehende Benutzer einem Unternehmen zu und geben Sie ihnen eine unternehmensspezifische Rolle.",
                    "keywords": []
                }
            },
            "sequence_number": 3,
            "action": "manage"
        },
        {
            "id": 18475,
            "module": "company_roles.php",
            "group": "company",
            "public_visibility": true,
            "i18n": {
                "nl": {
                    "title": "Rollen beheren",
                    "description": "Deze rollen en hun rechten gelden alleen binnen dit bedrijf. Systeemrollen blijven vast staan.",
                    "keywords": []
                },
                "fr": {
                    "title": "Gérer les rôles",
                    "description": "Ces rôles et autorisations ne s’appliquent qu’à cette entreprise. Les rôles système restent fixes.",
                    "keywords": []
                },
                "en": {
                    "title": "Manage roles",
                    "description": "These roles and permissions apply only within this company. System roles stay fixed.",
                    "keywords": []
                },
                "de": {
                    "title": "Rollen verwalten",
                    "description": "Diese Rollen und Berechtigungen gelten nur für dieses Unternehmen. Systemrollen bleiben fest.",
                    "keywords": []
                }
            },
            "sequence_number": 4,
            "action": "manage"
        },
        {
            "id": 18476,
            "module": "company_translations.php",
            "group": "company",
            "public_visibility": false,
            "i18n": {
                "nl": {
                    "title": "Bedrijfsspecifieke vertalingen",
                    "description": "Alleen teksten die afwijken van de algemene taalbestanden worden hier getoond.",
                    "keywords": []
                },
                "fr": {
                    "title": "Traductions propres à la société",
                    "description": "Seuls les textes différents des fichiers de langue généraux sont affichés ici.",
                    "keywords": []
                },
                "en": {
                    "title": "Company-specific translations",
                    "description": "Only texts that differ from the general language files are shown here.",
                    "keywords": []
                },
                "de": {
                    "title": "Unternehmenseigene Übersetzungen",
                    "description": "Hier werden nur Texte angezeigt, die von den allgemeinen Sprachdateien abweichen.",
                    "keywords": []
                }
            },
            "sequence_number": 5,
            "action": "manage"
        },
        {
            "id": 18477,
            "module": "index.php",
            "group": "public",
            "public_visibility": true,
            "i18n": {
                "nl": {
                    "title": "home",
                    "description": "Grompil helpt bedrijven hun diensten helder online aan te bieden. Een sobere, snelle en veilige basis, gebouwd met PHP en Bootstrap.",
                    "keywords": []
                },
                "fr": {
                    "title": "accueil",
                    "description": "Grompil aide les entreprises à présenter leurs services en ligne. Une base sobre, rapide et sécurisée, construite avec PHP et Bootstrap.",
                    "keywords": []
                },
                "en": {
                    "title": "home",
                    "description": "Grompil helps businesses present their services online. A clean, fast and secure foundation, built with PHP and Bootstrap.",
                    "keywords": []
                },
                "de": {
                    "title": "Startseite",
                    "description": "Grompil hilft Unternehmen, ihre Dienstleistungen online zu präsentieren. Eine klare, schnelle und sichere Basis, entwickelt mit PHP und Bootstrap.",
                    "keywords": []
                }
            },
            "sequence_number": 6,
            "action": "view"
        },
        {
            "id": 48361,
            "module": "scripts/check_templates.php",
            "group": "platform",
            "public_visibility": false,
            "i18n": {
                "nl": {
                    "title": "Templatecontrole",
                    "description": "Controleert templates op ontbrekende vertalingen en templatewaarden.",
                    "keywords": []
                },
                "fr": {
                    "title": "Contrôle des modèles",
                    "description": "Vérifie les traductions et les valeurs manquantes dans les modèles.",
                    "keywords": []
                },
                "en": {
                    "title": "Template check",
                    "description": "Checks templates for missing translations and template values.",
                    "keywords": []
                },
                "de": {
                    "title": "Vorlagenprüfung",
                    "description": "Prüft Vorlagen auf fehlende Übersetzungen und Vorlagenwerte.",
                    "keywords": []
                }
            },
            "sequence_number": 7,
            "action": "manage"
        },
        {
            "id": 16721,
            "module": "translations.php",
            "group": "platform",
            "public_visibility": false,
            "i18n": {
                "nl": {
                    "title": "Vertalingen",
                    "description": "toekennen van teksten aan keywords",
                    "keywords": []
                },
                "fr": {
                    "title": "Traductions",
                    "description": "!admin_translations!",
                    "keywords": []
                },
                "en": {
                    "title": "Translations",
                    "description": "!admin_translations!",
                    "keywords": []
                },
                "de": {
                    "title": "Übersetzungen",
                    "description": "!admin_translations!",
                    "keywords": []
                }
            },
            "sequence_number": 8,
            "action": "manage"
        },
        {
            "id": 76506,
            "module": "account.php",
            "group": "platform",
            "public_visibility": true,
            "i18n": {
                "nl": {
                    "title": "mijn account titles",
                    "description": "mijn account description",
                    "keywords": []
                },
                "fr": {
                    "title": "!admin_account_titles!",
                    "description": "!admin_account_description!",
                    "keywords": []
                },
                "en": {
                    "title": "!admin_account_titles!",
                    "description": "!admin_account_description!",
                    "keywords": []
                },
                "de": {
                    "title": "!admin_account_titles!",
                    "description": "!admin_account_description!",
                    "keywords": []
                }
            },
            "sequence_number": 9,
            "action": "manage"
        },
        {
            "id": 59384,
            "module": "navigation_keywords.php",
            "group": "platform",
            "public_visibility": false,
            "i18n": {
                "nl": {
                    "title": "Vertalingen beheren",
                    "description": "Webteksten beheren en taalbestanden publiceren.",
                    "keywords": []
                },
                "fr": {
                    "title": "Gérer les traductions",
                    "description": "Gérer les textes du site et publier les fichiers de langue.",
                    "keywords": []
                },
                "en": {
                    "title": "Manage translations",
                    "description": "Manage website text and publish language files.",
                    "keywords": []
                },
                "de": {
                    "title": "Übersetzungen verwalten",
                    "description": "Webtexte verwalten und Sprachdateien veröffentlichen.",
                    "keywords": []
                }
            },
            "sequence_number": 10,
            "action": "manage"
        },
        {
            "id": 58731,
            "module": "company_security.php",
            "group": "company",
            "public_visibility": true,
            "i18n": {
                "nl": {
                    "title": "bedrijfsinstellingen beheren",
                    "description": "",
                    "keywords": []
                },
                "fr": {
                    "title": "Gérer les paramètres de l’entreprise",
                    "description": "",
                    "keywords": []
                },
                "en": {
                    "title": "Manage company settings",
                    "description": "",
                    "keywords": []
                },
                "de": {
                    "title": "Unternehmenseinstellungen verwalten",
                    "description": "",
                    "keywords": []
                }
            },
            "sequence_number": 11,
            "action": "manage"
        },
        {
            "id": 34470,
            "module": "select-company.php",
            "group": "company",
            "public_visibility": true,
            "i18n": {
                "nl": {
                    "title": "Bedrijven kiezen",
                    "description": "Selecteer een bedrijf of voeg het toe aan het platform.",
                    "keywords": []
                },
                "fr": {
                    "title": "Sélectionner une entreprise",
                    "description": "Sélectionnez une entreprise ou ajoutez-la à la plateforme.",
                    "keywords": []
                },
                "en": {
                    "title": "Select a company",
                    "description": "Select a company or add it to the platform.",
                    "keywords": []
                },
                "de": {
                    "title": "Unternehmen auswählen",
                    "description": "Wählen Sie ein Unternehmen aus oder fügen Sie es der Plattform hinzu.",
                    "keywords": []
                }
            },
            "sequence_number": 12,
            "action": "manage"
        }
    ],
    "managerI18n": {
        "nl": {
            "manager_add_company_group": "＋ Bedrijfsblok",
            "manager_add_link": "＋ Link",
            "manager_add_link_title": "Link toevoegen",
            "manager_add_page": "blad toevoegen",
            "manager_add_platform_group": "＋ Grompil-blok",
            "manager_background_color": "Achtergrondkleur",
            "manager_base_url": "Basisadres van Grompil",
            "manager_cancel": "Annuleren",
            "manager_choose_file": "Bestand kiezen",
            "manager_close": "Sluiten",
            "manager_column_one": "1 kolom",
            "manager_column_three": "3 kolommen",
            "manager_column_two": "2 kolommen",
            "manager_columns": "Kolomindeling",
            "manager_company": "Bedrijf",
            "manager_company_group_title": "Bedrijfsblok toevoegen",
            "manager_count_many": "{count} functies en links",
            "manager_count_one": "{count} functie of link",
            "manager_delete_group": "Blok verwijderen",
            "manager_delete_link": "Link verwijderen",
            "manager_drop_company": "Sleep bedrijfslinks hiernaartoe",
            "manager_drop_platform": "Sleep vaste Grompil-links hiernaartoe",
            "manager_edit_group_page_title": "Bloknaam of blad wijzigen",
            "manager_edit_group_title": "Bloknaam wijzigen",
            "manager_edit_link": "Link aanpassen",
            "manager_empty_page": "Dit blad is leeg. Voeg een bedrijfsblok toe en plaats er je links in.",
            "manager_export_json": "Exporteren",
            "manager_favorite_add": "Maak favoriet",
            "manager_favorite_remove": "Verwijder favoriet",
            "manager_favorites": "☆ Favorieten",
            "manager_favorites_only": "★ Alleen favorieten",
            "manager_first_group": "Maak eerst een bedrijfsblok op dit blad aan.",
            "manager_group": "Blok",
            "manager_group_name": "Naam van het blok",
            "manager_group_options": "Blokopties",
            "manager_home": "Navigatie",
            "manager_import_json": "JSON importeren",
            "manager_language": "Taal",
            "manager_link_name": "Naam",
            "manager_link_url": "Webadres of pad",
            "manager_local": "Lokaal",
            "manager_make_group": "Blok maken",
            "manager_move_group": "Blok verslepen",
            "manager_page": "Blad",
            "manager_page_name": "Naam van het blad",
            "manager_page_number": "blad{number}",
            "manager_pages": "Bladen",
            "manager_platform_group_title": "Vast Grompil-blok toevoegen",
            "manager_platform_operator": "Platformbeheerder",
            "manager_rename_group": "Naam wijzigen",
            "manager_rename_group_page": "Naam of blad wijzigen",
            "manager_rename_page": "Bladnaam wijzigen",
            "manager_rename_page_label": "{name} hernoemen",
            "manager_reset_color": "Standaardkleur herstellen",
            "manager_save": "Bewaren",
            "manager_save_color": "Kleur bewaren",
            "manager_save_name": "Naam bewaren",
            "manager_search": "Zoek functies, omschrijvingen of trefwoorden…",
            "manager_settings": "Instellingen",
            "manager_settings_title": "Verbinding",
            "manager_show_all": "Alle functies",
            "manager_text_larger": "Tekst groter",
            "manager_text_normal": "Normale tekstgrootte",
            "manager_text_size": "Tekstgrootte",
            "manager_text_smaller": "Tekst kleiner",
            "manager_unavailable": "SaaS niet bereikbaar",
            "nav_logout": "Uitloggen"
        },
        "fr": {
            "manager_add_company_group": "＋ Bloc entreprise",
            "manager_add_link": "＋ Lien",
            "manager_add_link_title": "Ajouter un lien",
            "manager_add_page": "ajouter une feuille",
            "manager_add_platform_group": "＋ Bloc Grompil",
            "manager_background_color": "Couleur de fond",
            "manager_base_url": "Adresse de base de Grompil",
            "manager_cancel": "Annuler",
            "manager_choose_file": "Choisir un fichier",
            "manager_close": "Fermer",
            "manager_column_one": "1 colonne",
            "manager_column_three": "3 colonnes",
            "manager_column_two": "2 colonnes",
            "manager_columns": "Disposition des colonnes",
            "manager_company": "Entreprise",
            "manager_company_group_title": "Ajouter un bloc entreprise",
            "manager_count_many": "{count} fonctions et liens",
            "manager_count_one": "{count} fonction ou lien",
            "manager_delete_group": "Supprimer le bloc",
            "manager_delete_link": "Supprimer le lien",
            "manager_drop_company": "Déposez les liens de l’entreprise ici",
            "manager_drop_platform": "Déposez les liens Grompil fixes ici",
            "manager_edit_group_page_title": "Modifier le nom du bloc ou la feuille",
            "manager_edit_group_title": "Renommer le bloc",
            "manager_edit_link": "Modifier le lien",
            "manager_empty_page": "Cette feuille est vide. Ajoutez un bloc entreprise et vos liens.",
            "manager_export_json": "Exporter",
            "manager_favorite_add": "Ajouter aux favoris",
            "manager_favorite_remove": "Retirer des favoris",
            "manager_favorites": "☆ Favoris",
            "manager_favorites_only": "★ Favoris uniquement",
            "manager_first_group": "Créez d’abord un bloc entreprise sur cette feuille.",
            "manager_group": "Bloc",
            "manager_group_name": "Nom du bloc",
            "manager_group_options": "Options du bloc",
            "manager_home": "Navigation",
            "manager_import_json": "Importer JSON",
            "manager_language": "Langue",
            "manager_link_name": "Nom",
            "manager_link_url": "Adresse web ou chemin",
            "manager_local": "Local",
            "manager_make_group": "Créer le bloc",
            "manager_move_group": "Déplacer le bloc",
            "manager_page": "Feuille",
            "manager_page_name": "Nom de la feuille",
            "manager_page_number": "feuille{number}",
            "manager_pages": "Feuilles",
            "manager_platform_group_title": "Ajouter un bloc Grompil fixe",
            "manager_platform_operator": "Administrateur de plateforme",
            "manager_rename_group": "Renommer",
            "manager_rename_group_page": "Modifier le nom ou la feuille",
            "manager_rename_page": "Renommer la feuille",
            "manager_rename_page_label": "Renommer {name}",
            "manager_reset_color": "Rétablir la couleur par défaut",
            "manager_save": "Enregistrer",
            "manager_save_color": "Enregistrer la couleur",
            "manager_save_name": "Enregistrer le nom",
            "manager_search": "Rechercher des fonctions, descriptions ou mots-clés…",
            "manager_settings": "Paramètres",
            "manager_settings_title": "Connexion",
            "manager_show_all": "Toutes les fonctions",
            "manager_text_larger": "Agrandir le texte",
            "manager_text_normal": "Taille normale",
            "manager_text_size": "Taille du texte",
            "manager_text_smaller": "Réduire le texte",
            "manager_unavailable": "SaaS indisponible",
            "nav_logout": "Déconnexion"
        },
        "en": {
            "manager_add_company_group": "＋ Company block",
            "manager_add_link": "＋ Link",
            "manager_add_link_title": "Add link",
            "manager_add_page": "add sheet",
            "manager_add_platform_group": "＋ Grompil block",
            "manager_background_color": "Background color",
            "manager_base_url": "Grompil base address",
            "manager_cancel": "Cancel",
            "manager_choose_file": "Choose file",
            "manager_close": "Close",
            "manager_column_one": "1 column",
            "manager_column_three": "3 columns",
            "manager_column_two": "2 columns",
            "manager_columns": "Column layout",
            "manager_company": "Company",
            "manager_company_group_title": "Add company block",
            "manager_count_many": "{count} functions and links",
            "manager_count_one": "{count} function or link",
            "manager_delete_group": "Delete block",
            "manager_delete_link": "Delete link",
            "manager_drop_company": "Drag company links here",
            "manager_drop_platform": "Drag fixed Grompil links here",
            "manager_edit_group_page_title": "Change block name or sheet",
            "manager_edit_group_title": "Rename block",
            "manager_edit_link": "Edit link",
            "manager_empty_page": "This sheet is empty. Add a company block and place your links in it.",
            "manager_export_json": "Export",
            "manager_favorite_add": "Add favorite",
            "manager_favorite_remove": "Remove favorite",
            "manager_favorites": "☆ Favorites",
            "manager_favorites_only": "★ Favorites only",
            "manager_first_group": "Create a company block on this sheet first.",
            "manager_group": "Block",
            "manager_group_name": "Block name",
            "manager_group_options": "Block options",
            "manager_home": "Navigation",
            "manager_import_json": "Import JSON",
            "manager_language": "Language",
            "manager_link_name": "Name",
            "manager_link_url": "Web address or path",
            "manager_local": "Local",
            "manager_make_group": "Create block",
            "manager_move_group": "Drag block",
            "manager_page": "Sheet",
            "manager_page_name": "Sheet name",
            "manager_page_number": "sheet{number}",
            "manager_pages": "Sheets",
            "manager_platform_group_title": "Add fixed Grompil block",
            "manager_platform_operator": "Platform administrator",
            "manager_rename_group": "Rename",
            "manager_rename_group_page": "Change name or sheet",
            "manager_rename_page": "Rename sheet",
            "manager_rename_page_label": "Rename {name}",
            "manager_reset_color": "Restore default color",
            "manager_save": "Save",
            "manager_save_color": "Save color",
            "manager_save_name": "Save name",
            "manager_search": "Search functions, descriptions or keywords…",
            "manager_settings": "Settings",
            "manager_settings_title": "Connection",
            "manager_show_all": "All functions",
            "manager_text_larger": "Larger text",
            "manager_text_normal": "Normal text size",
            "manager_text_size": "Text size",
            "manager_text_smaller": "Smaller text",
            "manager_unavailable": "SaaS unavailable",
            "nav_logout": "Sign out"
        },
        "de": {
            "manager_add_company_group": "＋ Unternehmensblock",
            "manager_add_link": "＋ Link",
            "manager_add_link_title": "Link hinzufügen",
            "manager_add_page": "Blatt hinzufügen",
            "manager_add_platform_group": "＋ Grompil-Block",
            "manager_background_color": "Hintergrundfarbe",
            "manager_base_url": "Grompil-Basisadresse",
            "manager_cancel": "Abbrechen",
            "manager_choose_file": "Datei auswählen",
            "manager_close": "Schließen",
            "manager_column_one": "1 Spalte",
            "manager_column_three": "3 Spalten",
            "manager_column_two": "2 Spalten",
            "manager_columns": "Spaltenaufteilung",
            "manager_company": "Unternehmen",
            "manager_company_group_title": "Unternehmensblock hinzufügen",
            "manager_count_many": "{count} Funktionen und Links",
            "manager_count_one": "{count} Funktion oder Link",
            "manager_delete_group": "Block löschen",
            "manager_delete_link": "Link löschen",
            "manager_drop_company": "Unternehmenslinks hierher ziehen",
            "manager_drop_platform": "Feste Grompil-Links hierher ziehen",
            "manager_edit_group_page_title": "Blockname oder Blatt ändern",
            "manager_edit_group_title": "Block umbenennen",
            "manager_edit_link": "Link bearbeiten",
            "manager_empty_page": "Dieses Blatt ist leer. Fügen Sie einen Unternehmensblock und Ihre Links hinzu.",
            "manager_export_json": "Exportieren",
            "manager_favorite_add": "Zu Favoriten hinzufügen",
            "manager_favorite_remove": "Aus Favoriten entfernen",
            "manager_favorites": "☆ Favoriten",
            "manager_favorites_only": "★ Nur Favoriten",
            "manager_first_group": "Erstellen Sie zuerst einen Unternehmensblock auf diesem Blatt.",
            "manager_group": "Block",
            "manager_group_name": "Blockname",
            "manager_group_options": "Blockoptionen",
            "manager_home": "Navigation",
            "manager_import_json": "JSON importieren",
            "manager_language": "Sprache",
            "manager_link_name": "Name",
            "manager_link_url": "Webadresse oder Pfad",
            "manager_local": "Lokal",
            "manager_make_group": "Block erstellen",
            "manager_move_group": "Block verschieben",
            "manager_page": "Blatt",
            "manager_page_name": "Blattname",
            "manager_page_number": "Blatt{number}",
            "manager_pages": "Blätter",
            "manager_platform_group_title": "Festen Grompil-Block hinzufügen",
            "manager_platform_operator": "Plattformadministrator",
            "manager_rename_group": "Umbenennen",
            "manager_rename_group_page": "Name oder Blatt ändern",
            "manager_rename_page": "Blatt umbenennen",
            "manager_rename_page_label": "{name} umbenennen",
            "manager_reset_color": "Standardfarbe wiederherstellen",
            "manager_save": "Speichern",
            "manager_save_color": "Farbe speichern",
            "manager_save_name": "Namen speichern",
            "manager_search": "Funktionen, Beschreibungen oder Stichwörter suchen…",
            "manager_settings": "Einstellungen",
            "manager_settings_title": "Verbindung",
            "manager_show_all": "Alle Funktionen",
            "manager_text_larger": "Text vergrößern",
            "manager_text_normal": "Normale Textgröße",
            "manager_text_size": "Textgröße",
            "manager_text_smaller": "Text verkleinern",
            "manager_unavailable": "SaaS nicht erreichbar",
            "nav_logout": "Abmelden"
        }
    }
};
