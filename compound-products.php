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
 * @description: Authenticated JSON endpoint for searching compounding raw materials.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:compound-products
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    define('GROMPIL_ROOT', __DIR__);
}

require GROMPIL_ROOT . '/includes/bootstrap.php';
grompil_require_module_access('compound-products.php');
require_once GROMPIL_ROOT . '/includes/compounding_products.php';

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
$queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
if ($queryLength < 3 || strlen($query) > 200) {
    echo json_encode(['query' => $query, 'count' => 0, 'total_count' => 0, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawLimit = $_GET['limit'] ?? 20;
$limit = is_scalar($rawLimit) && filter_var($rawLimit, FILTER_VALIDATE_INT) !== false
    ? min(50, max(1, (int) $rawLimit))
    : 20;

try {
    $totalCount = 0;
    $matches = grompil_compounding_product_search($query, $limit, $totalCount);
} catch (Throwable $error) {
    error_log('Compounding product suggestion lookup failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'search_unavailable']);
    exit;
}

$results = array_map(static function (array $row): array {
    $reimbursement = array_map(static function (array $code): array {
        return [
            'chapter_code' => $code['chapter_code'] ?? '',
            'cnk' => $code['cnk'] ?? '',
            'list_number' => $code['list_number'] ?? '',
            'description_nl' => $code['description_nl'] ?? '',
            'description_fr' => $code['description_fr'] ?? '',
        ];
    }, $row['reimbursement_records'] ?? []);

    $sourceIds = [];
    foreach ($row['source_records'] ?? [] as $source) {
        if (!empty($source['external_id'])) {
            $sourceIds[] = (string) $source['external_id'];
        }
    }

    return [
        'id' => (int) $row['id'],
        'name_nl' => (string) ($row['preferred_name_nl'] ?? ''),
        'name_fr' => (string) ($row['preferred_name_fr'] ?? ''),
        'cas_number' => (string) ($row['cas_number'] ?? ''),
        'ec_number' => (string) ($row['ec_number'] ?? ''),
        'source_ids' => array_values(array_unique($sourceIds)),
        'reimbursement' => $reimbursement,
    ];
}, $matches);

echo json_encode(
    [
        'query' => $query,
        'count' => count($results),
        'total_count' => $totalCount,
        'has_more' => $totalCount > count($results),
        'results' => $results,
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
