# Gebruikerspagina met wijzigingen

`release-notes.php` toont de gepubliceerde vooruitgang en wijzigingen aan elke
ingelogde gebruiker. De pagina gebruikt de actieve taalcode (`NL`, `FR`, `EN` of
`DE`). Een wijziging kan per taal een eigen tekst hebben; ontbrekende vertalingen
vallen terug op Nederlands en daarna Engels.

Platformbeheerders krijgen op dezelfde pagina het formulier **Wijziging
toevoegen**. Vul minstens de tekst voor de actieve taal in. De DokuWiki-
pagina-ID is optioneel. Als die is ingevuld, krijgt iedere tekstregel een
helpicoon met level `users`; de link gebruikt de taalcode van de gebruiker.
Als het veld leeg blijft, wordt het ID van `ft_release_notes` getoond en wordt
geen DokuWiki-link toegevoegd. De DokuWiki-pagina zelf moet apart worden
aangemaakt en via DokuWiki-ACL leesbaar zijn voor gebruikers; de applicatie
maakt geen externe wiki-pagina's aan.

Gebruikers kunnen zich met het actieve login-e-mailadres uit hun profiel
inschrijven of uitschrijven. Bij inschrijving wordt de dan gekozen taalcode
bewaard. Bij publicatie ontvangen actieve abonnees een e-mail met de wijziging
in die taal, als die vertaling is ingevuld. Een ontbrekende vertaling valt terug
op Nederlands of Engels.

## Database-update

Voer `migrations/20261125_151433_release_updates.sql` één keer uit op iedere
database-omgeving. De migratie maakt de wijzigings- en abonnementstabellen aan,
stelt de toegangsregels in en wijst **Shift+F12** toe aan `release-notes.php`.
De bestaande inhoud van Shift+F12 wordt daarbij vervangen. De toetslabels en
tooltips worden voor NL, FR, EN en DE ingesteld.

Voer daarna `migrations/20261125_162339_release_notes_wiki_optional.sql` uit
op omgevingen waar `release_updates.sql` al eerder is uitgevoerd. Deze migratie
maakt de DokuWiki-pagina-ID optioneel; nieuwe omgevingen kunnen de aangepaste
`release_updates.sql` gebruiken en hoeven deze aanvullende migratie niet uit te
voeren.

De DokuWiki-basis-URL moet per installatie in `settings_local.php` zijn
ingesteld als de helpiconen moeten openen; zie ook
[`contextual-help.md`](contextual-help.md).
