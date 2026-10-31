<?php
/**
 *
 * @date_cre: Wed 2026-10-14 21:45:46
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Displays the administration landing page and available modules.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = require_login();
$currentUser = require_level_range(1, 100, $currentUser);
$adminPermissions = ['users.manage', 'roles.manage', 'translations.manage', 'companies.manage'];
if (!array_intersect($adminPermissions, $currentUser['permissions'] ?? [])) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}
$modules = [
    ['users.manage', 'users.php', 'nav_users', 'admin_users_description'],
    ['roles.manage', 'roles.php', 'nav_roles', 'admin_roles_description'],
    ['translations.manage', 'translations.php', 'nav_translations', 'admin_translations_description'],
    ['companies.manage', 'navigation_keywords.php', 'translations_heading', 'admin_translations_description'],
    ['companies.manage', 'scripts/check_templates.php', 'template_check_heading', 'template_check_intro'],
    ['companies.manage', 'companies.php', 'nav_companies', 'admin_companies_description'],
    ['companies.manage', 'platform-settings.php', 'platform_settings_title', 'platform_settings_intro'],
    ['companies.manage', 'platform-security.php', 'platform_security_title', 'platform_security_intro'],
    ['companies.manage', 'dd-varia.php', 'dd_varia_title', 'dd_varia_intro'],
];
$cards = '';
foreach ($modules as [$permission, $path, $titleKey, $descriptionKey]) {
    if (!has_permission($permission, $currentUser)) {
        continue;
    }
    if ($path === 'dd-varia.php'
        && (($currentUser['company_id'] ?? null) !== null || !is_platform_operator($currentUser))) {
        continue;
    }
    $cards .= '<div class="col-md-6 col-xl-4"><a class="card h-100 text-decoration-none shadow-sm admin-module" href="'
        . e($path) . '"><div class="card-body"><h2 class="h5 card-title">' . e(fn_t($titleKey))
        . '</h2><p class="card-text text-secondary mb-0">' . e(fn_t($descriptionKey)) . '</p></div></a></div>';
}
page_start(fn_t('admin_home'), $currentUser);
echo fn_templateRender('admin.html', ['_admin_modules_html' => $cards]);
page_end();

