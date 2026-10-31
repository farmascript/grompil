<!--
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Tracks Grompil progress, current status, and next steps.
 *
 */
-->

# Voortgang Grompil

## 25 oktober 2026

### Omgevingsafhankelijke credentials

- `includes/credentials_config.php` leest nu per installatie `settings_local.php` en aanvaardt uitsluitend `local`, `test` of `production`. Selectie op basis van bezoeker-IP, Host-header of proxyheaders wordt niet gebruikt.
- De locaties kiezen respectievelijk `credentials_local.php`, `credentials_db_test.php` en `credentials_db_production.php`. Dezelfde arraystructuur met `database`, `kbo` en `mail` blijft beschikbaar; secrets blijven buiten de webroots.
- `.gitignore` sluit `settings_local.php` uit. De lokale marker wordt met een `GROMPIL_ROOT`-controle beschermd tegen rechtstreekse webtoegang; CLI-opdrachten mogen hem laden.
- Ontbrekende of ongeldige markers en ontbrekende private credentials leiden niet tot een stille productie-fallback. De webapp toont de bestaande vertaalde databaseconfiguratiefout.
- README-, mail- en wachtwoordherstel-documentatie bijgewerkt. De databasecontrolehelper gebruikt nu dezelfde selector.

### Huidige stand

- De code verwacht dat elke installatie een eigen `settings_local.php` in de projectroot heeft en dat de corresponderende credentialsfile één niveau boven de hostingwebroot staat. Er zijn in deze wijziging geen lokale of hostingsecrets aangemaakt of gekopieerd.

### Volgende stap

1. Maak lokaal `settings_local.php` met `location => local` en plaats `credentials_local.php` buiten de lokale webroot.
2. Maak op de host afzonderlijke markers in `public_html` (`production`) en `grompil_test` (`test`), en plaats `credentials_db_production.php` en `credentials_db_test.php` erboven.
3. Vul in elk credentialsbestand uitsluitend de gegevens van de bijbehorende omgeving in; gebruik aparte databasegebruikers en controleer de verbinding eerst op test.

## 24 oktober 2026

### Naamgeving database-migraties

- Alle SQL-bestanden in `migrations/` gebruiken nu `YYYYMMDD-HHMMSS_omschrijving.sql`. Datum en tijd zijn 24-uurs lokale tijd; dezelfde datum sorteert daardoor chronologisch op bestandsnaam. Nieuwe migraties krijgen bij aanmaak een unieke tijdstempel.

### Klantenmonitor per bedrijf

- Een aparte pagina **Klanten beheren** toegevoegd voor beheerders met het bedrijfsrecht `company.users.manage`. De pagina gebruikt steeds de actieve bedrijfscontext; een bedrijfskiezer is niet nodig en een beheerder kan geen klanten van andere bedrijven opzoeken.
- De type-ahead zoekt serverzijdig op naam, e-mailadres, acroniem of gebruikersnummer, vanaf twee tekens, en toont maximaal twintig suggesties. Alleen klantaccounts (globaal en binnen het bedrijf level 200 of hoger) verschijnen.
- Na keuze toont de pagina accountstatus, goedkeuringsstatus, bedrijfsrol en -toegang, aanmaakdatum en laatste aanmelding. Er zijn geen wijzigingsacties of adresgegevens op deze monitor.
- `migrations/20261024-105957_customer_monitor_search.sql` voegt prefixindexen voor de drie tekstvelden toe. Voer deze migratie uit op de testdatabase en later op productie voordat je de pagina daar gebruikt. `schema.sql` bevat dezelfde indexen.
- Nieuwe teksten zijn toegevoegd voor NL, FR, EN en DE. Navigatiemetadata is toegevoegd en de applicatieversie is bijgewerkt naar `0.5.0`.

### Huidige stand

- De klantenmonitor is in de code opgenomen en beperkt alle zoek- en detailopvragingen tot het actieve bedrijf. De database-indexmigratie moet nog op de betreffende database worden uitgevoerd.

### Volgende stap

1. Voer `migrations/20261024-105957_customer_monitor_search.sql` uit op de lokale database en importeer/publiceer de nieuwe vertalingen.
2. Meld aan als beheerder van een bedrijf en controleer dat de navigatie naar **Klanten beheren** verschijnt; probeer een klant op naam, e-mailadres en acroniem te vinden.
3. Controleer dat een medewerker zonder `company.users.manage` geen toegang heeft en dat een andere bedrijfscontext geen klantgegevens kan teruggeven.

### Resetlink opnieuw versturen en platformparameters

- Na het eerste resetverzoek toont de pagina geen e-mailveld meer, maar een knop **Link opnieuw versturen**. De knop gebruikt hetzelfde adres en dezelfde CSRF-bescherming.
- Het maximum en de schuivende wachttijd zijn instelbaar in **Beheeromgeving → Systeemparameters**. De standaard blijft drie aanvragen per e-mailadres per 15 minuten, inclusief de eerste. Na het maximum wordt de knop tijdelijk uitgeschakeld en automatisch weer actief na de wachttijd. De aanvullende grens van twintig verzoeken per IP per uur blijft gelden.
- De instellingen staan in `platform_system_settings`; `migrations/20261024-110624_platform_system_settings.sql` maakt de tabel aan en stelt de beginwaarden in. De tabel bevat uitsluitend niet-geheime runtimeparameters: de applicatie kan ze lezen en alleen de platformbeheerder kan ze wijzigen. Bewaar hier geen wachtwoorden of sleutels.
- Uitrol: maak een databaseback-up en voer de migratie uit op elke database. De bijgewerkte `schema.sql` bevat dezelfde tabel en beleidsregels.

## 21 oktober 2026

### Wachtwoordherstel

- De login bevat **Wachtwoord vergeten?**, met behoud van de bedrijfsroute. Aanvragen via `forgot-password.php` versturen met de bestaande SMTP-helper een eenmalige resetlink; `reset-password.php` laat een nieuw wachtwoord instellen.
- Nieuwe migratie `migrations/20261021-183938_password_reset.sql` en overeenkomstige aanvulling in `schema.sql`: gehashte resettokens, verzoeklimieten en `users.auth_version` voor het ongeldig maken van eerdere sessies na reset. Gewone logins blijven vóór de migratie bruikbaar.
- Links verlopen na 30 minuten, zijn aan het account en bedrijf gebonden en worden bij gebruik of vervanging ongeldig. De schermbevestiging onthult niet of een account bestaat. Aanvragen zijn begrensd per IP en per e-mailadres/bedrijf; formulieren gebruiken CSRF. Geldige mailtokens verdwijnen via een redirect uit de zichtbare URL.
- Zeventien nieuwe vertaalkeys voor schermen en e-mail toegevoegd in NL, FR, EN en DE, geïmporteerd en gepubliceerd op deze werkplek.
- `scripts/test_password_reset.php` slaagt met tijdelijke PostgreSQL-tabellen, zonder bestaande accounts te wijzigen of mail te sturen. De test dekt onder meer bedrijfsscheiding, hashing, vervallen/vervangen/hergebruikte links, accountstatus, verzoeklimieten, sessieversie, herhaalbare migratie en vertrouwde URL's. Browsercontrole bevestigt de resetlink en het aanvraagformulier voor Ariodante.
- Nog nodig: de databasebeheerder moet de kleine migratie uitvoeren; de applicatiegebruiker is geen eigenaar van `users` en kan die niet toepassen. Daarna moet de echte mailontvangst/reset met een eigen account worden gecontroleerd. Op hosting `GROMPIL_PUBLIC_URL` instellen; lokaal is automatisch `http://localhost:8181/grompil/` beschikbaar. Zie `docs/password-recovery.md`.

### Namiddag: URL-manager met meerdere bladen

- Achtergrondkleuren van bedrijfsblokken hersteld: de kleuracties wijzigden een tijdelijke weergavekopie in plaats van het blok in `state.personal.groups`. Zowel bewaren als herstellen van de standaardkleur wijzigt nu het opgeslagen blok. Gerichte controle met de echte kleuracties, opslagfunctie en weergaveroutine bevestigt tonen, bewaren en terugladen van een kleur op blad2, plus blijvend herstel van de standaardkleur. JavaScript-syntaxcontrole slaagt.
- Herstel na melding van een verdwijnend bedrijfsblok: bij een ingelogde gebruiker zonder geselecteerd bedrijf gaf de voorkeuren-API HTTP 409, waarop de manager de lokale indeling leegmaakte. Deze situatie behoudt nu de zelfstandige browserindeling. Bij het verlaten van een bedrijfscontext wordt alleen de aparte lokale kopie hersteld, zonder bedrijfsgegevens over te nemen.
- Het laatst geopende blad wordt per lokale/bedrijfscontext in de browser onthouden. Toevoegen van een bedrijfsblok blijft automatisch opslaan; er is geen extra bewaarknop nodig. Gerichte regressiecontroles voor herhaalde synchronisatie zonder bedrijf, herstel van het laatste blad en scheiding van lokale/bedrijfsgegevens slagen.
- Vervolg: 65 algemene `manager_`-vertaalkeys toegevoegd in NL, FR, EN en DE, geïmporteerd in de lokale database en gepubliceerd naar de taalbestanden. Managerknoppen, bladbediening, veldlabels en standaardbladnamen volgen nu de taalkeuze. Eigen bladnamen blijven behouden; nieuwe namen kunnen per taal worden ingesteld.
- Online worden gepubliceerde managerlabels via `navigation_labels.php` geladen; de offline catalogus bevat dezelfde teksten. De nieuwe interne labelsmodule gebruikt de gedeelde `includes/module_guard.php` tegen directe uitvoering.
- De werkbalk heeft een automatische hoogte en kan bij kleinere breedtes over meerdere regels lopen. De bladnavigatie heeft eigen witruimte en een scheidingslijn. Browsercontrole bij 1168 en 850 pixels bevestigt dat de exportknop en bladknoppen niet overlappen.
- De navigatiegenerator herkent nu ook NAV-blokken met Windows-regeleinden. De catalogus bevat opnieuw alle 12 bestaande modules. De eerder ontbrekende `company_select`- en `company_select_intro`-vertalingen blijven als bestaande aandachtspunten staan.
- De bestaande lokale database is volgens de gebruiker met `schema.sql` bijgewerkt en werkt met de huidige code.
- De URL-manager begint met `blad1` en `blad2`. De laatste knop `blad toevoegen` maakt een volgend blad en opent het. Een potlood naast elke bladnaam opent het hernoemformulier.
- Bestaande indelingen en vaste Grompil-blokken blijven op het eerste blad. Nieuwe bedrijfsblokken krijgen het geopende blad. Via de blokopties kan een blok samen met zijn links naar een ander blad worden verplaatst; de linkeditor toont blad- en bloknamen samen.
- Bladen en bloktoewijzingen worden in de bestaande browser-/bedrijfsconfiguratie opgeslagen en gaan mee met JSON-export en -import. Hiervoor is geen nieuwe databasekolom of migratie nodig. De bestaande bedrijfsrechten blijven gelden.
- Browsercontrole geslaagd voor een blok en link op het tweede blad, hernoemen, behoud na herladen en verplaatsen van een blok met zijn link. Tijdelijke controlelink en controleblok zijn verwijderd en de bladnaam is hersteld.
- Aanvullende controles geslaagd voor migratie van oude configuratie, bladvalidatie, blad toevoegen, scheiding van bladinhoud, opslagformaat, alleen-lezenbediening en samenvoegen van geïmporteerde bladen. JavaScript-/PHP-syntax en templatecontrole slagen.
- De browsercontrole gebruikte lokale opslag; het opslaan van de bladen via een ingelogde bedrijfscontext moet nog in dagelijks gebruik worden bevestigd.

### Vandaag gedaan

- De tenantkolom `companies.pharmacy_permit_number` is vervangen door `companies.company_permit_number`; `schema.sql` migreert bestaande waarden en indexeert de nieuwe kolom.
- Je hebt het bijgewerkte `schema.sql` op de database uitgevoerd.
- De storefront gebruikt nu de generieke templatewaarde `@company_name@`. Publieke company-links gebruiken `?company=`; bestaande `?pharmacy=`-links blijven geaccepteerd.
- Algemene architectuur- en README-tekst spreekt nu over bedrijven. De apothekenregistertabel en zoekmodule blijven expliciet apotheek-specifiek omdat ze een echte externe sectorregistratie raadplegen.
- Databasevertalingen en hun keywords zijn niet aangepast.
- Het btw-nummer is optioneel in formulier, servervalidatie en databaseschema. Aan het formulier is de vertaalde toelichting `no_vat_number` toegevoegd; zonder btw-nummer blijft de databasewaarde leeg. De vertaalkey is geïmporteerd en naar de NL-, FR-, EN- en DE-taalbestanden geëxporteerd.
- Facturen moeten bij een lege btw-waarde de vertaalde presentatietekst `company_vat_number_missing` tonen. Die tekst wordt niet in het btw-veld opgeslagen; een factuurmodule is nog niet aanwezig.
- Aan de IP-toegangspagina is een vertaalde knop `return_to_navigation` toegevoegd die teruggaat naar `dashboard.php`.
- Vastgelegd dat hardcoded technische foutmeldingen en CLI-diagnostiek die niet door de vertaalroutine lopen in het Engels worden geschreven. De meldingen blijven bewust zichtbaar; alleen hun taal verandert.
- Engelse technische meldingen bijgewerkt in databaseconfiguratie, KBO-, mail- en adreshelpers en de directe uitvoer van beheerscripts. De KBO-configuratiefout gebruikt nu de stabiele token `kbo_not_configured`, die naar de bestaande vertaalde schermtekst verwijst.
- De niet-vertaalde IP-toegangspagina en templatecontrolefeedback op Engels gezet. De normale vertaalde schermteksten zijn ongewijzigd.
- `curl_close()` uit de adres-API verwijderd: sinds PHP 8 is dit een no-op en in PHP 8.5 is de functie deprecated.

### Huidige stand

- De algemene bedrijfswerking gebruikt `company_permit_number` en `?company=`; bestaande `?pharmacy=`-links blijven werken. De bijgewerkte schemakolom is op de database uitgevoerd.
- Een btw-nummer is optioneel. De IP-toegangspagina toont na een wijziging de bevestiging met de extra knop `return_to_navigation`.
- Hardcoded technische meldingen die niet door de vertaalroutine lopen, worden waar aangeraakt in het Engels getoond. Gewone gebruikersinterfacecopy blijft vertaald.

### Volgende stap

1. Controleer op de andere werkplek of de knop `Terug naar navigatie` na het opslaan van de IP-instelling correct naar de navigatie terugkeert.
2. Controleer daar ook de bedrijfswebsite en optionele btw-nummerinvoer met de bijgewerkte code en database.
3. Ga daarna verder met het veralgemenen van resterende bedrijfsspecifieke namen; laat apotheekregisterintegraties en de bestaande databasevertalingen ongewijzigd totdat die apart worden aangepakt.
4. Blijf de taalafspraak toepassen: onvertaalde technische foutmeldingen en CLI-diagnostiek in het Engels, normale gebruikersinterfacecopy via de vertaalroutine.

## 20 oktober 2026

### Vandaag gedaan

- De PHP-paginamodules zijn op functie ingedeeld onder `src/`: account, admin, api, companies, navigation, site, store, translations en users. Interne mappen en nieuwe modulebestanden gebruiken lowercase namen met underscores.
- De bestaande PHP-bestanden in de projectroot zijn behouden als kleine URL-entrypoints die de nieuwe modules laden. Bestaande webadressen, formulieren, redirects en de navigatiecatalogus blijven daardoor naar dezelfde publieke routes verwijzen.
- De interne bronbestanden en taalbestanden zijn tegen rechtstreekse HTTP-toegang afgeschermd met `src/.htaccess`.
- De architectuur en de vaste bestandsindeling zijn beschreven in `README.md`. Nieuwe modules volgen voortaan consequent het beginblokcommentaar met correcte datums, credits, copyright, e-mailadres en een korte Engelstalige `@description`.
- De bestaande `@description`-teksten zijn naar het Engels vertaald en inline gezet na `@description: `; de commentaarinspringing blijft intact.
- De navigatiecatalogus is opnieuw gegenereerd. De 12 navigatiemodules verwijzen naar bestaande projectroot-entrypoints.
- Statische controles zijn geslaagd: PHP-syntax voor 71 bestanden en templatecontrole voor 44 templates en 587 tokens.

### Huidige stand

- De functiegroepen zijn fysiek gescheiden onder `src/`; gedeelde helpers blijven in `includes/`, scripts in `scripts/`, HTML in `tmpl/` en de algemene taalbestanden in `src/lang/`.
- De runtime routes zijn compatibel gehouden via de bestaande root-entrypoints. De nieuwe modulebestanden horen alleen via deze entrypoints geladen te worden.
- De navigatiegenerator meldt nog ontbrekende vertaalsleutels `company_select` en `company_select_intro` voor NL, FR, EN en DE; voor die labels is momenteel een placeholder gepubliceerd.
- De syntax- en templatecontroles zijn uitgevoerd. De lokale browserroutes en de directe HTTP-blokkering van `src/` moeten nog op de Apache-installatie worden nagekeken.

### Volgende stap

1. Controleer in de browser de startpagina, login, registratie, bedrijfswebsite en de beheerpagina’s via hun bestaande root-URL’s.
2. Controleer dat een rechtstreekse HTTP-aanvraag naar een PHP-bestand onder `src/` wordt geweigerd.
3. Voeg de vertalingen voor `company_select` en `company_select_intro` toe in NL, FR, EN en DE en publiceer de navigatiecatalogus opnieuw.
4. Plaats volgende nieuwe modules in de passende functiegerichte map onder `src/` en gebruik het vastgelegde beginblokcommentaar.

## 18 oktober 2026

### Vandaag gedaan

- De URL-manager scheidt nu de vaste Grompil-navigatie van de bedrijfsindeling. Alleen een ingelogde platformbeheerder kan vaste Grompil-links verslepen of vaste Grompil-groepen aanmaken, aanpassen en verwijderen.
- De globale platformindeling wordt opgeslagen in PostgreSQL via `platform_navigation_preferences.php`; de nieuwe tabel en RLS-rechten zijn opgenomen in `schema.sql` en in migratie `migrations/20261018-212150_platform_navigation_preferences.sql`.
- Bedrijfslinks en bedrijfsblokken blijven onder de bestaande bedrijfsrechten vallen en worden opgeslagen in `company_navigation_preferences`.
- Het verslepen van blokken in de URL-manager is verbeterd zodat een blok ook naar een lege plek in het grid kan worden verplaatst. De grid heeft voldoende minimale hoogte om lege dropzones te bieden; de bestaande wisselwerking tussen twee bezette plekken blijft beschikbaar.
- `url-manager/styles.css` is opnieuw geformatteerd met consistente inspringing en één CSS-declaratie per regel. Dit maakt de styles leesbaarder; de browser blijft hetzelfde CSS-bestand gebruiken.
- Bedrijfsbloktitels worden nu per taal opgeslagen en weergegeven. Een titel die in EN wordt aangepast hoort daardoor niet de FR-, NL- of DE-titel te overschrijven.
- Bij persoonlijke managerlinks kan een lokaal pad zoals `/grompil/translations.php` worden ingevoerd. De URL-manager voegt dat toe aan de ingestelde basis-URL; volledige `http://`- en `https://`-adressen blijven bruikbaar voor externe sites.
- Bij het laden worden persoonlijke links die nog een volledige URL naar dezelfde Grompil-installatie bevatten omgezet naar een relatief pad met behoud van querystring en fragment. Externe URL's worden niet omgezet. De manager slaat de genormaliseerde configuratie vervolgens op in de beschikbare opslaglaag.
- Voor toegang per IP-adres is de bestaande tabel `access_ip_allowlist` hergebruikt; daarvoor is geen nieuwe schemamigratie nodig. `includes/bootstrap.php` bepaalt het server-waargenomen adres via `REMOTE_ADDR`, controleert voor company-beheerpagina's de actieve `admin`-allowlist en weigert toegang bij een mismatch. Een lege allowlist blijft onbeperkt om een nieuwe beheerder niet buiten te sluiten.
- Achter een reverse proxy worden `X-Forwarded-For`-adressen alleen gebruikt als het directe `REMOTE_ADDR` voorkomt in de expliciet ingestelde omgevingsvariabele `GROMPIL_TRUSTED_PROXIES`. De keten wordt vanaf de vertrouwde proxyzijde verwerkt; willekeurige doorgestuurde headers worden niet vertrouwd.
- `login.php` controleert de company-IP-regel ook vóór het aanmelden via een bedrijfsroute. De beperking geldt alleen voor beheer (`admin`); publieke bedrijfswebsites en publieke registratie blijven toegankelijk.
- Nieuwe beheerpagina `company_security.php` toont het door de server waargenomen IP-adres en de actieve beheerregels. Een companybeheerder kan het huidige adres als enige toegestane beheerlocatie instellen of de beperking uitschakelen. De wijziging gebeurt transactioneel.
- De gedeelde paginarendering toont nu een gewone footer met copyright. Voor platformbeheerders en companybeheerders met `company.settings.manage` wordt daarin ook het waargenomen IP-adres getoond. De footer staat in de normale documentstroom en blijft dus niet over de pagina-inhoud heen zweven.
- De navigatiecatalogus is opnieuw gegenereerd; `navigation.json` en `url-manager/catalog.js` bevatten de nieuwe beveiligingspagina.
- Voor `company_roles.name` is een PostgreSQL-query gegeven om alleen de eerste letter van bestaande waarden naar kleine letters om te zetten. Dit was een instructie voor handmatig databasegebruik; het is niet als automatische codewijziging uitgevoerd.

### Huidige stand

- Voer voor de bestaande lokale database `migrations/20261018-212150_platform_navigation_preferences.sql` uit als databasebeheerder, of neem de nieuwe tabel en policies mee wanneer `schema.sql` opnieuw wordt toegepast.
- Na de migratie kan een platformbeheerder vanuit de URL-manager vaste Grompil-groepen aanmaken en vaste links verplaatsen. Die indeling geldt daarna voor alle bedrijven.
- De managerwijzigingen zijn in de browser te gebruiken; de cacheversies van de manager-assets zijn verhoogd zodat de browser de bijgewerkte JavaScript, CSS en iframepagina opnieuw laadt. Same-origin persoonlijke URL's worden pas genormaliseerd wanneer de managerconfiguratie opnieuw wordt geladen.
- De IP-regels beperken alleen beheertoegang. De server kan alleen het adres zien dat de verbinding met PHP aanbiedt. Lokaal kan dat `::1` zijn (loopback), wat niet het publieke IP-adres van de computer onthult.
- Op een hostingomgeving achter een proxy moet `GROMPIL_TRUSTED_PROXIES` ingesteld worden op de exacte IP-adressen van de vertrouwde proxy's. Zonder die configuratie gebruikt Grompil het directe `REMOTE_ADDR`, dat dan mogelijk het proxy-adres is. Configureer dit vóór het inschakelen van een allowlist op productie.
- Een vast IP-adres kan wijzigen. Controleer daarom eerst welk adres de beveiligingspagina toont, en stel pas daarna de beperking in. Een platformbeheerder behoudt een herstelroute.
- De footer zit in de gedeelde, ingelogde paginarendering. Losstaande openbare storefront-templates hebben hun eigen template/footer; de beheer-IP-weergave is daar niet bedoeld.
- De labels en uitleg van `company_security.php` zijn momenteel grotendeels Nederlandstalig. De NAV-titel gebruikt wel het bestaande vertaalsysteem. Voor productie moeten de schermteksten nog naar NL, FR, EN en DE worden vertaald.
- De nieuwe beveiligingspagina is opgenomen in de gegenereerde catalogus. De code en navigatie zijn bijgewerkt, maar de IP-instelling moet nog in de browser worden gecontroleerd met de lokale omgeving.

### Volgende stap

1. Controleer in de manager dat vaste blokken naar lege gridplekken kunnen worden versleept en dat platformbeheerderaanpassingen na herladen bewaard blijven.
2. Controleer dat bedrijfsbloktitels per taal blijven verschillen en dat bestaande lokale Grompil-links nu als relatief pad opgeslagen staan.
3. Open als companybeheerder **Beveiligingsinstellingen**, noteer het getoonde IP-adres en stel alleen als dit het juiste adres is de beperking in. Controleer daarna zowel toegestane toegang als herstel via platformbeheer.
4. Voeg vertalingen voor de beveiligingsinstellingen toe in NL, FR, EN en DE en publiceer ze via de bestaande vertaalworkflow.
5. Vóór hosting: configureer de exacte vertrouwde proxy-IP's in `GROMPIL_TRUSTED_PROXIES`, controleer welk clientadres PHP ziet en test de allowlist voordat je die voor echte beheerders activeert.
6. Voer de migratie `migrations/20261018-212150_platform_navigation_preferences.sql` uit als die nog niet op de lokale database is uitgevoerd; dit staat los van IP-beveiliging, waarvoor de bestaande `access_ip_allowlist`-tabel wordt gebruikt.

## 17 oktober 2026

### Vandaag gedaan

- De bestaande codebasis bevestigd als `C:\usr\Dropbox\php\grompil`.
- De NAV-commentaarconventie vastgelegd en een procedurele CLI-generator toegevoegd in `scripts/generate_navigation.php`. De generator leest PHP-modules als tekst, haalt vertalingen uit de bestaande `$arrSubst`-taalarrays, detecteert dubbele IDs en schrijft alleen na geldige controle `navigation.json`.
- NAV-blokken uitgebreid voor negen modules, waaronder platformbeheer, bedrijfsmedewerkers, rollen, vertalingen, accountbeheer, de startpagina en templatecontrole. De generator levert de meertalige catalogus `navigation.json` met NL, FR, EN en DE.
- De zelfstandige browsergebaseerde URL-manager gebouwd en verder uitgebreid met groepen, tegels, zoeken, nummering, favorieten, JSON-import/export, kolom- en tekstgroottekeuze en bewerkbare bedrijfsindeling. De vaste Grompil-catalogus en bedrijfsaanpassingen worden apart behandeld; de bedrijfsindeling wordt in PostgreSQL bewaard.
- De manager toont waar de indeling wordt bewaard (lokaal of SaaS) en gebruikt beschrijvingstooltips. De navigatieknop hergebruikt de geopende manager-tab.
- De navigatieheader verduidelijkt voor platformbeheerders de werkcontext met **“Voor: [company]”**. Gewone bedrijfsleden zien de naam van hun actieve company. De uitlogknop, company-badge en navigatieknop gebruiken Bootstrap-tooltips.
- **Medewerkers beheren** opent voor een platformbeheerder nu zonder vooraf geselecteerde company. Een type-ahead companyzoekveld toont de inhoud pas nadat een company is gekozen. Bij bestaande medewerkers kan de bedrijfsrol gewijzigd en opgeslagen worden.
- `navigation.espanso.yml` aangemaakt met Espanso-triggers voor de negen links uit `navigation.json`, gericht op `http://localhost:8181/grompil/`.
- De technische afspraken en uitvoerwijze vastgelegd in `docs/url-manager.md` en gelinkt vanuit README.

### Huidige stand

- `scripts/generate_navigation.php` gebruikt `src/lang/lang_<taal>.php` of `GROMPIL_LANGUAGE_DIR`; missende/lege vertalingen vallen terug op EN en daarna NL. Moduletoegang en autorisatie blijven in Grompil.
- De URL-manager is bruikbaar als browserstartpagina. Vaste links zijn niet versleepbaar; bedrijfsaanpassingen worden aan de vaste catalogus toegevoegd en in de database bewaard.
- `company_members.php` ondersteunt nu het kiezen van een company en aanpassen van bestaande lidmaatschapsrollen. De nieuwe rolwijziging moet nog in de browser worden uitgeprobeerd.
- `navigation.espanso.yml` bevat vaste lokale URL's. Als poort of installatiepad verandert, moet de basis in dit bestand aangepast worden. Nieuwe modules uit `navigation.json` komen niet automatisch in het Espanso-bestand.
- De eerder afgesproken database-export naar Dropbox blijft de werkwijze om de ontwikkelomgeving tussen werkplekken te synchroniseren.

### Volgende stap

1. Controleer in de browser dat een bestaande medewerker bij PharmaSil de gewenste bedrijfsrol kan krijgen en dat de rol daarna correct in de kopbalk en het medewerkersoverzicht staat.
2. Gebruik de Espanso-triggers en controleer dat de lokale URLs openen; pas zo nodig de basis-URL aan.
3. Voeg NAV-blokken toe aan nieuwe modules en genereer daarna `navigation.json` opnieuw. Overweeg vervolgens een generator die ook `navigation.espanso.yml` vanuit dezelfde JSON bijwerkt.

## 16 oktober 2026

### Vandaag gedaan: storefronts

- De storefront kan per bedrijf een eigen template gebruiken in `tmpl/companies/<company-id>/store.html`. Als die ontbreekt, gebruikt de site het geselecteerde gedeelde thema uit `tmpl/themes/<thema>/store.html`, met `tmpl/default/store.html` als terugval.
- In **Beheer → Bedrijven** zijn keuze van een beschikbaar gedeeld thema en bestandsnamen voor logo en hero-afbeelding toegevoegd.
- Bedrijfsafbeeldingen worden gezocht in `media/companies/<company-id>/`. De template krijgt `@company_logo_url@` en `@company_hero_image_url@`; de standaardtemplate krijgt daarnaast veilige HTML-fragmenten voor logo en hero-afbeelding.
- `schema.sql` voegt de velden `store_theme`, `store_logo_file` en `store_hero_image_file` idempotent toe. README beschrijft de templatehiërarchie, afbeeldingsmappen en placeholders.
- Bij het koppelen van een bestaande gebruiker aan een bedrijf is een bedrijfsrol nu verplicht. De server weigert een ontbrekende of ongeldige rol; vóór opslaan toont een Bootstrap-bevestiging de gebruiker, het bedrijf en de rol. De labels zijn vertaald naar NL, FR, EN en DE.
- De login-cookie en PHP-sessie blijven nu maximaal 30 dagen geldig. Uitloggen verwijdert de login-cookie direct. Het sluiten van de cookiemelding wordt in de browser 180 dagen onthouden.

### Huidige stand

- De nieuwe company-kolommen moeten nog met `schema.sql` in de lokale database worden toegevoegd.
- `users.company_id = NULL` is correct voor de platformbeheerder: die hoort niet bij één bedrijf en kan bedrijven beheren. Koppel dit account niet als gewone bedrijfsmedewerker.
- Er is nog geen gedeeld thema naast `default`; nieuwe thema’s verschijnen automatisch zodra hun map `tmpl/themes/<naam>/store.html` bevat.
- Afbeeldingen worden handmatig in de company-mediamap geplaatst; een uploadfunctie is niet toegevoegd.
- De login-cookie verloopt uiterlijk na 30 dagen; de PHP-sessiedata is op dezelfde termijn ingesteld. De cookiemelding blijft na sluiten 180 dagen verborgen in die browser.

### Volgende stap

1. Maak een databasebackup en voer het bijgewerkte `schema.sql` uit.
2. Plaats desgewenst logo- en hero-bestanden in `media/companies/<company-id>/` en vul de bestandsnamen in bij **Beheer → Bedrijven**.
3. Maak voor een eerste alternatief thema `tmpl/themes/<naam>/store.html`, of maak voor één bedrijf `tmpl/companies/<company-id>/store.html`.
4. Voer `php scripts/import_translations.php` uit en publiceer de bijgewerkte NL-, FR-, EN- en DE-bestanden, inclusief de nieuwe rolbevestiging en cookie-uitleg.
5. Controleer de publieke bedrijfswebsite, de bevestiging bij het toevoegen van medewerkers, en het gedrag van de login-cookie en cookiemelding.

### Eerder vandaag: platform- en bedrijfsfuncties

- `select-company.php` uitgebreid voor platformbeheerders met zoeken en paginering van het apothekenregister, een detailweergave en een expliciete aanmaakstap.
- Het KBO-nummer wordt uit `Vergunninghouder` gehaald. De officiële KBO-webservice kan de onderneming controleren en publieke naam-, status-, adres-, telefoon- en e-mailgegevens tonen. De extra telefoon/e-mail en juridische naam worden bij het aanmaken opgeslagen; het adres uit het apothekenregister blijft het vestigingsadres.
- `companies` krijgt `legal_name` en `pharmacy_permit_number`. De unieke koppeling geldt per vergunningsnummer, omdat één KBO-nummer bij meerdere apotheken kan voorkomen. Het bestaande unieke index op `enterprise_number` wordt verwijderd.
- De app-rol krijgt leesrecht op de tabel `pharmacies` wanneer die in `public` aanwezig is. Nieuwe UI-teksten zijn toegevoegd in NL, FR, EN en DE.

- De root van Grompil toont nu de SaaS-startpagina, geïnspireerd op `media/ontwerp_startpagina.png`, met de twee logo-assets uit `media/`.
- De pagina’s home, diensten, over ons en contact zijn beschikbaar via `index.php?page=...`. De Grompil-teksten zijn toegevoegd aan de NL-, FR-, EN- en DE-taalbestanden en kunnen via de bestaande vertaalworkflow worden beheerd.
- De taalkeuze staat rechts in de navigatie. De homepage bevat daarnaast een lijst van actieve bedrijven die hun publieke website hebben ingeschakeld.
- Bedrijven zijn bereikbaar met `/c=<slug>` via `.htaccess`; `index.php?c=<slug>` is de alternatieve route. Per bedrijf zijn home, diensten, over ons en contact beschikbaar.
- Bedrijfsspecifieke pagina-inhoud kan per taal in `company_pages` worden opgeslagen. De structuur en fallbackteksten blijven in PHP; bedrijfsinhoud wordt als gewone tekst opgeslagen en veilig gerenderd. Een invoerscherm om die teksten te beheren is nog niet gemaakt.
- Registratie en login vanuit een bedrijfsroute zijn op dat bedrijf begrensd. `users.company_id` en unieke e-mailadressen per bedrijf zorgen ervoor dat dezelfde e-mail bij verschillende bedrijven aparte gebruikersrecords kan hebben.
- `company_members.php` koppelt voortaan alleen een ongekoppeld algemeen personeelsaccount aan een bedrijf en sluit platformbeheerders daarvan uit.
- `users.acronym` is toegevoegd als optionele, hoofdletterongevoelige loginnaam, uniek binnen het bedrijf. Nieuwe gebruikers kunnen het bij registratie kiezen; bestaande gebruikers kunnen het zelf instellen of wijzigen via **Mijn account**. Het inlogscherm accepteert zowel e-mailadres als acroniem.
- De onduidelijke melding **Aanvraag in behandeling** op een bedrijfspagina is vervangen: accounts zonder bedrijfstoegang krijgen nu uitleg, gebruikers die bij een ander bedrijf zijn aangemeld krijgen een afmeldknop, en platformbeheerders zien de beheerlink.
- De accountpagina controleert dat een acroniem niet botst met een ander e-mailadres of acroniem binnen dezelfde accountscope. De SQL-index blijft de unieke waarde afdwingen bij gelijktijdige wijzigingen.

### Huidige stand

- Voer `schema.sql` opnieuw uit op de lokale database voordat je de nieuwe aanmaakstroom gebruikt. De lokale databaseverbinding was tijdens deze wijziging niet beschikbaar, dus de werking tegen de actuele tabel is nog niet bevestigd.
- Voeg aan het private `credentials.php` een sectie `kbo` toe met de KBO-webservice `username` en `password`. Aanvaard de KBO-webservicevoorwaarden in het portaal. De actuele FOD-documentatie vermeldt een gratis limiet van 3.000 verzoeken per dag.
- Het apothekenregister moet in `public.pharmacies` staan met de kolommen zoals in Adminer getoond, en moet gevuld zijn. De selectieschermzoekactie ondersteunt naam, adres, postcode, gemeente, vergunninghouder en vergunningsnummer.

- De code en `schema.sql` zijn aangepast, maar de schemawijziging is nog niet uitgevoerd op de lokale database. Maak eerst een database-export.
- De schemawijziging dupliceert bestaande gebruikers voor hun bestaande bedrijfslidmaatschappen en deactiveert daarna de oude niet-platformaccounts. De oude records blijven bewaard. Voer de migratie alleen uit nadat de backup is gecontroleerd.
- De publieke route vereist dat het bedrijf actief en publiek is. Test na de migratie zowel `/c=<slug>` als de queryroute, en controleer registratie en login afzonderlijk voor twee bedrijven.
- De Grompil-vertalingen zijn toegevoegd aan de PHP-taalbestanden. Na de schemamigratie kunnen ontbrekende keys met `php scripts/import_translations.php` aan de vertalingstabel worden toegevoegd en via Beheer → Vertalingen worden gepubliceerd.
- Voor de acroniemwijziging moet `schema.sql` opnieuw worden uitgevoerd; daarin staat de idempotente `ALTER TABLE` en de unieke index per bedrijf. Importeer daarna ook de nieuwe acroniemvertalingen.
- `.htaccess` gebruikt Apache `mod_rewrite`; als die niet beschikbaar is, blijft `index.php?c=<slug>` werken.

### Volgende stap

1. Exporteer de huidige PostgreSQL-database als backup.
2. Voer `schema.sql` uit met de databasebeheerder en controleer dat alle statements slagen.
3. Voer `php scripts/import_translations.php` uit en publiceer de taalbestanden.
4. Controleer in de browser de marketingpagina’s, de taalkeuze, een publieke bedrijfsroute, en registratie/login met dezelfde e-mail bij twee bedrijven.
5. Maak daarna een beheerscherm voor de vier bedrijfspagina’s en hun NL/FR/EN/DE-teksten.

## 15 oktober 2026

### Vandaag gedaan

- De multi-tenantbasis voor apotheken gemaakt: apotheken zijn tenants, `company_users` koppelt accounts aan apotheken en `company_roles` bevat rollen en rechten per apotheek. De login ondersteunt een apotheek kiezen en PostgreSQL Row Level Security is ingesteld voor de tenantgrenzen.
- Beheer van apotheekmedewerkers, personeelsaanvragen en apotheekrollen toegevoegd.
- In **Beheer → Gebruikers** een formulier toegevoegd om algemene gebruikersaccounts aan te maken. De pagina vereist `users.manage` en een gebruikerslevel tussen 1 en 100 (grenzen exclusief). Wachtwoorden moeten minstens 12 tekens bevatten en worden gehasht.
- `users` uitgebreid met `name_first` en `name_last`. Beheer en publieke registratie vragen voornaam en achternaam; `display_name` wordt daaruit samengesteld. Bestaande accounts houden lege waarden in deze nieuwe velden; namen zijn niet automatisch opgesplitst.
- In **Beheer → Apotheken** per apotheek een knop toegevoegd om de publieke website zichtbaar of verborgen te maken. De actie wijzigt alleen `is_public` en laat `id` ongemoeid.
- De rolkeuze bij **Medewerker toevoegen of rol wijzigen** begint leeg, zodat de beheerder bewust een apotheekrol kiest.
- De 30 HTML-templates in `tmpl/default/` opnieuw ingedeeld met regels en inspringing voor de HTML-structuur en formuliervelden. Placeholdernamen en templateverwerking zijn behouden.
- Deze HTML-opmaak als projectstandaard vastgelegd in `.editorconfig` en `README.md`.
- De openbare pagina van **Apotheek De Blauwe Pil** geopend met slug `249037-deblauwepil`. De website werd actief nadat `is_public` voor company `id = 2` op `TRUE` was gezet.
- De ontwerpafspraken en huidige uitrolstatus bijgewerkt in `README.md` en `docs/multi-tenant-architecture.md`.

### Huidige stand

- De openbare apotheekwebsite werkt lokaal. De zichtbaarheid kan nu vanuit **Beheer → Apotheken** gewijzigd worden, zonder Adminer.
- De volledige bijgewerkte `schema.sql` is nog niet bevestigd als uitgevoerd. De database heeft wel de kolom `is_public`; de nieuwe kolommen `name_first` en `name_last` moeten nog in de lokale database worden aangebracht voordat de bijgewerkte accountformulieren gebruikt kunnen worden.
- Vertalingen voor de nieuwe schermen staan in `src/lang/`; importeer en publiceer ze na de schemamigratie.
- `users.email` is nog het centrale loginadres. Een afwijkend contact- of loginadres per apotheek is nog niet geïmplementeerd.
- Domeinrecords voor productie moeten nog via databasebeheer worden toegevoegd en geverifieerd; hiervoor bestaat nog geen beheerscherm.
- De storefront is nog een basispagina. Producten, voorraad, patiëntendossiers en bestellingen zijn nog niet gebouwd.

### Volgende stap

1. Maak een export/backup van de lokale PostgreSQL-database.
2. Voer het bijgewerkte `schema.sql` in Adminer uit met een databasebeheerder. Controleer dat de migratie afrondt, inclusief `name_first`, `name_last`, tenantrollen en RLS-beleid.
3. Voer `php scripts/import_translations.php` uit en publiceer de vertalingen via `translations.php` of `php scripts/export_translations.php`.
4. Controleer in de browser het aanmaken van een gebruiker, het koppelen aan een apotheek en de nieuwe knop voor openbare website aan/uit.
5. Voeg daarna een beheerscherm toe voor domeinnamen en verificatie, zodat ook domeininstellingen Adminer niet meer vereisen.


## 20 oktober 2026

### Vandaag gedaan

- De KBO-foutafhandeling verbeterd: opgevangen fouten worden serverzijdig gelogd zonder het verzoek of credentials te loggen. SOAP-fouten worden onderscheiden voor niet-aanvaarde gebruiksvoorwaarden, afgewezen aanmelding en onbereikbare dienst.
- Apache-configuratie en accesslog gecontroleerd: `DocumentRoot` wijst naar `C:/usr/Dropbox/php`, en de KBO-POST bereikt `/grompil/select-company.php`. Het Apache-errorlog blijft leeg.
- De PHP-extensie cURL geactiveerd in `C:/php/php.ini`; `php_curl.dll` stond al in de configuratiemap maar `extension=curl` was uitgecommentarieerd.
- Na activatie van cURL wordt de KBO-aanroep wel uitgevoerd, maar de HTTP-verbinding faalt. De diagnose toont voortaan de concrete cURL-fout of HTTP-status.
- De officiële Mozilla CA-bundel van curl opgeslagen als `C:/php/cacert.pem` en `curl.cainfo` in `C:/php/php.ini` daarheen laten verwijzen. De PHP CLI haalt de KBO-WSDL nu op met TLS-controle ingeschakeld (HTTP 200); daarna is de KBO-opvraging via de website ook geslaagd.
- De meldingen voor deze gevallen toegevoegd in NL, FR, EN en DE.
- De gewijzigde PHP-bestanden gecontroleerd met `php -l`.
- In `src/account/select_company.php` een tijdelijke vroege guard toegevoegd: directe toegang zonder `GROMPIL_ROOT` krijgt HTTP 404 en de bestaande `not_allowed.php`-pagina. `set_error_handler()` vangt een ongedefinieerde constante niet af, omdat PHP 8 daarvoor een `Error` werpt.
- De tijdelijke technische KBO-diagnose van de pagina verwijderd zodra de oorzaak gevonden was. In `C:/php/php.ini` zijn `display_errors` en `display_startup_errors` uitgezet; PHP-fouten worden gelogd naar `C:/Apache24/logs/php_errors.log` en niet in HTML getoond.
- Vastgelegd als architectuurafspraak: URL-entrypoints blijven publiek; interne modules worden met een gedeelde guard-include beschermd, geladen via een pad dat niet van `GROMPIL_ROOT` afhangt. Gebruik geen gekopieerde guardblokken per bestand. De Apache-`.htaccess`-bescherming blijft aanvullend en `AllowOverride` moet per server gecontroleerd worden.

### Huidige stand

- De KBO-opvraging werkt op deze werkplek. De problemen zaten in de lokale PHP-configuratie, niet in de KBO-credentials of de applicatiedatabase: eerst was cURL niet geladen, daarna ontbrak voor PHP cURL de CA-bundel om het TLS-certificaat te controleren. TLS-verificatie is ingeschakeld gebleven.

### Volgende stap

1. Vergelijk op de andere werkplek de PHP-instellingen die Apache werkelijk gebruikt (`PHPIniDir` in `httpd.conf`). Controleer dat `extension=curl` actief is en `curl.cainfo` wijst naar een bestaand, leesbaar CA-bundelbestand.
2. Controleer met de bijbehorende `php.exe --ri curl` of cURL geladen is; test eventueel het ophalen van de KBO-WSDL zonder credentials. Herstart Apache na wijzigingen in `php.ini`.
3. Als de KBO-aanroep daar nog faalt, vergelijk de Apache/PHP-instellingen en bekijk het door die server ingestelde PHP-errorlog; deel geen credentials.
## 21 oktober 2026

### Vandaag gedaan

- Vastgelegd dat hardcoded technische foutmeldingen en CLI-diagnostiek die niet door de vertaalroutine lopen in het Engels worden geschreven. De meldingen blijven bewust zichtbaar; alleen hun taal verandert.
- Engelse technische meldingen bijgewerkt in databaseconfiguratie, KBO-, mail- en adreshelpers en de directe uitvoer van beheerscripts. De KBO-configuratiefout gebruikt nu de stabiele token `kbo_not_configured`, die naar de bestaande vertaalde schermtekst verwijst.
- De niet-vertaalde IP-toegangspagina en templatecontrolefeedback op Engels gezet. De normale vertaalde schermteksten zijn ongewijzigd.
- `curl_close()` uit de adres-API verwijderd: sinds PHP 8 is dit een no-op en in PHP 8.5 is de functie deprecated.

### Huidige stand

- Technische meldingen die rechtstreeks uit PHP of CLI komen, zijn waar aangeraakt Engelstalig. De taalafspraak staat ook in de README.

### Volgende stap

1. Blijf deze afspraak toepassen wanneer nieuwe technische foutmeldingen of CLI-diagnostiek worden toegevoegd.
2. Houd alle normale gebruikersinterfacecopy in de bestaande vertaalroutine of expliciete taalvarianten.

## 24 oktober 2026

### Vandaag gedaan

- `scripts/generate_navigation.php` genereert nu naast `navigation.json` en `url-manager/catalog.js` ook `navigation.espanso.yml` in de projectroot.
- Iedere aanroepbare applicatiemodule met een NAV-blok krijgt een individuele `:gp-...`-trigger en de gedeelde `:gpmod`-trigger. Espanso kan bij de gedeelde trigger een keuzelijst tonen; de bestandsnaam staat als label bij elke keuze. Modules onder `scripts/` worden uit het Espanso-bestand weggelaten.
- Bestaande individuele triggers blijven bij opnieuw genereren behouden. Nieuwe modules krijgen een trigger op basis van hun modulepad.
- De lokale URL-basis is standaard `http://localhost:8181/grompil/` en kan worden aangepast met `--base-url=URL`.
- Semantische versie verhoogd van 0.5.0 naar 0.6.0 voor deze generatoruitbreiding.

### Huidige stand

- Na het toevoegen van een aanroepbare module met een geldig NAV-blok volstaat het om `php scripts/generate_navigation.php` opnieuw uit te voeren. Het Espanso-bestand wordt dan op dezelfde plek bijgewerkt.

### Volgende stap

1. Kopieer `navigation.espanso.yml` naar de Espanso-matchmap als die map een eigen kopie gebruikt; herlaad Espanso indien de wijziging niet automatisch wordt opgepikt.

## 25 oktober 2026

### Vandaag gedaan

- Een platformbeheerder kan vanuit een actieve bedrijfscontext via **Terug naar platformbeheer** de company verlaten zonder uit te loggen. De knop wordt alleen getoond aan platformbeheerders en leidt terug naar het platformbeheerscherm.
- Het wissen van de actieve context vraagt bevestiging omdat de sessiecontext ook in andere tabbladen van dezelfde browser verandert.
- De knop en bevestiging zijn vertaald in NL, FR, EN en DE. De vertalingen staan in migratie `20261025-094147_platform_leave_company_context.sql`.
- Semantische versie verhoogd naar 0.7.0.

### Huidige stand

- De bedrijfscontext wordt verwijderd uit de sessie; de platformbeheerder blijft ingelogd en behoudt platformrechten.
- De vertaalmigratie moet nog op databases worden uitgevoerd waar de nieuwe teksten in `substitutions` moeten worden opgenomen.

### Volgende stap

1. Voer `migrations/20261025-094147_platform_leave_company_context.sql` uit op de lokale database en later op test en productie.

## 31 oktober 2026

### Vandaag gedaan

- Platformbeheerders met een globale rol op level 1–100 krijgen na hun wachtwoord een TOTP-stap voor Google Authenticator en compatibele apps. Nieuwe koppelingen tonen handmatig invoerbare sleutel en eenmalige herstelcodes.
- TOTP-geheimen worden met Sodium versleuteld in `platform_totp_credentials`; herstelcodes worden gehasht opgeslagen. De encryptiesleutel komt uit `settings_local.php` en hoort per installatie uniek te zijn.
- De bestaande `access_ip_allowlist` is aangesloten voor platformbeheer. Een beheerpagina laat platformbeheerders IP-adressen of CIDR-netwerken beheren.
- De IP-controle voor companybeheer geldt voor rollen onder level 200. Klantrollen vanaf level 200 worden niet door de bedrijfs-allowlist geblokkeerd.
- OTPHP 11.5.0 en de vereiste pakketten zijn toegevoegd via Composer. De softwareversie is verhoogd naar 0.11.0.
- Databasewijziging: `migrations/20261031-090000_platform_mfa.sql`. De drie omgevingsgebonden `schema_grant_*.sql`-bestanden verlenen ook toegang tot de nieuwe MFA-tabellen.
- De instel- en uitrolstappen staan in `docs/platform-login-security.md`.

### Huidige stand

- De code en migratie zijn lokaal voorbereid; de migratie is nog niet uitgevoerd op een database en niets is naar de test- of productiesite gedeployed. Omdat `vendor/` niet in Git staat, moet op de testserver na synchronisatie ook `composer install --no-dev --prefer-dist --optimize-autoloader` worden uitgevoerd.
- Configureer eerst per omgeving `platform_totp_encryption_key` in het genegeerde `settings_local.php`-bestand. Zonder geldige sleutel kan platformbeheer niet aanmelden.

### Volgende stappen

1. Volg `docs/platform-login-security.md` voor de lokale sleutel, migratie en grants.
2. Synchroniseer naar test; voeg daar een aparte sleutel toe en voer migratie en grants uit.
3. Test platformaanmelding, herstelcodes en toegestane IP-regels op test voordat productie aan de beurt komt.

### Aanvulling: company-context na platformlogin

- Platformbeheerders komen na een nieuwe login altijd eerst in het platformbeheer, ook als ze maar één company kunnen kiezen.
- **Terug naar platformbeheer** wist de actieve company-context dus ook voor volgende logins. Opnieuw naar een company gaan gebeurt bewust via **Bedrijven kiezen**.
- Semantische versie verhoogd naar 0.11.1; hiervoor is geen databasemigratie nodig.

