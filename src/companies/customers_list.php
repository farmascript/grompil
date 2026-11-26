<?php
/**
 * @date_cre: Wed 2026-11-25 20:45:47
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Displays all customer fields with company-scoped search, sorting and pagination.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:companies:customers_list
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$actor = require_login();
$company = require_company_context($actor);
if (!grompil_can_access_module('customers-list.php', $actor, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}

$companyId = (int) $company['id'];
$columns = [
    'id' => 'customers_list_col_id',
    'customer_code' => 'customers_list_col_customer_code',
    'name_first' => 'customers_list_col_name_first',
    'name_last' => 'customers_list_col_name_last',
    'date_of_birth' => 'customers_list_col_date_of_birth',
    'date_of_death' => 'customers_list_col_date_of_death',
    'is_active' => 'customers_list_col_is_active',
    'created_by_user_code' => 'customers_list_col_created_by_user_code',
    'updated_by_user_code' => 'customers_list_col_updated_by_user_code',
    'created_at' => 'customers_list_col_created_at',
    'updated_at' => 'customers_list_col_updated_at',
];
$query = trim(is_scalar($_GET['q'] ?? null) ? (string) $_GET['q'] : '');
$field = is_scalar($_GET['field'] ?? null) ? (string) $_GET['field'] : 'all';
if ($field !== 'all' && !array_key_exists($field, $columns)) {
    $field = 'all';
}
$sort = is_scalar($_GET['sort'] ?? null) ? (string) $_GET['sort'] : 'name_last';
if (!array_key_exists($sort, $columns)) {
    $sort = 'name_last';
}
$direction = strtolower(is_scalar($_GET['direction'] ?? null) ? (string) $_GET['direction'] : 'asc');
$direction = $direction === 'desc' ? 'DESC' : 'ASC';
$perPage = is_scalar($_GET['per_page'] ?? null) ? (string) $_GET['per_page'] : '10';
if (!in_array($perPage, ['10', '20', '50', 'all'], true)) {
    $perPage = '10';
}
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = is_int($requestedPage) && $requestedPage > 0 ? $requestedPage : 1;

$where = 'company_id = $1';
$params = [$companyId];
if ($query !== '') {
    $params[] = $query;
    $searchParameter = '$' . count($params);
    if ($field === 'all') {
        $searchExpressions = array_map(
            static fn (string $column): string => "strpos(lower(COALESCE(\"$column\"::text, '')), lower($searchParameter)) > 0",
            array_keys($columns)
        );
        $where .= ' AND (' . implode(' OR ', $searchExpressions) . ')';
    } else {
        $where .= " AND strpos(lower(COALESCE(\"$field\"::text, '')), lower($searchParameter)) > 0";
    }
}

$rows = [];
$totalRows = 0;
$queryFailed = false;
try {
    $countResult = db_query_params('customers.list_count', "SELECT COUNT(*) AS total FROM bt_customers WHERE $where", $params);
    $totalRows = (int) (pg_fetch_result($countResult, 0, 'total') ?: 0);
    $pageSize = $perPage === 'all' ? max(1, $totalRows) : (int) $perPage;
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $order = "\"$sort\" $direction" . ($sort === 'name_last' ? ', lower(name_first) ASC, id ASC' : ', id ASC');
    $sql = 'SELECT ' . implode(', ', array_map(static fn (string $column): string => '"' . $column . '"', array_keys($columns)))
        . " FROM bt_customers WHERE $where ORDER BY $order";
    if ($perPage !== 'all') {
        $paramsForRows = $params;
        $paramsForRows[] = $pageSize;
        $paramsForRows[] = $offset;
        $sql .= ' LIMIT $' . (count($paramsForRows) - 1) . ' OFFSET $' . count($paramsForRows);
    } else {
        $paramsForRows = $params;
    }
    $result = db_query_params('customers.list_rows', $sql, $paramsForRows);
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }
} catch (GrompilDatabaseQueryException) {
    $queryFailed = true;
    $totalRows = 0;
    $rows = [];
    $totalPages = 1;
    $pageSize = (int) ($perPage === 'all' ? 10 : $perPage);
    $offset = 0;
}

$makeUrl = static function (array $overrides = []) use ($query, $field, $sort, $direction, $perPage, $page): string {
    $parameters = [
        'q' => $query,
        'field' => $field,
        'sort' => $sort,
        'direction' => strtolower($direction),
        'per_page' => $perPage,
        'page' => $page,
    ];
    return 'customers-list.php?' . http_build_query(array_merge($parameters, $overrides));
};
$pagination = '';
if (!$queryFailed) {
    $links = [
        [1, fn_t('customers_list_first')],
        [max(1, $page - 1), fn_t('customers_list_previous')],
        [min($totalPages, $page + 1), fn_t('customers_list_next')],
        [$totalPages, fn_t('customers_list_last')],
    ];
    $parts = [];
    foreach ($links as [$targetPage, $label]) {
        $disabled = $targetPage === $page || $totalPages === 1;
        $parts[] = $disabled
            ? '<span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">' . e($label) . '</span>'
            : '<a class="btn btn-sm btn-outline-secondary" href="' . e($makeUrl(['page' => $targetPage])) . '">' . e($label) . '</a>';
    }
    $pagination = '<div class="btn-group" role="group" aria-label="' . e(fn_t('customers_list_pagination')) . '">' . implode('', $parts) . '</div>';
}

$headers = '';
foreach ($columns as $column => $translationKey) {
    $nextDirection = $sort === $column && $direction === 'ASC' ? 'desc' : 'asc';
    $indicator = $sort === $column ? ($direction === 'ASC' ? ' ▲' : ' ▼') : '';
    $headers .= '<th scope="col"><a class="link-body-emphasis text-decoration-none" title="'
        . e($translationKey) . '" data-bs-toggle="tooltip" data-bs-title="' . e($translationKey) . '" tabindex="0" href="'
        . e($makeUrl(['sort' => $column, 'direction' => $nextDirection, 'page' => 1])) . '">'
        . e(fn_t($translationKey)) . $indicator . '</a></th>';
}

$bodyRows = '';
foreach ($rows as $row) {
    $bodyRows .= '<tr>';
    foreach ($columns as $column => $_translationKey) {
        $value = $row[$column] ?? null;
        if ($value === null || $value === '') {
            $display = '—';
        } elseif ($column === 'is_active') {
            $display = fn_t($value === 't' ? 'active' : 'inactive');
        } elseif (in_array($column, ['date_of_birth', 'date_of_death'], true)) {
            try {
                $display = (new DateTimeImmutable((string) $value))->format('Y-m-d');
            } catch (Throwable) {
                $display = (string) $value;
            }
        } elseif (in_array($column, ['created_at', 'updated_at'], true)) {
            try {
                $display = (new DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $display = (string) $value;
            }
        } else {
            $display = (string) $value;
        }
        $bodyRows .= '<td>' . e($display) . '</td>';
    }
    $bodyRows .= '</tr>';
}
if ($bodyRows === '') {
    $bodyRows = '<tr><td colspan="' . count($columns) . '" class="text-center text-secondary py-4">'
        . e($queryFailed ? fn_t('customers_list_unavailable') : fn_t('customers_list_empty')) . '</td></tr>';
}

$pageStart = $totalRows === 0 ? 0 : $offset + 1;
$pageEnd = min($totalRows, $offset + count($rows));
page_start(fn_t('customers_list_title'), $actor, true);
echo fn_templateRender('customers_list.html', [
    'customers_list_title' => fn_t('customers_list_title'),
    'customers_list_intro' => fn_t('customers_list_intro'),
    'customers_list_search_label' => fn_t('customers_list_search_label'),
    'customers_list_query_label' => fn_t('customers_list_query_label'),
    'customers_list_field_label' => fn_t('customers_list_field_label'),
    'customers_list_search_placeholder' => fn_t('customers_list_search_placeholder'),
    'customers_list_search_all' => fn_t('customers_list_search_all'),
    'customers_list_per_page' => fn_t('customers_list_per_page'),
    'customers_list_search_button' => fn_t('customers_list_search_button'),
    'customers_list_page_summary' => sprintf(fn_t('customers_list_page_summary'), $pageStart, $pageEnd, $totalRows),
    '_customers_list_pagination_html' => $pagination,
    '_customers_list_headers_html' => $headers,
    '_customers_list_rows_html' => $bodyRows,
    '_customers_list_search_value' => e($query),
    '_customers_list_field_options_html' => '<option value="all"' . ($field === 'all' ? ' selected' : '') . '>' . e(fn_t('customers_list_search_all')) . '</option>'
        . implode('', array_map(static fn (string $column, string $translationKey): string => '<option value="' . e($column) . '"'
            . ($field === $column ? ' selected' : '') . '>' . e(fn_t($translationKey)) . '</option>', array_keys($columns), array_values($columns))),
    '_customers_list_per_page_options_html' => implode('', array_map(static fn (string $size): string => '<option value="' . $size . '"'
        . ($perPage === $size ? ' selected' : '') . '>' . ($size === 'all' ? e(fn_t('customers_list_all')) : $size) . '</option>', ['10', '20', '50', 'all'])),
]);
page_end();
