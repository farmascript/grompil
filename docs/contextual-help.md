# Contextgevoelige hulp via DokuWiki

Stel in `settings_local.php` de installatiemap in:

```php
'dokuwiki' => 'https://dw.grompil.com/!saas/',
```

Een ontbrekende of lege waarde schakelt de hulplinks uit. Gebruik een gewone URL zonder extra escapes.

`settings_local.php` staat in `.gitignore` en wordt door de deployment uitgesloten.
Voeg de instelling daarom afzonderlijk toe binnen de bestaande `return [...]`
van elke installatie die de hulplinks moet tonen, ook op de hosting. Alleen de
code uploaden is niet voldoende. Laat de andere instellingen in het bestand staan.

Na het laden van de gedeelde templates zijn deze helpers beschikbaar:

```php
fn_dokuwikiUrl('users', '87271');
fn_dokuwikiIcon('extern', '511300');
```

Zet op de gewenste plaats in de HTML-template een placeholder met het level en
de numerieke DokuWiki-pagina-ID:

```html
<h1>@page_title@ @_page_help_html_extern_511300@</h1>
```

De ondersteunde levels zijn `users`, `dev`, `testers` en `extern`. Voor NL wordt
`<basisurl>/doku.php?id=doc:grompil:<level>:<id>` gebruikt. Voor FR, EN en DE
wordt de taalnamespace voor het pagina-ID geplaatst, bijvoorbeeld
`<basisurl>/doku.php?id=en:doc:grompil:<level>:<id>`. ID's zijn vaste numerieke strings;
zowel bestaande NAV-ID's als zes willekeurige cijfers zijn bruikbaar. Controleer
een nieuwe ID op duplicaten en hergebruik de ID niet voor een ander onderwerp.
Houd ook de namespace vast; een verhuizing verandert de link.

De templateverwerking maakt hiervan automatisch het helpicoon en de taalafhankelijke
URL. Ondersteunde placeholdervorm: `@_page_help_html_<level>_<id>@`, waarbij het
level `users`, `dev`, `testers` of `extern` is en de ID alleen cijfers bevat.
Het icoon opent een nieuw tabblad, bevat een toegankelijk label in NL/FR/EN/DE
en vereist geen iconenbibliotheek.

Het voorbeeld staat naast de titel van de publieke homepagina in de gedeelde
thema's en verwijst naar `doc:grompil:extern:511300`. Een eigen company-template
moet de placeholder zelf bevatten om het icoon daar te tonen.

DokuWiki beheert pagina-inhoud, reacties, registratie en ACL. Het level in de
link verleent geen rechten. Ontbrekende pagina's kunnen in de wiki worden
aangemaakt door iemand met de juiste rechten; de SaaS maakt ze niet zelf aan.

## Controle na installatie

Open een publieke homepagina waarvan de actieve thematemplate de placeholder
bevat. Naast de titel hoort een rond informatie-icoon te staan. De link opent
in een nieuw tabblad:

`https://dw.grompil.com/!saas/doku.php?id=doc:grompil:extern:511300` in NL, of
`https://dw.grompil.com/!saas/doku.php?id=en:doc:grompil:extern:511300` in EN.

Ontbreekt het icoon, controleer dan eerst de `dokuwiki`-instelling in
`settings_local.php` van de installatie die je daadwerkelijk bezoekt. Controleer
daarna of de gewijzigde templateverwerking, de helper en de actieve template
zijn gedeployed. Een eventuele company-template override moet de placeholder
ook bevatten.

Controleer in DokuWiki afzonderlijk of bezoekers de pagina mogen lezen en of
geregistreerde gebruikers volgens de ingestelde ACL opmerkingen mogen plaatsen.
