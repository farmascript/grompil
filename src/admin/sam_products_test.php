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
$matches = [];
$searchError = false;
if ($query !== '') {
    try {
        $matches = grompil_sam_product_search($query, 50);
    } catch (Throwable $error) {
        error_log('SAM product test lookup failed: ' . $error->getMessage());
        $searchError = true;
    }
}

$rowsHtml = '';
foreach ($matches as $match) {
    $status = trim((string) ($match['ampp_status'] ?? ''));
    if (($match['amp_status'] ?? '') !== '') {
        $status = trim((string) $match['amp_status']) . ' / ' . $status;
    }
    $rowsHtml .= '<tr>'
        . '<td><code>' . e((string) $match['cnk']) . '</code></td>'
        . '<td><code>' . e((string) $match['amp_code']) . '</code><br><small class="text-secondary">'
        . e((string) $match['ampp_code']) . '</small></td>'
        . '<td>' . e($status) . '</td>'
        . '<td><strong>' . e((string) ($match['prescription_name_nl'] ?? '')) . '</strong>'
        . '<br><span class="text-secondary">' . e((string) ($match['prescription_name_fr'] ?? '')) . '</span>'
        . '<br><small>' . e((string) ($match['official_name'] ?? '')) . '</small></td>'
        . '<td>' . e((string) ($match['medicine_type'] ?? '')) . '</td>'
        . '<td class="text-end">' . e(number_format((float) $match['match_score'] * 100, 1)) . '%</td>'
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
    'sam_products_test_amp_ampp' => fn_t('sam_products_test_amp_ampp'),
    'sam_products_test_status' => fn_t('sam_products_test_status'),
    'sam_products_test_prescription_name' => fn_t('sam_products_test_prescription_name'),
    'sam_products_test_medicine_type' => fn_t('sam_products_test_medicine_type'),
    'sam_products_test_match_score' => fn_t('sam_products_test_match_score'),
    'sam_products_test_query_value' => $query,
    '_sam_products_test_results_html' => $rowsHtml,
    '_sam_products_test_feedback_html' => $searchError
        ? '<div class="alert alert-danger" role="alert">' . e(fn_t('sam_products_test_unavailable')) . '</div>'
        : ($query !== '' && $matches === []
            ? '<div class="alert alert-info" role="status">' . e(fn_t('sam_products_test_no_results')) . '</div>'
            : ''),
    '_sam_products_test_table_html' => $matches === [] ? '' : '<div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr>'
        . '<th scope="col">CNK</th><th scope="col">' . e(fn_t('sam_products_test_amp_ampp')) . '</th>'
        . '<th scope="col">' . e(fn_t('sam_products_test_status')) . '</th><th scope="col">'
        . e(fn_t('sam_products_test_prescription_name')) . '</th><th scope="col">'
        . e(fn_t('sam_products_test_medicine_type')) . '</th><th scope="col" class="text-end">'
        . e(fn_t('sam_products_test_match_score')) . '</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>',
    '_sam_products_test_results_heading_html' => $matches === [] ? '' : '<h2 class="h5 mb-3">'
        . e(fn_t('sam_products_test_results')) . ' <span class="badge text-bg-secondary">'
        . count($matches) . '</span></h2>',
]);
page_end();
