<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:57
 * @date_mut: Wed 2026-10-21 09:10:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Creates the first platform administrator through the PHP CLI.
 *
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require GROMPIL_ROOT . '/includes/bootstrap.php';

$email = strtolower(
    trim((string) getenv('GROMPIL_SETUP_ADMIN_EMAIL'))
);
$password = (string) getenv('GROMPIL_SETUP_ADMIN_PASSWORD');
$displayName = trim(
    (string) (getenv('GROMPIL_SETUP_ADMIN_NAME') ?: 'Grompil-beheerder')
);
$nameParts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$firstName = (string) array_shift($nameParts);
$lastName = implode(' ', $nameParts);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(
        STDERR,
        "Stel GROMPIL_SETUP_ADMIN_EMAIL en GROMPIL_SETUP_ADMIN_PASSWORD in (min. 12 tekens).\n"
    );
    exit(1);
}

$roleResult = pg_query_params(
    db(),
    'SELECT id FROM roles WHERE lower(name) = lower($1) ORDER BY id LIMIT 1',
    ['Beheerder']
);

$adminRole = $roleResult
    ? pg_fetch_assoc($roleResult)
    : false;

if (!$adminRole) {
    fwrite(STDERR, "Run schema.sql first.\n");
    exit(1);
}

$existingUserResult = pg_query_params(
    db(),
    'SELECT id FROM users WHERE lower(email) = lower($1) AND company_id IS NULL',
    [$email]
);

if (
    $existingUserResult
    && pg_num_rows($existingUserResult) > 0
) {
    fwrite(STDERR, "The account already exists.\n");
    exit(1);
}

$createUserResult = pg_query_params(
    db(),
    'INSERT INTO users (
        email,
        display_name,
        name_first,
        name_last,
        password_hash,
        role_id
     ) VALUES ($1, $2, $3, $4, $5, $6)',
    [
        $email,
        $displayName,
        $firstName,
        $lastName,
        password_hash($password, PASSWORD_DEFAULT),
        (int) $adminRole['id'],
    ]
);

if (!$createUserResult) {
    fwrite(STDERR, "Account creation failed.\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Beheerder aangemaakt. Verwijder setup-variabelen.\n"
);

