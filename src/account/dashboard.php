<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:56
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the signed-in dashboard and its available modules.
 *
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$user = require_login();
$company = require_company_context($user);
if (!$company['is_platform_operator']
    && ($company['company_role_level'] <= 1 || $company['company_role_level'] >= 200)) {
    redirect('index.php?c=' . rawurlencode((string) $company['slug']));
}
page_start(fn_t('nav_dashboard'), $user);

$modules = ['module_inventory', 'module_refills', 'module_team'];
$cards = '';
foreach ($modules as $module) {
    $cards .= fn_templateRender('dashboard_module.html', ['module_name' => fn_t($module)]);
}

echo fn_templateRender('dashboard.html', [
    'user_name' => (string) $user['display_name'],
    'role_name' => (string) ($company['company_role_name'] ?? $user['role_name']),
    '_module_cards_html' => $cards,
]);
page_end();

