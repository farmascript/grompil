<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:57
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages global user roles and their permissions.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('roles.php');
$permissionCatalog = [
    'users.manage' => 'perm_users_manage',
    'roles.manage' => 'perm_roles_manage',
    'translations.manage' => 'perm_translations_manage',
];
$messageKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $deleteResult = pg_query_params(
            db(),
            'DELETE FROM roles WHERE id = $1 AND is_system = FALSE
             AND NOT EXISTS (SELECT 1 FROM users WHERE role_id = $1)',
            [(int) ($_POST['id'] ?? 0)]
        );
        $messageKey = $deleteResult && pg_affected_rows($deleteResult) > 0
            ? 'role_deleted' : 'role_delete_blocked';
    } else {
        $roleId = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $level = filter_var($_POST['level'] ?? null, FILTER_VALIDATE_INT);
        $permissions = array_values(array_intersect(
            array_keys($permissionCatalog),
            (array) ($_POST['permissions'] ?? [])
        ));
        if ($name === '' || $level === false || $level === null || $level < 0) {
            $messageKey = $name === '' ? 'role_name_required' : 'role_level_invalid';
        } else {
            $permissionsJson = json_encode($permissions, JSON_THROW_ON_ERROR);
            $saveResult = $roleId > 0
                ? pg_query_params(db(), 'UPDATE roles SET name = $1, description = $2, level = $3, permissions = $4::jsonb, updated_at = now() WHERE id = $5', [$name, $description, $level, $permissionsJson, $roleId])
                : pg_query_params(db(), 'INSERT INTO roles (name, description, level, permissions) VALUES ($1, $2, $3, $4::jsonb)', [$name, $description, $level, $permissionsJson]);
            $messageKey = $saveResult ? 'role_saved' : 'role_save_failed';
        }
    }
}

$editingRole = null;
if (isset($_GET['edit'])) {
    $roleResult = pg_query_params(db(), 'SELECT * FROM roles WHERE id = $1', [(int) $_GET['edit']]);
    $editingRole = $roleResult ? pg_fetch_assoc($roleResult) : null;
}
$rolesResult = pg_query(
    db(),
    'SELECT r.*, count(u.id) AS users_count FROM roles AS r
     LEFT JOIN users AS u ON u.role_id = r.id GROUP BY r.id ORDER BY r.id'
);
$selectedPermissions = $editingRole ? (json_decode($editingRole['permissions'], true) ?: []) : [];

$permissionChecks = '';
foreach ($permissionCatalog as $key => $labelKey) {
    $permissionChecks .= fn_templateRender('roles_permission.html', [
        'permission_key' => $key,
        'permission_label' => fn_t($labelKey),
        '_checked' => in_array($key, $selectedPermissions, true) ? 'checked' : '',
    ]);
}
$roleRows = '';
while ($rolesResult && $role = pg_fetch_assoc($rolesResult)) {
    $permissionKeys = json_decode($role['permissions'], true) ?: [];
    $labels = array_map(
        static fn (string $permission): string => fn_t($permissionCatalog[$permission] ?? $permission),
        $permissionKeys
    );
    $deleteForm = '';
    if ($role['is_system'] !== 't') {
        $deleteForm = '<form method="post" action="roles.php" class="d-inline ms-2">' . csrf_field()
            . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'
            . (int) $role['id'] . '"><button class="btn btn-link btn-sm text-danger p-0" type="submit">'
            . e(fn_t('delete')) . '</button></form>';
    }
    $roleRows .= fn_templateRender('roles_row.html', [
        'role_name_value' => (string) $role['name'],
        'role_description_value' => (string) $role['description'],
        'users_count' => (string) $role['users_count'],
        'permission_list' => $labels ? implode(', ', $labels) : fn_t('base'),
        'role_id' => (string) $role['id'],
        'role_level' => (string) $role['level'],
        'edit' => fn_t('edit'),
        '_delete_form_html' => $deleteForm,
    ]);
}

page_start(fn_t('nav_roles'), $currentUser);
$message = $messageKey === '' ? '' : '<div class="alert alert-info" role="status">' . e(fn_t($messageKey)) . '</div>';
echo fn_templateRender('roles.html', [
    '_message_html' => $message,
    'form_title' => fn_t($editingRole ? 'roles_edit' : 'roles_new'),
    'role_id' => (string) ($editingRole['id'] ?? ''),
    'role_value' => (string) ($editingRole['name'] ?? ''),
    'description_value' => (string) ($editingRole['description'] ?? ''),
    'level_value' => (string) ($editingRole['level'] ?? '200'),
    '_permission_checks_html' => $permissionChecks,
    '_cancel_link_html' => $editingRole ? '<a class="ms-2" href="roles.php">' . e(fn_t('cancel')) . '</a>' : '',
    '_csrf_field_html' => csrf_field(),
    '_role_rows_html' => $roleRows,
]);
page_end();

