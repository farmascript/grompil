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
 * @description: Manages profile details and account settings for the signed-in user.
 *
 */

declare(strict_types=1);


require GROMPIL_ROOT . '/includes/bootstrap.php';
$user = require_login();
$message = '';
$acronym = '';
$displayName = (string) ($user['display_name'] ?? '');
$result = pg_query_params(db(), 'SELECT display_name, acronym, street, house_number, box_number, postal_code, city, country_code, address_reference_id, password_hash, must_change_password FROM users WHERE id = $1', [$user['id']]);
$account = $result ? pg_fetch_assoc($result) : false;
$address = [
    'street' => (string) ($user['street'] ?? ''),
    'house_number' => (string) ($user['house_number'] ?? ''),
    'box_number' => (string) ($user['box_number'] ?? ''),
    'postal_code' => (string) ($user['postal_code'] ?? ''),
    'city' => (string) ($user['city'] ?? ''),
    'country_code' => (string) ($user['country_code'] ?? 'BE'),
    'address_reference_id' => (string) ($user['address_reference_id'] ?? ''),
];
if ($account) {
    $displayName = (string) ($account['display_name'] ?? $displayName);
    $acronym = (string) ($account['acronym'] ?? '');
    foreach (array_keys($address) as $field) {
        $address[$field] = (string) ($account[$field] ?? $address[$field]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'profile');
    if (($user['must_change_password'] ?? 'f') === 't' && $action !== 'password_change') {
        $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('password_change_required')) . '</div>';
    } elseif ($action === 'password_change') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $profileSubmitted = array_key_exists('acronym', $_POST);
        if ($profileSubmitted) {
            $displayName = trim((string) ($_POST['display_name'] ?? ''));
            $acronym = trim((string) $_POST['acronym']);
            foreach (['street', 'house_number', 'box_number', 'postal_code', 'city', 'address_reference_id'] as $field) {
                $address[$field] = trim((string) ($_POST[$field] ?? ''));
            }
            $address['country_code'] = strtoupper(trim((string) ($_POST['country_code'] ?? 'BE')));
        }
        if (!$account || !password_verify($currentPassword, (string) $account['password_hash'])) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('password_current_invalid')) . '</div>';
        } elseif (strlen($newPassword) < 12) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('password_too_short')) . '</div>';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('password_confirmation_mismatch')) . '</div>';
        } elseif (password_verify($newPassword, (string) $account['password_hash'])) {
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('password_must_be_new')) . '</div>';
        } else {
            $profileValid = true;
            if ($profileSubmitted) {
                $displayNameLength = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);
                if ($displayName === '' || $displayNameLength > 255 || preg_match('/[\x00-\x1F\x7F]/', $displayName)) {
                    $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('account_profile_invalid')) . '</div>';
                    $profileValid = false;
                }
                $acronymLength = function_exists('mb_strlen') ? mb_strlen($acronym) : strlen($acronym);
                if ($profileValid && ($acronymLength > 80 || preg_match('/[\x00-\x1F\x7F]/', $acronym))) {
                    $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_invalid')) . '</div>';
                    $profileValid = false;
                } elseif ($profileValid) {
                    $scopeResult = $user['company_id'] === null
                        ? pg_query_params(
                            db(),
                            'SELECT id FROM users
                             WHERE company_id IS NULL AND id <> $1
                               AND (($2 <> \'\' AND (lower(email) = lower($2) OR lower(acronym) = lower($2)))
                                    OR lower(acronym) = lower($3))
                             LIMIT 1',
                            [$user['id'], $acronym, $user['email']]
                        )
                        : pg_query_params(
                            db(),
                            'SELECT id FROM users
                             WHERE company_id = $1 AND id <> $2
                               AND (($3 <> \'\' AND (lower(email) = lower($3) OR lower(acronym) = lower($3)))
                                    OR lower(acronym) = lower($4))
                             LIMIT 1',
                            [$user['company_id'], $user['id'], $acronym, $user['email']]
                        );
                    if (!$scopeResult || pg_num_rows($scopeResult) > 0) {
                        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_taken')) . '</div>';
                        $profileValid = false;
                    }
                }
            }
            if ($profileValid) {
                if ($profileSubmitted) {
                    $saved = pg_query_params(
                        db(),
                        'UPDATE users SET password_hash = $1, must_change_password = FALSE,
                            display_name = $2, acronym = NULLIF($3, \'\'), street = $4, house_number = $5,
                            box_number = $6, postal_code = $7, city = $8, country_code = $9,
                            address_reference_id = $10, updated_at = now() WHERE id = $11',
                        [password_hash($newPassword, PASSWORD_DEFAULT), $displayName, $acronym,
                            $address['street'], $address['house_number'], $address['box_number'],
                            $address['postal_code'], $address['city'], $address['country_code'],
                            $address['address_reference_id'], $user['id']]
                    );
                } else {
                    $saved = pg_query_params(
                        db(),
                        'UPDATE users SET password_hash = $1, must_change_password = FALSE, updated_at = now() WHERE id = $2',
                        [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]
                    );
                }
                if ($saved) {
                    redirect('account.php?password_changed=1#change-password');
                }
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('password_change_failed')) . '</div>';
            }
        }
    } else {
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $acronym = trim((string) ($_POST['acronym'] ?? ''));
        foreach (['street', 'house_number', 'box_number', 'postal_code', 'city', 'address_reference_id'] as $field) {
            $address[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        $address['country_code'] = strtoupper(trim((string) ($_POST['country_code'] ?? 'BE')));
        $displayNameLength = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);
        $length = function_exists('mb_strlen') ? mb_strlen($acronym) : strlen($acronym);
        if ($displayName === '' || $displayNameLength > 255 || preg_match('/[\x00-\x1F\x7F]/', $displayName)) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('account_profile_invalid')) . '</div>';
        } elseif ($length > 80 || preg_match('/[\x00-\x1F\x7F]/', $acronym)) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_invalid')) . '</div>';
        } else {
            $scopeResult = $user['company_id'] === null
                ? pg_query_params(
                    db(),
                    'SELECT id FROM users
                     WHERE company_id IS NULL AND id <> $1
                       AND (($2 <> \'\' AND (lower(email) = lower($2) OR lower(acronym) = lower($2)))
                            OR lower(acronym) = lower($3))
                     LIMIT 1',
                    [$user['id'], $acronym, $user['email']]
                )
                : pg_query_params(
                    db(),
                    'SELECT id FROM users
                     WHERE company_id = $1 AND id <> $2
                       AND (($3 <> \'\' AND (lower(email) = lower($3) OR lower(acronym) = lower($3)))
                            OR lower(acronym) = lower($4))
                     LIMIT 1',
                    [$user['company_id'], $user['id'], $acronym, $user['email']]
                );
            if (!$scopeResult || pg_num_rows($scopeResult) > 0) {
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_taken')) . '</div>';
            } else {
                $saved = pg_query_params(
                    db(),
                    "UPDATE users SET display_name = $1, acronym = NULLIF($2, ''), street = $3,
                        house_number = $4, box_number = $5, postal_code = $6, city = $7,
                        country_code = $8, address_reference_id = $9, updated_at = now() WHERE id = $10",
                    [$displayName, $acronym, $address['street'], $address['house_number'], $address['box_number'], $address['postal_code'], $address['city'], $address['country_code'], $address['address_reference_id'], $user['id']]
                );
                if ($saved) {
                    $message = '<div class="alert alert-success" role="status">' . e(fn_t('acronym_saved')) . '</div>';
                } else {
                    $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_save_failed')) . '</div>';
                }
            }
        }
    }
}

if (isset($_GET['password_changed'])) {
    $message = '<div class="alert alert-success" role="status">' . e(fn_t('password_changed')) . '</div>';
} elseif (($user['must_change_password'] ?? 'f') === 't') {
    $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('password_change_required')) . '</div>';
}

page_start(fn_t('account_title'), $user);
$mustChangePassword = ($user['must_change_password'] ?? 'f') === 't';
$forcedProfileFieldsHtml = $mustChangePassword
    ? '<input type="hidden" name="country_code" value="' . e($address['country_code']) . '">'
        . '<div class="mb-3"><label class="form-label" for="setup-display-name">' . e(fn_t('display_name')) . '</label>'
        . '<input class="form-control" id="setup-display-name" name="display_name" maxlength="255" required value="' . e($displayName) . '"></div>'
        . '<div class="mb-3"><label class="form-label" for="setup-acronym">' . e(fn_t('acronym')) . '</label>'
        . '<input class="form-control" id="setup-acronym" name="acronym" maxlength="80" autocomplete="username" value="' . e($acronym) . '">'
        . '<div class="form-text">' . e(fn_t('acronym_help')) . '</div></div>'
        . fn_templateRender('address_fields.html', [
            'address_prefix' => 'account-setup-address',
            'address_country_code_value' => $address['country_code'],
            'address_reference_id_value' => $address['address_reference_id'],
            'street_reference_id_value' => '',
            'street_value' => $address['street'],
            'house_number_value' => $address['house_number'],
            'box_number_value' => $address['box_number'],
            'postal_code_value' => $address['postal_code'],
            'city_value' => $address['city'],
        ])
    : '';
echo fn_templateRender('account.html', [
    'account_title' => fn_t('account_title'),
    'account_intro' => fn_t('account_intro'),
    'acronym' => fn_t('acronym'),
    'display_name' => fn_t('display_name'),
    'display_name_value' => $displayName,
    'acronym_help' => fn_t('acronym_help'),
    'acronym_value' => $acronym,
    'acronym_save' => fn_t('save'),
    '_profile_form_hidden' => $mustChangePassword ? 'hidden' : '',
    '_forced_profile_fields_html' => $forcedProfileFieldsHtml,
    '_account_message_html' => $message,
    '_csrf_field_html' => csrf_field(),
    'country_code_value' => $address['country_code'],
    '_address_fields_html' => fn_templateRender('address_fields.html', [
        'address_prefix' => 'account-address',
        'address_country_code_value' => $address['country_code'],
        'address_reference_id_value' => $address['address_reference_id'],
        'street_reference_id_value' => '',
        'street_value' => $address['street'],
        'house_number_value' => $address['house_number'],
        'box_number_value' => $address['box_number'],
        'postal_code_value' => $address['postal_code'],
        'city_value' => $address['city'],
    ]),
    'password_section_title' => fn_t('password_section_title'),
    'password_section_help' => fn_t('password_section_help'),
    'password_current_label' => fn_t('password_current_label'),
    'password_new_label' => fn_t('password_new_label'),
    'password_confirm_label' => fn_t('password_confirm_label'),
    'password_save' => fn_t('password_save'),
    '_password_csrf_field_html' => csrf_field(),
]);
page_end();

