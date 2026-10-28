<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Provides shared authorization checks for company administration modules.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

/**
 *  Creates the first company administrator and emails a temporary password.
 *
 * @param int $companyId The company database ID.
 * @param string $companyName The company name.
 * @param string $companySlug The company URL slug.
 * @param string $email The account email address.
 * @param string $language The language code.
 * @return bool
 */
function create_initial_company_administrator(
    int $companyId,
    string $companyName,
    string $companySlug,
    string $email,
    string $language = 'NL'
): bool {
    $email = strtolower(trim($email));
    if ($companyId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $roles = pg_query_params(
        db(),
        "SELECT
            (SELECT id FROM roles WHERE name = 'Publiek') AS base_role_id,
            (SELECT id FROM company_roles WHERE company_id = $1 AND name = 'Beheerder') AS company_role_id",
        [$companyId]
    );
    $role = $roles ? pg_fetch_assoc($roles) : false;
    if (!$role || empty($role['base_role_id']) || empty($role['company_role_id'])) {
        return false;
    }

    $temporaryPassword = bin2hex(random_bytes(16));
    $userResult = pg_query_params(
        db(),
        'INSERT INTO users (
            company_id, email, display_name, password_hash, role_id, must_change_password
         ) VALUES ($1, $2, $3, $4, $5, TRUE) RETURNING id',
        [$companyId, $email, 'Beheerder', password_hash($temporaryPassword, PASSWORD_DEFAULT), (int) $role['base_role_id']]
    );
    $user = $userResult ? pg_fetch_assoc($userResult) : false;
    if (!$user) {
        return false;
    }

    $membership = pg_query_params(
        db(),
        "INSERT INTO company_users (
            company_id, user_id, company_role_id, is_active,
            approval_status, approved_at
         ) VALUES ($1, $2, $3, TRUE, 'approved', now())",
        [$companyId, (int) $user['id'], (int) $role['company_role_id']]
    );
    if (!$membership) {
        return false;
    }

    $language = strtoupper($language);
    $messages = [
        'NL' => ['Beheerdersaccount voor ', 'Je beheerdersaccount is aangemaakt.', 'Bedrijf', 'Inloggen', 'E-mailadres', 'Tijdelijk wachtwoord', 'Open Grompil en meld je aan bij dit bedrijf met het e-mailadres en tijdelijke wachtwoord. Je moet meteen een eigen wachtwoord instellen.', 'Bedrijfscode'],
        'FR' => ['Compte administrateur pour ', 'Votre compte administrateur a été créé.', 'Entreprise', 'Connexion', 'Adresse e-mail', 'Mot de passe temporaire', 'Ouvrez Grompil et connectez-vous à cette entreprise avec votre adresse e-mail et le mot de passe temporaire. Vous devrez immédiatement choisir votre propre mot de passe.', 'Code de l’entreprise'],
        'EN' => ['Administrator account for ', 'Your administrator account has been created.', 'Company', 'Sign in', 'Email address', 'Temporary password', 'Open Grompil and sign in to this company with your email address and temporary password. You must set your own password immediately.', 'Company code'],
        'DE' => ['Administratorkonto für ', 'Ihr Administratorkonto wurde erstellt.', 'Unternehmen', 'Anmelden', 'E-Mail-Adresse', 'Temporäres Passwort', 'Öffnen Sie Grompil und melden Sie sich mit Ihrer E-Mail-Adresse und dem temporären Passwort bei diesem Unternehmen an. Sie müssen sofort ein eigenes Passwort festlegen.', 'Unternehmenscode'],
    ];
    [$subjectPrefix, $intro, $companyLabel, $loginLabel, $emailLabel, $passwordLabel, $instructions, $slugLabel]
        = $messages[$language] ?? $messages['NL'];

    $safeCompany = htmlspecialchars($companyName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safePassword = htmlspecialchars($temporaryPassword, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeSlug = htmlspecialchars($companySlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<p>' . htmlspecialchars($intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><strong>' . htmlspecialchars($companyLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> ' . $safeCompany . '</p>'
        . '<p><strong>' . htmlspecialchars($slugLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> ' . $safeSlug . '</p>'
        . '<p><strong>' . htmlspecialchars($emailLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> ' . $safeEmail . '</p>'
        . '<p><strong>' . htmlspecialchars($passwordLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> <code>' . $safePassword . '</code></p>'
        . '<p>' . htmlspecialchars($instructions, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    $text = $intro . "\n\n" . $companyLabel . ': ' . $companyName . "\n" . $slugLabel . ': ' . $companySlug
        . "\n" . $emailLabel . ': ' . $email . "\n" . $passwordLabel . ': ' . $temporaryPassword
        . "\n\n" . $instructions;

    try {
        grompil_send_mail($email, $subjectPrefix . $companyName, $html, $text);
        return true;
    } catch (Throwable $exception) {
        error_log('Initial company administrator email could not be sent: ' . $exception->getMessage());
        return false;
    }
}

