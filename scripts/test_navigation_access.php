<?php
/**
 *
 * @date_cre: Sat 2026-10-24
 * @date_mut: Sat 2026-10-24
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Checks shared module authorization without changing users or connecting to the database.
 *
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

function navigation_test_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Test failed: ' . $message);
}

$staff = ['id' => 123, 'company_id' => 12, 'role_level' => 200, 'role_name' => 'Publiek',
    'permissions' => [], 'must_change_password' => 'f'];
$company = ['id' => 12, 'is_platform_operator' => false, 'company_role_level' => 150, 'company_permissions' => []];
$platform = array_replace($staff, ['company_id' => null, 'role_level' => 50, 'role_name' => 'Beheerder',
    'permissions' => ['companies.manage', 'users.manage', 'roles.manage', 'translations.manage']]);
$rules = grompil_module_access_rules();
$catalog = json_decode((string) file_get_contents(dirname(__DIR__) . '/navigation.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($catalog['items'] as $item) {
    navigation_test_expect(array_key_exists($item['module'], $rules), 'every catalog module has a registered access rule');
}
foreach (array_keys($rules) as $module) {
    navigation_test_expect(grompil_can_access_module($module, null, null) === ($module === 'index.php'), 'anonymous users see public home only');
}
navigation_test_expect(grompil_can_access_module('account.php', $staff, null), 'signed-in users can access their account without a company');
navigation_test_expect(grompil_can_access_module('select-company.php', $staff, null), 'signed-in users can select a company');
navigation_test_expect(!grompil_can_access_module('company_roles.php', $staff, $company), 'staff cannot manage roles without the company permission');
navigation_test_expect(!grompil_can_access_module('companies.php', $staff, $company), 'tenant users cannot manage platform companies');

foreach (['company_members.php' => 'company.users.manage', 'company_roles.php' => 'company.roles.manage',
    'company_translations.php' => 'company.translations.manage', 'company_security.php' => 'company.settings.manage'] as $module => $permission) {
    $authorizedCompany = array_replace($company, ['company_permissions' => [$permission]]);
    navigation_test_expect(grompil_can_access_module($module, $staff, $authorizedCompany), 'company permission grants only its matching module');
    navigation_test_expect(!grompil_can_access_module($module, $staff, null), 'company modules require an active company');
    $otherModule = $module === 'company_roles.php' ? 'company_security.php' : 'company_roles.php';
    navigation_test_expect(!grompil_can_access_module($otherModule, $staff, $authorizedCompany), 'one company permission does not grant a different module');
}

foreach (['companies.php', 'users.php', 'roles.php', 'translations.php', 'navigation_keywords.php', 'scripts/check_templates.php'] as $module) {
    foreach ([0 => false, 1 => false, 2 => true, 99 => true, 100 => false, 200 => false] as $level => $allowed) {
        navigation_test_expect(grompil_can_access_module($module, array_replace($platform, ['role_level' => $level]), null) === $allowed,
            'global management uses the same open level interval as the module');
    }
}
$limitedPlatform = array_replace($platform, ['role_name' => 'Custom role', 'permissions' => ['companies.manage']]);
navigation_test_expect(!grompil_can_access_module('users.php', $limitedPlatform, null), 'platform status does not grant every global permission');
navigation_test_expect(grompil_can_access_module('company_members.php', $platform, null), 'platform membership manager works without an active company');
navigation_test_expect(!grompil_can_access_module('company_roles.php', $platform, null), 'platform company roles still require a company');
$platformCompany = array_replace($company, ['is_platform_operator' => true]);
navigation_test_expect(grompil_can_access_module('company_roles.php', $platform, $platformCompany), 'platform company context grants company administration');
$legacyPlatform = array_replace($platform, ['permissions' => []]);
navigation_test_expect(grompil_can_access_module('companies.php', $legacyPlatform, null), 'existing Beheerder fallback remains supported');

$passwordChange = array_replace($platform, ['must_change_password' => 't']);
navigation_test_expect(grompil_can_access_module('account.php', $passwordChange, null), 'password changes remain reachable');
navigation_test_expect(!grompil_can_access_module('companies.php', $passwordChange, null), 'other modules stay hidden until the required password change');
navigation_test_expect(!grompil_can_access_module('unknown.php', $platform, $platformCompany), 'unregistered modules are not assumed accessible');
echo "Navigation access checks passed; no database changes or external connections made.\n";
