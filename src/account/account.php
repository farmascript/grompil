<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Sat 2026-11-07 18:00:02
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages profile details and account settings for the signed-in user.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:account:account
 */

declare(strict_types=1);


require GROMPIL_ROOT . '/includes/bootstrap.php';
$accountQuery = static function (string $operation, string $sql, array $params = []): PgSql\Result|false {
    try {
        return db_query_params($operation, $sql, $params);
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
};
$user = require_login();
$message = '';
$acronym = '';
$displayName = (string) ($user['display_name'] ?? '');
$result = $accountQuery('account.load', 'SELECT display_name, acronym, password_hash, must_change_password FROM bt_users WHERE user_code = $1', [$user['user_code']]);
$account = $result ? pg_fetch_assoc($result) : false;
$address = grompil_user_primary_address((string) $user['user_code']);
$userCode = (string) $user['user_code'];
$addressTypes = dd_varia_get_options('address_types', '_root');
$addressTypeLabels = [];
foreach ($addressTypes as $addressType) {
    $addressTypeLabels[(string) $addressType['key']] = (string) $addressType['label'];
}
$loadUserAddresses = static function () use ($accountQuery, $userCode): array {
    $result = $accountQuery(
        'account.addresses.list',
        'SELECT id, address_type_key, label, street, house_number, box_number,
                postal_code, city, country_code, address_reference_id,
                is_primary, sort_order
         FROM dt_user_locations
         WHERE user_code = $1 AND is_active
         ORDER BY sort_order, id',
        [$userCode]
    );
    $rows = [];
    while ($result && $row = pg_fetch_assoc($result)) {
        $row['id'] = (int) $row['id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_primary'] = $row['is_primary'] === 't';
        $rows[] = $row;
    }
    return $rows;
};
$addressRows = $loadUserAddresses();
$addressModalOpen = false;
$addressFormOpen = false;
$addressFormMessage = '';
$addressFormValues = [
    'id' => 0, 'address_type_key' => 'residential', 'label' => '', 'street' => '',
    'house_number' => '', 'box_number' => '', 'postal_code' => '', 'city' => '',
    'country_code' => 'BE', 'address_reference_id' => '', 'sort_order' => 0,
    'is_primary' => false,
];
$editAddressId = filter_var($_GET['edit_address'] ?? null, FILTER_VALIDATE_INT);
if ($editAddressId !== false && $editAddressId !== null && $editAddressId > 0) {
    foreach ($addressRows as $row) {
        if ($row['id'] === $editAddressId) {
            $addressFormValues = array_merge($addressFormValues, $row);
            $addressFormValues['is_primary'] = $row['is_primary'];
            $addressModalOpen = true;
            $addressFormOpen = true;
            break;
        }
    }
}
if (isset($_GET['add_address'])) {
    $addressModalOpen = true;
    $addressFormOpen = true;
    $addressFormValues['sort_order'] = (count($addressRows) + 1) * 10;
}
$selectedAddressId = filter_var($_GET['address_id'] ?? null, FILTER_VALIDATE_INT);
$selectedAddress = null;
if ($selectedAddressId !== false && $selectedAddressId !== null && $selectedAddressId > 0) {
    foreach ($addressRows as $row) {
        if ($row['id'] === $selectedAddressId) {
            $selectedAddress = $row;
            break;
        }
    }
}
if ($selectedAddress === null) {
    foreach ($addressRows as $row) {
        if ($row['is_primary']) {
            $selectedAddress = $row;
            break;
        }
    }
}
if ($selectedAddress === null && $addressRows !== []) {
    $selectedAddress = $addressRows[0];
}
$selectedAddressPosition = 0;
foreach ($addressRows as $index => $row) {
    if ($selectedAddress !== null && $row['id'] === $selectedAddress['id']) {
        $selectedAddressPosition = $index + 1;
        break;
    }
}
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
    if (str_starts_with($action, 'address_')) {
        $addressModalOpen = true;
        $id = filter_var($_POST['address_id'] ?? 0, FILTER_VALIDATE_INT);
        $addressId = $id === false ? 0 : (int) $id;
        if ($action === 'address_save') {
            $addressFormOpen = true;
            $addressFormValues = [
                'id' => $addressId,
                'address_type_key' => trim((string) ($_POST['address_type_key'] ?? '')),
                'label' => trim((string) ($_POST['label'] ?? '')),
                'street' => trim((string) ($_POST['street'] ?? '')),
                'house_number' => trim((string) ($_POST['house_number'] ?? '')),
                'box_number' => trim((string) ($_POST['box_number'] ?? '')),
                'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
                'city' => trim((string) ($_POST['city'] ?? '')),
                'country_code' => strtoupper(trim((string) ($_POST['country_code'] ?? 'BE'))),
                'address_reference_id' => trim((string) ($_POST['address_reference_id'] ?? '')),
                'sort_order' => filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT),
                'is_primary' => isset($_POST['is_primary']),
            ];
            $addressFormValues['sort_order'] = $addressFormValues['sort_order'] === false
                ? 0 : (int) $addressFormValues['sort_order'];
            $streetLength = function_exists('mb_strlen')
                ? mb_strlen($addressFormValues['street']) : strlen($addressFormValues['street']);
            $cityLength = function_exists('mb_strlen')
                ? mb_strlen($addressFormValues['city']) : strlen($addressFormValues['city']);
            $addressValid = isset($addressTypeLabels[$addressFormValues['address_type_key']])
                && $addressFormValues['label'] !== ''
                && strlen($addressFormValues['label']) <= 160
                && $streetLength <= 160
                && strlen($addressFormValues['house_number']) <= 32
                && strlen($addressFormValues['box_number']) <= 32
                && strlen($addressFormValues['postal_code']) <= 20
                && $cityLength <= 100
                && preg_match('/^[A-Z]{2}$/', $addressFormValues['country_code']) === 1
                && $addressFormValues['sort_order'] >= 0
                && $addressFormValues['sort_order'] <= 100000
                && ($addressFormValues['street'] !== '' || $addressFormValues['postal_code'] !== '' || $addressFormValues['city'] !== '');
            if (!$addressValid) {
                $addressFormMessage = '<div class="alert alert-warning" role="status">'
                    . e(fn_t('account_address_invalid')) . '</div>';
            } else {
                $transaction = $accountQuery('account.address_save.begin', 'BEGIN');
                $existingPrimary = $accountQuery(
                    'account.address_save.has_primary',
                    'SELECT id FROM dt_user_locations WHERE user_code = $1 AND is_active AND is_primary LIMIT 1',
                    [$userCode]
                );
                $editingPrimary = false;
                foreach ($addressRows as $existingAddress) {
                    if ($existingAddress['id'] === $addressId && $existingAddress['is_primary']) {
                        $editingPrimary = true;
                        break;
                    }
                }
                $makePrimary = $addressFormValues['is_primary'] || $editingPrimary
                    || !$existingPrimary || pg_num_rows($existingPrimary) === 0;
                $primaryCleared = true;
                if ($transaction && $makePrimary) {
                    $primaryCleared = (bool) $accountQuery('account.address_save.clear_primary',
                        'UPDATE dt_user_locations SET is_primary = FALSE, updated_at = now()
                         WHERE user_code = $1 AND is_active AND is_primary', [$userCode]);
                }
                if ($transaction && $existingPrimary && $primaryCleared && $addressId > 0) {
                    $savedAddress = $accountQuery(
                        'account.address_update',
                        'UPDATE dt_user_locations
                         SET address_type_key = $1, label = $2, street = $3, house_number = $4,
                             box_number = $5, postal_code = $6, city = $7, country_code = $8,
                             address_reference_id = $9, sort_order = $10, is_primary = $11,
                             is_active = TRUE, updated_at = now()
                         WHERE id = $12 AND user_code = $13 AND is_active
                         RETURNING id',
                        [
                            $addressFormValues['address_type_key'], $addressFormValues['label'],
                            $addressFormValues['street'], $addressFormValues['house_number'],
                            $addressFormValues['box_number'], $addressFormValues['postal_code'],
                            $addressFormValues['city'], $addressFormValues['country_code'],
                            $addressFormValues['address_reference_id'], $addressFormValues['sort_order'],
                            $makePrimary ? 'true' : 'false', $addressId, $userCode,
                        ]
                    );
                    $savedAddress = $savedAddress && pg_affected_rows($savedAddress) === 1
                        ? $savedAddress : false;
                } elseif ($transaction && $existingPrimary && $primaryCleared) {
                    $savedAddress = $accountQuery(
                        'account.address_insert',
                        'INSERT INTO dt_user_locations (
                            user_code, company_id, address_type_key, label, street, house_number, box_number,
                            postal_code, city, country_code, address_reference_id, sort_order, is_primary, is_active
                         ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, TRUE)
                         RETURNING id',
                        [
                            $userCode, $user['company_id'], $addressFormValues['address_type_key'],
                            $addressFormValues['label'], $addressFormValues['street'],
                            $addressFormValues['house_number'], $addressFormValues['box_number'],
                            $addressFormValues['postal_code'], $addressFormValues['city'],
                            $addressFormValues['country_code'], $addressFormValues['address_reference_id'],
                            $addressFormValues['sort_order'], $makePrimary ? 'true' : 'false',
                        ]
                    );
                } else {
                    $savedAddress = false;
                }
                $savedId = $savedAddress ? (int) (pg_fetch_assoc($savedAddress)['id'] ?? 0) : 0;
                $saved = $savedAddress && $savedId > 0
                    && $accountQuery('account.address_save.commit', 'COMMIT');
                if (!$saved && $transaction) {
                    $accountQuery('account.address_save.rollback', 'ROLLBACK');
                }
                if ($saved) {
                    redirect('account.php#addresses');
                }
                $addressFormMessage = '<div class="alert alert-danger" role="alert">'
                    . e(fn_t('account_address_save_failed')) . '</div>';
            }
        } elseif ($action === 'address_set_primary' && $addressId > 0) {
            $transaction = $accountQuery('account.address_primary.begin', 'BEGIN');
            $clear = $transaction ? $accountQuery(
                'account.address_primary.clear',
                'UPDATE dt_user_locations SET is_primary = FALSE, updated_at = now()
                 WHERE user_code = $1 AND is_active', [$userCode]
            ) : false;
            $selected = $clear ? $accountQuery(
                'account.address_primary.set',
                'UPDATE dt_user_locations SET is_primary = TRUE, updated_at = now()
                 WHERE id = $1 AND user_code = $2 AND is_active RETURNING id', [$addressId, $userCode]
            ) : false;
            $saved = $selected && pg_affected_rows($selected) === 1
                && $accountQuery('account.address_primary.commit', 'COMMIT');
            if (!$saved && $transaction) {
                $accountQuery('account.address_primary.rollback', 'ROLLBACK');
            }
            if ($saved) {
                redirect('account.php?address_id=' . $addressId . '#addresses');
            }
            $message = '<div class="alert alert-danger" role="alert">'
                . e(fn_t('account_address_save_failed')) . '</div>';
        } elseif ($action === 'address_deactivate' && $addressId > 0) {
            $transaction = $accountQuery('account.address_deactivate.begin', 'BEGIN');
            $wasPrimaryResult = $transaction ? $accountQuery(
                'account.address_deactivate.load',
                'SELECT is_primary FROM dt_user_locations WHERE id = $1 AND user_code = $2 AND is_active',
                [$addressId, $userCode]
            ) : false;
            $wasPrimaryRow = $wasPrimaryResult ? pg_fetch_assoc($wasPrimaryResult) : false;
            $deactivated = $wasPrimaryRow ? $accountQuery(
                'account.address_deactivate.update',
                'UPDATE dt_user_locations SET is_active = FALSE, is_primary = FALSE, updated_at = now()
                 WHERE id = $1 AND user_code = $2 AND is_active',
                [$addressId, $userCode]
            ) : false;
            $fallback = true;
            if ($deactivated && $wasPrimaryRow['is_primary'] === 't') {
                $next = $accountQuery(
                    'account.address_deactivate.next',
                    'SELECT id FROM dt_user_locations WHERE user_code = $1 AND is_active
                     ORDER BY sort_order, id LIMIT 1', [$userCode]
                );
                $nextRow = $next ? pg_fetch_assoc($next) : false;
                $fallback = !$nextRow || (bool) $accountQuery(
                    'account.address_deactivate.set_fallback',
                    'UPDATE dt_user_locations SET is_primary = TRUE, updated_at = now()
                     WHERE id = $1 AND user_code = $2 AND is_active', [(int) $nextRow['id'], $userCode]
                );
            }
            $saved = $deactivated && $fallback
                && $accountQuery('account.address_deactivate.commit', 'COMMIT');
            if (!$saved && $transaction) {
                $accountQuery('account.address_deactivate.rollback', 'ROLLBACK');
            }
            if ($saved) {
                redirect('account.php#addresses');
            }
            $message = '<div class="alert alert-danger" role="alert">'
                . e(fn_t('account_address_save_failed')) . '</div>';
        }
        $addressRows = $loadUserAddresses();
    } elseif (($user['must_change_password'] ?? 'f') === 't' && $action !== 'password_change') {
        $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('password_change_required')) . '</div>';
    } elseif ($action === 'password_change') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $profileSubmitted = array_key_exists('acronym', $_POST);
        $profileAddressSubmitted = array_key_exists('street', $_POST);
        if ($profileSubmitted) {
            $displayName = trim((string) ($_POST['display_name'] ?? ''));
            $acronym = trim((string) $_POST['acronym']);
            if ($profileAddressSubmitted) {
                foreach (['street', 'house_number', 'box_number', 'postal_code', 'city', 'address_reference_id'] as $field) {
                    $address[$field] = trim((string) ($_POST[$field] ?? ''));
                }
                $address['country_code'] = strtoupper(trim((string) ($_POST['country_code'] ?? 'BE')));
            }
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
                        ? $accountQuery(
                            'account.check_acronym_platform',
                            'SELECT user_code FROM bt_users AS other
                             WHERE company_id IS NULL AND user_code <> $1
                               AND (($2 <> \'\' AND (EXISTS (
                                       SELECT 1 FROM dt_user_contacts contact
                                       WHERE contact.user_code = other.user_code AND contact.is_login_email
                                         AND contact.is_active AND lower(contact.contact_value) = lower($2)
                                   ) OR lower(acronym) = lower($2)))
                                    OR lower(acronym) = lower($3))
                             LIMIT 1',
                            [$user['user_code'], $acronym, $user['email']]
                        )
                        : $accountQuery(
                            'account.check_acronym_company',
                            'SELECT user_code FROM bt_users AS other
                             WHERE company_id = $1 AND user_code <> $2
                               AND (($3 <> \'\' AND (EXISTS (
                                       SELECT 1 FROM dt_user_contacts contact
                                       WHERE contact.user_code = other.user_code AND contact.is_login_email
                                         AND contact.is_active AND lower(contact.contact_value) = lower($3)
                                   ) OR lower(acronym) = lower($3)))
                                    OR lower(acronym) = lower($4))
                             LIMIT 1',
                            [$user['company_id'], $user['user_code'], $acronym, $user['email']]
                        );
                    if (!$scopeResult || pg_num_rows($scopeResult) > 0) {
                        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_taken')) . '</div>';
                        $profileValid = false;
                    }
                }
            }
            if ($profileValid) {
                if ($profileSubmitted) {
                    $transaction = $accountQuery('account.change_password_and_profile.begin', 'BEGIN');
                    $saved = $transaction ? $accountQuery(
                        'account.change_password_and_profile',
                        'UPDATE bt_users SET password_hash = $1, must_change_password = FALSE,
                            display_name = $2, acronym = NULLIF($3, \'\'), updated_at = now()
                         WHERE user_code = $4',
                        [password_hash($newPassword, PASSWORD_DEFAULT), $displayName, $acronym, $user['user_code']]
                    ) : false;
                    $saved = $saved && (!$profileAddressSubmitted || grompil_save_user_primary_address(
                        (string) $user['user_code'], $user['company_id'] === null ? null : (int) $user['company_id'], $address
                    ));
                    $saved = $saved && $accountQuery('account.change_password_and_profile.commit', 'COMMIT');
                    if (!$saved && $transaction) {
                        $accountQuery('account.change_password_and_profile.rollback', 'ROLLBACK');
                    }
                } else {
                    $saved = $accountQuery(
                        'account.change_password',
                        'UPDATE bt_users SET password_hash = $1, must_change_password = FALSE, updated_at = now() WHERE user_code = $2',
                        [password_hash($newPassword, PASSWORD_DEFAULT), $user['user_code']]
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
        $displayNameLength = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);
        $length = function_exists('mb_strlen') ? mb_strlen($acronym) : strlen($acronym);
        if ($displayName === '' || $displayNameLength > 255 || preg_match('/[\x00-\x1F\x7F]/', $displayName)) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('account_profile_invalid')) . '</div>';
        } elseif ($length > 80 || preg_match('/[\x00-\x1F\x7F]/', $acronym)) {
            $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_invalid')) . '</div>';
        } else {
            $scopeResult = $user['company_id'] === null
                ? $accountQuery(
                    'account.check_acronym_platform',
                            'SELECT user_code FROM bt_users AS other
                     WHERE company_id IS NULL AND user_code <> $1
                               AND (($2 <> \'\' AND (EXISTS (
                                       SELECT 1 FROM dt_user_contacts contact
                                       WHERE contact.user_code = other.user_code AND contact.is_login_email
                                         AND contact.is_active AND lower(contact.contact_value) = lower($2)
                                   ) OR lower(acronym) = lower($2)))
                            OR lower(acronym) = lower($3))
                     LIMIT 1',
                    [$user['user_code'], $acronym, $user['email']]
                )
                : $accountQuery(
                    'account.check_acronym_company',
                        'SELECT user_code FROM bt_users AS other
                     WHERE company_id = $1 AND user_code <> $2
                           AND (($3 <> \'\' AND (EXISTS (
                                   SELECT 1 FROM dt_user_contacts contact
                                   WHERE contact.user_code = other.user_code AND contact.is_login_email
                                     AND contact.is_active AND lower(contact.contact_value) = lower($3)
                               ) OR lower(acronym) = lower($3)))
                            OR lower(acronym) = lower($4))
                     LIMIT 1',
                    [$user['company_id'], $user['user_code'], $acronym, $user['email']]
                );
            if (!$scopeResult || pg_num_rows($scopeResult) > 0) {
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('acronym_taken')) . '</div>';
            } else {
                $transaction = $accountQuery('account.save_profile.begin', 'BEGIN');
                $saved = $transaction ? $accountQuery(
                    'account.save_profile',
                    "UPDATE bt_users SET display_name = $1, acronym = NULLIF($2, ''), updated_at = now()
                     WHERE user_code = $3",
                    [$displayName, $acronym, $user['user_code']]
                ) : false;
                $saved = $saved && $accountQuery('account.save_profile.commit', 'COMMIT');
                if (!$saved && $transaction) {
                    $accountQuery('account.save_profile.rollback', 'ROLLBACK');
                }
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
            'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
            'address_lookup_no_results' => fn_t('address_lookup_no_results'),
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
$addressTypeOptionsHtml = '';
foreach ($addressTypes as $addressType) {
    $typeKey = (string) $addressType['key'];
    $selected = $typeKey === (string) $addressFormValues['address_type_key'] ? ' selected' : '';
    $addressTypeOptionsHtml .= '<option value="' . e($typeKey) . '"' . $selected . '>'
        . e((string) $addressType['label']) . '</option>';
}
$addressListHtml = '';
$editingAddressId = $addressFormOpen && (int) $addressFormValues['id'] > 0
    ? (int) $addressFormValues['id']
    : 0;
foreach ($addressRows as $index => $addressRow) {
    if ($editingAddressId > 0 && (int) $addressRow['id'] !== $editingAddressId) {
        continue;
    }
    $addressText = implode(', ', array_filter([
        trim((string) $addressRow['street'] . ' ' . (string) $addressRow['house_number']
            . ($addressRow['box_number'] !== '' ? ' ' . fn_t('address_box_number') . ' ' . $addressRow['box_number'] : '')),
        trim((string) $addressRow['postal_code'] . ' ' . (string) $addressRow['city']),
        (string) $addressRow['country_code'],
    ], static fn(string $part): bool => $part !== ''));
    $typeLabel = $addressTypeLabels[(string) $addressRow['address_type_key']]
        ?? (string) $addressRow['address_type_key'];
    $isSelected = $selectedAddress !== null && $selectedAddress['id'] === $addressRow['id'];
    $addressListHtml .= '<article class="account-address-card border rounded p-3 mb-2">'
        . '<div class="d-flex flex-wrap justify-content-between gap-2">'
        . '<div><strong>' . e($addressRow['label'] !== '' ? (string) $addressRow['label'] : $typeLabel) . '</strong>'
        . ' <span class="text-secondary">(' . e($typeLabel) . ')</span>'
        . ($addressRow['is_primary'] ? ' <span class="badge text-bg-success">' . e(fn_t('account_address_default')) . '</span>' : '')
        . ($isSelected ? ' <span class="badge text-bg-secondary">' . e(fn_t('account_address_displayed')) . '</span>' : '')
        . '<div>' . e($addressText) . '</div><div class="small text-secondary">'
        . e(fn_t('account_address_order')) . ': ' . (int) $addressRow['sort_order'] . '</div></div></div>'
        . '<div class="account-address-actions d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">'
        . '<a class="btn btn-sm btn-outline-secondary" href="account.php?address_id=' . (int) $addressRow['id'] . '#addresses">'
        . e(fn_t('account_address_show')) . '</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="account.php?edit_address=' . (int) $addressRow['id'] . '#addresses">'
        . e(fn_t('account_address_edit')) . '</a>'
        . (!$addressRow['is_primary'] ? '<form method="post" action="account.php#addresses">' . csrf_field()
            . '<input type="hidden" name="action" value="address_set_primary">'
            . '<input type="hidden" name="address_id" value="' . (int) $addressRow['id'] . '">'
            . '<button class="btn btn-sm btn-outline-success" type="submit">' . e(fn_t('account_address_make_default')) . '</button></form>' : '')
        . '<form method="post" action="account.php#addresses">' . csrf_field()
        . '<input type="hidden" name="action" value="address_deactivate">'
        . '<input type="hidden" name="address_id" value="' . (int) $addressRow['id'] . '">'
        . '<button class="btn btn-sm btn-outline-danger" type="submit">' . e(fn_t('account_address_remove')) . '</button></form>'
        . '</div></article>';
}
if ($addressListHtml === '') {
    $addressListHtml = '<p class="text-secondary">' . e(fn_t('account_address_none')) . '</p>';
}
$selectedAddressHtml = '';
if ($selectedAddress !== null) {
    $selectedAddressText = implode(', ', array_filter([
        trim((string) $selectedAddress['street'] . ' ' . (string) $selectedAddress['house_number']
            . ($selectedAddress['box_number'] !== '' ? ' ' . fn_t('address_box_number') . ' ' . $selectedAddress['box_number'] : '')),
        trim((string) $selectedAddress['postal_code'] . ' ' . (string) $selectedAddress['city']),
        (string) $selectedAddress['country_code'],
    ], static fn(string $part): bool => $part !== ''));
    $selectedTypeLabel = $addressTypeLabels[(string) $selectedAddress['address_type_key']]
        ?? (string) $selectedAddress['address_type_key'];
    $selectedAddressHtml = '<div class="mt-2">'
        . '<strong>' . e($selectedAddress['label'] !== '' ? (string) $selectedAddress['label'] : $selectedTypeLabel) . '</strong>'
        . ' <span class="text-secondary">(' . e($selectedTypeLabel) . ')</span><br>'
        . e($selectedAddressText) . '</div>';
}
$addressModalHtml = '<div class="modal fade" id="account-address-modal" tabindex="-1" aria-labelledby="account-address-modal-title"'
    . ' aria-hidden="true" data-auto-open="' . ($addressModalOpen ? 'true' : 'false') . '">'
    . '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">'
    . '<div class="modal-header"><h2 class="modal-title fs-5" id="account-address-modal-title">'
    . e(fn_t('account_addresses_title')) . '</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'
    . e(fn_t('close')) . '"></button></div><div class="modal-body">'
    . '<p>' . e(fn_t('account_address_count')) . ': ' . count($addressRows) . '</p>'
    . $addressFormMessage . $addressListHtml
    . '<details' . ($addressFormOpen ? ' open' : '') . ' class="border rounded p-3 mt-3"><summary class="fw-semibold">'
    . e($addressFormValues['id'] > 0 ? fn_t('account_address_edit') : fn_t('account_address_add'))
    . '</summary><form method="post" action="account.php#addresses" class="mt-3">' . csrf_field()
    . '<input type="hidden" name="action" value="address_save"><input type="hidden" name="address_id" value="' . (int) $addressFormValues['id'] . '">'
    . '<input type="hidden" name="country_code" value="' . e((string) $addressFormValues['country_code']) . '">'
    . '<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="account-address-type">'
    . e(fn_t('account_address_type')) . '</label><select class="form-select" id="account-address-type" name="address_type_key" required>'
    . $addressTypeOptionsHtml . '</select></div><div class="col-md-6"><label class="form-label" for="account-address-label">'
    . e(fn_t('account_address_label')) . '</label><input class="form-control" id="account-address-label" name="label" maxlength="160" required value="'
    . e((string) $addressFormValues['label']) . '"></div></div>'
    . fn_templateRender('address_fields.html', [
        'address_prefix' => 'account-address-manager',
        'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
        'address_lookup_no_results' => fn_t('address_lookup_no_results'),
        'address_country_code_value' => $addressFormValues['country_code'],
        'address_reference_id_value' => $addressFormValues['address_reference_id'],
        'street_reference_id_value' => '', 'street_value' => $addressFormValues['street'],
        'house_number_value' => $addressFormValues['house_number'], 'box_number_value' => $addressFormValues['box_number'],
        'postal_code_value' => $addressFormValues['postal_code'], 'city_value' => $addressFormValues['city'],
    ])
    . '<div class="row g-3 align-items-end mt-1"><div class="col-md-4"><label class="form-label" for="account-address-order">'
    . e(fn_t('account_address_order')) . '</label><input class="form-control" type="number" min="0" max="100000" id="account-address-order" name="sort_order" value="'
    . (int) $addressFormValues['sort_order'] . '"></div><div class="col-md-8 form-check mb-2">'
    . '<input class="form-check-input" type="checkbox" id="account-address-primary" name="is_primary"'
    . ($addressFormValues['is_primary'] ? ' checked' : '') . '><label class="form-check-label" for="account-address-primary">'
    . e(fn_t('account_address_make_default')) . '</label></div></div><button class="btn btn-grompil mt-3" type="submit">'
    . e(fn_t('save')) . '</button></form></details></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">'
    . e(fn_t('close')) . '</button></div></div></div></div>';
$addressManagerHtml = '<section id="addresses" class="border rounded p-3 mb-4"><div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">'
    . '<div><h2 class="h5 mb-1">' . e(fn_t('account_addresses_title')) . '</h2><div class="small text-secondary">'
    . e(sprintf(fn_t('account_address_position'), $selectedAddress !== null ? $selectedAddressPosition : 0, count($addressRows)))
    . ($selectedAddress !== null && $selectedAddress['is_primary'] ? ' · ' . e(fn_t('account_address_default')) : '')
    . $selectedAddressHtml . '</div></div><div class="d-flex gap-2">'
    . '<button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#account-address-modal">'
    . e(fn_t('account_addresses_manage')) . ' (' . count($addressRows) . ')</button>'
    . '<a class="btn btn-grompil" href="account.php?add_address=1#addresses">' . e(fn_t('account_address_add')) . '</a>'
    . '</div></div></section>' . $addressModalHtml;
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
    '_address_fields_html' => '',
    '_address_manager_html' => $addressManagerHtml,
    'password_section_title' => fn_t('password_section_title'),
    'password_section_help' => fn_t('password_section_help'),
    'password_current_label' => fn_t('password_current_label'),
    'password_new_label' => fn_t('password_new_label'),
    'password_confirm_label' => fn_t('password_confirm_label'),
    'password_save' => fn_t('password_save'),
    '_password_csrf_field_html' => csrf_field(),
]);
page_end();

