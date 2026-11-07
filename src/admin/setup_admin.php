<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:57
 * @date_mut: Sat 2026-11-07 14:54:17
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Creates the first platform administrator through the PHP CLI.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:admin:setup_admin
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

try {
    $roleResult = db_query_params(
        'setup_admin.find_role',
        'SELECT id FROM ft_roles WHERE lower(name) = lower($1) ORDER BY id LIMIT 1',
        ['Beheerder']
    );
} catch (GrompilDatabaseQueryException) {
    $roleResult = false;
}

$adminRole = $roleResult
    ? pg_fetch_assoc($roleResult)
    : false;

if (!$adminRole) {
    fwrite(STDERR, "Run schema.sql first.\n");
    exit(1);
}

try {
    $existingUserResult = db_query_params(
        'setup_admin.check_existing',
        'SELECT u.id FROM bt_users AS u JOIN dt_user_contacts AS contact
           ON contact.user_code = u.user_code AND contact.is_login_email AND contact.is_active
         WHERE lower(contact.contact_value) = lower($1) AND u.company_id IS NULL',
        [$email]
    );
} catch (GrompilDatabaseQueryException) {
    fwrite(STDERR, "Account check failed.\n");
    exit(1);
}

if (
    $existingUserResult
    && pg_num_rows($existingUserResult) > 0
) {
    fwrite(STDERR, "The account already exists.\n");
    exit(1);
}

try {
    $createUserResult = grompil_insert_user_with_unique_code(
        static fn (string $userCode): PgSql\Result|false => db_query_params(
            'setup_admin.create',
            'INSERT INTO bt_users (
                user_code, display_name, name_first, name_last, password_hash, role_id
             ) VALUES ($1, $2, $3, $4, $5, $6)
             ON CONFLICT (user_code) DO NOTHING RETURNING user_code',
            [$userCode, $displayName, $firstName, $lastName, password_hash($password, PASSWORD_DEFAULT), (int) $adminRole['id']]
        )
    );
} catch (GrompilDatabaseQueryException) {
    $createUserResult = false;
}

if (!$createUserResult) {
    fwrite(STDERR, "Account creation failed.\n");
    exit(1);
}

$createdAdmin = pg_fetch_assoc($createUserResult);
if (!$createdAdmin || !grompil_save_user_login_email((string) $createdAdmin['user_code'], null, $email)) {
    fwrite(STDERR, "Account contact creation failed.\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Beheerder aangemaakt. Verwijder setup-variabelen.\n"
);

