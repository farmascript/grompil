# Unieke gebruikerscodes

Elke gebruikersrij heeft naast de interne `users.id` een unieke `users.user_code` van zes tekens. De code wordt gemaakt door een onafhankelijke random integer om te zetten met `getalNaarCode()`. De database bewaakt de uniciteit; bij een botsing probeert Grompil opnieuw.

`users.id` blijft de interne primaire sleutel voor foreign keys en joins. `user_code` is een blijvende alternatieve identifier die geschikt is voor exports en verwijzingen buiten de database. De code is geen geheim en verleent geen toegang.

## Database bijwerken

Voer dit afzonderlijk uit voor iedere omgeving, ook na een verse `schema.sql`. Het schema kan tijdens de migratie bestaande gebruikersrijen kopiëren; die krijgen daarna hun code via de backfill:

1. Importeer `migrations/20261107-120000_user_public_code.sql` in de database. Dit voegt de kolom en unieke index toe zonder bestaande rijen te verwijderen.
2. Synchroniseer de nieuwe softwarecode, zodat de backfillgenerator beschikbaar is.
3. Voer vanuit de Grompil-projectmap `php scripts/backfill_user_codes.php` uit. Dit kent bestaande gebruikers codes toe en kan veilig opnieuw worden uitgevoerd.
4. Nadat het script meldt dat de backfill klaar is, voer je `migrations/20261107-120100_user_public_code_not_null.sql` uit met een databaseaccount dat eigenaar is van de tabel. Dit zorgt dat toekomstige rijen niet zonder code kunnen worden opgeslagen.

`schema.sql` bevat de kolom en uniciteitsindex. Op een al bijgewerkte database kun je stap 1 overslaan als de kolom en index al bestaan. De PHP-aanmaakroutes genereren de code voor platformbeheerders, bedrijfsbeheerders, accountaanvragen en gebruikers die via gebruikersbeheer worden aangemaakt.
