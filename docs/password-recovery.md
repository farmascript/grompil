<!--
/**
 *
 * @date_cre: Wed 2026-10-21 17:30:00
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Documents installation, behavior and verification of password recovery.
 *
 */
-->

# Wachtwoord vergeten

Het inlogscherm bevat **Wachtwoord vergeten?**. De algemene login verwijst naar `forgot-password.php`; een bedrijfslogin bewaart de context, bijvoorbeeld `forgot-password.php?c=ariodante`. De gebruiker voert het e-mailadres in, ontvangt een link en stelt via `reset-password.php` een nieuw wachtwoord van minstens 12 tekens in. Er wordt geen wachtwoord per e-mail verzonden en er volgt geen automatische aanmelding.

## Eenmalig inschakelen

1. Maak een backup en voer `migrations/20261021-183938_password_reset.sql` en daarna `migrations/20261024-110624_platform_system_settings.sql` in Adminer uit als databasebeheerder. Het volledige `schema.sql` bevat beide uitbreidingen.
2. SMTP gebruikt de instellingen in de `mail`-sectie van het credentialsbestand dat via `settings_local.php` voor deze installatie is geselecteerd; zie [mail-configuratie.md](mail-configuratie.md).
3. Op localhost wordt het lokale serveradres met de Apache-poort gebruikt. Zet op hosting in `settings_local.php` de `public_url` op de vaste HTTPS-basis-URL van de applicatie, bijvoorbeeld `'public_url' => 'https://test.grompil.com'`. Gebruik bij installatie in een submap het volledige pad, bijvoorbeeld `https://voorbeeld.be/grompil/`; bij installatie op de domeinroot `https://voorbeeld.be/`. De omgevingsvariabele `GROMPIL_PUBLIC_URL` blijft beschikbaar en heeft voorrang. De resetlink wordt niet uit een willekeurige Host-header opgebouwd.
4. De nieuwe teksten staan in NL, FR, EN en DE in de gewone vertaalworkflow. Bij een andere installatie: importeer ontbrekende keys met `php scripts/import_translations.php` en publiceer met `php scripts/export_translations.php`.

De login blijft ook vóór de migratie bruikbaar. Resetaanvragen hebben de nieuwe tabellen nodig; zonder die migratie verschijnt de vertaalde melding dat wachtwoordherstel tijdelijk niet beschikbaar is.

## Gedrag

- Alleen actieve, goedgekeurde accounts krijgen een link. De bevestiging is gelijk voor onbekende adressen, uitgesloten accounts en begrensde aanvragen. E-mailfouten geven geen informatie over het bestaan van een account; er wordt alleen een technische melding zonder adres of token gelogd.
- Hetzelfde e-mailadres bij twee bedrijven betreft twee aparte accounts. De algemene route behandelt alleen algemene accounts. De bedrijfsroute behandelt alleen accounts van dat actieve bedrijf; publieke zichtbaarheid is hiervoor, net zoals bij inloggen, niet vereist. De IP-beperking op de bedrijfslogin blijft ook na reset gelden.
- Tokens bevatten 32 willekeurige bytes; alleen een SHA-256-hash wordt opgeslagen. Links verlopen na 30 minuten, zijn eenmalig en worden vervangen bij een nieuwe aanvraag. Een tussentijdse wachtwoordwijziging maakt een eerder uitgegeven link ook ongeldig.
- Een geldige link wordt eerst in de serversessie opgenomen. Een redirect verwijdert het token uit de zichtbare URL voordat de formulierpagina externe stylesheets laadt. De pagina gebruikt `no-store` en `no-referrer`. De uiteindelijke wijziging vereist ook een geldige CSRF-token.
- De database vergrendelt het account tijdens de wijziging, wijzigt het wachtwoord en verwijdert alle tokens voor dat account in één transactie. `users.auth_version` wordt verhoogd zodat bestaande aanmeldsessies bij hun volgende request ongeldig worden. Oudere sessies gelden als versie 0.
- Per e-mailadres en bedrijf geldt de platformlimiet voor aanvragen binnen een schuivende periode; standaard zijn dit drie aanvragen in 15 minuten, inclusief de eerste aanvraag. De platformbeheerder kan beide waarden wijzigen via **Beheeromgeving → Systeemparameters**. Daarnaast blijven maximaal twintig aanvragen per IP per uur toegestaan. De limieten worden onder databasevergrendeling gecontroleerd. Na een aanvraag verdwijnt het e-mailveld en verschijnt een knop om de link opnieuw te versturen. Bij het bereiken van de limiet wordt die knop tijdelijk uitgeschakeld en na de wachttijd weer actief. In de aanvraaghistoriek wordt een hash van het e-mailadres opgeslagen. Oude verzoeken en vervallen links worden bij nieuwe toegestane aanvragen opgeruimd.

## Controle

`php scripts/test_password_reset.php` gebruikt uitsluitend tijdelijke PostgreSQL-tabellen in één verbinding en verstuurt geen mail. De test controleert bedrijfsscheiding, hashing, vervanging en vervaldatum van tokens, hergebruik, wachtwoordwijziging, sessieversie, accountstatus, verzoeklimieten, herhaalbare migratie en vertrouwde basis-URL's. De test werkt ook vóór de echte migratie en meldt apart of die al uitgevoerd is.

Controleer na de migratie de mailverzending met een eigen account: vraag via de juiste loginroute een resetlink aan, open de mail, kies een nieuw wachtwoord en meld je daarmee aan. Controleer dat het oude wachtwoord en de al gebruikte link niet meer werken.
