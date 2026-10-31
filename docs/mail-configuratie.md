<!--
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:48
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Documents the Grompil mail server configuration.
 *
 */
-->

# E-mail via SMTP

Grompil kan e-mail versturen via een SMTP-server met PHPMailer. De SMTP-gegevens horen in de `mail`-sectie van het credentialsbestand dat bij de installatie hoort. Dat bestand staat buiten de webroot.

De lokale, test- en productie-installatie kiezen hun credentialsbestand via `settings_local.php`. De bestanden heten lokaal `credentials_local.php`, op test `credentials_db_test.php` en op productie `credentials_db_production.php`; op hosting staan ze boven respectievelijk `grompil_test` en `public_html`. Alle bestanden geven een configuratiearray met de bestaande secties `database`, `kbo` en `mail` terug. Zo kunnen SMTP-instellingen per omgeving verschillen en kan de testomgeving bijvoorbeeld een aparte mailconfiguratie gebruiken.

Voeg in de `mail`-sectie van het geselecteerde bestand de SMTP-instellingen toe. Behoud ook de `database`- en eventuele `kbo`-secties:

```php
'mail' => [
    'host' => 'smtp.provider.example',
    'port' => 587,
    'username' => 'accountnaam',
    'password' => 'providerwachtwoord-of-appwachtwoord',
    'encryption' => 'tls', // tls voor STARTTLS, ssl voor SMTPS, none alleen indien de server dit vereist
    'from_email' => 'noreply@example.com',
    'from_name' => 'Grompil',
    'reply_to' => 'support@example.com', // optioneel
],
```

Installeer de projectafhankelijkheden vanuit de projectmap met `composer install`. Dit maakt `vendor/` en `composer.lock` aan. De `vendor/`-map bevat code van derden; `composer.lock` legt de gebruikte versies vast.

De procedurele helper `includes/mailer.php` biedt `grompil_send_mail($recipient, $subject, $htmlBody, $textBody)`. De functie geeft een fout als de bibliotheek of configuratie ontbreekt en gooit een uitzondering als SMTP-verzending mislukt. De helper wordt gebruikt voor het eerste bedrijfsbeheerdersaccount, wachtwoordherstel en de welkomstmail na een klantregistratie. De HTML-layout van de klantwelkomstmail staat in `tmpl/core/welcome_customer_email.html`. Voor e-mails met een absolute link is op hosting `public_url` in `settings_local.php` nodig; `GROMPIL_PUBLIC_URL` kan dit overrulen. Zie [password-recovery.md](password-recovery.md).

Op localhost is uitgaand HTTPS/SMTP-verkeer nodig. Providerinstellingen, zoals host, poort en encryptie, verschillen per e-mailprovider. Gebruik een app-wachtwoord wanneer de provider dat vereist. Gebruik `encryption => 'none'` niet over een openbaar netwerk.

