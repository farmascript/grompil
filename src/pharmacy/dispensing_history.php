<?php
declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/dispensing_history.php';

$actor = require_login();
$company = require_company_context($actor);
if (!grompil_can_access_module('dispensing-history.php', $actor, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}
$companyId = (int) $company['id'];
$language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));

if (($_GET['history_api'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'method_not_allowed']);
        exit;
    }
    $rawCustomerCode = $_GET['customer_code'] ?? '';
    $customerCode = is_scalar($rawCustomerCode) ? strtoupper(trim((string) $rawCustomerCode)) : '';
    if ($customerCode !== '' && preg_match('/^[A-Z0-9]{6}$/D', $customerCode) !== 1) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_customer']);
        exit;
    }
    $rawScope = $_GET['scope'] ?? '';
    $scope = is_scalar($rawScope) ? (string) $rawScope : '';
    if (($customerCode !== '' && $scope !== 'customer')
        || ($customerCode === '' && !in_array($scope, ['anonymous', 'all'], true))) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_scope']);
        exit;
    }
    $rawQuery = $_GET['q'] ?? '';
    $query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
    $rawPage = $_GET['page'] ?? 1;
    $page = is_scalar($rawPage) && filter_var($rawPage, FILTER_VALIDATE_INT) !== false
        ? max(1, (int) $rawPage) : 1;
    $rawPerPage = $_GET['per_page'] ?? 25;
    $perPage = is_scalar($rawPerPage) && in_array((int) $rawPerPage, [10, 25, 50, 100], true)
        ? (int) $rawPerPage : 25;
    try {
        $dateFrom = grompil_dispensing_history_parse_date($_GET['date_from'] ?? null);
        $dateTo = grompil_dispensing_history_parse_date($_GET['date_to'] ?? null);
    } catch (InvalidArgumentException) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_date']);
        exit;
    }
    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_date_range']);
        exit;
    }
    try {
        $history = grompil_dispensing_history_fetch(
            $companyId, $query, $customerCode, $scope === 'anonymous', $page, $perPage, $language, $dateFrom, $dateTo
        );
        echo json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $error) {
        error_log('Dispensing history lookup failed: ' . get_class($error));
        http_response_code(503);
        echo json_encode(['error' => 'history_unavailable']);
    }
    exit;
}

$rawQuery = $_GET['q'] ?? '';
$query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
$query = function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100);
$rawPage = $_GET['page'] ?? 1;
$page = is_scalar($rawPage) && filter_var($rawPage, FILTER_VALIDATE_INT) !== false
    ? max(1, (int) $rawPage) : 1;
$rawPerPage = $_GET['per_page'] ?? 25;
$perPage = is_scalar($rawPerPage) && in_array((int) $rawPerPage, [10, 25, 50, 100], true)
    ? (int) $rawPerPage : 25;
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$rawDateFrom = array_key_exists('date_from', $_GET) ? $_GET['date_from'] : $today;
$rawDateTo = array_key_exists('date_to', $_GET) ? $_GET['date_to'] : $today;
try {
    $dateFrom = grompil_dispensing_history_parse_date($rawDateFrom);
    $dateTo = grompil_dispensing_history_parse_date($rawDateTo);
} catch (InvalidArgumentException) {
    $dateFrom = $today;
    $dateTo = $today;
}
if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
    $dateFrom = $today;
    $dateTo = $today;
}

try {
    $history = grompil_dispensing_history_fetch(
        $companyId, $query, '', false, $page, $perPage, $language, $dateFrom, $dateTo
    );
} catch (Throwable $error) {
    error_log('Dispensing history page failed: ' . get_class($error));
    grompil_show_error(503, 72805, fn_t('dispensing_history_unavailable'), 'Historiek niet beschikbaar');
}

$sessionGroups = [];
foreach ($history['rows'] as $row) {
    $sessionGroups[$row['history_id']][] = $row;
}
$groupsHtml = '';
foreach ($sessionGroups as $sessionRows) {
    $firstRow = $sessionRows[0];
    $groupsHtml .= '<section class="card mb-3 history-session-card">'
        . '<div class="card-header d-flex align-items-center gap-3 flex-wrap">'
        . '<strong>' . e($firstRow['completed_at_display']) . '</strong>'
        . '<span><span class="text-secondary" title="' . e($firstRow['session_id']) . '">'
        . e(fn_t('dispensing_history_prescription_number')) . ':</span> '
        . ($firstRow['prescription_number'] === null ? '—' : '#' . e((string) $firstRow['prescription_number'])) . '</span>'
        . '</div><div class="table-responsive"><table class="table table-sm table-striped table-hover align-middle mb-0">'
        . '<thead><tr><th>' . e(fn_t('dispensing_history_recipient')) . '</th>'
        . '<th>' . e(fn_t('dispensing_history_status')) . '</th>'
        . '<th>' . e(fn_t('dispensing_history_cnk')) . '</th>'
        . '<th>' . e(fn_t('dispensing_history_product')) . '</th>'
        . '<th class="text-end">' . e(fn_t('dispensing_history_quantity')) . '</th></tr></thead><tbody>';
    foreach ($sessionRows as $row) {
    $productName = $language === 'FR'
        ? ($row['product_name_fr'] ?: $row['product_name_nl'])
        : ($row['product_name_nl'] ?: $row['product_name_fr']);
    $package = $language === 'FR'
        ? ($row['package_display_fr'] ?: $row['package_display_nl'])
        : ($row['package_display_nl'] ?: $row['package_display_fr']);
    $groupsHtml .= '<tr><td><span' . ($row['customer_code'] !== '' ? ' title="' . e($row['customer_code']) . '"' : '') . '>'
        . e($row['party_name']) . '</span></td><td><span class="badge text-bg-light">' . e($row['status_code']) . '</span></td>'
        . '<td><small>' . e($row['cnk']) . '</small></td><td><strong>' . e($productName) . '</strong>'
        . ($package !== '' ? '<span class="ms-2 fw-bold">' . e($package) . '</span>' : '')
        . '</td><td class="text-end">' . e((string) $row['quantity']) . '</td></tr>';
    }
    $groupsHtml .= '</tbody></table></div></section>';
}
if ($groupsHtml === '') {
    $groupsHtml = '<div class="text-center text-secondary py-4">' . e(fn_t('dispensing_history_empty')) . '</div>';
}

$paginationHtml = '<nav aria-label="' . e(fn_t('dispensing_history_pagination')) . '"><ul class="pagination pagination-sm mb-0">';
foreach ([
    [1, fn_t('dispensing_history_first')],
    [max(1, $history['page'] - 1), fn_t('dispensing_history_previous')],
    [min($history['pages'], $history['page'] + 1), fn_t('dispensing_history_next')],
    [$history['pages'], fn_t('dispensing_history_last')],
] as $index => [$targetPage, $label]) {
    $disabled = ($index < 2 && $history['page'] === 1)
        || ($index >= 2 && $history['page'] >= $history['pages']);
    $href = 'dispensing-history.php?' . http_build_query([
        'q' => $query, 'per_page' => $perPage, 'page' => $targetPage,
        'date_from' => $dateFrom ?? '', 'date_to' => $dateTo ?? '',
    ]);
    $paginationHtml .= '<li class="page-item' . ($disabled ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e($href) . '"' . ($disabled ? ' aria-disabled="true" tabindex="-1"' : '') . '>'
        . e($label) . '</a></li>';
}
$paginationHtml .= '</ul></nav>';
$startLine = $history['total'] === 0 ? 0 : (($history['page'] - 1) * $perPage) + 1;
$endLine = min($history['total'], $history['page'] * $perPage);
$countLabel = str_replace(
    ['{start}', '{end}', '{total}'],
    [(string) $startLine, (string) $endLine, (string) $history['total']],
    fn_t('dispensing_history_count')
);

page_start(fn_t('dispensing_history_title'), $actor);
echo fn_templateRender('dispensing_history.html', [
    'dispensing_history_title' => fn_t('dispensing_history_title'),
    'dispensing_history_intro' => fn_t('dispensing_history_intro'),
    'dispensing_history_search_label' => fn_t('dispensing_history_search_label'),
    'dispensing_history_search_placeholder' => fn_t('dispensing_history_search_placeholder'),
    'dispensing_history_search_minimum' => fn_t('dispensing_history_search_minimum'),
    'dispensing_history_date_filter' => fn_t('dispensing_history_date_filter'),
    'dispensing_history_date_from' => fn_t('dispensing_history_date_from'),
    'dispensing_history_date_to' => fn_t('dispensing_history_date_to'),
    'dispensing_history_date_range_error' => fn_t('dispensing_history_date_range_error'),
    'history_language' => strtolower($language),
    'dispensing_history_per_page' => fn_t('dispensing_history_per_page'),
    'dispensing_history_date' => fn_t('dispensing_history_date'),
    'dispensing_history_session' => fn_t('dispensing_history_session'),
    'dispensing_history_prescription_number' => fn_t('dispensing_history_prescription_number'),
    'dispensing_history_recipient' => fn_t('dispensing_history_recipient'),
    'dispensing_history_status' => fn_t('dispensing_history_status'),
    'dispensing_history_cnk' => fn_t('dispensing_history_cnk'),
    'dispensing_history_product' => fn_t('dispensing_history_product'),
    'dispensing_history_package' => fn_t('dispensing_history_package'),
    'dispensing_history_quantity' => fn_t('dispensing_history_quantity'),
    'dispensing_history_search_button' => fn_t('dispensing_history_search_button'),
    'dispensing_history_first' => fn_t('dispensing_history_first'),
    'dispensing_history_previous' => fn_t('dispensing_history_previous'),
    'dispensing_history_next' => fn_t('dispensing_history_next'),
    'dispensing_history_last' => fn_t('dispensing_history_last'),
    '_history_groups_html' => $groupsHtml,
    '_history_pagination_html' => $paginationHtml,
    'history_query_value' => e($query),
    'history_date_from_value' => e($dateFrom ?? ''),
    'history_date_to_value' => e($dateTo ?? ''),
    'history_per_page_10_selected' => $perPage === 10 ? 'selected' : '',
    'history_per_page_25_selected' => $perPage === 25 ? 'selected' : '',
    'history_per_page_50_selected' => $perPage === 50 ? 'selected' : '',
    'history_per_page_100_selected' => $perPage === 100 ? 'selected' : '',
    'history_count_label' => e($countLabel),
    'history_count_template' => e(fn_t('dispensing_history_count')),
    'history_script_url' => e(fn_appBaseHref() . 'assets/dispensing-history.js?v=' . (string) @filemtime(GROMPIL_ROOT . '/assets/dispensing-history.js')),
]);
page_end();
