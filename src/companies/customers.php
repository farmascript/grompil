<?php
/**
 *
 * @date_cre: Sat 2026-10-24 00:00:00
 * @date_mut: Sat 2026-11-07 14:54:17
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Lets company administrators find and monitor their customers.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:companies:customers
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$actor = require_login();
$company = require_company_context($actor);
if (!grompil_can_access_module('customers.php', $actor, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}
$companyId = (int) $company['id'];

if (($_GET['ajax'] ?? '') === 'search') {
    header('Cache-Control: no-store, private');
    header('Content-Type: application/json; charset=utf-8');
    $rawQuery = $_GET['q'] ?? '';
    $query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
    $queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
    if ($queryLength < 2 || $queryLength > 80) {
        echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $normalizedQuery = function_exists('mb_strtolower')
        ? mb_strtolower($query, 'UTF-8')
        : strtolower($query);
    $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalizedQuery);
    $contains = '%' . $escapedQuery . '%';
    $exactCustomerCode = preg_match('/^[A-Z0-9]{6}$/i', $query) === 1 ? strtoupper($query) : '';
    $includeDeceased = (string) ($_GET['include_deceased'] ?? '') === '1';
    try {
        $result = db_query_params(
            'customers.search',
            "SELECT customer_code, name_first, name_last, date_of_birth, date_of_death, is_active
             FROM bt_customers
             WHERE company_id = $1
               AND (date_of_death IS NULL OR $4::boolean)
               AND (lower(name_first) LIKE $2 OR lower(name_last) LIKE $2
                    OR lower(name_first || ' ' || name_last) LIKE $2
                    OR lower(name_last || ' ' || name_first) LIKE $2
                    OR customer_code = NULLIF($3, ''))
             ORDER BY lower(name_last), lower(name_first), customer_code
             LIMIT 20",
            [$companyId, $contains, $exactCustomerCode, $includeDeceased ? 'true' : 'false']
        );
    } catch (GrompilDatabaseQueryException) {
        $result = false;
    }
    if (!$result) {
        http_response_code(503);
        echo json_encode(['results' => [], 'error' => 'search_unavailable']);
        exit;
    }
    $matches = [];
    while ($row = pg_fetch_assoc($result)) {
        $matches[] = [
            'customer_code' => (string) $row['customer_code'],
            'name' => trim((string) $row['name_first'] . ' ' . (string) $row['name_last']),
            'date_of_birth' => (string) ($row['date_of_birth'] ?? ''),
            'is_deceased' => !empty($row['date_of_death']),
            'deceased_label' => !empty($row['date_of_death']) ? fn_t('patient_deceased') : '',
            'death_date_display' => !empty($row['date_of_death'])
                ? fn_formatDateDisplay((string) $row['date_of_death']) : '',
            'age_at_death' => fn_ageAtDate(
                (string) ($row['date_of_birth'] ?? ''), (string) ($row['date_of_death'] ?? '')
            ),
            'is_active' => $row['is_active'] === 't',
        ];
    }
    echo json_encode(['results' => $matches], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$selectedCustomer = null;
$rawCustomerCode = $_GET['customer_code'] ?? null;
$customerCode = is_scalar($rawCustomerCode) ? strtoupper(trim((string) $rawCustomerCode)) : '';
$customerCodeValid = preg_match('/^[A-Z0-9]{6}$/', $customerCode) === 1;
if ($customerCodeValid) {
    try {
        $result = db_query_params(
            'customers.load_details',
            "SELECT customer_code, name_first, name_last, date_of_birth, date_of_death, is_active, created_at, updated_at
             FROM bt_customers
             WHERE company_id = $1 AND customer_code = $2",
            [$companyId, $customerCode]
        );
    } catch (GrompilDatabaseQueryException) {
        $result = false;
    }
    $selectedCustomer = $result ? pg_fetch_assoc($result) : false;
    if ($selectedCustomer === false) {
        $selectedCustomer = null;
    }
}

$details = '';
if ($selectedCustomer !== null) {
    $details = fn_templateRender('customer_details.html', [
        'customer_name' => trim((string) $selectedCustomer['name_first'] . ' ' . (string) $selectedCustomer['name_last']),
        'customer_code' => (string) $selectedCustomer['customer_code'],
        'customer_birth_date' => $selectedCustomer['date_of_birth'] === null
            ? fn_t('customers_not_recorded') : fn_formatDateDisplay((string) $selectedCustomer['date_of_birth']),
        'customer_death_date' => empty($selectedCustomer['date_of_death'])
            ? fn_t('customers_not_recorded') : fn_formatDeathDateDisplay(
                (string) ($selectedCustomer['date_of_birth'] ?? ''), (string) $selectedCustomer['date_of_death']
            ),
        'customer_status' => fn_t($selectedCustomer['is_active'] === 't' ? 'active' : 'inactive'),
        'customer_created_at' => fn_formatDateDisplay((string) $selectedCustomer['created_at']),
        'customer_updated_at' => fn_formatDateDisplay((string) $selectedCustomer['updated_at']),
        'customers_detail_heading' => fn_t('customers_detail_heading'),
        'customers_code' => fn_t('customers_code'),
        'customers_birth_date' => fn_t('customer_birth_date'),
        'customers_death_date' => fn_t('patient_death_date'),
        'customers_status' => fn_t('col_status'),
        'customers_created_at' => fn_t('customers_created_at'),
        'customers_updated_at' => fn_t('customers_updated_at'),
    ]);
} elseif ($customerCode !== '') {
    $details = '<div class="alert alert-warning" role="status">' . e(fn_t('customers_not_found')) . '</div>';
}

page_start(fn_t('customers_heading'), $actor);
echo fn_templateRender('customers.html', [
    'customers_heading' => fn_t('customers_heading'),
    'customers_intro' => fn_t('customers_intro'),
    'customers_search_label' => fn_t('customers_search_label'),
    'customers_search_placeholder' => fn_t('customers_search_placeholder'),
    'customers_search_help' => fn_t('customers_search_help'),
    'customers_search_empty' => fn_t('customers_search_empty'),
    'customers_search_unavailable' => fn_t('customers_search_unavailable'),
    'patient_show_deceased' => fn_t('patient_show_deceased'),
    '_customers_deceased_checked' => (string) ($_GET['include_deceased'] ?? '') === '1' ? 'checked' : '',
    '_customer_details_html' => $details,
]);
page_end();
