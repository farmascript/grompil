<?php
/**
 *
 * @date_cre: Wed 2026-10-14 19:35:56
 * @date_mut: Thu 2026-11-12 13:57:10
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Loads the signed-in dashboard and its available modules.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:account:dashboard
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

$cards = '';
if (grompil_can_access_module('dispense.php', $user, $company)) {
    $cards .= fn_templateRender('dashboard_dispensing.html', [
        'dispense_dashboard_title' => fn_t('dispense_dashboard_title'),
        'dispense_dashboard_description' => fn_t('dispense_dashboard_description'),
        'dispense_dashboard_action' => fn_t('dispense_dashboard_action'),
    ]);
}
foreach (['module_inventory', 'module_refills', 'module_team'] as $module) {
    $cards .= fn_templateRender('dashboard_module.html', ['module_name' => fn_t($module)]);
}

echo fn_templateRender('dashboard.html', [
    'user_name' => (string) $user['display_name'],
    'role_name' => (string) ($company['company_role_name'] ?? $user['role_name']),
    '_module_cards_html' => $cards,
]);
page_end();

