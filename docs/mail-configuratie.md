<!--
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:48
 * @date_mut: Wed 2026-10-21 18:39:38
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

Grompil kan e-mail versturen via een SMTP-server met PHPMailer. De SMTP-gegevens horen in hetzelfde privébestand buiten de webroot als de databasegegevens, standaard `C:\usr\credentials.php`.

Voeg daar naast de bestaande `database`-sectie een `mail`-sectie aan toe. Behoud de bestaande database-inhoud:

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

De procedurele helper `includes/mailer.php` biedt `grompil_send_mail($recipient, $subject, $htmlBody, $textBody)`. De functie geeft een fout als de bibliotheek of configuratie ontbreekt en gooit een uitzondering als SMTP-verzending mislukt. De helper wordt gebruikt voor het eerste bedrijfsbeheerdersaccount en voor wachtwoordherstel. Voor wachtwoordherstel op hosting is ook `GROMPIL_PUBLIC_URL` nodig; zie [password-recovery.md](password-recovery.md).

Op localhost is uitgaand HTTPS/SMTP-verkeer nodig. Providerinstellingen, zoals host, poort en encryptie, verschillen per e-mailprovider. Gebruik een app-wachtwoord wanneer de provider dat vereist. Gebruik `encryption => 'none'` niet over een openbaar netwerk.

