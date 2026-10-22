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
 * @description: Manages company members, requests, approvals, and company roles.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
$actor = require_login();
$isPlatformOperator = is_platform_operator($actor);
$tenant = null;

if ($isPlatformOperator) {
    $companiesResult = pg_query(db(), 'SELECT id, name FROM companies ORDER BY name');
    $companies = [];
    while ($companiesResult && $companyRow = pg_fetch_assoc($companiesResult)) {
        $companies[] = $companyRow;
    }
} else {
    $tenant = require_company_context($actor);
    if (!has_company_permission('company.users.manage', $tenant, $actor)) {
        grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
    }
    $companies = [['id' => $tenant['id'], 'name' => $tenant['name']]];
}

$defaultCompanyId = $isPlatformOperator ? 0 : (int) ($companies[0]['id'] ?? 0);
$companyId = (int) ($_POST['company_id'] ?? $_GET['company_id'] ?? $defaultCompanyId);
$company = null;
foreach ($companies as $companyRow) {
    if ((int) $companyRow['id'] === $companyId) {
        $company = $companyRow;
        break;
    }
}
if (!$isPlatformOperator && $companyId !== (int) $tenant['id']) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $targetUserId = (int) ($_POST['user_id'] ?? 0);

    if ($company === null) {
        http_response_code(404);
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('company_not_found')) . '</div>';
    } elseif ($action === 'add' && $isPlatformOperator) {
        $roleId = (int) ($_POST['company_role_id'] ?? 0);
        $selectedRoleResult = $roleId > 0 ? pg_query_params(
            db(),
            'SELECT id, name FROM company_roles WHERE company_id = $1 AND id = $2 AND level > 0 AND level < 200',
            [$companyId, $roleId]
        ) : false;
        $selectedRole = $selectedRoleResult ? pg_fetch_assoc($selectedRoleResult) : false;
        if ($targetUserId < 1 || $selectedRole === false) {
            $message = '<div class="alert alert-warning" role="status">'
                . e(fn_t('company_members_role_required')) . '</div>';
        } else {
        $collisionResult = pg_query_params(
            db(),
            "SELECT 1
             FROM users AS target
             JOIN users AS existing ON existing.company_id = $1 AND existing.id <> target.id
             WHERE target.id = $2 AND (
                lower(existing.email) = lower(target.email)
                OR lower(existing.acronym) = lower(target.email)
                OR (target.acronym IS NOT NULL AND (
                    lower(existing.email) = lower(target.acronym)
                    OR lower(existing.acronym) = lower(target.acronym)
                ))
             ) LIMIT 1",
            [$companyId, $targetUserId]
        );
        if ($collisionResult && pg_num_rows($collisionResult) > 0) {
            $message = '<div class="alert alert-warning" role="status">'
                . e(fn_t('company_member_already_exists')) . '</div>';
        } else {
            pg_query(db(), 'BEGIN');
            $claimResult = pg_query_params(
                db(),
                "UPDATE users AS u SET company_id = $2, updated_at = now()
                 FROM roles AS r
                 WHERE u.id = $1 AND u.company_id IS NULL AND u.role_id = r.id
                   AND u.is_active AND u.approval_status = 'approved'
                   AND r.level > 0 AND r.level < 200 AND NOT (r.permissions ? 'companies.manage')
                   AND NOT EXISTS (SELECT 1 FROM company_users AS existing WHERE existing.user_id = u.id)
                   AND NOT EXISTS (
                        SELECT 1 FROM users AS existing
                        WHERE existing.company_id = $2 AND (
                            lower(existing.email) = lower(u.email)
                            OR lower(existing.acronym) = lower(u.email)
                            OR (u.acronym IS NOT NULL AND (
                                lower(existing.email) = lower(u.acronym)
                                OR lower(existing.acronym) = lower(u.acronym)
                            ))
                        )
                   )",
                [$targetUserId, $companyId]
            );
            $result = $claimResult && pg_affected_rows($claimResult) === 1 ? pg_query_params(
                db(),
                "INSERT INTO company_users (
                    company_id, user_id, company_role_id, is_active,
                    approval_status, approved_at, approved_by
                 )
                 SELECT $1, u.id, cr.id, TRUE, 'approved', now(), $4
                 FROM users AS u
                 JOIN roles AS ur ON ur.id = u.role_id
                 JOIN company_roles AS cr ON cr.company_id = $1 AND cr.id = $3
                 WHERE u.id = $2 AND u.company_id = $1 AND u.is_active AND u.approval_status = 'approved'
                   AND ur.level > 0 AND ur.level < 200 AND cr.level > 0 AND cr.level < 200
                 ON CONFLICT (company_id, user_id) DO UPDATE SET
                    company_role_id = EXCLUDED.company_role_id,
                    is_active = TRUE, approval_status = 'approved',
                    approved_at = now(), approved_by = EXCLUDED.approved_by,
                    updated_at = now()",
                [$companyId, $targetUserId, $roleId, $actor['id']]
            ) : false;
            $saved = $result && pg_affected_rows($result) > 0 && pg_query(db(), 'COMMIT');
            if (!$saved) {
                pg_query(db(), 'ROLLBACK');
            }
            $message = $saved
                ? '<div class="alert alert-success" role="status">' . e(fn_t('company_member_saved')) . '</div>'
                : '<div class="alert alert-danger" role="alert">' . e(fn_t('company_member_save_failed')) . '</div>';
        }
        }
    } elseif ($action === 'role' && ($isPlatformOperator || $targetUserId !== (int) $actor['id'])) {
        $roleId = (int) ($_POST['company_role_id'] ?? 0);
        $roleResult = $roleId > 0 ? pg_query_params(
            db(),
            'UPDATE company_users AS cu SET company_role_id = cr.id, updated_at = now()
             FROM company_roles AS cr
             WHERE cu.company_id = $1 AND cu.user_id = $2
               AND cu.approval_status = \'approved\'
               AND cr.company_id = cu.company_id AND cr.id = $3
               AND cr.level > 0 AND cr.level < 200',
            [$companyId, $targetUserId, $roleId]
        ) : false;
        $message = $roleResult && pg_affected_rows($roleResult) === 1
            ? '<div class="alert alert-success" role="status">' . e(fn_t('company_member_saved')) . '</div>'
            : '<div class="alert alert-danger" role="alert">' . e(fn_t('company_member_save_failed')) . '</div>';
    } elseif ($action === 'approve') {
        $roleId = (int) ($_POST['company_role_id'] ?? 0);
        pg_query(db(), 'BEGIN');
        $result = pg_query_params(
            db(),
            "UPDATE company_users AS cu SET company_role_id = cr.id,
                    is_active = TRUE, approval_status = 'approved',
                    approved_at = now(), approved_by = $4, updated_at = now()
             FROM company_roles AS cr
             WHERE cu.company_id = $1 AND cu.user_id = $2
               AND cu.approval_status = 'pending'
               AND cr.company_id = cu.company_id AND cr.id = $3
               AND cr.level > 0 AND cr.level < 200",
            [$companyId, $targetUserId, $roleId, $actor['id']]
        );
        if ($result && pg_affected_rows($result) === 1) {
            pg_query_params(
                db(),
                "UPDATE users SET is_active = TRUE, approval_status = 'approved',
                    approved_at = COALESCE(approved_at, now()),
                    approved_by = COALESCE(approved_by, $2), updated_at = now()
                 WHERE id = $1",
                [$targetUserId, $actor['id']]
            );
            $ok = (bool) pg_query(db(), 'COMMIT');
        } else {
            pg_query(db(), 'ROLLBACK');
            $ok = false;
        }
        $message = $ok
            ? '<div class="alert alert-success" role="status">' . e(fn_t('company_member_saved')) . '</div>'
            : '<div class="alert alert-danger" role="alert">' . e(fn_t('company_member_save_failed')) . '</div>';
    } elseif ($action === 'reject') {
        pg_query(db(), 'BEGIN');
        $result = pg_query_params(
            db(),
            "UPDATE company_users SET is_active = FALSE, approval_status = 'rejected',
                approved_by = $3, updated_at = now()
             WHERE company_id = $1 AND user_id = $2 AND approval_status = 'pending'",
            [$companyId, $targetUserId, $actor['id']]
        );
        if ($result && pg_affected_rows($result) === 1) {
            pg_query_params(
                db(),
                "UPDATE users SET is_active = FALSE, approval_status = 'rejected', updated_at = now()
                 WHERE id = $1 AND company_id = $2 AND approval_status = 'pending'",
                [$targetUserId, $companyId]
            );
            $rejected = (bool) pg_query(db(), 'COMMIT');
        } else {
            pg_query(db(), 'ROLLBACK');
            $rejected = false;
        }
        $message = $rejected
            ? '<div class="alert alert-success" role="status">' . e(fn_t('company_member_saved')) . '</div>'
            : '<div class="alert alert-danger" role="alert">' . e(fn_t('company_member_save_failed')) . '</div>';
    } elseif ($action === 'status' && ($isPlatformOperator || $targetUserId !== (int) $actor['id'])) {
        $active = (($_POST['is_active'] ?? '') === '1');
        $result = pg_query_params(
            db(),
            "UPDATE company_users SET is_active = $1, updated_at = now()
             WHERE company_id = $2 AND user_id = $3 AND approval_status = 'approved'",
            [$active ? 'true' : 'false', $companyId, $targetUserId]
        );
        $message = $result && pg_affected_rows($result) === 1
            ? '<div class="alert alert-success" role="status">' . e(fn_t('company_member_saved')) . '</div>'
            : '<div class="alert alert-danger" role="alert">' . e(fn_t('company_member_save_failed')) . '</div>';
    }
}

$companyOptions = '';
foreach ($companies as $companyRow) {
    $optionValue = (string) $companyRow['name'] . ' (#' . (int) $companyRow['id'] . ')';
    $companyOptions .= '<option value="' . e($optionValue) . '" data-company-id="' . (int) $companyRow['id'] . '"></option>';
}
$selectedCompanyName = '';
foreach ($companies as $companyRow) {
    if ((int) $companyRow['id'] === $companyId) {
        $selectedCompanyName = (string) $companyRow['name'] . ' (#' . (int) $companyRow['id'] . ')';
        break;
    }
}
$companySelector = $isPlatformOperator
    ? '<form method="get" action="company_members.php" class="mb-4" data-company-members-picker>'
        . '<label class="form-label" for="member-company-search">' . e(fn_t('company_name_label')) . '</label>'
        . '<div class="d-flex gap-2"><input class="form-control" id="member-company-search" type="search" list="member-company-options"'
        . ' autocomplete="off" value="' . e($selectedCompanyName) . '">'
        . '<datalist id="member-company-options">' . $companyOptions . '</datalist>'
        . '<input type="hidden" id="member-company-id" name="company_id" value="' . ($company !== null ? $companyId : '') . '">'
        . '<button class="btn btn-outline-secondary" id="member-company-open" type="submit"' . ($company !== null ? '' : ' disabled') . '>'
        . e(fn_t('company_members_open')) . '</button></div></form>'
    : '<p class="text-secondary mb-4">' . e((string) ($company['name'] ?? '')) . '</p>';

$roles = [];
$users = [];
$memberRows = '';
if ($company !== null) {
    $roleResult = pg_query_params(
        db(),
        'SELECT id, name FROM company_roles WHERE company_id = $1 AND level > 0 AND level < 200 ORDER BY level, name',
        [$companyId]
    );
    while ($roleResult && $roleRow = pg_fetch_assoc($roleResult)) {
        $roles[] = $roleRow;
    }

    if ($isPlatformOperator) {
        $userResult = pg_query_params(
            db(),
            "SELECT u.id, u.display_name, u.email
             FROM users AS u JOIN roles AS r ON r.id = u.role_id
             WHERE u.company_id IS NULL AND u.is_active AND u.approval_status = 'approved'
               AND r.level > 0 AND r.level < 200 AND NOT (r.permissions ? 'companies.manage')
               AND NOT EXISTS (
                    SELECT 1 FROM users AS existing
                    WHERE existing.company_id = $1 AND (
                        lower(existing.email) = lower(u.email)
                        OR lower(existing.acronym) = lower(u.email)
                        OR (u.acronym IS NOT NULL AND (
                            lower(existing.email) = lower(u.acronym)
                            OR lower(existing.acronym) = lower(u.acronym)
                        ))
                    )
               )
             ORDER BY u.display_name",
            [$companyId]
        );
        while ($userResult && $userRow = pg_fetch_assoc($userResult)) {
            $users[] = $userRow;
        }
    }

    $membersResult = pg_query_params(
        db(),
        'SELECT u.id, u.display_name, u.email, cu.is_active, cu.approval_status,
                cu.company_role_id, cr.name AS role_name
         FROM company_users AS cu
         JOIN users AS u ON u.id = cu.user_id
         JOIN company_roles AS cr ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
         WHERE cu.company_id = $1 AND cr.level > 0 AND cr.level < 200
         ORDER BY CASE cu.approval_status WHEN \'pending\' THEN 0 WHEN \'approved\' THEN 1 ELSE 2 END,
                  u.display_name',
        [$companyId]
    );
    while ($membersResult && $member = pg_fetch_assoc($membersResult)) {
        $isPending = $member['approval_status'] === 'pending';
        $active = $member['is_active'] === 't';
        $actions = '';
        if ($isPending) {
            $pendingRoleOptions = '';
            foreach ($roles as $role) {
                $selected = (int) $role['id'] === (int) $member['company_role_id'] ? ' selected' : '';
                $pendingRoleOptions .= '<option value="' . e((string) $role['id']) . '"' . $selected . '>' . e((string) $role['name']) . '</option>';
            }
            $actions = '<form method="post" action="company_members.php?company_id=' . $companyId . '" class="d-flex flex-wrap gap-2">'
                . csrf_field() . '<input type="hidden" name="company_id" value="' . $companyId . '"><input type="hidden" name="user_id" value="'
                . (int) $member['id'] . '"><select class="form-select form-select-sm" name="company_role_id" aria-label="'
                . e(fn_t('company_members_role')) . '">' . $pendingRoleOptions . '</select><button class="btn btn-sm btn-success" name="action" value="approve">'
                . e(fn_t('approve')) . '</button><button class="btn btn-sm btn-outline-danger" name="action" value="reject">'
                . e(fn_t('reject')) . '</button></form>';
        } elseif ($isPlatformOperator || (int) $member['id'] !== (int) $actor['id']) {
            $memberRoleOptions = '';
            foreach ($roles as $role) {
                $selected = (int) $role['id'] === (int) $member['company_role_id'] ? ' selected' : '';
                $memberRoleOptions .= '<option value="' . e((string) $role['id']) . '"' . $selected . '>' . e((string) $role['name']) . '</option>';
            }
            $actions = '<div class="d-flex flex-column gap-2">'
                . '<form method="post" action="company_members.php?company_id=' . $companyId . '" class="d-flex gap-2">'
                . csrf_field() . '<input type="hidden" name="company_id" value="' . $companyId . '"><input type="hidden" name="user_id" value="'
                . (int) $member['id'] . '"><select class="form-select form-select-sm" name="company_role_id" aria-label="'
                . e(fn_t('company_members_role')) . '">' . $memberRoleOptions . '</select><button class="btn btn-sm btn-outline-primary"'
                . ' type="submit" name="action" value="role">' . e(fn_t('save')) . '</button></form>'
                . '<form method="post" action="company_members.php?company_id=' . $companyId . '" class="m-0">'
                . csrf_field() . '<input type="hidden" name="company_id" value="' . $companyId . '"><input type="hidden" name="user_id" value="'
                . (int) $member['id'] . '"><input type="hidden" name="is_active" value="' . ($active ? '0' : '1') . '"><button class="btn btn-sm btn-outline-secondary"'
                . ' type="submit" name="action" value="status">' . e(fn_t($active ? 'company_member_deactivate' : 'company_member_activate'))
                . '</button></form></div>';
        }
        $memberRows .= fn_templateRender('company_member_row.html', [
            'display_name' => (string) $member['display_name'],
            'user_email' => (string) $member['email'],
            'role_name' => (string) $member['role_name'],
            'status_name' => fn_t($isPending ? 'approval_pending' : ($active ? 'active' : 'inactive')),
            '_member_actions_html' => $actions,
        ]);
    }
}

$userOptions = '';
foreach ($users as $userRow) {
    $userOptions .= '<option value="' . e((string) $userRow['id']) . '">'
        . e((string) $userRow['display_name'] . ' · ' . (string) $userRow['email']) . '</option>';
}
if ($userOptions === '') {
    $userOptions = '<option value="" selected disabled>' . e(fn_t('company_members_no_unassigned_users')) . '</option>';
}
$roleOptions = '<option value="" selected disabled>' . e(fn_t('company_members_choose_role')) . '</option>';
foreach ($roles as $roleRow) {
    $roleOptions .= '<option value="' . e((string) $roleRow['id']) . '">' . e((string) $roleRow['name']) . '</option>';
}
$addForm = $isPlatformOperator && $company !== null
    ? fn_templateRender('company_members_add_form.html', [
        '_csrf_field_html' => csrf_field(),
        '_user_options_html' => $userOptions,
        '_role_options_html' => $roleOptions,
        'company_id' => (string) $companyId,
        'company_name' => (string) $company['name'],
        'company_members_confirm_title' => fn_t('company_members_confirm_title'),
        'company_members_confirm_intro' => fn_t('company_members_confirm_intro'),
        'company_members_confirm_user' => fn_t('company_members_user'),
        'company_members_confirm_company' => fn_t('company_name_label'),
        'company_members_confirm_role' => fn_t('company_members_role'),
        'company_members_confirm_ok' => fn_t('ok'),
        'company_members_confirm_cancel' => fn_t('cancel'),
    ])
    : '';
$membersPanel = $company === null ? '' : fn_templateRender('company_members_panel.html', [
    '_add_form_html' => $addForm,
    '_member_rows_html' => $memberRows,
    '_empty_members_html' => $memberRows === ''
        ? '<p class="mb-0 text-secondary">' . e(fn_t('company_members_empty')) . '</p>'
        : '',
    'company_id' => (string) $companyId,
    'company_name' => (string) $company['name'],
]);

page_start(fn_t('company_members_heading'), $actor);
echo fn_templateRender('company_members.html', [
    '_message_html' => $message,
    '_company_selector_html' => $companySelector,
    '_members_panel_html' => $membersPanel,
]);
page_end();

