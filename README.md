<!--
/**
 *
 * @date_cre: Wed 2026-10-14 19:46:31
 * @date_mut: Wed 2026-10-21 18:39:38
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Documents Grompil installation, configuration, administration, and architecture.
 *
 */
-->

# Grompil — PHP 8 en PostgreSQL

Procedurele PHP 8-app voor bedrijven. PostgreSQL gebruikt de pgsql-extensie; de interface gebruikt Bootstrap 5.3.8.

De losse browsergebaseerde URL-manager staat in `url-manager/`. Zie [docs/url-manager.md](docs/url-manager.md) voor gebruik en de JSON-catalogusgenerator.

## Vereisten

- PHP 8.1+ met de pgsql-, json- en curl-extensies
- PostgreSQL 14+
- HTTPS voor gebruik in productie

## Database instellen met Adminer

1. Meld je in Adminer aan met een PostgreSQL-account dat databases en gebruikers mag beheren.
2. Maak via Adminer een database met de naam grompil aan (UTF-8).
3. Maak via Adminer een databasegebruiker met de naam grompil_app aan. Stel een sterk wachtwoord in. Geef deze gebruiker geen superuser-, createdb- of createrole-rechten.
4. Selecteer de database grompil en voer schema.sql uit via Adminers SQL-opdracht of Import-pagina.

schema.sql bevat gewone PostgreSQL-SQL. Het maakt de tabellen en startrollen in de geselecteerde database aan en kent grompil_app de benodigde rechten toe. Het bestand maakt de database zelf niet aan: die stap doe je in Adminer.

## Applicatie configureren

Stel deze omgevingsvariabelen in voor PHP:

- GROMPIL_DB_HOST (standaard 127.0.0.1)
- GROMPIL_DB_PORT (standaard 5432)
- GROMPIL_DB_NAME (standaard grompil)
- GROMPIL_DB_USER (standaard grompil_app)
- GROMPIL_DB_PASSWORD (het wachtwoord van grompil_app)

De eerste beheerder wordt momenteel via de PHP CLI aangemaakt. Stel tijdelijk GROMPIL_SETUP_ADMIN_EMAIL en GROMPIL_SETUP_ADMIN_PASSWORD in (minimaal 12 tekens) en voer php setup-admin.php uit. Verwijder de setup-variabelen daarna.

### E-mail via SMTP

Wachtwoordherstel via **Wachtwoord vergeten?** gebruikt dezelfde SMTP-configuratie. Voer voor bestaande databases `migrations/20261021_password_reset.sql` uit als databasebeheerder; zie [docs/password-recovery.md](docs/password-recovery.md) voor installatie en controle. Op hosting is hiervoor de vaste HTTPS-basis-URL `GROMPIL_PUBLIC_URL` vereist. Op localhost wordt de lokale serverpoort gebruikt.

Grompil gebruikt PHPMailer voor SMTP. Voeg de mailinstellingen toe aan de `mail`-sectie van het privébestand `C:\usr\credentials.php`; zie [docs/mail-configuratie.md](docs/mail-configuratie.md). Installeer de afhankelijkheden vanuit de projectmap met `composer install`. De helper `includes/mailer.php` verzorgt SMTP-verzending.

Bij het aanmaken van een nieuw bedrijf vraagt het platformbeheer een e-mailadres voor de eerste bedrijfsbeheerder. Grompil maakt een company-gebonden account met de rol Beheerder aan en mailt een willekeurig tijdelijk wachtwoord. Bij de eerste aanmelding wordt de gebruiker naar **Mijn account** gestuurd om dat wachtwoord te wijzigen. Voer `schema.sql` uit om de kolom `users.must_change_password` toe te voegen voordat je deze functie gebruikt. Synchroniseer daarna de nieuwe vertaalteksten met `php scripts/import_translations.php` en `php scripts/export_translations.php`.

Stel de webroot in op deze map en configureer PHP via je webserver. Voor lokaal testen kan de PHP development server worden gebruikt.

## Inbegrepen

De login gebruikt password_hash() en password_verify(), parameterized queries, CSRF-tokens, beschermde sessiecookies, sessie-ID-vernieuwing en een tijdelijke rem op herhaalde mislukte inlogpogingen. De login-cookie en PHP-sessie zijn maximaal 30 dagen geldig; uitloggen verwijdert de cookie direct. Startrollen zijn Beheerder, Apotheker en Medewerker. Beheerders kunnen gebruikersrollen en rolrechten aanpassen.

## Versienummer

Het centrale versienummer staat in `VERSION` en wordt in de SaaS-menubalk en URL-manager getoond als `vers. x.y.z`. Verhoog de revisie (`x.y.z`) voor correcties, het deelnummer (`x.y.0`) voor uitbreidingen en het hoofdnummer (`x.0.0`) voor een ingrijpende of incompatibele wijziging. Voer na een wijziging van `VERSION` ook `php scripts/generate_navigation.php` uit, zodat de losse URL-manager dezelfde versie toont.

Bootstrap 5.3.8 wordt via de officiële CDN geladen met integrity-hashes. Documentatie: https://getbootstrap.com/docs/5.3/getting-started/introduction/

## Templates en vertalingen

De HTML staat in `tmpl/default/`; de PHP-modules verwerken requests en vullen placeholders zoals `@users_heading@` in. De modules staan per functie onder `src/` (`src/account/`, `src/companies/`, `src/users/` enzovoort). De bestanden in de projectroot blijven kleine URL-entrypoints die de juiste module laden. Zo blijven bestaande links en redirects werken terwijl de implementatie per domein gegroepeerd is. Gebruik voor nieuwe interne modulebestanden lowercase namen met underscores. De gedeelde procedurele functies staan in `includes/`, beheerscripts in `scripts/`, templates in `tmpl/` en taalbestanden in `src/lang/` met de codes `NL`, `FR`, `EN` en `DE`.

De projectroot-bestanden zijn publieke URL-entrypoints en stellen `GROMPIL_ROOT` in voordat ze een interne module laden. Bestanden in `src/` zijn intern en horen niet rechtstreeks via een URL gestart te worden. `src/.htaccess` is bedoeld om die map in Apache af te schermen; controleer per Apache-installatie dat `.htaccess`-regels met `AllowOverride` zijn toegestaan. Als extra PHP-bescherming gebruiken interne modules voortaan een gedeelde guard-include die via een pad onafhankelijk van `GROMPIL_ROOT` wordt geladen. Dupliceer de volledige guard niet in elk modulebestand. De gedeelde include staat in `includes/module_guard.php` en wordt gebruikt door de managerlabels-module. Bestaande modules moeten nog op deze include worden aangesloten; `src/account/select_company.php` heeft voorlopig een eigen guard.

Nieuwe modules krijgen consequent het projectbrede beginblokcommentaar met aanmaak- en wijzigingsdatum, credits, copyright, contactadres en een korte Engelstalige `@description`. De omschrijving staat na `@description: `; vervolgregels gebruiken dezelfde commentaarinspringing.

Hardcoded technische foutmeldingen en CLI-diagnostiek die niet via de vertaalroutine lopen, worden in het Engels geschreven. Bewust getoonde meldingen blijven zichtbaar; normale interface- en bedrijfsgerichte teksten gebruiken vertaalkeys of expliciete taalvarianten.

Hardcoded technische foutmeldingen en CLI-diagnostiek die niet via de vertaalroutine lopen, worden in het Engels geschreven. Bewust getoonde meldingen blijven zichtbaar; normale interface- en bedrijfsgerichte teksten gebruiken vertaalkeys of expliciete taalvarianten.

Controleer de template-placeholders met:

```powershell
php scripts/check_templates.php
```

De controle meldt vertaalkeywords die in een of meer taalbestanden ontbreken en `@_...@`-templatewaarden waarvoor geen PHP-arrayvervanging is gevonden. Dit is een statische controle; templatewaarden die dynamisch worden opgebouwd, kunnen handmatige controle vragen.

De publieke bedrijfswebsite gebruikt standaard `tmpl/default/store.html` (Grompil klassiek). Het gedeelde thema **Grompil rustig** staat in `tmpl/themes/serene/store.html` en gebruikt `assets/store-theme-serene.css`. De platformbeheerder kiest per bedrijf het thema in **Beheer → Bedrijven → Uiterlijk publieke website**; `companies.store_theme` bewaart die keuze. Andere gedeelde thema’s verschijnen automatisch zodra ze een map `tmpl/themes/<naam>/store.html` hebben. Een bedrijfsspecifieke `tmpl/companies/<company-id>/store.html` krijgt voorrang op het gekozen gedeelde thema. Alle templates gebruiken dezelfde `@...@`-vervanging. Voor bedrijfsbeelden zijn `@company_logo_url@` en `@company_hero_image_url@` beschikbaar; er zijn ook veilige HTML-fragmenten `@_company_logo_html@` en `@_company_hero_image_html@` voor de storefronttemplates.

Plaats afbeeldingen in `media/companies/<company-id>/` en vul in het bedrijfsbeheer alleen de bestandsnaam in (JPG, PNG, WebP of GIF). Een afbeelding wordt alleen getoond als het bestand werkelijk in die vaste map bestaat. De templatebestanden bevatten de layout; company-specifieke teksten blijven in de database/vertalingen.

HTML-templates worden leesbaar opgemaakt met structurele tags op aparte regels en vier spaties per inspringniveau. `.editorconfig` legt UTF-8, LF-regeleinden, vier spaties en een afsluitende newline vast.

Algemene vertalingen worden bijgehouden in de tabel `substitutions` met een rij per company-scope, sleutel en taalcode (`NL`, `FR`, `EN`, `DE`). De importer voegt alle algemene vertaalbare teksten toe onder `company_id = 0`. De Franse en Duitse sleutels worden initieel leeg aangemaakt; zolang ze leeg zijn toont de applicatie de Engelse tekst als terugval.

De zelfstandige URL-manager gebruikt `scripts/generate_navigation.php` om NAV-commentaarblokken uit aanroepbare modules om te zetten naar `navigation.json`. De generator hergebruikt de bestaande gegenereerde PHP-taalarrays en voert de applicatiemodules niet uit. De NAV-conventie, het JSON-schema en de eerste permanente module-ID's staan in [docs/url-manager.md](docs/url-manager.md).

```powershell
php scripts/import_translations.php
```

De uitbreidbare beheeromgeving staat op `admin.php`. Modules worden daar alleen getoond als de ingelogde gebruiker de bijbehorende machtiging heeft. Elke beheermodule controleert bovendien het strikte levelbereik tussen 1 en 100 (grenzen exclusief). Nieuwe beheerfuncties kunnen later als extra module worden toegevoegd.

Voor dagelijks vertaalbeheer gebruikt een beheerder met de rolmachtiging `translations.manage` de pagina `translations.php`. Die importeert ontbrekende sleutels, laat teksten per taal aanpassen en publiceert de PHP-taalbestanden. Voer `schema.sql` opnieuw uit om de machtiging aan de startrol Beheerder toe te kennen.

De CLI-scripts blijven beschikbaar als technische fallback. Exporteer wijzigingen naar de PHP-taalbestanden met:

```powershell
php scripts/export_translations.php
```

Geef desgewenst een taalcode mee, bijvoorbeeld `php scripts/export_translations.php FR`. De export genereert `src/lang/lang_<code-lowercase>.php` als `$arrSubst`-array. De vertaalde templatekeys staan tussen `@...@`. Placeholders die met `_` beginnen en runtimewaarden zoals een gebruikersnaam blijven rechtstreeks uit PHP komen. De importer vult alleen ontbrekende sleutels aan en overschrijft bestaande databasevertalingen niet. De webserver moet schrijfrechten hebben op `src/lang/` om taalbestanden te publiceren. Voor productie kun je `GROMPIL_LANGUAGE_DIR` instellen op een bestaande aparte map buiten de webroot; PHP moet daar bestanden kunnen lezen en vervangen. Als de taalbestanden daar nog niet staan, gebruikt de app bij de eerste paginaweergave de standaardbestanden en publiceert de beheerpagina ze naar die aparte map. Stel dezelfde variabele in voor de CLI-export als je die als fallback gebruikt.

## Registratie en accountgoedkeuring

Publieke registratie verloopt via `register.php`. Klantaccounts krijgen de rol Publiek en worden direct actief. Personeelsaanvragen blijven inactief totdat een beheerder ze goedkeurt en een personeelsrol toewijst. Registratie vereist een wachtwoord van minimaal 12 tekens en is per IP beperkt. Voer na deze schemawijziging `schema.sql` opnieuw uit in Adminer; de wijzigingen vullen bestaande tabellen aan.

Voer daarna `php scripts/import_translations.php` uit om de nieuwe vertaaltermen als ontbrekende sleutels aan de vertaaltabel toe te voegen, en vervolgens `php scripts/export_translations.php` om ze in de PHP-taalbestanden beschikbaar te maken.

Rollen hebben een numeriek `level`. De huidige startindeling is Beheerder 10, Apotheker 110, Medewerker 120 en Publiek 200. `has_level($exact)` vergelijkt exact (SS); met twee grenzen toetst het een open interval (SL), dus strikt groter dan de ondergrens en kleiner dan de bovengrens. Dit is een extra classificatie; routebeveiliging blijft ook expliciete rechten vereisen.

De tabel `access_ip_allowlist` is toegevoegd met aparte scopes `admin` en `staff` en PostgreSQL `inet`-netwerken. IP-blokkering wordt aangezet nadat de echte vaste IP-adressen van het bedrijf en beheeromgeving zijn vastgelegd; zo voorkom je dat de bestaande beheerder zichzelf buitensluit. De lijst is bedoeld als extra controle naast login en rechten. De publieke storefront is nu een basispagina; catalogus, voorraad, betaling en verkoopregels zijn nog niet aangesloten.

## Multi-tenant basis

Elk bedrijf is een tenant in `companies`. `company_users` koppelt gebruikers aan een bedrijf; `company_roles` bevat de rollen en rechten per bedrijf. Na het uitvoeren van `schema.sql` worden bestaande memberships op basis van hun rolnaam gekoppeld. De rol van een gebruiker in één bedrijf bepaalt geen toegang tot een ander bedrijf.

Gebruikers hebben naast `display_name` de velden `name_first` en `name_last`. Nieuwe accounts vragen voornaam en achternaam en vullen `display_name` met de gecombineerde naam. Bij bestaande accounts blijven de nieuwe velden leeg totdat ze later worden ingevuld.

Een gebruiker met één company-membership komt na het inloggen meteen in dat bedrijf. Bij meerdere memberships kiest die gebruiker via `select-company.php`. Platformbeheer kan actieve bedrijven kiezen en gebruikers koppelen via `company_members.php`; tenantbeheerders kunnen daar aanvragen goedkeuren en toegang beheren. Bedrijfsrollen beheer je via `company_roles.php` nadat je dat bedrijf als actieve werkplek hebt gekozen.

Platformbeheerders kunnen op `select-company.php` zoeken in het Belgische apothekenregister en een geregistreerde apotheek na een KBO-controle als afzonderlijke company aanmaken. Dit is een optionele, sectorspecifieke registratiebron; de SaaS-tenant en de overige bedrijfsmodules gebruiken generieke company-termen. Het KBO-nummer wordt uit `pharmacies."Vergunninghouder"` gelezen. De KBO-webservicegegevens horen in het private `credentials.php`-bestand onder `kbo` (`username` en `password`); aanvaard eerst de actuele gebruiksvoorwaarden via het KBO-portaal. Per dag zijn volgens de actuele FOD-documentatie maximaal 3.000 verzoeken beschikbaar. `company_permit_number` bewaart het externe vergunningsnummer.

`index.php` toont de Grompil-startsite. De marketingpagina’s zijn bereikbaar met `?page=home`, `services`, `about_us` of `contact`; de taalkeuze staat rechts in de navigatie. Actieve bedrijven met een publieke website verschijnen op de startpagina. Een bedrijf is bereikbaar via `/c=<slug>` (Apache `mod_rewrite`) of `index.php?c=<slug>`.

Bedrijfspagina’s gebruiken `company_pages`, met één rij per bedrijf, paginatype en taalcode. De pagina-indeling en standaardteksten staan in de PHP-bronbestanden; bedrijfsspecifieke inhoud hoort in de database. De inhoud wordt als platte tekst opgeslagen en HTML-geëscaped weergegeven. Het beheerformulier voor deze inhoud moet nog worden gebouwd.

De algemene vertalingen staan in `substitutions` met `company_id = 0` en worden naar `src/lang/lang_<taal>.php` gepubliceerd. Companybeheerders met de machtiging `company.translations.manage` kunnen in `company_translations.php` alleen eigen tekstoverschrijvingen beheren. De sleutel moet een bestaand algemeen keyword zijn. Overrides worden opgeslagen met het company-id en gepubliceerd naar `src/lang/companies/<company_id>/lang_<taal>.php`; bij het laden worden ze over de algemene taalarray gelegd. Verwijderen van een override laat de algemene vertaling weer gelden.

Mislukte logins worden in `login_attempts` per company, e-mailadres en IP-adres geteld. `company_id = 0` is de globale loginroute; een login vanaf een companypagina gebruikt het ID van die company.

Voer voor de company-vertalingen eerst het bijgewerkte `schema.sql` uit in Adminer en daarna `php scripts/import_translations.php` om nieuwe algemene vertaalsleutels toe te voegen. Publiceer daarna de algemene taalbestanden met `php scripts/export_translations.php`. De companybeheerder kan vervolgens per taal eigen overrides publiceren vanuit de beheerpagina.

Registratie en login via `/c=<slug>` zijn bedrijfsspecifiek. Elke bedrijfsregistratie maakt een eigen `users`-record met `company_id`; hetzelfde e-mailadres kan bij meerdere bedrijven bestaan, maar wordt daar niet als hetzelfde gebruikersrecord hergebruikt. `users.acronym` is optioneel en uniek binnen het bedrijf (hoofdletterongevoelig). Een gebruiker kan het bij registratie kiezen of later via **Mijn account** wijzigen; login accepteert het e-mailadres of acroniem. De schemawijziging dupliceert bestaande membership-accounts en deactiveert oude niet-platformaccounts. Maak vóór uitvoering een databasebackup en voer daarna het bijgewerkte `schema.sql` uit als databasebeheerder.

Voer voor deze wijziging eerst een database-export/backup uit. Voer daarna het bijgewerkte `schema.sql` uit in Adminer met een databasebeheerder; de toepassing moet verbinden als `grompil_app`, die geen eigenaar van de tabellen en geen `BYPASSRLS` mag zijn. Importeer daarna de nieuwe taaltermen met `php scripts/import_translations.php` en publiceer ze via `translations.php` of `php scripts/export_translations.php`. Controleer vervolgens welke bestaande apotheek en medewerker als eerste tenant gebruikt worden. De schemawijziging is voorbereid maar nog niet op jouw lokale database uitgevoerd.

De tenant-tabellen hebben PostgreSQL row-level security. De storefront, domeinkoppeling, membership en rolbasis zijn geïmplementeerd; productcatalogus, voorraad, patiëntendossiers, bestellingen, betalingen, auditing en productie-isolatiecontrole zijn volgende stappen. Zie [docs/multi-tenant-architecture.md](docs/multi-tenant-architecture.md) voor ontwerpafspraken en de resterende uitrolstappen.

# Grompil

## Databaseconfiguratie

De applicatie leest databasegegevens uit `C:\usr\credentials.php`, buiten de projectmap en webroot. Het bestand moet een PHP-bestand zijn dat een array teruggeeft met een `database`-sectie. Vul lokaal het databasewachtwoord in. Deel of commit nooit de ingevulde credentials.

De hoster kan een ander pad gebruiken via `GROMPIL_CREDENTIALS_FILE`; deze omgevingsvariabele bevat alleen het pad naar het bestand. Als het wachtwoord in het PHP-bestand is ingevuld, zijn de afzonderlijke `GROMPIL_DB_*`-wachtwoordvariabelen niet nodig.

## Belgische adresopzoeking

De adresvelden van bedrijven en gebruikers kunnen officiële Belgische suggesties gebruiken via BeSt Address. De browser praat alleen met `address_api.php`, dat de gratis openbare BOSA-endpoint server-side aanroept; voor deze endpoint is geen OAuth-configuratie nodig. `BEST_ADDRESS_API_URL` is optioneel en heeft als standaard de openbare dienst; die heeft geen beschikbaarheidsgarantie. Als de API tijdelijk onbeschikbaar is, blijven handmatige adresinvoer en opslaan mogelijk.

Voer na de adreswijziging opnieuw `schema.sql` uit. Daarna kun je `php scripts/import_translations.php` uitvoeren en de taalbestanden publiceren. Het formulier biedt suggesties voor postcode/gemeente, straat en huisnummer; pas na het kiezen van een officieel adres wordt de BeSt-identificator opgeslagen.

Voer na een schemawijziging `schema.sql` opnieuw uit in Adminer. Het bestand gebruikt idempotente tabellen en kolommen.

