<?php
/**
 * @date_cre: Fri 2026-11-27 15:33:57
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Lists company-scoped sign-ins and sign-outs with filters and pagination.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:companies:login_history
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$actor = require_login();
$isPlatformOperator = is_platform_operator($actor);
$companyOptions = $isPlatformOperator ? available_companies($actor) : [];
$company = null;
$selectedCompanyId = 0;

if ($isPlatformOperator) {
    $requestedCompanyId = filter_var($_GET['company_id'] ?? 0, FILTER_VALIDATE_INT);
    if (is_int($requestedCompanyId) && $requestedCompanyId > 0) {
        foreach ($companyOptions as $option) {
            if ((int) $option['id'] === $requestedCompanyId) {
                $selectedCompanyId = $requestedCompanyId;
                $company = $option;
                break;
            }
        }
    }
    if ($company !== null) {
        set_database_tenant_context($selectedCompanyId, (string) $actor['user_code'], true);
    }
} else {
    $company = require_company_context($actor);
    $selectedCompanyId = (int) $company['id'];
}

if (!grompil_can_access_module('login-history.php', $actor, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}

$query = trim(is_scalar($_GET['q'] ?? null) ? (string) $_GET['q'] : '');
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$fromDate = is_scalar($_GET['from'] ?? null) ? (string) $_GET['from'] : $today;
$toDate = is_scalar($_GET['to'] ?? null) ? (string) $_GET['to'] : $today;
$validateDate = static function (string $date) use ($today): string {
    if ($date === '') return '';
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $parseErrors = DateTimeImmutable::getLastErrors();
    return $parsedDate !== false
        && ($parseErrors === false || ($parseErrors['warning_count'] === 0 && $parseErrors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date
        ? $date
        : $today;
};
$fromDate = $validateDate($fromDate);
$toDate = $validateDate($toDate);
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}
$perPage = is_scalar($_GET['per_page'] ?? null) ? (string) $_GET['per_page'] : '20';
if (!in_array($perPage, ['10', '20', '50', 'all'], true)) {
    $perPage = '20';
}
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = is_int($requestedPage) && $requestedPage > 0 ? $requestedPage : 1;

$rows = [];
$totalRows = 0;
$totalPages = 1;
$pageSize = $perPage === 'all' ? 1 : (int) $perPage;
$offset = 0;
$queryFailed = false;
if ($selectedCompanyId > 0) {
    $conditions = ['h.company_id = $1'];
    $params = [$selectedCompanyId];
    if ($fromDate !== '') {
        $params[] = $fromDate;
        $conditions[] = "h.event_at >= ($" . count($params) . "::date::timestamp AT TIME ZONE 'Europe/Brussels')";
    }
    if ($toDate !== '') {
        $params[] = $toDate;
        $conditions[] = "h.event_at < (($" . count($params) . "::date + 1)::timestamp AT TIME ZONE 'Europe/Brussels')";
    }
    if ($query !== '') {
        $params[] = $query;
        $searchParameter = '$' . count($params);
        $params[] = fn_t('login_history_event_login');
        $loginLabelParameter = '$' . count($params);
        $params[] = fn_t('login_history_event_logout');
        $logoutLabelParameter = '$' . count($params);
        $conditions[] = "(
            strpos(lower(COALESCE(u.display_name, '')), lower($searchParameter)) > 0
            OR strpos(lower(COALESCE(u.name_first, '') || ' ' || COALESCE(u.name_last, '')), lower($searchParameter)) > 0
            OR strpos(lower(h.user_code), lower($searchParameter)) > 0
            OR strpos(lower(h.ip_address), lower($searchParameter)) > 0
            OR strpos(lower(h.event_key), lower($searchParameter)) > 0
            OR strpos(lower(CASE h.event_key WHEN 'login' THEN $loginLabelParameter WHEN 'logout' THEN $logoutLabelParameter ELSE '' END), lower($searchParameter)) > 0
        )";
    }
    $where = implode(' AND ', $conditions);
    try {
        $countResult = db_query_params(
            'login_history.list_count',
            "SELECT COUNT(*) AS total FROM st_login_history h JOIN bt_users u ON u.user_code = h.user_code WHERE $where",
            $params
        );
        $totalRows = (int) (pg_fetch_result($countResult, 0, 'total') ?: 0);
        $pageSize = $perPage === 'all' ? max(1, $totalRows) : (int) $perPage;
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $rowParams = $params;
        $sql = "SELECT h.event_at, h.event_key, h.user_code, h.ip_address,
                       COALESCE(NULLIF(btrim(u.display_name), ''), NULLIF(btrim(u.name_first || ' ' || u.name_last), ''), h.user_code) AS full_name
                FROM st_login_history h
                JOIN bt_users u ON u.user_code = h.user_code
                WHERE $where
                ORDER BY h.event_at DESC, h.id DESC";
        if ($perPage !== 'all') {
            $rowParams[] = $pageSize;
            $rowParams[] = $offset;
            $sql .= ' LIMIT $' . (count($rowParams) - 1) . ' OFFSET $' . count($rowParams);
        }
        $result = db_query_params('login_history.list_rows', $sql, $rowParams);
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
    } catch (GrompilDatabaseQueryException) {
        $queryFailed = true;
        $rows = [];
        $totalRows = 0;
        $totalPages = 1;
        $pageSize = $perPage === 'all' ? 20 : (int) $perPage;
        $offset = 0;
    }
}

$makeUrl = static function (array $overrides = []) use ($query, $fromDate, $toDate, $perPage, $page, $selectedCompanyId): string {
    $parameters = [
        'company_id' => $selectedCompanyId > 0 ? $selectedCompanyId : null,
        'from' => $fromDate,
        'to' => $toDate,
        'q' => $query,
        'per_page' => $perPage,
        'page' => $page,
    ];
    return 'login-history.php?' . http_build_query(array_merge($parameters, $overrides));
};
$pagination = '';
if ($selectedCompanyId > 0 && !$queryFailed) {
    $links = [
        [1, fn_t('login_history_first')],
        [max(1, $page - 1), fn_t('login_history_previous')],
        [min($totalPages, $page + 1), fn_t('login_history_next')],
        [$totalPages, fn_t('login_history_last')],
    ];
    $parts = [];
    foreach ($links as [$targetPage, $label]) {
        $disabled = $targetPage === $page || $totalPages === 1;
        $parts[] = $disabled
            ? '<span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">' . e($label) . '</span>'
            : '<a class="btn btn-sm btn-outline-secondary" href="' . e($makeUrl(['page' => $targetPage])) . '">' . e($label) . '</a>';
    }
    $pagination = '<div class="btn-group" role="group" aria-label="' . e(fn_t('login_history_pagination')) . '">' . implode('', $parts) . '</div>';
}

$bodyRows = '';
foreach ($rows as $row) {
    try {
        $eventAt = (new DateTimeImmutable((string) $row['event_at']))
            ->setTimezone(new DateTimeZone('Europe/Brussels'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        $eventAt = (string) $row['event_at'];
    }
    $eventKey = (string) $row['event_key'];
    $eventLabel = fn_t($eventKey === 'login' ? 'login_history_event_login' : 'login_history_event_logout');
    $bodyRows .= '<tr><td>' . e($eventAt) . '</td><td>' . e($eventLabel) . '</td><td>'
        . e((string) $row['full_name']) . '<div class="small text-secondary">' . e((string) $row['user_code'])
        . '</div></td><td>' . e((string) $row['ip_address']) . '</td></tr>';
}
if ($selectedCompanyId > 0 && $bodyRows === '') {
    $bodyRows = '<tr><td colspan="4" class="text-center text-secondary py-4">'
        . e($queryFailed ? fn_t('login_history_unavailable') : fn_t('login_history_empty')) . '</td></tr>';
}
$pageStart = $totalRows === 0 ? 0 : $offset + 1;
$pageEnd = min($totalRows, $offset + count($rows));
$companyOptionsHtml = '<option value="">' . e(fn_t('login_history_choose_company')) . '</option>';
foreach ($companyOptions as $option) {
    $companyOptionsHtml .= '<option value="' . e((string) $option['id']) . '"'
        . ((int) $option['id'] === $selectedCompanyId ? ' selected' : '') . '>'
        . e((string) $option['name']) . '</option>';
}

page_start(fn_t('login_history_title'), $actor, true);
echo fn_templateRender('login_history.html', [
    'login_history_title' => fn_t('login_history_title'),
    'login_history_intro' => fn_t('login_history_intro'),
    'login_history_company_label' => fn_t('login_history_company_label'),
    'login_history_from_label' => fn_t('login_history_from_label'),
    'login_history_to_label' => fn_t('login_history_to_label'),
    'login_history_query_label' => fn_t('login_history_query_label'),
    'login_history_query_placeholder' => fn_t('login_history_query_placeholder'),
    'login_history_per_page' => fn_t('login_history_per_page'),
    'login_history_search_button' => fn_t('login_history_search_button'),
    'login_history_page_summary' => sprintf(fn_t('login_history_page_summary'), $pageStart, $pageEnd, $totalRows),
    '_login_history_company_options_html' => $companyOptionsHtml,
    '_login_history_pagination_html' => $pagination,
    '_login_history_rows_html' => $bodyRows,
    '_login_history_company_field_html' => $isPlatformOperator
        ? '<div class="col-12 col-lg-3"><label class="form-label" for="login-history-company">' . e(fn_t('login_history_company_label'))
            . '</label><select class="form-select" id="login-history-company" name="company_id" required>' . $companyOptionsHtml . '</select></div>'
        : '',
    '_login_history_from_value' => e($fromDate),
    '_login_history_to_value' => e($toDate),
    '_login_history_query_value' => e($query),
    '_login_history_per_page_options_html' => implode('', array_map(static fn (string $size): string => '<option value="' . $size . '"'
        . ($perPage === $size ? ' selected' : '') . '>' . ($size === 'all' ? e(fn_t('customers_list_all')) : $size) . '</option>', ['10', '20', '50', 'all'])),
    '_login_history_headers_html' => '<th scope="col">' . e(fn_t('login_history_col_date')) . '</th><th scope="col">'
        . e(fn_t('login_history_col_event')) . '</th><th scope="col">' . e(fn_t('login_history_col_user'))
        . '</th><th scope="col">' . e(fn_t('login_history_col_ip')) . '</th>',
    '_login_history_no_company_html' => $selectedCompanyId > 0 ? ''
        : '<div class="alert alert-info mb-0" role="status">' . e(fn_t('login_history_select_company_prompt')) . '</div>',
]);
page_end();
