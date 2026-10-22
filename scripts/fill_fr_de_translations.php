<?php
/**
 *
 * @date_cre: Wed 2026-10-14 22:18:51
 * @date_mut: Wed 2026-10-21 09:10:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Fills French and German translations from the Dutch source text.
 *
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/database_config.php';

$connectionString = grompil_database_connection_string();
if ($connectionString === null) {
    fwrite(STDERR, "Database password is missing from credentials.php or the environment variables.\n");
    exit(1);
}
$connection = @pg_connect($connectionString);
if (!$connection) {
    fwrite(STDERR, "Could not connect to PostgreSQL.\n");
    exit(1);
}
pg_query($connection, "SELECT set_config('grompil.platform_operator', 'true', false)");

// Translation arrays follow the alphabetical key order in lang_nl.php.
$fr = [
    'Accès refusé.', 'Client — compte immédiatement actif', 'Personnel de pharmacie — demande soumise à approbation', 'Type de compte', 'Actif',
    'Administration', 'Administration', 'Choisissez une rubrique pour gérer l’environnement de la pharmacie.', 'Configurer les niveaux numériques et les autorisations.', 'Gérer les textes du site et publier les fichiers de langue.', 'Approuver les comptes et gérer les accès.',
    'Approuvé', 'En attente d’approbation', 'Refusé', 'Choisissez un rôle valide pour le personnel avant d’approuver.', 'Demande traitée.', 'Approuver', 'Base', 'Annuler', 'Choisir le rôle attribué lors de l’approbation', 'Approbation', 'Dernière connexion', 'Autorisations', 'Rôle', 'Statut', 'Utilisateur', 'Utilisateurs',
    'Bonjour,', 'Vous êtes connecté en tant que', 'Bienvenue sur Grompil', 'Supprimer', 'Description', 'Nom', 'Modifier', 'Adresse e-mail', 'nl', 'Demande invalide.', 'Échec de la connexion. Vérifiez vos identifiants ou réessayez plus tard.',
    'Logiciel pour pharmacies', 'Plus de temps pour les soins.', 'Une journée mieux organisée.', 'Connectez-vous avec les identifiants de votre compte.', 'Connectez-vous à l’espace de travail sécurisé de votre pharmacie.', 'Connexion sécurisée · Session protégée', 'Se connecter en toute sécurité', 'Connexion', 'Bon retour',
    'Cette rubrique sera développée dans une prochaine étape.', 'Gestion des stocks', 'Médicaments à renouveler', 'Bientôt', 'Vue d’ensemble de l’équipe', 'Administration', 'Vue d’ensemble', 'Déconnexion', 'Rôles et autorisations', 'Site public de la pharmacie', 'Traductions', 'Utilisateurs', 'Jamais', 'Mot de passe', 'Confirmer le mot de passe',
    'Gérer les rôles', 'Gérer les traductions', 'Gérer les utilisateurs', 'Autorisations de gestion', 'Aucune inscription n’a pu aboutir. Vérifiez vos données ou connectez-vous.', 'Après l’inscription, un administrateur examinera votre demande.', 'Veuillez remplir correctement tous les champs. Le mot de passe doit contenir au moins 12 caractères.', 'Trop de tentatives d’inscription. Réessayez plus tard.', 'Demander un compte', 'Votre demande a été reçue. Un administrateur l’examinera avant l’activation de votre compte.', 'Votre compte client est créé et immédiatement actif. Vous pouvez vous connecter.', 'Votre demande de compte personnel a été reçue. Un administrateur doit l’approuver et attribuer un rôle.', 'Demander un compte', 'Pas encore de compte ? Demander un compte', 'Refuser', 'Ce rôle est essentiel au système ou encore utilisé.', 'Rôle supprimé.', 'SS : niveau exact. SL : intervalle ouvert.', 'Saisissez un niveau numérique positif valide.', 'Niveau numérique', 'Nom du rôle', 'Veuillez saisir un nom de rôle.', 'Enregistrer le rôle', 'Échec de l’enregistrement. Vérifiez si le nom du rôle existe déjà.', 'Rôle enregistré.', 'Modifier le rôle', 'Rôles', 'Nouveau rôle', 'Enregistrer',
    'Accompagnement personnalisé par l’équipe de votre pharmacie.', 'Conseils de confiance', 'Votre pharmacie reste votre interlocuteur habituel, même en ligne.', 'Près de chez vous', 'Une offre claire et des commandes pratiques, au même endroit.', 'En toute simplicité', 'Pharmacie · soins · proximité', 'Découvrir la pharmacie', 'Créez votre compte client', 'L’offre en ligne de votre pharmacie sera bientôt disponible ici.', 'Bientôt disponible', 'Votre pharmacie, toujours à portée de main', 'Créez votre compte', 'Votre pharmacie', 'Des soins de qualité, près de chez vous', 'Des soins de confiance, un service personnalisé et une offre soigneusement sélectionnée par votre pharmacie.', 'Ouvrir la navigation', 'Notre offre', 'Nos services', 'Par l’équipe de pharmacie que vous connaissez', 'Service personnalisé', 'Propulsé par', 'Découvrez les services de votre pharmacie et trouvez bientôt en ligne ce dont vous avez besoin.', 'Bien plus qu’une boutique en ligne', 'Tout commence par des soins de qualité.', 'Aller à la gestion de la pharmacie', 'Un service personnalisé qui reste proche de vous', 'Pharmacie', 'Les services de votre pharmacie, désormais aussi en ligne.', 'Illustration graphique de la pharmacie en ligne', 'À votre écoute', 'Soins, conseils et service de confiance', 'VOTRE PHARMACIE EN LIGNE',
    'Publier les fichiers de langue', 'Gérer les traductions', 'Importer les textes manquants', 'Échec de l’importation. Aucune donnée n’a été enregistrée.', 'Les textes manquants ont été ajoutés et les fichiers de langue mis à jour.', 'Modifiez les textes par langue. La publication recrée les fichiers de langue.', 'Clé', 'Langue', 'Enregistrement dans la base de données réussi, mais échec de la publication. Vérifiez les droits d’écriture du dossier des langues.', 'Les fichiers de langue NL, FR, EN et DE ont été publiés.', 'Enregistrer et publier', 'Traductions enregistrées et fichier de langue publié.', 'Vous ne pouvez pas modifier ici le statut ou le rôle de votre propre compte.', 'Impossible de mettre à jour l’utilisateur.', 'Utilisateur mis à jour.', 'Utilisateurs et accès', 'Espace de travail de la pharmacie', 'Vous', 'Texte', 'Service personnalisé, près de chez vous', 'Connecté en tant que', 'Votre pharmacie.',
];

$de = [
    'Zugriff verweigert.', 'Kunde — Konto sofort nutzen', 'Apothekenpersonal — Antrag zur Genehmigung', 'Kontoart', 'Aktiv',
    'Verwaltungsbereich', 'Verwaltungsbereich', 'Wählen Sie einen Bereich zur Verwaltung der Apothekenumgebung.', 'Numerische Levels und Berechtigungen konfigurieren.', 'Webtexte verwalten und Sprachdateien veröffentlichen.', 'Konten genehmigen und Zugänge verwalten.',
    'Genehmigt', 'Wartet auf Genehmigung', 'Abgelehnt', 'Wählen Sie vor der Genehmigung eine gültige Personalrolle.', 'Antrag bearbeitet.', 'Genehmigen', 'Basis', 'Abbrechen', 'Personalrolle für die Genehmigung auswählen', 'Genehmigung', 'Zuletzt angemeldet', 'Berechtigungen', 'Rolle', 'Status', 'Benutzer', 'Benutzer',
    'Guten Tag,', 'Sie sind angemeldet als', 'Willkommen bei Grompil', 'Löschen', 'Beschreibung', 'Name', 'Bearbeiten', 'E-Mail-Adresse', 'de', 'Ungültige Anfrage.', 'Anmeldung fehlgeschlagen. Prüfen Sie Ihre Daten oder versuchen Sie es später erneut.',
    'Software für Apotheken', 'Mehr Zeit für die Beratung.', 'Mehr Überblick im Alltag.', 'Melden Sie sich mit Ihren Zugangsdaten an.', 'Melden Sie sich in der geschützten Arbeitsumgebung Ihrer Apotheke an.', 'Sichere Verbindung · Geschützte Sitzung', 'Sicher anmelden', 'Anmelden', 'Willkommen zurück',
    'Dieser Bereich wird in einem nächsten Schritt weiterentwickelt.', 'Lagerverwaltung', 'Dauermedikation', 'Demnächst', 'Teamübersicht', 'Verwaltung', 'Übersicht', 'Abmelden', 'Rollen und Berechtigungen', 'Öffentliche Apothekenwebsite', 'Übersetzungen', 'Benutzer', 'Noch nie', 'Passwort', 'Passwort wiederholen',
    'Rollen verwalten', 'Übersetzungen verwalten', 'Benutzer verwalten', 'Verwaltungsrechte', 'Die Registrierung konnte nicht abgeschlossen werden. Prüfen Sie Ihre Daten oder melden Sie sich an.', 'Nach der Registrierung prüft ein Administrator Ihren Antrag.', 'Füllen Sie alle Felder korrekt aus. Das Passwort muss mindestens 12 Zeichen haben.', 'Zu viele Registrierungsversuche. Bitte versuchen Sie es später erneut.', 'Konto beantragen', 'Ihr Antrag ist eingegangen. Ein Administrator prüft ihn vor der Aktivierung Ihres Kontos.', 'Ihr Kundenkonto ist erstellt und sofort aktiv. Sie können sich jetzt anmelden.', 'Ihr Personal-Antrag ist eingegangen. Ein Administrator muss ihn genehmigen und eine Personalrolle zuweisen.', 'Konto beantragen', 'Noch kein Konto? Konto beantragen', 'Ablehnen', 'Diese Rolle ist systemkritisch oder wird noch verwendet.', 'Rolle gelöscht.', 'SS: genaues Level. SL: offener Wertebereich.', 'Geben Sie ein gültiges positives numerisches Level ein.', 'Numerisches Level', 'Rollenname', 'Geben Sie einen Rollennamen ein.', 'Rolle speichern', 'Speichern fehlgeschlagen. Prüfen Sie, ob der Rollenname bereits besteht.', 'Rolle gespeichert.', 'Rolle bearbeiten', 'Rollen', 'Neue Rolle', 'Speichern',
    'Persönliche Beratung durch das Team Ihrer Apotheke.', 'Vertrauensvolle Beratung', 'Ihre Apotheke bleibt auch online Ihre vertraute Anlaufstelle.', 'In Ihrer Nähe', 'Ein übersichtliches Online-Angebot und praktische Bestellmöglichkeiten an einem Ort.', 'Einfach erledigt', 'Apotheke · Beratung · Nähe', 'Apotheke entdecken', 'Erstellen Sie Ihr Kundenkonto', 'Das Online-Angebot Ihrer Apotheke wird hier bereitgestellt.', 'Demnächst verfügbar', 'Ihre Apotheke immer griffbereit', 'Konto erstellen', 'Ihre Apotheke', 'Gute Versorgung ganz in Ihrer Nähe', 'Vertraute Versorgung, persönlicher Service und ein sorgfältig ausgewähltes Angebot Ihrer Apotheke.', 'Navigation öffnen', 'Unser Angebot', 'Unser Service', 'Vom Apothekenteam, das Sie kennen', 'Persönlicher Service', 'Ermöglicht durch', 'Entdecken Sie den Service Ihrer Apotheke und finden Sie bald auch online, was Sie brauchen.', 'Mehr als ein Webshop', 'Gute Versorgung steht am Anfang.', 'Zur Apothekenverwaltung', 'Persönlicher Service ganz in Ihrer Nähe', 'Apotheke', 'Der Service Ihrer Apotheke, jetzt auch online.', 'Grafische Illustration der Online-Apotheke', 'Mit Aufmerksamkeit für Sie', 'Versorgung, Beratung und vertrauter Service', 'IHRE ONLINE-APOTHEKE',
    'Sprachdateien veröffentlichen', 'Übersetzungen verwalten', 'Fehlende Texte importieren', 'Import fehlgeschlagen. Es wurden keine Daten gespeichert.', 'Fehlende Texte wurden hinzugefügt und Sprachdateien aktualisiert.', 'Bearbeiten Sie Texte je Sprache. Beim Veröffentlichen werden die Sprachdateien neu erstellt.', 'Schlüssel', 'Sprache', 'Datenbankänderung erfolgreich, aber Veröffentlichung fehlgeschlagen. Prüfen Sie die Schreibrechte des Sprachordners.', 'Die Sprachdateien für NL, FR, EN und DE wurden veröffentlicht.', 'Speichern und veröffentlichen', 'Übersetzungen gespeichert und Sprachdatei veröffentlicht.', 'Sie können Ihren eigenen Kontostatus oder Ihre Rolle hier nicht ändern.', 'Benutzer konnte nicht aktualisiert werden.', 'Benutzer aktualisiert.', 'Benutzer und Zugänge', 'Arbeitsbereich der Apotheke', 'Sie', 'Text', 'Persönlicher Service in Ihrer Nähe', 'Angemeldet als', 'Ihre Apotheke.',
];

$nlFile = dirname(__DIR__) . '/src/lang/lang_nl.php';
$fr = array_merge(array_slice($fr, 0, 106), [
    'Des soins personnalisés, près de chez vous.',
    'Votre pharmacie. En ligne et près de chez vous.',
    'Des soins de confiance, un service personnalisé et une offre soigneusement sélectionnée — par votre pharmacie.',
    'Ouvrir la navigation',
    'L’offre',
    'Nos services',
    'Par l’équipe de pharmacie que vous connaissez',
    'Service personnalisé',
    'Propulsé par',
    'Découvrez les services de votre pharmacie et trouvez bientôt en ligne ce dont vous avez besoin.',
    'Bien plus qu’une boutique en ligne',
    'Tout commence par des soins de qualité.',
    'Connecté en tant que',
    'Aller à la gestion de la pharmacie',
    'Des soins qui restent proches de vous',
    'Pharmacie',
    'Le service de votre pharmacie, désormais aussi en ligne.',
    'Illustration graphique de la pharmacie en ligne',
    'À votre écoute',
    'Soins, conseils et service de confiance',
    'VOTRE PHARMACIE EN LIGNE',
    'Publier les fichiers de langue',
    'Gérer les traductions',
    'Importer les textes manquants',
    'Échec de l’importation. Aucune donnée n’a été enregistrée.',
    'Les textes manquants ont été ajoutés et les fichiers de langue mis à jour.',
    'Modifiez les textes par langue. La publication recrée les fichiers de langue.',
    'Clé',
    'Langue',
    'Enregistrement dans la base de données réussi, mais échec de la publication. Vérifiez les droits d’écriture du dossier des langues.',
    'Les fichiers de langue NL, FR, EN et DE ont été publiés.',
    'Enregistrer et publier',
    'Traductions enregistrées et fichier de langue publié.',
    'Texte',
    'Vous ne pouvez pas modifier ici le statut ou le rôle de votre propre compte.',
    'Impossible de mettre à jour l’utilisateur.',
    'Utilisateur mis à jour.',
    'Utilisateurs et accès',
    'Espace de travail de la pharmacie',
    'Vous',
]);
$de = array_merge(array_slice($de, 0, 106), [
    'Persönliche Beratung, ganz in Ihrer Nähe.',
    'Ihre Apotheke. Online und in Ihrer Nähe.',
    'Vertraute Beratung, persönlicher Service und ein sorgfältig ausgewähltes Angebot Ihrer Apotheke.',
    'Navigation öffnen',
    'Unser Angebot',
    'Unser Service',
    'Vom Apothekenteam, das Sie kennen',
    'Persönlicher Service',
    'Ermöglicht durch',
    'Entdecken Sie den Service Ihrer Apotheke und finden Sie bald auch online, was Sie brauchen.',
    'Mehr als ein Webshop',
    'Gute Versorgung steht am Anfang.',
    'Angemeldet als',
    'Zur Apothekenverwaltung',
    'Versorgung, die in Ihrer Nähe bleibt',
    'Apotheke',
    'Der Service Ihrer Apotheke, jetzt auch online.',
    'Grafische Illustration der Online-Apotheke',
    'Mit Aufmerksamkeit für Sie',
    'Versorgung, Beratung und vertrauter Service',
    'IHRE ONLINE-APOTHEKE',
    'Sprachdateien veröffentlichen',
    'Übersetzungen verwalten',
    'Fehlende Texte importieren',
    'Import fehlgeschlagen. Es wurden keine Daten gespeichert.',
    'Fehlende Texte wurden hinzugefügt und Sprachdateien aktualisiert.',
    'Bearbeiten Sie Texte je Sprache. Beim Veröffentlichen werden die Sprachdateien neu erstellt.',
    'Schlüssel',
    'Sprache',
    'Datenbankänderung erfolgreich, aber Veröffentlichung fehlgeschlagen. Prüfen Sie die Schreibrechte des Sprachordners.',
    'Die Sprachdateien für NL, FR, EN und DE wurden veröffentlicht.',
    'Speichern und veröffentlichen',
    'Übersetzungen gespeichert und Sprachdatei veröffentlicht.',
    'Text',
    'Sie können Ihren eigenen Kontostatus oder Ihre Rolle hier nicht ändern.',
    'Benutzer konnte nicht aktualisiert werden.',
    'Benutzer aktualisiert.',
    'Benutzer und Zugänge',
    'Arbeitsbereich der Apotheke',
    'Sie',
]);
$arrSubst = [];
require $nlFile;
$keys = array_keys($arrSubst);
if (count($keys) !== count($fr) || count($keys) !== count($de)) {
    fwrite(STDERR, sprintf("Counts do not match: keys=%d FR=%d DE=%d\n", count($keys), count($fr), count($de)));
    foreach ($keys as $index => $key) {
        fwrite(STDERR, sprintf("%03d %-36s %s\n", $index, $key, $fr[$index] ?? '[MISSING]'));
    }
    exit(1);
}
if (!pg_query($connection, 'BEGIN')) {
    fwrite(STDERR, "Failed to start the transaction.\n");
    exit(1);
}
foreach (['FR' => $fr, 'DE' => $de] as $code => $values) {
    foreach ($keys as $index => $key) {
        $value = $values[$index];
        if ($key === 'html_language') {
            $value = strtolower($code);
        }
        $result = pg_query_params(
            $connection,
            'UPDATE substitutions SET "value" = $1 WHERE company_id = 0 AND "key" = $2 AND code = $3 AND trim("value") = $4',
            [$value, $key, $code, '']
        );
        if (!$result) {
            pg_query($connection, 'ROLLBACK');
            fwrite(STDERR, "Update failed; transaction rolled back.\n");
            exit(1);
        }
    }
}
if (!pg_query($connection, 'COMMIT')) {
    pg_query($connection, 'ROLLBACK');
    fwrite(STDERR, "Commit failed; transaction rolled back.\n");
    exit(1);
}
fwrite(STDOUT, "Empty FR and DE translations have been filled.\n");

