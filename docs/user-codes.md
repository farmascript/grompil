# Unieke gebruikerscodes

Elke gebruikersrij heeft naast de interne `users.id` een unieke `users.user_code` van zes tekens. De code wordt gemaakt door een onafhankelijke random integer om te zetten met `getalNaarCode()`. De database bewaakt de uniciteit; bij een botsing probeert Grompil opnieuw.

`users.id` blijft de interne primaire sleutel voor foreign keys en joins. `user_code` is een blijvende alternatieve identifier die geschikt is voor exports en verwijzingen buiten de database. De code is geen geheim en verleent geen toegang.

## Database bijwerken

Voer dit afzonderlijk uit voor iedere omgeving, ook na een verse `schema.sql`. Het schema kan tijdens de migratie bestaande gebruikersrijen kopiëren; die krijgen daarna hun code via de backfill:

1. Importeer `migrations/20261107-120000_user_public_code.sql` en `migrations/20261107-120200_user_code_format_check.sql` in de database. Dit voegt de kolom, unieke index en formaatcontrole toe zonder bestaande rijen te verwijderen.
2. Synchroniseer de nieuwe softwarecode, zodat de backfillgenerator beschikbaar is.
3. Voer vanuit de Grompil-projectmap `php scripts/backfill_user_codes.php` uit. Dit kent ontbrekende codes toe en herstelt codes die niet aan het formaat voldoen; je kunt het veilig opnieuw uitvoeren.
4. Nadat het script meldt dat alle codes geldig zijn, voer je `migrations/20261107-120100_user_public_code_not_null.sql` en `migrations/20261107-120300_user_code_format_validate.sql` uit met een databaseaccount dat eigenaar is van de tabel. Dit maakt de kolom verplicht en valideert de formaatcontrole.

`schema.sql` bevat de kolom, uniciteitsindex en formaatcontrole. Op een al bijgewerkte database kun je de eerste migratie overslaan als de kolom en index al bestaan; voer de formaatcontrole-migratie wel uit als die nog ontbreekt. De PHP-aanmaakroutes genereren de code voor platformbeheerders, bedrijfsbeheerders, accountaanvragen en gebruikers die via gebruikersbeheer worden aangemaakt.
