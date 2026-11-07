<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:15:50
 * @date_mut: Sat 2026-11-07
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Processes public customer registrations and staff requests.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:account:register
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$registerQuery = static function (string $operation, string $sql, array $params = []): PgSql\Result|false {
    try {
        return db_query_params($operation, $sql, $params);
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
};

$signedInUser = current_user();
if ($signedInUser !== null) {
    redirect_after_login($signedInUser);
}

$registrationCompany = resolve_company_slug(trim((string) ($_GET['c'] ?? '')));
if ($registrationCompany === null) {
    grompil_show_error(404, 12545, 'Open eerst de publieke website van een bedrijf om te registreren.', 'Registratie vereist een bedrijfswebsite');
}
set_database_tenant_context((int) $registrationCompany['id'], '', false);

$messageKey = '';
$messageType = 'danger';
$firstName = trim((string) ($_POST['name_first'] ?? ''));
$lastName = trim((string) ($_POST['name_last'] ?? ''));
$name = trim($firstName . ' ' . $lastName);
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$acronym = trim((string) ($_POST['acronym'] ?? ''));
$address = [
    'street' => trim((string) ($_POST['street'] ?? '')),
    'house_number' => trim((string) ($_POST['house_number'] ?? '')),
    'box_number' => trim((string) ($_POST['box_number'] ?? '')),
    'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'country_code' => strtoupper(trim((string) ($_POST['country_code'] ?? 'BE'))),
    'address_reference_id' => trim((string) ($_POST['address_reference_id'] ?? '')),
];
$accountType = (string) ($_POST['account_type'] ?? 'customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateResult = $registerQuery(
        'register.check_rate_limit',
        "SELECT count(*) AS total FROM registration_attempts
         WHERE ip_address = $1 AND attempted_at > now() - interval '1 hour'",
        [$ipAddress]
    );
    $rate = $rateResult ? pg_fetch_assoc($rateResult) : false;
    $registerQuery('register.record_attempt', 'INSERT INTO registration_attempts (ip_address) VALUES ($1)', [$ipAddress]);
    $valid = in_array($accountType, ['customer', 'staff'], true)
        && $firstName !== '' && $lastName !== ''
        && (function_exists('mb_strlen') ? mb_strlen($firstName) : strlen($firstName)) <= 120
        && (function_exists('mb_strlen') ? mb_strlen($lastName) : strlen($lastName)) <= 120
        && (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) <= 255
        && filter_var($email, FILTER_VALIDATE_EMAIL)
        && strlen($email) <= 254
        && (function_exists('mb_strlen') ? mb_strlen($acronym) : strlen($acronym)) <= 80
        && (function_exists('mb_strlen') ? mb_strlen($address['street']) : strlen($address['street'])) <= 160
        && strlen($address['house_number']) <= 32 && strlen($address['box_number']) <= 32
        && strlen($address['postal_code']) <= 20
        && (function_exists('mb_strlen') ? mb_strlen($address['city']) : strlen($address['city'])) <= 100
        && preg_match('/^[A-Z]{2}$/', $address['country_code']) === 1
        && !preg_match('/[\x00-\x1F\x7F]/', $acronym)
        && strlen((string) ($_POST['password'] ?? '')) >= 12
        && strlen((string) ($_POST['password'] ?? '')) <= 4096
        && hash_equals((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));

    if (!$rate || (int) $rate['total'] >= 5) {
        $messageKey = 'register_rate_limited';
    } elseif (!$valid) {
        $messageKey = 'register_invalid';
    } else {
        $conflictResult = $registerQuery(
            'register.check_conflict',
            'SELECT id FROM users
             WHERE company_id = $1
               AND (($2 <> \'\' AND (lower(acronym) = lower($2) OR lower(email) = lower($2)))
                    OR lower(acronym) = lower($3))
             LIMIT 1',
            [(int) $registrationCompany['id'], $acronym, $email]
        );
        $acronymConflict = $conflictResult && pg_num_rows($conflictResult) > 0;
        $globalRoleResult = $registerQuery('register.find_global_role', "SELECT id FROM roles WHERE lower(name) = 'publiek' ORDER BY id LIMIT 1");
        $globalRole = $globalRoleResult ? pg_fetch_assoc($globalRoleResult) : false;
        $companyRoleName = $accountType === 'customer' ? 'Publiek' : 'Medewerker';
        $companyRoleResult = $registerQuery(
            'register.find_company_role',
            'SELECT id FROM company_roles WHERE company_id = $1 AND lower(name) = lower($2) ORDER BY id LIMIT 1',
            [(int) $registrationCompany['id'], $companyRoleName]
        );
        $companyRole = $companyRoleResult ? pg_fetch_assoc($companyRoleResult) : false;
        if ($acronymConflict) {
            $messageKey = 'register_exists';
        } elseif ($globalRole && $companyRole && $registerQuery('register.begin', 'BEGIN')) {
            $insertUserParams = [
                $email,
                $name,
                $firstName,
                $lastName,
                $address['street'],
                $address['house_number'],
                $address['box_number'],
                $address['postal_code'],
                $address['city'],
                $address['country_code'],
                $address['address_reference_id'],
                $acronym,
                password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                (int) $globalRole['id'],
                $accountType === 'customer' ? 'approved' : 'pending',
                (int) $registrationCompany['id'],
            ];
            $insertResult = grompil_insert_user_with_unique_code(
                static fn (string $userCode): PgSql\Result|false => $registerQuery(
                    'register.insert_user',
                    "INSERT INTO users (email, display_name, name_first, name_last, street, house_number, box_number, postal_code, city, country_code, address_reference_id, acronym, password_hash, role_id, is_active, approval_status, company_id, user_code)
                     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NULLIF($12, ''), $13, $14, TRUE, $15, $16, $17)
                     ON CONFLICT DO NOTHING RETURNING user_code",
                    [...$insertUserParams, $userCode]
                )
            );
            $createdUser = $insertResult ? pg_fetch_assoc($insertResult) : false;
            if ($createdUser) {
                $customer = $accountType === 'customer';
                $membershipResult = $registerQuery(
                    'register.insert_membership',
                    "INSERT INTO company_users (
                        company_id, user_code, company_role_id, is_active,
                        approval_status, approved_at
                     ) VALUES ($1, $2, $3, $4, $5, CASE WHEN $4 THEN now() ELSE NULL END)",
                    [
                        (int) $registrationCompany['id'],
                        (string) $createdUser['user_code'],
                        (int) $companyRole['id'],
                        $customer ? 'true' : 'false',
                        $customer ? 'approved' : 'pending',
                    ]
                );
                if ($membershipResult && $registerQuery('register.commit', 'COMMIT')) {
                    $messageKey = $customer ? 'register_success_customer' : 'register_success_staff';
                    $messageType = 'success';
                    if ($customer) {
                        try {
                            require_once GROMPIL_ROOT . '/includes/mailer.php';
                            require_once GROMPIL_ROOT . '/includes/password_reset.php';
                            $mailLanguage = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
                            if (!in_array($mailLanguage, ['NL', 'FR', 'EN', 'DE'], true)) {
                                $mailLanguage = 'NL';
                            }
                            $companyName = (string) $registrationCompany['name'];
                            $loginUrl = password_reset_base_url() . 'login.php?' . http_build_query([
                                'c' => (string) $registrationCompany['slug'],
                                'lang' => $mailLanguage,
                            ], '', '&', PHP_QUERY_RFC3986);
                            $welcomeHtml = fn_templateRender('welcome_customer_email.html', [
                                'html_language' => strtolower($mailLanguage),
                                'company_name' => $companyName,
                                'customer_name' => $name,
                                'login_url' => $loginUrl,
                            ]);
                            $welcomeText = fn_t('welcome_customer_greeting') . ' ' . $name . ",\n\n"
                                . fn_t('welcome_customer_intro') . "\n\n"
                                . fn_t('welcome_customer_login_help') . ' ' . $companyName . ".\n"
                                . $loginUrl . "\n\n"
                                . fn_t('welcome_customer_footer');
                            grompil_send_mail(
                                $email,
                                fn_t('welcome_customer_subject') . ' · ' . $companyName,
                                $welcomeHtml,
                                $welcomeText
                            );
                        } catch (Throwable $exception) {
                            error_log('Customer welcome email could not be sent.');
                        }
                    }
                } else {
                    $registerQuery('register.rollback_membership_failure', 'ROLLBACK');
                    grompil_show_error(500, 50631, 'Registratie is tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'Registratie niet beschikbaar');
                }
            } else {
                $registerQuery('register.rollback_user_failure', 'ROLLBACK');
                $messageKey = 'register_exists';
            }
        } else {
            grompil_show_error(500, 50631, 'Registratie is tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'Registratie niet beschikbaar');
        }
    }
}

$message = $messageKey === '' ? ''
    : '<div class="alert alert-' . e($messageType) . '" role="status">' . e(fn_t($messageKey)) . '</div>';
echo fn_templateRender('registration.html', [
    '_registration_content_hidden' => $messageType === 'success' ? 'hidden' : '',
    '_customer_checked' => $accountType === 'customer' ? 'checked' : '',
    '_staff_checked' => $accountType === 'staff' ? 'checked' : '',
    '_customer_login_cta_html' => $messageType === 'success' && $accountType === 'customer'
        ? '<a class="btn btn-grompil btn-lg w-100 mt-3" href="' . e('login.php?c=' . rawurlencode((string) $registrationCompany['slug'])) . '">' . e(fn_t('login_title')) . '</a>'
        : '',
    'display_name_value' => $name,
    'name_first_value' => $firstName,
    'name_last_value' => $lastName,
    'email_value' => $email,
    'acronym_value' => $acronym,
    '_message_html' => $message,
    '_csrf_field_html' => csrf_field(),
    '_cookie_footer_html' => fn_templateRender('cookie_footer.html'),
    '_address_fields_html' => fn_templateRender('address_fields.html', [
        'address_prefix' => 'register-address',
        'address_country_code_value' => $address['country_code'],
        'address_reference_id_value' => $address['address_reference_id'],
        'street_reference_id_value' => '',
        'street_value' => $address['street'],
        'house_number_value' => $address['house_number'],
        'box_number_value' => $address['box_number'],
        'postal_code_value' => $address['postal_code'],
        'city_value' => $address['city'],
    ]),
    'company_slug' => (string) $registrationCompany['slug'],
    'login_link' => 'login.php?c=' . rawurlencode((string) $registrationCompany['slug']),
    '_language_selector_html' => fn_languageSelector(),
]);

