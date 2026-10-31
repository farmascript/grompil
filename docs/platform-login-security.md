# Platform- en bedrijfsbeveiliging

## Gedrag

- Platformbeheerders (globale rollen level 1–100) melden zich aan met wachtwoord en daarna een TOTP-code uit Google Authenticator, Microsoft Authenticator of een compatibele app.
- De eerste platformaanmelding na activering begeleidt de beheerder bij het koppelen van een authenticator en toont eenmalige herstelcodes.
- De TOTP-sleutel wordt versleuteld opgeslagen. De versleutelingssleutel staat uitsluitend in het genegeerde `settings_local.php`-bestand.
- Platformbeheer kan onder **Beheeromgeving → IP-toegang platformbeheer** tot IPv4- of IPv6-adressen/netwerken worden beperkt. Een lege lijst betekent dat er geen IP-filter is.
- De bestaande company-IP-regels gelden voor companybeheerders en medewerkers. Klantrollen (level 200 of hoger) vallen buiten dit filter.
- Een gebruikte herstelcode wordt ongeldig. Bewaar de getoonde codes buiten de server.

## Voorbereiding per installatie

Voer dit op iedere installatie afzonderlijk uit. Gebruik per installatie een eigen sleutel; kopieer de sleutel niet naar GitHub.

1. Genereer lokaal een willekeurige sleutel met `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`.
2. Voeg de uitvoer toe aan het genegeerde `settings_local.php`-bestand, bijvoorbeeld:

   ```php
   'platform_totp_encryption_key' => 'PLAK_HIER_DE_44_TEKENS_LANGE_BASE64_SLEUTEL',
   ```

3. Herhaal dit voor de testinstallatie met een andere sleutel. Houd de sleutel buiten de repository en maak er een beveiligde back-up van. Verlies van de sleutel maakt opgeslagen TOTP-sleutels onleesbaar.
4. De lokale Composer-update heeft `composer.json`, `composer.lock` en de lokale `vendor/` bijgewerkt. Voer op de testserver na synchronisatie in de projectmap `composer install --no-dev --prefer-dist --optimize-autoloader` uit; `vendor/` staat niet in Git.
5. Voer `migrations/20261031-090000_platform_mfa.sql` op de lokale database uit.
6. Voer na het aanmaken van de tabellen het passende `schema_grant_*.sql`-bestand uit. De grantbestanden geven de applicatierol toegang tot de twee nieuwe MFA-tabellen.
7. Synchroniseer de code naar de testsite, installeer daar de Composer-afhankelijkheden, vul de eigen testsleutel in `settings_local.php` in en voer de migratie en test-grants uit.
8. Meld lokaal en daarna op test aan als platformbeheerder. Koppel de authenticator en bewaar de herstelcodes.

Voer de migratie en grants niet uit op productie tot de testuitrol is gecontroleerd.

## Herstel

Een platformbeheerder die door een gewijzigde IP-lijst wordt buitengesloten, kan de globale regels via SSH en PostgreSQL herstellen. Een verloren authenticator kan door de ontwikkelaar worden hersteld door de betreffende rij(en) uit `platform_totp_credentials` en `platform_totp_recovery_codes` te verwijderen; bij de volgende aanmelding wordt de authenticator opnieuw gekoppeld.
