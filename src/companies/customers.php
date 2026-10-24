<?php
/**
 *
 * @date_cre: Sat 2026-10-24 00:00:00
 * @date_mut: Sat 2026-10-24 00:00:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Lets company administrators find and monitor their customers.
 *
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
    $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalizedQuery) . '%';
    $digits = ctype_digit($query) ? $query : '';
    $result = pg_query_params(
        db(),
        "SELECT u.id, u.display_name, u.email, u.acronym
         FROM users AS u
         JOIN roles AS r ON r.id = u.role_id
         JOIN company_users AS cu ON cu.company_id = u.company_id AND cu.user_id = u.id
         JOIN company_roles AS cr ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
         WHERE u.company_id = $1 AND cu.company_id = $1
           AND r.level >= 200 AND cr.level >= 200
           AND (lower(u.display_name) LIKE $2 OR lower(u.email) LIKE $2
                OR lower(u.acronym) LIKE $2 OR u.id = NULLIF($3, '')::bigint)
         ORDER BY lower(u.display_name), u.id
         LIMIT 20",
        [$companyId, $prefix, $digits]
    );
    if (!$result) {
        http_response_code(503);
        echo json_encode(['results' => [], 'error' => 'search_unavailable']);
        exit;
    }
    $matches = [];
    while ($row = pg_fetch_assoc($result)) {
        $matches[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['display_name'],
            'email' => (string) $row['email'],
            'acronym' => (string) ($row['acronym'] ?? ''),
        ];
    }
    echo json_encode(['results' => $matches], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$selectedCustomer = null;
$rawCustomerId = $_GET['customer_id'] ?? null;
$customerId = is_scalar($rawCustomerId) ? filter_var($rawCustomerId, FILTER_VALIDATE_INT) : false;
if ($customerId !== false && $customerId !== null && $customerId > 0) {
    $result = pg_query_params(
        db(),
        "SELECT u.id, u.display_name, u.email, u.acronym, u.is_active,
                u.approval_status, u.created_at, u.last_login_at,
                cu.is_active AS company_active, cu.approval_status AS company_approval_status,
                cr.name AS company_role
         FROM users AS u
         JOIN roles AS r ON r.id = u.role_id
         JOIN company_users AS cu ON cu.company_id = u.company_id AND cu.user_id = u.id
         JOIN company_roles AS cr ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
         WHERE u.company_id = $1 AND cu.company_id = $1 AND u.id = $2
           AND r.level >= 200 AND cr.level >= 200",
        [$companyId, $customerId]
    );
    $selectedCustomer = $result ? pg_fetch_assoc($result) : false;
    if ($selectedCustomer === false) {
        $selectedCustomer = null;
    }
}

$details = '';
if ($selectedCustomer !== null) {
    $dateLabel = static function (?string $value): string {
        if ($value === null || $value === '') {
            return fn_t('never');
        }
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i');
        } catch (Throwable $exception) {
            return $value;
        }
    };
    $details = fn_templateRender('customer_details.html', [
        'customer_name' => (string) $selectedCustomer['display_name'],
        'customer_email' => (string) $selectedCustomer['email'],
        'customer_acronym' => (string) ($selectedCustomer['acronym'] ?? ''),
        'customer_role' => (string) $selectedCustomer['company_role'],
        'customer_account_status' => fn_t($selectedCustomer['is_active'] === 't' ? 'active' : 'inactive'),
        'customer_approval_status' => fn_t('approval_' . (string) $selectedCustomer['approval_status']),
        'customer_company_status' => fn_t($selectedCustomer['company_active'] === 't' ? 'active' : 'inactive'),
        'customer_company_approval_status' => fn_t('approval_' . (string) $selectedCustomer['company_approval_status']),
        'customer_created_at' => $dateLabel((string) $selectedCustomer['created_at']),
        'customer_last_login_at' => $dateLabel($selectedCustomer['last_login_at'] === null ? null : (string) $selectedCustomer['last_login_at']),
        'customers_detail_heading' => fn_t('customers_detail_heading'),
        'customers_email' => fn_t('email'),
        'customers_acronym' => fn_t('acronym'),
        'customers_role' => fn_t('col_role'),
        'customers_account_status' => fn_t('customers_account_status'),
        'customers_approval_status' => fn_t('col_approval'),
        'customers_company_access' => fn_t('customers_company_access'),
        'customers_company_approval' => fn_t('customers_company_approval'),
        'customers_created_at' => fn_t('created_at'),
        'customers_last_login' => fn_t('col_last_login'),
    ]);
} elseif ($customerId !== false && $customerId !== null) {
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
    '_customer_details_html' => $details,
]);
page_end();
