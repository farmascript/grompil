<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:48
 * @date_mut: Wed 2026-10-21 09:10:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Renders safe error pages with an internal error code.
 *
 */

declare(strict_types=1);

/** Render a stable, user-facing error page without requiring the database. */
function grompil_show_error(int $status, int $code, string $message, string $title = 'An error occurred'): never
{
    http_response_code($status);
    $language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
    if (!in_array($language, ['NL', 'FR', 'EN', 'DE'], true)) {
        $language = 'NL';
    }
    $catalog = [
        12544 => [
            'NL' => ['Website niet beschikbaar', 'Deze website is niet beschikbaar. Controleer de link of neem contact op met het bedrijf.'],
            'FR' => ['Site web indisponible', 'Ce site web est indisponible. Vérifiez le lien ou contactez l’entreprise.'],
            'EN' => ['Website unavailable', 'This website is unavailable. Check the link or contact the company.'],
            'DE' => ['Website nicht verfügbar', 'Diese Website ist nicht verfügbar. Prüfen Sie den Link oder wenden Sie sich an das Unternehmen.'],
        ],
        18403 => [
            'NL' => ['Aanmelden niet mogelijk', 'Het bedrijfsinlogscherm is niet beschikbaar. Controleer de bedrijfslink.'],
            'FR' => ['Connexion impossible', 'L’écran de connexion de cette entreprise est indisponible. Vérifiez le lien.'],
            'EN' => ['Sign-in unavailable', 'The company sign-in page is unavailable. Check the company link.'],
            'DE' => ['Anmeldung nicht möglich', 'Die Anmeldeseite des Unternehmens ist nicht verfügbar. Prüfen Sie den Link.'],
        ],
        21607 => [
            'NL' => ['Aanvraag niet toegestaan', 'Gebruik de afmeldknop in de applicatie om af te melden.'],
            'FR' => ['Requête non autorisée', 'Utilisez le bouton de déconnexion de l’application pour vous déconnecter.'],
            'EN' => ['Request not allowed', 'Use the sign-out button in the application to sign out.'],
            'DE' => ['Anfrage nicht zulässig', 'Verwenden Sie zum Abmelden die Schaltfläche in der Anwendung.'],
        ],
        32719 => [
            'NL' => ['Dienst tijdelijk niet beschikbaar', 'De databaseconfiguratie ontbreekt. Neem contact op met de systeembeheerder.'],
            'FR' => ['Service temporairement indisponible', 'La configuration de la base de données est manquante. Contactez l’administrateur système.'],
            'EN' => ['Service temporarily unavailable', 'The database configuration is missing. Contact the system administrator.'],
            'DE' => ['Dienst vorübergehend nicht verfügbar', 'Die Datenbankkonfiguration fehlt. Wenden Sie sich an den Systemadministrator.'],
        ],
        33826 => [
            'NL' => ['Dienst tijdelijk niet beschikbaar', 'De database is momenteel niet bereikbaar. Probeer het later opnieuw.'],
            'FR' => ['Service temporairement indisponible', 'La base de données est momentanément inaccessible. Réessayez plus tard.'],
            'EN' => ['Service temporarily unavailable', 'The database is currently unreachable. Please try again later.'],
            'DE' => ['Dienst vorübergehend nicht verfügbar', 'Die Datenbank ist momentan nicht erreichbar. Bitte versuchen Sie es später erneut.'],
        ],
        40152 => [
            'NL' => ['Geen toegang', 'Je hebt geen toegang tot deze pagina.'],
            'FR' => ['Accès refusé', 'Vous n’avez pas accès à cette page.'],
            'EN' => ['Access denied', 'You do not have access to this page.'],
            'DE' => ['Zugriff verweigert', 'Sie haben keinen Zugriff auf diese Seite.'],
        ],
        45878 => [
            'NL' => ['Ongeldige aanvraag', 'De aanvraag kon niet worden gecontroleerd. Vernieuw de pagina en probeer opnieuw.'],
            'FR' => ['Requête invalide', 'La requête n’a pas pu être vérifiée. Actualisez la page et réessayez.'],
            'EN' => ['Invalid request', 'The request could not be verified. Refresh the page and try again.'],
            'DE' => ['Ungültige Anfrage', 'Die Anfrage konnte nicht überprüft werden. Aktualisieren Sie die Seite und versuchen Sie es erneut.'],
        ],
        50631 => [
            'NL' => ['Registratie niet beschikbaar', 'Registratie is tijdelijk niet beschikbaar. Probeer het later opnieuw.'],
            'FR' => ['Inscription indisponible', 'L’inscription est temporairement indisponible. Réessayez plus tard.'],
            'EN' => ['Registration unavailable', 'Registration is temporarily unavailable. Please try again later.'],
            'DE' => ['Registrierung nicht verfügbar', 'Die Registrierung ist vorübergehend nicht verfügbar. Bitte versuchen Sie es später erneut.'],
        ],
        61724 => [
            'NL' => ['Controle mislukt', 'De bestaande vertalingen konden niet worden gecontroleerd. Probeer het later opnieuw.'],
            'FR' => ['Vérification échouée', 'Les traductions existantes n’ont pas pu être vérifiées. Réessayez plus tard.'],
            'EN' => ['Check failed', 'The existing translations could not be checked. Please try again later.'],
            'DE' => ['Prüfung fehlgeschlagen', 'Die vorhandenen Übersetzungen konnten nicht geprüft werden. Bitte versuchen Sie es später erneut.'],
        ],
        72805 => [
            'NL' => ['Pagina niet beschikbaar', 'Een paginaonderdeel kon niet worden geladen. Neem contact op met de beheerder.'],
            'FR' => ['Page indisponible', 'Un élément de la page n’a pas pu être chargé. Contactez l’administrateur.'],
            'EN' => ['Page unavailable', 'A page component could not be loaded. Contact the administrator.'],
            'DE' => ['Seite nicht verfügbar', 'Ein Seitenelement konnte nicht geladen werden. Wenden Sie sich an den Administrator.'],
        ],
    ];
    [$title, $message] = $catalog[$code][$language] ?? [$title, $message];
    $labels = [
        'NL' => ['Meldingsnummer', 'Terug', 'Startpagina'],
        'FR' => ['Numéro de référence', 'Retour', 'Accueil'],
        'EN' => ['Reference number', 'Back', 'Home'],
        'DE' => ['Meldungsnummer', 'Zurück', 'Startseite'],
    ][$language];
    $templatePath = dirname(__DIR__) . '/tmpl/default/error.html';
    $template = is_file($templatePath) ? file_get_contents($templatePath) : false;
    $values = [
        '@error_title@' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        '@error_message@' => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        '@error_code@' => (string) $code,
        '@error_reference_label@' => $labels[0],
        '@error_back_label@' => $labels[1],
        '@error_home_label@' => $labels[2],
    ];
    if ($template === false) {
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>' . $values['@error_title@']
            . '</title><main><h1>' . $values['@error_title@'] . '</h1><p>' . $values['@error_message@']
            . '</p><p>Reference number ' . $code . '</p></main></html>';
    } else {
        echo strtr($template, $values);
    }
    exit;
}

