# Unieke gebruikerscodes

Elke gebruikersrij heeft naast de interne `users.id` een unieke `users.user_code` van zes tekens. De code wordt gemaakt door een onafhankelijke random integer om te zetten met `getalNaarCode()`. De database bewaakt de uniciteit; bij een botsing probeert Grompil opnieuw.

`users.id` is de primaire sleutel van de tabel `users` zelf. Relaties vanuit andere tabellen, sessies, formulieren en app-aanroepen gebruiken `user_code`; queries mogen `users.id` niet gebruiken om een gebruiker op te zoeken of te koppelen. De code is geen geheim en verleent geen toegang.

## Database bijwerken

De codes zijn lokaal en op test al aangemaakt. Voer onderstaande relationele migratie eenmaal uit op iedere database voordat de nieuwe PHP-code daar actief wordt. De migratie bewaart de bestaande relaties en vertaalt ze naar `user_code`; ze verwijdert geen gebruikers of andere records.

1. Maak eerst een databaseback-up.
2. Importeer `migrations/20261107-130000_user_code_relations.sql` met een account dat eigenaar is van de betrokken tabellen. Dit vervangt user-ID-kolommen en foreign keys door `user_code`, en werkt de RLS-policies bij.
3. Synchroniseer/deploy daarna direct de bijbehorende PHP-code. Tijdens deze overgang moet de website kort in onderhoud staan: oude code verwacht de oude kolommen en nieuwe code verwacht de nieuwe kolommen.

`schema.sql` beschrijft de nieuwe tabellenstructuur voor lege databases en voor herhaalde uitvoering nadat de relationele migratie is toegepast. De oudere code-aanmaakmigraties en backfill blijven alleen nodig voor databases die nog geen `user_code` hebben. Alle PHP-aanmaakroutes blijven een code genereren voor nieuwe gebruikers.
