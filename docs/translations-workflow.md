# Workflow van taalbestanden

Deze handleiding beschrijft hoe vertaalteksten in Grompil worden toegevoegd, beheerd en tijdens het uitvoeren geladen.

## Welke plek heeft welke rol?

- `src/lang/lang_nl.php`, `lang_fr.php`, `lang_en.php` en `lang_de.php` bevatten de bronwaarden en de standaardvertalingen als PHP-array `$arrSubst`. De bestanden leggen ook vast welke vertaalkeys bestaan.
- `ft_substitutions` is de databasebron voor algemeen vertaalbeheer. Algemene vertalingen hebben `company_id = 0`; iedere rij bevat een sleutel, taalcode en tekst.
- `translations.php` is de beheerpagina voor algemene vertalingen. Je wijzigt teksten daar en publiceert ze als PHP-taalbestanden.
- `src/lang/companies/<company_id>/lang_<taal>.php` bevat gepubliceerde company-specifieke overschrijvingen. Die worden beheerd via `company_translations.php`.

De PHP-taalbestanden zijn de runtimebestanden die de app inleest. In de standaardopstelling staan bron- en gepubliceerde bestanden allebei in `src/lang/`; in een installatie met `GROMPIL_LANGUAGE_DIR` staan de gepubliceerde bestanden apart. Voor algemeen beheer bewaart de database de vertalingen waarop de beheerpagina en export werken.

## Hoe wordt een tekst gekozen?

Bij een gewone pagina bouwt `fn_loadLanguage()` de vertaalarray op in deze volgorde:

1. De bronwaarden uit het taalbestand van de gekozen taal.
2. Het gepubliceerde algemene taalbestand voor die taal. Die waarden overschrijven de bronwaarden.
3. Als een company actief is: het gepubliceerde taalbestand met company-overschrijvingen.
4. Engelse waarden voor keys die nog ontbreken of leeg zijn.

Daarom kunnen Franse of Duitse vertalingen tijdelijk leeg blijven: de app toont dan de Engelse tekst. Voor uitvoer die zelf een taalparameter krijgt, zoals de afleverbon-PDF, gebruikt code `fn_tForLanguage($key, $language)` om dezelfde gepubliceerde taal- en companybestanden voor die expliciete taal te lezen. `fn_t($key)` gebruikt de huidige taal van de pagina.

## Een nieuwe algemene vertaaltekst toevoegen

1. Kies een unieke, beschrijvende key in lowercase, bijvoorbeeld `dispensing_receipt_pdf_title`.
2. Voeg die key toe aan alle vier de bronbestanden onder `src/lang/`. Vul de waarden voor NL, FR, EN en DE in. Gebruik placeholders zoals `{count}` wanneer een variabel getal in de tekst komt.
3. Open **Vertalingen beheren** (`translations.php`) en klik op **Ontbrekende teksten importeren**. Deze knop voegt de ontbrekende algemene rijen toe aan `ft_substitutions` en publiceert daarna meteen alle taalbestanden. Bestaande databasevertalingen worden niet overschreven.
4. Pas teksten per taal aan en klik op **Taalbestanden publiceren** om je wijzigingen naar de taalbestanden te publiceren.
5. Neem de gewijzigde bron- en gegenereerde taalbestanden mee in de deployment. De PHP-bestanden moeten leesbaar zijn voor de applicatie; publiceren via de beheerpagina vereist schrijfrechten op de ingestelde taalmap.

De beheerpagina is de normale werkwijze. De CLI-scripts zijn alleen een technische fallback voor beheer via de opdrachtregel:

```powershell
php scripts/translations_2_export.php
php scripts/translations_2_export.php FR
```

Voer bij de CLI-werkwijze eerst `php scripts/translations_1_import.php` uit om ontbrekende keys toe te voegen. Daarna exporteert de eerste opdracht alle ondersteunde talen; met een taalcode exporteer je alleen die taal. Het CLI-exportscript schrijft de databasewaarden naar het bestand en voegt zelf geen bronwaarden toe. De beheerpagina voegt de bronwaarden en databasewaarden samen bij het publiceren.

## Company-specifieke teksten

Een company-override hoort alleen thuis in `company_translations.php` wanneer dat bedrijf bewust een afwijkende tekst nodig heeft. De key moet al als algemene key bestaan. De override wordt opgeslagen met het company-id en gepubliceerd naar de map voor dat bedrijf. Zonder override gebruikt de pagina de algemene vertaling.

## Welke code gebruik je?

- In PHP-HTML-rendering met de huidige paginataal: `fn_t('key')`.
- In PHP-uitvoer met een expliciete taal, zoals een PDF: `fn_tForLanguage('key', $language)`.
- In HTML-templates: de bijbehorende placeholder `@key@`; de PHP-module vult die via de vertaalarray.
- Bewaar interfacewoorden niet als vaste strings in de renderingcode. Voeg ze als vertaalkeys toe aan de bronbestanden.

## Taalbestanden buiten de webroot

Als `GROMPIL_LANGUAGE_DIR` is ingesteld, gebruikt de applicatie die map voor gepubliceerde algemene taalbestanden en company-overrides. De bronbestanden blijven in `src/lang/`. Zorg dat zowel PHP als de exporterende beheerpagina of CLI voldoende lees- en schrijfrechten op de ingestelde map hebben. Stel dezelfde variabele in wanneer je de CLI-export gebruikt.
