<?php
/**
 * @date_cre: Fri 2026-11-13
 * @date_mut: Fri 2026-11-13
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Platform-only read-only search page for compounding raw materials.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:admin:compound_products_test
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
$currentUser = grompil_require_module_access('compound-products-test.php');
if (($currentUser['company_id'] ?? null) !== null || !is_platform_operator($currentUser)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
}
require_once GROMPIL_ROOT . '/includes/compounding_products.php';

$rawQuery = $_GET['q'] ?? '';
$query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
$rawMaterialIdInput = $_GET['raw_material_id'] ?? '';
$rawMaterialId = is_scalar($rawMaterialIdInput) && ctype_digit((string) $rawMaterialIdInput)
    ? (int) $rawMaterialIdInput
    : null;
$matches = [];
$totalCount = 0;
$searchError = false;
if ($query !== '' || $rawMaterialId !== null) {
    try {
        $matches = grompil_compounding_product_search($query, 50, $totalCount, $rawMaterialId);
    } catch (Throwable $error) {
        error_log('Compounding product test lookup failed: ' . $error->getMessage());
        $searchError = true;
    }
}

$resultsHtml = '';
foreach ($matches as $match) {
    $sourceRows = '';
    foreach ($match['source_records'] as $source) {
        $sourceRows .= '<tr><td>' . e((string) ($source['source_system'] ?? '')) . '</td>'
            . '<td>' . e((string) ($source['source_record_type'] ?? '')) . '</td>'
            . '<td><code>' . e((string) ($source['external_id'] ?? '')) . '</code></td>'
            . '<td>' . e((string) ($source['source_status'] ?? '')) . '</td>'
            . '<td>' . e(trim((string) ($source['valid_from'] ?? '') . ' – ' . (string) ($source['valid_to'] ?? ''))) . '</td></tr>';
    }
    $reimbursementRows = '';
    foreach ($match['reimbursement_records'] as $code) {
        $description = $code['description_nl'] ?: ($code['description_fr'] ?? '');
        $conditions = $code['conditions_nl'] ?: ($code['conditions_fr'] ?? '');
        $reimbursementRows .= '<tr><td>' . e((string) ($code['chapter_code'] ?? '')) . '</td>'
            . '<td><code>' . e((string) ($code['cnk'] ?? '')) . '</code></td>'
            . '<td>' . e((string) ($code['list_number'] ?? '')) . '</td>'
            . '<td>' . e((string) $description) . '</td>'
            . '<td>' . e((string) $conditions) . '</td>'
            . '<td>' . e(trim((string) ($code['valid_from'] ?? '') . ' – ' . (string) ($code['valid_to'] ?? ''))) . '</td></tr>';
    }
    $synonyms = array_merge($match['synonyms_nl'], $match['synonyms_fr']);
    $detailRows = [
        fn_t('compound_products_test_cas') => (string) ($match['cas_number'] ?? ''),
        fn_t('compound_products_test_ec') => (string) ($match['ec_number'] ?? ''),
        fn_t('compound_products_test_formula') => (string) ($match['chemical_formula'] ?? ''),
        fn_t('compound_products_test_molecular_mass') => (string) ($match['molecular_mass'] ?? ''),
        fn_t('compound_products_test_pharmacopoeia') => (string) ($match['pharmacopoeia_reference'] ?? ''),
        fn_t('compound_products_test_synonyms') => implode('; ', array_filter($synonyms, 'is_string')),
    ];
    $detailsHtml = '';
    foreach ($detailRows as $label => $value) {
        if ($value !== '') {
            $detailsHtml .= '<dt class="col-sm-3">' . e((string) $label) . '</dt><dd class="col-sm-9">' . e($value) . '</dd>';
        }
    }
    $activeLabel = ($match['is_active'] ?? 'f') === 't' ? fn_t('compound_products_test_active') : fn_t('compound_products_test_inactive');
    $resultsHtml .= '<article class="card mb-3"><div class="card-body">'
        . '<div class="d-flex flex-wrap align-items-baseline gap-2 mb-2"><h2 class="h5 mb-0">'
        . e((string) ($match['preferred_name_nl'] ?: fn_t('compound_products_test_unnamed'))) . '</h2>'
        . '<span class="text-secondary">' . e((string) ($match['preferred_name_fr'] ?? '')) . '</span>'
        . '<span class="badge text-bg-' . (($match['is_active'] ?? 'f') === 't' ? 'success' : 'secondary') . '">' . e($activeLabel) . '</span></div>'
        . '<dl class="row small mb-3">' . $detailsHtml . '</dl>'
        . '<h3 class="h6">' . e(fn_t('compound_products_test_sources')) . '</h3>'
        . ($sourceRows === '' ? '<p class="text-secondary small">' . e(fn_t('compound_products_test_no_sources')) . '</p>'
            : '<div class="table-responsive mb-3"><table class="table table-sm table-striped align-middle"><thead><tr><th>' . e(fn_t('compound_products_test_source')) . '</th><th>' . e(fn_t('compound_products_test_record_type')) . '</th><th>' . e(fn_t('compound_products_test_identifier')) . '</th><th>' . e(fn_t('compound_products_test_status')) . '</th><th>' . e(fn_t('compound_products_test_validity')) . '</th></tr></thead><tbody>' . $sourceRows . '</tbody></table></div>')
        . '<h3 class="h6">' . e(fn_t('compound_products_test_reimbursement')) . '</h3>'
        . ($reimbursementRows === '' ? '<p class="text-secondary small mb-0">' . e(fn_t('compound_products_test_no_reimbursement')) . '</p>'
            : '<div class="table-responsive"><table class="table table-sm table-striped align-middle"><thead><tr><th>' . e(fn_t('compound_products_test_chapter')) . '</th><th>CNK</th><th>' . e(fn_t('compound_products_test_list_number')) . '</th><th>' . e(fn_t('compound_products_test_description')) . '</th><th>' . e(fn_t('compound_products_test_conditions')) . '</th><th>' . e(fn_t('compound_products_test_validity')) . '</th></tr></thead><tbody>' . $reimbursementRows . '</tbody></table></div>')
        . '</div></article>';
}

$resultsHeading = '';
if ($matches !== []) {
    $countMessage = str_replace(['{shown}', '{total}'], [(string) count($matches), (string) $totalCount], fn_t('compound_products_test_showing_results'));
    $resultsHeading = '<h2 class="h5 mb-3">' . e(fn_t('compound_products_test_results')) . ' <span class="badge text-bg-secondary">' . count($matches) . '</span></h2>'
        . '<p class="text-secondary small">' . e($countMessage) . '</p>';
}

page_start(fn_t('compound_products_test_title'), $currentUser);
echo fn_templateRender('compound_products_test.html', [
    'compound_products_test_title' => fn_t('compound_products_test_title'),
    'compound_products_test_intro' => fn_t('compound_products_test_intro'),
    'compound_products_test_query_label' => fn_t('compound_products_test_query_label'),
    'compound_products_test_help' => fn_t('compound_products_test_help'),
    'compound_products_test_search' => fn_t('compound_products_test_search'),
    'compound_products_test_query_value' => $query,
    'compound_products_test_raw_material_id' => $rawMaterialId !== null ? (string) $rawMaterialId : '',
    'compound_products_test_search_limit' => '20',
    'compound_products_test_search_url' => e(fn_appBaseHref() . 'compound-products.php'),
    'compound_products_test_search_error' => e(fn_t('compound_products_test_unavailable')),
    'compound_products_test_search_more' => e(fn_t('compound_products_test_showing_results')),
    'compound_products_test_language' => strtolower((string) ($_SESSION['language'] ?? 'NL')),
    '_compound_products_test_feedback_html' => $searchError
        ? '<div class="alert alert-danger" role="alert">' . e(fn_t('compound_products_test_unavailable')) . '</div>'
        : ($query !== '' && $matches === [] ? '<div class="alert alert-info" role="status">' . e(fn_t('compound_products_test_no_results')) . '</div>' : ''),
    '_compound_products_test_results_heading_html' => $resultsHeading,
    '_compound_products_test_results_html' => $resultsHtml,
]);
page_end();
