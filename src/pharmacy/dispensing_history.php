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
    $rawQuery = $_GET['q'] ?? '';
    $query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
    $rawPage = $_GET['page'] ?? 1;
    $page = is_scalar($rawPage) && filter_var($rawPage, FILTER_VALIDATE_INT) !== false
        ? max(1, (int) $rawPage) : 1;
    $rawPerPage = $_GET['per_page'] ?? 25;
    $perPage = is_scalar($rawPerPage) && in_array((int) $rawPerPage, [10, 25, 50, 100], true)
        ? (int) $rawPerPage : 25;
    try {
        $history = grompil_dispensing_history_fetch($companyId, $query, $customerCode, $page, $perPage, $language);
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

try {
    $history = grompil_dispensing_history_fetch($companyId, $query, '', $page, $perPage, $language);
} catch (Throwable $error) {
    error_log('Dispensing history page failed: ' . get_class($error));
    grompil_show_error(503, 72805, fn_t('dispensing_history_unavailable'), 'Historiek niet beschikbaar');
}

$rowsHtml = '';
foreach ($history['rows'] as $row) {
    $productName = $language === 'FR'
        ? ($row['product_name_fr'] ?: $row['product_name_nl'])
        : ($row['product_name_nl'] ?: $row['product_name_fr']);
    $package = $language === 'FR'
        ? ($row['package_display_fr'] ?: $row['package_display_nl'])
        : ($row['package_display_nl'] ?: $row['package_display_fr']);
    $sessionShort = substr((string) $row['session_id'], 0, 8);
    $rowsHtml .= '<tr>'
        . '<td class="text-nowrap">' . e($row['completed_at_display']) . '</td>'
        . '<td><code title="' . e($row['session_id']) . '">' . e($sessionShort) . '…</code></td>'
        . '<td>' . e($row['party_name'])
        . ($row['customer_code'] !== '' ? '<span class="d-block small text-secondary">' . e($row['customer_code']) . '</span>' : '')
        . '</td>'
        . '<td><span class="badge text-bg-light">' . e($row['status_code']) . '</span></td>'
        . '<td><small>' . e($row['cnk']) . '</small></td>'
        . '<td><strong>' . e($productName) . '</strong></td>'
        . '<td><strong>' . e($package) . '</strong></td>'
        . '<td class="text-end">' . e((string) $row['quantity']) . '</td>'
        . '</tr>';
}
if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="8" class="text-center text-secondary py-4">' . e(fn_t('dispensing_history_empty')) . '</td></tr>';
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
    'dispensing_history_per_page' => fn_t('dispensing_history_per_page'),
    'dispensing_history_date' => fn_t('dispensing_history_date'),
    'dispensing_history_session' => fn_t('dispensing_history_session'),
    'dispensing_history_recipient' => fn_t('dispensing_history_recipient'),
    'dispensing_history_status' => fn_t('dispensing_history_status'),
    'dispensing_history_cnk' => fn_t('dispensing_history_cnk'),
    'dispensing_history_product' => fn_t('dispensing_history_product'),
    'dispensing_history_package' => fn_t('dispensing_history_package'),
    'dispensing_history_quantity' => fn_t('dispensing_history_quantity'),
    'dispensing_history_search_button' => fn_t('dispensing_history_search_button'),
    '_history_rows_html' => $rowsHtml,
    '_history_pagination_html' => $paginationHtml,
    'history_query_value' => e($query),
    'history_per_page_10_selected' => $perPage === 10 ? 'selected' : '',
    'history_per_page_25_selected' => $perPage === 25 ? 'selected' : '',
    'history_per_page_50_selected' => $perPage === 50 ? 'selected' : '',
    'history_per_page_100_selected' => $perPage === 100 ? 'selected' : '',
    'history_count_label' => e($countLabel),
]);
page_end();
