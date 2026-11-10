# Grompil-bookmarks installeren

Met deze browserextensie komen er twee mappen vooraan op je bookmarkbalk:

- **Grompil-functies** bevat de Grompil-modules die je met je huidige account
  mag openen.
- **Mijn navigator** bevat de indeling en links uit de URL-manager.

Je bestaande bookmarks blijven staan en schuiven naar rechts. De extensie
ververst haar twee mappen alleen wanneer je op **Synchroniseren** klikt.

## Eenmalig installeren in Vivaldi, Chrome of Edge

De installatie is voor elke browser apart. Je gebruikt telkens dezelfde map.

1. Open in de browser de extensiepagina:
   - Vivaldi: typ `vivaldi://extensions` in de adresbalk.
   - Chrome: typ `chrome://extensions`.
   - Edge: typ `edge://extensions`.
2. Zet **Ontwikkelaarsmodus** aan. In Edge en Chrome staat die meestal rechts
   bovenaan; in Vivaldi kan de plaats verschillen.
3. Klik op **Niet-verpakte extensie laden** of **Load unpacked**.
4. Selecteer deze map, de map waarin `manifest.json` staat:
   `C:\usr\Dropbox\php\grompil\browser-extensions\grompil-bookmarks`
5. Bevestig de keuze. De extensie verschijnt nu in de lijst.
6. Open de extensieknop naast de adresbalk. Als je die niet ziet, open dan het
   extensiemenu (puzzelstukje) en pin **Grompil Bookmarks** vast.

## De bookmarkmappen vullen of vernieuwen

1. Meld je aan op de gewenste Grompil-installatie.
2. Open de URL-manager in diezelfde browser, bijvoorbeeld
   `https://test.grompil.com/url-manager/index.html`.
3. Kies in de URL-manager het juiste bedrijf en de gewenste taal.
4. Klik op **Grompil Bookmarks** en daarna op **Synchroniseren**.
5. Open de bookmarkbalk. In Chrome, Edge en Vivaldi kun je die tonen met
   `Ctrl+Shift+B`.

Bij een volgende synchronisatie worden alleen de twee Grompil-mappen vervangen.
Andere bookmarks worden niet verwijderd. Zet daarom geen eigen bookmarks
binnen de twee Grompil-mappen.

## Opera en Firefox

Opera ondersteunt de meeste Chrome-extensie-API's. Je kunt dezelfde
ontwikkelmap proberen te laden via Opera's extensiepagina. Firefox gebruikt
grotendeels dezelfde WebExtensions-aanpak; laden kan tijdelijk via
`about:debugging` → **This Firefox** → **Load Temporary Add-on**, en kies dan
`manifest.json`. De extensie is nog niet in Opera of Firefox getest.
