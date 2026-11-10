# Grompil bookmarkmappen

Deze Manifest V3-extensie maakt twee gegenereerde mappen vooraan op de
bookmarkbalk:

1. **Grompil-functies**: modules uit `navigation.json` die de ingelogde sessie
   op dat moment mag openen, gegroepeerd volgens de platformcatalogus.
2. **Mijn navigator**: bladen, persoonlijke groepen, bedrijfs- of persoonlijke
   links en catalogusmodules die in de URL-manager aan eigen groepen zijn
   toegewezen.

Start de synchronisatie terwijl de online Grompil-URL-manager de actieve tab
is. De extensie leest de catalogus, actuele toegangsrechten en platform- en
persoonlijke/bedrijfsindeling van die installatie. De sessiecookie wordt alleen
gebruikt door dezelfde-origin-verzoeken vanuit de actieve pagina. De extensie
bewaart geen inloggegevens en stuurt geen navigatiegegevens naar derden. Er
zijn geen brede hostrechten; `activeTab` wordt pas gebruikt nadat je zelf de
extensieknop indrukt.

Bij elke synchronisatie vervangt de extensie alleen de twee mappen die zij
eerder heeft aangemaakt. Andere bookmarks blijven staan en schuiven naar
rechts wanneer de mappen op positie nul en één worden ingevoegd. Zet geen
handmatig beheerde bookmarks in deze twee mappen: hun inhoud wordt bij de
volgende synchronisatie opnieuw opgebouwd.

## Lokale installatie voor ontwikkeling

Dit is een ontwikkelversie, geen ondertekende release uit een extensiewinkel.
Voor Chrome, Edge, Vivaldi en Opera open je de extensiepagina, schakel je
ontwikkelaarsmodus in en kies je **Niet-verpakte extensie laden** of **Load
unpacked**. Selecteer deze map en pin daarna de extensieknop als je die naast
de adresbalk wilt zien. Open vervolgens de online URL-manager, kies taal en
bedrijfscontext en klik op **Synchroniseren** in de extensiepopup.

Firefox kan de ontwikkelversie tijdelijk laden via `about:debugging` → **This
Firefox** → **Load Temporary Add-on**. Selecteer `manifest.json`. De extensie
gebruikt callback-stijl `chrome.*`-aanroepen voor gedeelde WebExtensions-
compatibiliteit. Controleer voor de gebruikte Firefox-versie of
`scripting.executeScript` de `MAIN`-wereld ondersteunt. Opera ondersteunt het
merendeel van de Chrome-extensie-API's; controleer installatie met de actuele
Opera-versie.
