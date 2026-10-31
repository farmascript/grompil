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
 * @description: Shares module access rules between page guards and the URL manager.
 *
 */

declare(strict_types=1);

/**
 *  Unknown modules stay hidden until their entry requirements have been registered.
 *
 * @return array
 */
function grompil_module_access_rules(): array
{
    return [
        'index.php' => ['public' => true],
        'account.php' => [],
        'select-company.php' => [],
        'companies.php' => ['permission' => 'companies.manage', 'level_range' => [1, 100]],
        'platform-settings.php' => ['permission' => 'companies.manage', 'level_range' => [1, 100]],
        'platform-security.php' => ['permission' => 'companies.manage', 'level_range' => [1, 100], 'platform_operator_only' => true],
        'dd-varia.php' => [
            'permission' => 'companies.manage',
            'level_range' => [1, 100],
            'platform_operator_only' => true,
        ],
        'sam-products-test.php' => [
            'permission' => 'companies.manage',
            'level_range' => [1, 100],
            'platform_operator_only' => true,
        ],
        'users.php' => ['permission' => 'users.manage', 'level_range' => [1, 100]],
        'roles.php' => ['permission' => 'roles.manage', 'level_range' => [1, 100]],
        'translations.php' => ['permission' => 'translations.manage', 'level_range' => [1, 100]],
        'navigation_keywords.php' => ['permission' => 'companies.manage', 'level_range' => [1, 100]],
        'scripts/check_templates.php' => ['permission' => 'companies.manage', 'level_range' => [1, 100]],
        'company_members.php' => ['company_permission' => 'company.users.manage', 'platform_without_company' => true],
        'customers.php' => ['company_permission' => 'company.users.manage'],
        'sam-products.php' => [],
        'company_roles.php' => ['company_permission' => 'company.roles.manage'],
        'company_translations.php' => ['company_permission' => 'company.translations.manage'],
        'company_security.php' => ['company_permission' => 'company.settings.manage'],
    ];
}

/**
 *  Evaluate the same level and permission helpers used by the protected modules.
 *
 * @param string $module The module identifier.
 * @param ?array $user The authenticated user record.
 * @param ?array $company The company record.
 * @return bool
 */
function grompil_can_access_module(string $module, ?array $user, ?array $company): bool
{
    $rule = grompil_module_access_rules()[$module] ?? null;
    if ($rule === null) return false;
    if ($rule['public'] ?? false) return true;
    if ($user === null) return false;
    if (($user['must_change_password'] ?? 'f') === 't' && $module !== 'account.php') return false;
    if (isset($rule['level_range']) && !has_level($rule['level_range'][0], $rule['level_range'][1], $user)) return false;
    if (isset($rule['permission']) && !has_permission($rule['permission'], $user)) return false;
    if (($rule['platform_operator_only'] ?? false)
        && (($user['company_id'] ?? null) !== null || !is_platform_operator($user))) return false;
    if (isset($rule['company_permission'])) {
        if (($rule['platform_without_company'] ?? false) && is_platform_operator($user)) return true;
        return $company !== null && has_company_permission($rule['company_permission'], $company, $user);
    }
    return true;
}

/**
 *  Guard global management modules using the rule also exposed to navigation.
 *
 * @param string $module The module identifier.
 * @return array
 */
function grompil_require_module_access(string $module): array
{
    $user = require_login();
    if (!grompil_can_access_module($module, $user, null)) {
        grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
    }
    return $user;
}
