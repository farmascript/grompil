<!--
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Wed 2026-10-21 18:04:36
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Documents the URL manager behavior and configuration.
 *
 */
-->

# Universele URL-manager

## Generator

Run the catalog generator from the Grompil project root:

```powershell
php scripts/generate_navigation.php
```

It scans PHP source files for `@NAV` comment blocks, reads the trusted generated language arrays, and writes `navigation.json` to the webroot. When `url-manager/` exists under the source root, it also writes `url-manager/catalog.js` so the local-file browser start page can load the catalog without a manual file selection. It also regenerates `navigation.espanso.yml` in the project root from the same NAV modules, excluding modules under `scripts/` because they are not callable application URLs. Each included module gets its own `:gp-...` trigger and the shared `:gpmod` trigger; Espanso uses the `label` filename to show the module choices when `:gpmod` is typed. Existing individual `:gp-...` shortcuts are kept when the matching module remains in the catalog. Application modules are read as text and are never executed. The generator requires the language arrays to expose `$arrSubst`, which is the structure used by `src/lang/lang_nl.php`, `lang_fr.php`, `lang_en.php` and `lang_de.php`.

Optional arguments are `--root=PATH`, `--webroot=PATH`, `--output=PATH`, `--application=ID` and `--base-url=URL`. The defaults are the project root, the project root as webroot, `navigation.json` in the webroot, `grompil` and `http://localhost:8181/grompil/`. `--base-url` sets the address used in the Espanso matches. If `GROMPIL_LANGUAGE_DIR` is set, the generator reads language arrays there and falls back to the project `src/lang/` files when an individual language file is absent.

## NAV comment format

Put one block in each callable PHP module. The permanent ID is stored in that module and must not be changed or reused.

```php
/*
@NAV
id: 18472
group: platform
group_title: @nav_admin@
action: manage
title: @nav_companies@
description: @admin_companies_description@
keywords: @companies_keywords@
public_visibility: false
sequence_number: 1
@ENDNAV
*/
```

`id`, `group`, `group_title` and `title` are required. IDs are positive integers no greater than JavaScript's safe integer limit and must be unique across all discovered NAV blocks. The technical group ID is lowercase and language-neutral. `action`, `description` and `keywords` are optional. `public_visibility` defaults to `true` and remains in the catalog for compatibility; online visibility is determined by the actual module access rules described below. `sequence_number` is an optional fixed number from 1 to 99. Fixed numbers must be unique. Modules without one receive a deterministic number starting at 100 when the catalog is generated. The manager displays two-digit padding for numbers below 100. Translatable values must each be one existing `@keyword@` token. A keyword translation is split on `|` and emitted as a JSON array; omit `keywords` when there are no search terms. One NAV block per PHP file is supported in this first version.

Group titles used by modules sharing a group must resolve to the same text in every language. Missing or blank translations fall back in this order: requested language, English, Dutch. If a token has no nonempty value in any language array, the generator uses `!keyword!` for that language and reports a warning. Add the actual values to the general translations and export the language files to replace these placeholders. Existing `!keyword!` values are treated as intentional translations and remain visible for that language.

When adding a NAV keyword, first check whether it already exists in the general translations. Add missing terms to the general `substitutions` (`company_id = 0`) and export the language files before running the navigation generator. Provide NL, FR, EN and DE values. If a meaningful translation cannot be found for a language, use the keyword wrapped in exclamation marks, for example `@moeilijk_keyword@` becomes `!moeilijk_keyword!`.

The module path in JSON is calculated from the file's path relative to the webroot and uses `/` separators. Set `--webroot` when PHP sources are stored separately from the public document root. The URL-manager supplies the environment-specific base URL and joins it to this relative module path.

## JSON schema version 1

The root object contains `schemaVersion`, `application`, `defaultLanguage`, `languages`, `groups` and `items`. Each group has a stable technical `id` and translated titles under `i18n`. Each item has its permanent numeric `id`, relative `module` path, `group`, optional `action`, and translated title, description and keyword array for each language.

The generator currently includes these first modules:

| ID | Module | Group |
|---:|---|---|
| 18472 | `companies.php` | `platform` |
| 18473 | `users.php` | `platform` |
| 18474 | `company_members.php` | `company` |
| 18475 | `company_roles.php` | `company` |
| 18476 | `company_translations.php` | `company` |

These IDs are reserved permanently. Add further modules by assigning a new ID in that module's comment, then run the generator again. No application routing, authorization, or translation function is changed by the URL-manager generator.

## Browsermanager gebruiken

### Toegangsrechten

De online manager vraagt bij openen en opnieuw focussen aan `navigation_access.php` welke modules de huidige sessie mag openen. De regels in `includes/module_access.php` gebruiken dezelfde gebruikersniveaus en globale of bedrijfsrechten als de betrokken modulecontroles. Een platformstatus geeft bijvoorbeeld niet automatisch het recht `users.manage`, en bedrijfsvertalingen zijn zichtbaar voor een bedrijfsgebruiker met `company.translations.manage`, ook wanneer het oudere catalogusveld `public_visibility` op `false` staat.

Niet-toegankelijke modules verdwijnen uit de tegels, zoekresultaten en favorieten. Opgeslagen persoonlijke links naar dezelfde bekende modules worden eveneens gefilterd; externe links blijven zichtbaar. Lege vaste blokken worden voor gewone gebruikers verborgen. De opgeslagen indeling en favorieten worden niet verwijderd, zodat ze na een rechtenwijziging weer beschikbaar kunnen worden. Bij een mislukte toegangscontrole blijven beschermde functies verborgen. De toegangslijst wordt niet lokaal bewaard.

Registreer nieuwe modules in `grompil_module_access_rules()` en hergebruik die regel in hun PHP-toegangscontrole. Onbekende catalogusmodules blijven online verborgen. `grompil_require_module_access()` is de gedeelde guard voor globaal beheer; bedrijfsmodules bepalen eerst hun actieve bedrijf en controleren daarna `grompil_can_access_module()`. De modules blijven bij rechtstreeks openen altijd hun eigen toegang controleren.

Een manager die als lokaal `file://`-bestand wordt geopend heeft geen verifieerbare ingelogde servercontext en behoudt de volledige catalogus. Open de manager via de Grompil-webserver om de tegels aan de huidige gebruikersrechten aan te passen.

Controleer de toegangsregels met `php scripts/test_navigation_access.php` en het tonen, zoeken en verversen met `node scripts/test_navigation_visibility.js` (een actuele Node.js-versie vereist).

### Openen en indeling bewaren

Bij een ingelogde online manager staat bovenaan **Uitloggen**. Deze knop gebruikt de bestaande `logout.php`-route met POST, het CSRF-token van de sessie en de slug van het actieve bedrijf. Na afmelden verschijnt het inlogscherm van dat bedrijf. Zonder actieve bedrijfscontext verschijnt het algemene inlogscherm. Het formulier navigeert de volledige pagina, ook wanneer de manager in het navigatievenster van een module is geopend. De knop blijft verborgen bij een lokale `file://`-manager of zonder gecontroleerde ingelogde sessie. Het CSRF-token wordt niet in de browserindeling of JSON-export opgeslagen.

Open `url-manager/index.html` als apart browsertabblad. De manager laadt de meegeleverde `catalog.js` automatisch, zowel via de webserver als wanneer je `index.html` als lokaal bestand opent. Na het opnieuw genereren van de catalogus wordt ook deze lokale kopie bijgewerkt en via Dropbox gesynchroniseerd. Handmatig `navigation.json` kiezen blijft als terugvaloptie beschikbaar.

Stel bij Instellingen het basisadres in op de webroot van de Grompil-installatie, bijvoorbeeld `http://localhost/grompil/`. Zonder Grompil-login bewaart de bedrijfsindeling lokaal in IndexedDB van die browser. Wanneer de manager via een ingelogde company-omgeving wordt geopend, synchroniseert bedrijfslinks en -groepen hij met `company_navigation_preferences` in PostgreSQL. De configuratie is per bedrijf en applicatie afgeschermd. Goedgekeurde medewerkers kunnen deze lezen; alleen de platformbeheerder of een bedrijfsbeheerder met niveau onder 100 en `company.settings.manage` kan bedrijfswijzigingen bewaren.

De vaste Grompil-catalogus wordt standaard uit `navigation.json` geladen. De platformbeheerder kan vaste links naar andere vaste groepen verplaatsen en extra Grompil-groepen toevoegen via de knop **Grompil-blok**. Deze platformindeling wordt apart van bedrijfsindelingen in `platform_navigation_preferences` bewaard en is voor alle bedrijven hetzelfde. Andere gebruikers kunnen de vaste indeling bekijken, maar niet herschikken of Grompil-groepen aanmaken. De browseropslag blijft als lokale reserve beschikbaar en wordt per bedrijf gescheiden.

Voer voor een bestaande database eenmalig `migrations/20261017-212150_company_navigation_preferences.sql` en `migrations/20261018-212150_platform_navigation_preferences.sql` uit als databasebeheerder. Neem de tabeldefinities en RLS-policies ook mee bij nieuwe installaties via `schema.sql`. De JSON kan met **Exporteren** en **JSON importeren** nog steeds tussen bedrijven of zelfstandige managerinstallaties worden uitgewisseld. Import biedt samenvoegen of vervangen.

Je kunt eigen blokken aanmaken, tegels tussen blokken verslepen en persoonlijke links aan een blok koppelen. Nieuwe catalogusfuncties verschijnen automatisch in hun standaardblok; persoonlijke verplaatsingen blijven bewaard.

De manager begint met twee bladen: **blad1** en **blad2**. Bovenaan wissel je van blad; de laatste knop **blad toevoegen** maakt een volgend blad aan en opent dat meteen. Met het potlood naast een bladknop wijzig je de naam. Bestaande indelingen blijven op het eerste blad staan; vaste Grompil-blokken staan daar ook.

Een nieuw bedrijfsblok krijgt standaard het geopende blad. Via **Blokopties → Naam of blad wijzigen** kun je een bedrijfsblok met alle bijbehorende links naar een ander blad verplaatsen. Bij een link toont de blokkeuze zowel de bladnaam als de bloknaam, zodat je een link ook afzonderlijk op een ander blad kunt plaatsen. Maak op een leeg blad eerst een bedrijfsblok aan.

Bladnamen en de bladkeuze per bedrijfsblok worden met de bestaande configuratie opgeslagen in de browser of in `company_navigation_preferences.configuration`. Ze gaan ook mee met JSON-export en -import. Er is geen schemamigratie nodig. De bestaande bedrijfsrechten bepalen wie bladen en hun indeling mag wijzigen.

Een bedrijfsblok wordt na **Blok maken** automatisch opgeslagen; een afzonderlijke bewaarhandeling is niet nodig. Zonder geselecteerd bedrijf wordt de zelfstandige browserindeling gebruikt, ook als je als platformbeheerder bent ingelogd. De navigator onthoudt per context het laatst geopende blad in de browser.

De managerknoppen, veldlabels en standaardbladnamen gebruiken de algemene vertaalsleutels met prefix `manager_`. Ze zijn in NL, FR, EN en DE beschikbaar en kunnen via **Beheer → Vertalingen** worden aangepast. Na publiceren en herladen haalt de online manager de teksten via `navigation_labels.php` uit de gepubliceerde taalbestanden. Voor gebruik als lokaal bestand neemt `scripts/generate_navigation.php` dezelfde teksten op onder `managerI18n` in `navigation.json` en `url-manager/catalog.js`; genereer daarvoor na vertaalwijzigingen de catalogus opnieuw.

Standaardnamen zoals `blad2` worden in de gekozen taal getoond. Eigen bestaande namen blijven behouden. Bij hernoemen wordt de nieuwe naam voor de huidige taal opgeslagen, zoals bij bedrijfsblokken; via de taalkeuze kun je dus per taal een andere bladnaam instellen. De werkbalk groeit mee wanneer de knoppen over meerdere regels worden verdeeld. De bladen staan in een afzonderlijke rij met eigen witruimte en scheidingslijn.

Managertegels tonen het `sequence_number` uit de catalogus: vaste Grompil-functies gebruiken vaste nummers van 01 tot en met 99. Modules zonder expliciet nummer krijgen bij generatie een nummer vanaf 100. Bedrijfslinks krijgen een blijvend nummer vanaf 100, zodat gebruikers en ontwikkelaars naar dezelfde link kunnen verwijzen.

