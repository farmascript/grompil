<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:57
 * @date_mut: Wed 2026-11-11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages user accounts through platform administration.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:users:users
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('users.php');
$messageKey = '';
$newUserFirstName = trim((string) ($_POST['name_first'] ?? ''));
$newUserLastName = trim((string) ($_POST['name_last'] ?? ''));
$newUserName = trim($newUserFirstName . ' ' . $newUserLastName);
$newUserEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
$newUserAddress = [
    'street' => trim((string) ($_POST['street'] ?? '')),
    'house_number' => trim((string) ($_POST['house_number'] ?? '')),
    'box_number' => trim((string) ($_POST['box_number'] ?? '')),
    'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'country_code' => strtoupper(trim((string) ($_POST['country_code'] ?? 'BE'))),
    'address_reference_id' => trim((string) ($_POST['address_reference_id'] ?? '')),
    'lambert72_x' => grompil_address_coordinate($_POST['lambert72_x'] ?? '', -1000000, 1000000, 3) ?? '',
    'lambert72_y' => grompil_address_coordinate($_POST['lambert72_y'] ?? '', -1000000, 1000000, 3) ?? '',
    'wgs84_latitude' => grompil_address_coordinate($_POST['wgs84_latitude'] ?? '', -90, 90, 7) ?? '',
    'wgs84_longitude' => grompil_address_coordinate($_POST['wgs84_longitude'] ?? '', -180, 180, 7) ?? '',
];
$selectedNewRoleId = (int) ($_POST['new_role_id'] ?? 0);
$addFormOpen = false;

$roles = [];
try {
    $roleResult = db_query_params('users.role_options', 'SELECT id, name, level FROM ft_roles ORDER BY name');
} catch (GrompilDatabaseQueryException) {
    $roleResult = false;
}
while ($roleResult && $role = pg_fetch_assoc($roleResult)) {
    $roles[] = $role;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userCode = (string) ($_POST['user_code'] ?? '');
    $action = (string) ($_POST['action'] ?? 'update');

    if ($action === 'add') {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $validRole = false;
        foreach ($roles as $role) {
            if ((int) $role['id'] === $selectedNewRoleId) {
                $validRole = true;
                break;
            }
        }
        $addFormOpen = true;
        $firstLength = function_exists('mb_strlen') ? mb_strlen($newUserFirstName) : strlen($newUserFirstName);
        $lastLength = function_exists('mb_strlen') ? mb_strlen($newUserLastName) : strlen($newUserLastName);
        $displayLength = function_exists('mb_strlen') ? mb_strlen($newUserName) : strlen($newUserName);
        if ($newUserFirstName === '' || $newUserLastName === ''
            || $firstLength > 120 || $lastLength > 120 || $displayLength > 255
            || !filter_var($newUserEmail, FILTER_VALIDATE_EMAIL) || strlen($newUserEmail) > 254
            || strlen($password) < 12 || strlen($password) > 4096
            || !hash_equals($password, $passwordConfirm) || !$validRole
            || (function_exists('mb_strlen') ? mb_strlen($newUserAddress['street']) : strlen($newUserAddress['street'])) > 160
            || strlen($newUserAddress['house_number']) > 32 || strlen($newUserAddress['box_number']) > 32
            || strlen($newUserAddress['postal_code']) > 20
            || (function_exists('mb_strlen') ? mb_strlen($newUserAddress['city']) : strlen($newUserAddress['city'])) > 100
            || preg_match('/^[A-Z]{2}$/', $newUserAddress['country_code']) !== 1
            || !grompil_address_selection_allowed(
                $newUserAddress,
                isset($_POST['manual_address_without_coordinates'])
            )) {
            $messageKey = 'user_add_invalid';
        } else {
            try {
                $userParams = [
                    $newUserName,
                    $newUserFirstName,
                    $newUserLastName,
                    password_hash($password, PASSWORD_DEFAULT),
                    $selectedNewRoleId,
                    $currentUser['user_code'],
                ];
                $transaction = (bool) db_query_params('users.create.begin', 'BEGIN');
                $result = $transaction ? grompil_insert_user_with_unique_code(
                    static fn (string $userCode): PgSql\Result|false => db_query_params(
                        'users.create',
                        "INSERT INTO bt_users (
                            display_name, name_first, name_last, password_hash, role_id, is_active,
                            approval_status, approved_at, approved_by_user_code, user_code
                         ) VALUES ($1, $2, $3, $4, $5, TRUE, 'approved', now(), $6, $7)
                         ON CONFLICT DO NOTHING RETURNING user_code",
                        [...$userParams, $userCode]
                    )
                ) : false;
            } catch (GrompilDatabaseQueryException) {
                $result = false;
            }
            $created = $result ? pg_fetch_assoc($result) : false;
            if ($created) {
                try {
                    $saved = grompil_save_user_login_email((string) $created['user_code'], null, $newUserEmail)
                        && grompil_save_user_primary_address((string) $created['user_code'], null, $newUserAddress);
                    $saved = $saved && (bool) db_query_params('users.create.commit', 'COMMIT');
                } catch (GrompilDatabaseQueryException) {
                    $saved = false;
                }
                if (!$saved && $transaction) {
                    db_query_params('users.create.rollback', 'ROLLBACK');
                }
                $messageKey = $saved ? 'user_add_success' : 'user_add_failed';
            } else {
                if ($transaction) {
                    db_query_params('users.create.rollback', 'ROLLBACK');
                }
                $messageKey = 'user_add_failed';
            }
            if ($messageKey === 'user_add_success') {
                $newUserName = '';
                $newUserFirstName = '';
                $newUserLastName = '';
                $newUserEmail = '';
                $newUserAddress = array_fill_keys(array_keys($newUserAddress), '');
                $newUserAddress['country_code'] = 'BE';
                $selectedNewRoleId = 0;
                $addFormOpen = false;
            }
        }
    } elseif ($userCode === (string) $currentUser['user_code']) {
        $messageKey = 'user_self_error';
    } elseif ($action === 'approve') {
        $staffRoleId = (int) ($_POST['role_id'] ?? 0);
        try {
            $result = db_query_params(
                'users.approve',
                "UPDATE bt_users
                 SET role_id = $1, approval_status = 'approved', is_active = TRUE,
                     approved_at = now(), approved_by_user_code = $2, updated_at = now()
                 WHERE user_code = $3 AND company_id IS NULL AND approval_status = 'pending'
                   AND EXISTS (SELECT 1 FROM ft_roles WHERE id = $1 AND level > 100 AND level < 200)",
                [$staffRoleId, $currentUser['user_code'], $userCode]
            );
        } catch (GrompilDatabaseQueryException) {
            $result = false;
        }
        $messageKey = $result && pg_affected_rows($result) === 1
            ? 'approval_success' : 'approval_role_required';
    } elseif ($action === 'reject') {
        try {
            $result = db_query_params(
                'users.reject',
                "UPDATE bt_users SET approval_status = 'rejected', is_active = FALSE,
                     updated_at = now() WHERE user_code = $1 AND company_id IS NULL AND approval_status = 'pending'",
                [$userCode]
            );
        } catch (GrompilDatabaseQueryException) {
            $result = false;
        }
        $messageKey = $result && pg_affected_rows($result) === 1
            ? 'approval_success' : 'user_update_failed';
    } else {
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 'true' : 'false';
        try {
            $result = db_query_params(
                'users.update',
                "UPDATE bt_users SET role_id = $1, is_active = $2, updated_at = now()
                 WHERE user_code = $3 AND company_id IS NULL AND approval_status = 'approved'",
                [$roleId, $isActive, $userCode]
            );
        } catch (GrompilDatabaseQueryException) {
            $result = false;
        }
        $messageKey = $result && pg_affected_rows($result) === 1
            ? 'user_update_success' : 'user_update_failed';
    }
}

try {
    $usersResult = db_query_params(
        'users.list',
        'SELECT u.user_code, contact.contact_value AS email, u.display_name, u.role_id, u.is_active,
                u.approval_status, r.name AS role_name, u.last_login_at
         FROM bt_users AS u JOIN ft_roles AS r ON r.id = u.role_id
         JOIN dt_user_contacts AS contact
           ON contact.user_code = u.user_code AND contact.is_login_email AND contact.is_active
         WHERE u.company_id IS NULL
         ORDER BY CASE u.approval_status WHEN \'pending\' THEN 0 ELSE 1 END,
                  u.display_name'
    );
} catch (GrompilDatabaseQueryException) {
    $usersResult = false;
}

$rows = '';
while ($usersResult && $user = pg_fetch_assoc($usersResult)) {
    $userCode = (string) $user['user_code'];
    $isCurrentUser = $userCode === (string) $currentUser['user_code'];
    $isPending = $user['approval_status'] === 'pending';
    $isApproved = $user['approval_status'] === 'approved';
    $formId = ($isPending ? 'approve-user-' : 'user-') . $userCode;
    $actionName = $isPending ? 'approve' : 'update';
    $form = $isCurrentUser ? '' : '<form id="' . e($formId) . '" method="post" action="users.php">'
        . csrf_field() . '<input type="hidden" name="action" value="' . $actionName
        . '"><input type="hidden" name="user_code" value="' . e($userCode) . '"></form>';

    $roleControl = e($user['role_name']);
    if ($isPending) {
        $roleControl = '<select form="' . e($formId) . '" class="form-select form-select-sm" name="role_id" required>'
            . '<option value="">' . e(fn_t('choose_staff_role')) . '</option>';
        foreach ($roles as $role) {
            if ((int) $role['level'] <= 100 || (int) $role['level'] >= 200) {
                continue;
            }
            $roleControl .= '<option value="' . (int) $role['id'] . '">' . e($role['name']) . '</option>';
        }
        $roleControl .= '</select>';
    } elseif (!$isCurrentUser && $isApproved) {
        $roleControl = '<select form="' . e($formId) . '" class="form-select form-select-sm" name="role_id" aria-label="'
            . e(fn_t('col_role') . ' ' . $user['display_name']) . '">';
        foreach ($roles as $role) {
            $selected = (int) $user['role_id'] === (int) $role['id'] ? ' selected' : '';
            $roleControl .= '<option value="' . (int) $role['id'] . '"' . $selected . '>' . e($role['name']) . '</option>';
        }
        $roleControl .= '</select>';
    }

    if ($isPending) {
        $statusControl = '<span class="badge text-bg-warning">' . e(fn_t('approval_pending')) . '</span>';
        $action = '<button form="' . e($formId) . '" class="btn btn-sm btn-success" type="submit">'
            . e(fn_t('approve')) . '</button>';
            $action .= '<form method="post" action="users.php" class="d-inline ms-1">' . csrf_field()
            . '<input type="hidden" name="user_code" value="' . e($userCode) . '"><input type="hidden" name="action" value="reject">'
            . '<button class="btn btn-sm btn-outline-danger" type="submit">' . e(fn_t('reject')) . '</button></form>';
    } elseif (!$isApproved) {
        $statusControl = '<span class="badge text-bg-secondary">' . e(fn_t('approval_rejected')) . '</span>';
        $action = e(fn_t('approval_rejected'));
    } else {
        $statusControl = $isCurrentUser
            ? '<span class="badge text-bg-success">' . e(fn_t('active')) . '</span>'
            : '<div class="form-check"><input form="' . e($formId) . '" class="form-check-input" type="checkbox" name="is_active" value="1"'
                . ($user['is_active'] === 't' ? ' checked' : '') . '><label class="form-check-label">' . e(fn_t('active')) . '</label></div>';
        $action = $isCurrentUser
            ? e(fn_t('you'))
            : '<button form="' . e($formId) . '" class="btn btn-sm btn-grompil" type="submit">' . e(fn_t('save')) . '</button>';
    }

    $rows .= fn_templateRender('users_row.html', [
        'display_name' => (string) $user['display_name'],
        'user_email' => (string) $user['email'],
        'approval_status' => fn_t('approval_' . $user['approval_status']),
        'last_login' => $user['last_login_at'] !== null ? (string) $user['last_login_at'] : fn_t('never'),
        '_form_html' => $form,
        '_role_control_html' => $roleControl,
        '_status_control_html' => $statusControl,
        '_action_html' => $action,
    ]);
}

$message = $messageKey === '' ? ''
    : '<div class="alert alert-info" role="status">' . e(fn_t($messageKey)) . '</div>';
$roleOptions = '<option value="">' . e(fn_t('user_add_choose_role')) . '</option>';
foreach ($roles as $role) {
    $selected = $selectedNewRoleId === (int) $role['id'] ? ' selected' : '';
    $roleOptions .= '<option value="' . (int) $role['id'] . '"' . $selected . '>'
        . e((string) $role['name']) . '</option>';
}
$addUserForm = fn_templateRender('user_add_form.html', [
    '_csrf_field_html' => csrf_field(),
    '_role_options_html' => $roleOptions,
    'display_name_value' => $newUserName,
    'name_first_value' => $newUserFirstName,
    'name_last_value' => $newUserLastName,
    'email_value' => $newUserEmail,
    '_address_fields_html' => fn_templateRender('address_fields.html', [
        'address_prefix' => 'new-user-address',
        'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
        'address_lookup_no_results' => fn_t('address_lookup_no_results'),
        'address_postal_reminder' => fn_t('address_postal_reminder'),
        'address_select_required' => fn_t('address_select_required'),
        'address_manual_prompt' => fn_t('address_manual_prompt'),
        'address_manual_required' => fn_t('address_manual_required'),
        'address_manual_confirm' => fn_t('address_manual_confirm'),
        'address_country_code_value' => $newUserAddress['country_code'],
        'address_reference_id_value' => $newUserAddress['address_reference_id'],
        'lambert72_x_value' => $newUserAddress['lambert72_x'], 'lambert72_y_value' => $newUserAddress['lambert72_y'],
        'wgs84_latitude_value' => $newUserAddress['wgs84_latitude'], 'wgs84_longitude_value' => $newUserAddress['wgs84_longitude'],
        'street_reference_id_value' => '',
        'street_value' => $newUserAddress['street'],
        'house_number_value' => $newUserAddress['house_number'],
        'box_number_value' => $newUserAddress['box_number'],
        'postal_code_value' => $newUserAddress['postal_code'],
        'city_value' => $newUserAddress['city'],
    ]),
    'country_code_value' => $newUserAddress['country_code'],
    '_form_open_class' => $addFormOpen ? ' show' : '',
]);
page_start(fn_t('nav_users'), $currentUser);
echo fn_templateRender('users.html', [
    '_message_html' => $message,
    '_user_rows_html' => $rows,
    '_add_user_form_html' => $addUserForm,
]);
page_end();

