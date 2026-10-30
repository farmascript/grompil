<?php
/**
 * Authenticated JSON endpoint for searching the imported SAM product catalog.
 *
 * Examples:
 *   /sam-products.php?q=0046128
 *   /sam-products.php?q=Haldol%205%20mg%2Fml
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/sam_products.php';
grompil_require_module_access('sam-products.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$rawQuery = $_GET['q'] ?? '';
$query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
if ($query === '') {
    http_response_code(400);
    echo json_encode([
        'error' => 'query_required',
        'examples' => [
            'cnk' => 'sam-products.php?q=0046128',
            'prescription_name' => 'sam-products.php?q=Haldol%205%20mg%2Fml',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$rawLimit = $_GET['limit'] ?? 20;
$limit = is_scalar($rawLimit) && filter_var($rawLimit, FILTER_VALIDATE_INT) !== false
    ? (int) $rawLimit
    : 20;

try {
    $matches = grompil_sam_product_search($query, $limit);
} catch (Throwable $error) {
    error_log('SAM product lookup failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'search_unavailable']);
    exit;
}

$results = array_map(static function (array $row): array {
    return [
        'match_type' => $row['match_type'],
        'match_score' => $row['match_score'],
        'cnk' => $row['cnk'],
        'amp_code' => $row['amp_code'],
        'ampp_code' => $row['ampp_code'],
        'amp_status' => $row['amp_status'],
        'ampp_status' => $row['ampp_status'],
        'official_name' => $row['official_name'],
        'name_nl' => $row['name_nl'],
        'name_fr' => $row['name_fr'],
        'prescription_name_nl' => $row['prescription_name_nl'],
        'prescription_name_fr' => $row['prescription_name_fr'],
        'medicine_type' => $row['medicine_type'],
    ];
}, $matches);

echo json_encode(
    ['query' => $query, 'count' => count($results), 'results' => $results],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
