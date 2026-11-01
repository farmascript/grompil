# Contextgevoelige hulp via DokuWiki

Stel in `settings_local.php` de installatiemap in:

```php
'dokuwiki' => 'https://dw.grompil.com/!saas/',
```

Een ontbrekende of lege waarde schakelt de hulplinks uit. Gebruik een gewone URL zonder extra escapes.

Na het laden van de gedeelde templates zijn deze helpers beschikbaar:

```php
fn_dokuwikiUrl('users', '87271');
fn_dokuwikiIcon('extern', '511300');
```

De ondersteunde levels zijn `users`, `dev`, `testers` en `extern`. De URL wordt
`<basisurl>/doku.php?id=doc:grompil:<level>:<id>`. ID's zijn vaste numerieke strings;
zowel bestaande NAV-ID's als zes willekeurige cijfers zijn bruikbaar. Controleer
een nieuwe ID op duplicaten en hergebruik de ID niet voor een ander onderwerp.
Houd ook de namespace vast; een verhuizing verandert de link.

Geef het icoon als HTML-fragment door aan een templateplaceholder met het
voorvoegsel `_`, bijvoorbeeld `_page_help_html`. Het icoon opent een nieuw
tabblad, bevat een toegankelijk label in NL/FR/EN/DE en vereist geen iconenbibliotheek.

Het voorbeeld staat naast de titel van de publieke homepagina van company met
slug `ariodante`, in beide gedeelde thema's, en verwijst naar
`doc:grompil:extern:511300`. Andere bedrijven krijgen dit voorbeeld niet.

DokuWiki beheert pagina-inhoud, reacties, registratie en ACL. Het level in de
link verleent geen rechten. Ontbrekende pagina's kunnen in de wiki worden
aangemaakt door iemand met de juiste rechten; de SaaS maakt ze niet zelf aan.
