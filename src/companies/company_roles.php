<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:49
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages roles and permissions within a company.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
$user = require_login();
$company = require_company_context($user);
if (!has_company_permission('company.roles.manage', $company, $user)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}

$messageKey = '';
$permissionCatalog = [
    'company.users.manage' => 'company_permission_users',
    'company.roles.manage' => 'company_permission_roles',
    'company.settings.manage' => 'company_permission_settings',
    'company.translations.manage' => 'company_permission_translations',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $level = filter_var($_POST['level'] ?? null, FILTER_VALIDATE_INT);
    $permissions = array_values(array_intersect(
        array_keys($permissionCatalog),
        (array) ($_POST['permissions'] ?? [])
    ));

    if ($name === '' || $level === false || $level === null || $level < 1 || $level >= 200) {
        $messageKey = $name === '' ? 'role_name_required' : 'company_role_level_invalid';
    } else {
        $permissionsJson = json_encode($permissions, JSON_THROW_ON_ERROR);
        if ($id > 0) {
            $result = pg_query_params(
                db(),
                'UPDATE company_roles SET name=$1, description=$2, level=$3,
                    permissions=$4::jsonb, updated_at=now()
                 WHERE id=$5 AND company_id=$6 AND is_system=FALSE',
                [$name, $description, $level, $permissionsJson, $id, $company['id']]
            );
        } else {
            $result = pg_query_params(
                db(),
                'INSERT INTO company_roles (company_id, name, description, level, permissions)
                 VALUES ($1, $2, $3, $4, $5::jsonb)',
                [$company['id'], $name, $description, $level, $permissionsJson]
            );
        }
        $messageKey = $result ? 'company_role_saved' : 'company_role_save_failed';
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $result = pg_query_params(
        db(),
        'SELECT * FROM company_roles WHERE id=$1 AND company_id=$2 AND is_system=FALSE',
        [(int) $_GET['edit'], $company['id']]
    );
    $editing = $result ? pg_fetch_assoc($result) : null;
}

$selectedPermissions = $editing ? (json_decode($editing['permissions'], true) ?: []) : [];
$permissionChecks = '';
foreach ($permissionCatalog as $permission => $label) {
    $permissionChecks .= fn_templateRender('roles_permission.html', [
        'permission_key' => $permission,
        'permission_label' => fn_t($label),
        '_checked' => in_array($permission, $selectedPermissions, true) ? 'checked' : '',
    ]);
}

$rows = '';
$result = pg_query_params(
    db(),
    'SELECT cr.*, count(cu.user_id) AS users_count
     FROM company_roles AS cr
     LEFT JOIN company_users AS cu ON cu.company_id=cr.company_id AND cu.company_role_id=cr.id
     WHERE cr.company_id=$1 AND cr.level > 0 AND cr.level < 200
     GROUP BY cr.id ORDER BY cr.is_system DESC, cr.level, cr.name',
    [$company['id']]
);
while ($result && $role = pg_fetch_assoc($result)) {
    $permissionKeys = json_decode($role['permissions'], true) ?: [];
    $labels = array_map(
        static fn (string $permission): string => fn_t($permissionCatalog[$permission] ?? $permission),
        $permissionKeys
    );
    $edit = $role['is_system'] === 't'
        ? e(fn_t('system_role'))
        : '<a href="company_roles.php?edit=' . (int) $role['id'] . '">' . e(fn_t('edit')) . '</a>';
    $rows .= fn_templateRender('company_role_row.html', [
        'role_name_value' => (string) $role['name'],
        'role_description_value' => (string) $role['description'],
        'users_count' => (string) $role['users_count'],
        'permission_list' => $labels ? implode(', ', $labels) : fn_t('base'),
        'role_level' => (string) $role['level'],
        '_role_edit_html' => $edit,
    ]);
}

page_start(fn_t('company_roles_heading'), $user);
echo fn_templateRender('company_roles.html', [
    '_message_html' => $messageKey === '' ? '' : '<div class="alert alert-info" role="status">' . e(fn_t($messageKey)) . '</div>',
    'form_title' => fn_t($editing ? 'company_roles_edit' : 'company_roles_new'),
    'company_name' => (string) $company['name'],
    'role_id' => (string) ($editing['id'] ?? ''),
    'role_value' => (string) ($editing['name'] ?? ''),
    'description_value' => (string) ($editing['description'] ?? ''),
    'level_value' => (string) ($editing['level'] ?? '120'),
    '_permission_checks_html' => $permissionChecks,
    '_cancel_link_html' => $editing ? '<a class="ms-2" href="company_roles.php">' . e(fn_t('cancel')) . '</a>' : '',
    '_csrf_field_html' => csrf_field(),
    '_role_rows_html' => $rows,
]);
page_end();

