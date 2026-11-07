# Onderhoudsmodus

De webapp leest `maintenance_mode` uit de omgevingsspecifieke `settings_local.php`.
De standaardwaarde is `false`. Zet deze op `true` om alle PHP-webpagina's en API's
een eenvoudige onderhoudspagina met HTTP-status 503 te laten tonen. De melding volgt
de taalvoorkeur van de browser (NL, FR, EN of DE).

De controle gebeurt vroeg in `includes/bootstrap.php`, vóór sessiestart en database-
verbinding. CLI-opdrachten blijven werken, zodat databasebeheer vanaf SSH door kan
gaan. De vlag geldt per installatie; wijzig hem apart in de lokale en testomgeving.

Zodra onderhoudsmodus actief is, zijn ook beheerpagina's en login geblokkeerd. Zet de
instelling na de wijziging terug op `false` via SSH of bestandsbeheer. De instelling
staat in `settings_local.php` en hoort niet in Git.

Gebruik voor een geplande uitrol deze volgorde:

1. Zorg dat de code met onderhoudsmodus al op de doelomgeving staat.
2. Zet daar `maintenance_mode` op `true`.
3. Voer de databaseschemawijziging uit en synchroniseer de bijbehorende code.
4. Zet `maintenance_mode` weer op `false` en controleer de website.
