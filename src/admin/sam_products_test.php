<?php
/**
 * Platform-only test screen for the read-only SAM product search helper.
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('sam-products-test.php');
if (($currentUser['company_id'] ?? null) !== null || !is_platform_operator($currentUser)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
}
require_once GROMPIL_ROOT . '/includes/sam_products.php';

$rawQuery = $_GET['q'] ?? '';
$query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
$availableStatuses = ['AUTHORIZED', 'REVOKED', 'SUSPENDED'];
$rawStatuses = $_GET['status'] ?? null;
$statuses = array_key_exists('status_filter', $_GET)
    ? (is_array($rawStatuses) ? array_values(array_intersect($availableStatuses, array_filter($rawStatuses, 'is_string'))) : [])
    : $availableStatuses;
$statusFilterHtml = '';
foreach ($availableStatuses as $statusValue) {
    $checked = in_array($statusValue, $statuses, true) ? ' checked' : '';
    $statusFilterHtml .= '<label class="form-check form-check-inline mb-0">'
        . '<input class="form-check-input" type="checkbox" name="status[]" value="' . e($statusValue) . '"' . $checked . ' data-sam-status-filter>'
        . '<span class="form-check-label">' . e($statusValue) . '</span></label>';
}
$matches = [];
$searchError = false;
if ($query !== '') {
    try {
        $matches = grompil_sam_product_search($query, 50, $statuses);
    } catch (Throwable $error) {
        error_log('SAM product test lookup failed: ' . $error->getMessage());
        $searchError = true;
    }
}

$rowsHtml = '';
foreach ($matches as $match) {
    $statusLetters = ['AUTHORIZED' => 'A', 'REVOKED' => 'R', 'SUSPENDED' => 'S'];
    $statusCodes = array_values(array_unique(array_filter([
        $statusLetters[$match['amp_status'] ?? ''] ?? null,
        $statusLetters[$match['ampp_status'] ?? ''] ?? null,
    ])));
    $description = $match['prescription_name_nl'] ?: ($match['prescription_name_fr'] ?? '');
    $package = $match['pack_display_nl'] ?: ($match['pack_display_fr'] ?? '');
    $rowsHtml .= '<tr>'
        . '<td><code>' . e((string) $match['cnk']) . '</code></td>'
        . '<td>' . e((string) $description) . '</td>'
        . '<td>' . e((string) $package) . '</td>'
        . '<td>' . e(implode('/', $statusCodes)) . '</td>'
        . '<td>' . e((string) ($match['atc_class_path'] ?? '')) . '</td>'
        . '</tr>';
}

page_start(fn_t('sam_products_test_title'), $currentUser);
echo fn_templateRender('sam_products_test.html', [
    'sam_products_test_title' => fn_t('sam_products_test_title'),
    'sam_products_test_intro' => fn_t('sam_products_test_intro'),
    'sam_products_test_query_label' => fn_t('sam_products_test_query_label'),
    'sam_products_test_query_placeholder' => fn_t('sam_products_test_query_placeholder'),
    'sam_products_test_help' => fn_t('sam_products_test_help'),
    'sam_products_test_search' => fn_t('sam_products_test_search'),
    'sam_products_test_results' => fn_t('sam_products_test_results'),
    'sam_products_test_no_results' => fn_t('sam_products_test_no_results'),
    'sam_products_test_unavailable' => fn_t('sam_products_test_unavailable'),
    'sam_products_test_status_code' => fn_t('sam_products_test_status_code'),
    'sam_products_test_prescription_name' => fn_t('sam_products_test_prescription_name'),
    'sam_products_test_pack_display' => fn_t('sam_products_test_pack_display'),
    'sam_products_test_atc_class' => fn_t('sam_products_test_atc_class'),
    'sam_products_test_query_value' => $query,
    'sam_products_test_status_filter' => fn_t('sam_products_test_status_filter'),
    '_sam_products_test_status_filters_html' => $statusFilterHtml,
    '_sam_products_test_results_html' => $rowsHtml,
    '_sam_products_test_feedback_html' => $searchError
        ? '<div class="alert alert-danger" role="alert">' . e(fn_t('sam_products_test_unavailable')) . '</div>'
        : ($query !== '' && $matches === []
            ? '<div class="alert alert-info" role="status">' . e(fn_t('sam_products_test_no_results')) . '</div>'
            : ''),
    '_sam_products_test_table_html' => $matches === [] ? '' : '<div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr>'
        . '<th scope="col">CNK</th><th scope="col">' . e(fn_t('sam_products_test_prescription_name')) . '</th>'
        . '<th scope="col">' . e(fn_t('sam_products_test_pack_display')) . '</th><th scope="col">'
        . e(fn_t('sam_products_test_status_code')) . '</th><th scope="col">'
        . e(fn_t('sam_products_test_atc_class')) . '</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>',
    '_sam_products_test_results_heading_html' => $matches === [] ? '' : '<h2 class="h5 mb-3">'
        . e(fn_t('sam_products_test_results')) . ' <span class="badge text-bg-secondary">'
        . count($matches) . '</span></h2>',
]);
page_end();
