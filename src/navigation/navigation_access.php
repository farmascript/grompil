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
 * @description: Returns modules accessible to the current session and company.
 *
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/module_guard.php';
require GROMPIL_ROOT . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$user = current_user();
$company = $user !== null ? current_company_context($user) : null;
$allowedModules = [];
$companyId = (int) ($_SESSION['active_company_id'] ?? 0);
$ipAllowed = $user === null || $companyId < 1 || is_platform_operator($user)
    || company_management_ip_allowed($companyId);
foreach (grompil_module_access_rules() as $module => $rule) {
    if (($ipAllowed || ($rule['public'] ?? false)) && grompil_can_access_module($module, $user, $company)) {
        $allowedModules[] = $module;
    }
}
echo json_encode([
    'allowedModules' => $allowedModules,
    'knownModules' => array_keys(grompil_module_access_rules()),
    'logout' => $user === null ? null : [
        'csrfToken' => csrf_token(),
        'companySlug' => (string) ($company['slug'] ?? ''),
    ],
], JSON_UNESCAPED_SLASHES);
