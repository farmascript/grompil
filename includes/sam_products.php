<?php
/**
 * Read-only lookup helpers for the imported public SAM product catalog.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Find SAM catalog rows by exact CNK or fuzzy Dutch/French prescription name.
 *
 * Numeric input is treated as an exact CNK lookup. Other input is matched
 * against both prescription-name columns with PostgreSQL pg_trgm similarity.
 * A CNK can belong to multiple AMP/AMPP registrations, so CNK lookups may
 * return multiple rows.
 *
 * Callers must enforce the appropriate authentication and company context.
 *
 * @return list<array<string, mixed>>
 */
function grompil_sam_product_search(string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '' || strlen($query) > 200) {
        return [];
    }

    $limit = max(1, min($limit, 50));

    // CNKs may be visually grouped with spaces, dots, or hyphens. Preserve
    // leading zeroes and query only the exact normalized code.
    if (preg_match('/^[0-9 .-]+$/', $query) === 1) {
        $cnk = preg_replace('/[ .-]/', '', $query) ?? '';
        if ($cnk === '' || strlen($cnk) > 7) {
            return [];
        }

        $result = pg_query_params(
            db(),
            "SELECT
                sam_export_version, sam_id, validity_date,
                amp_code, ampp_code, cnk,
                amp_status, ampp_status,
                official_name, name_nl, name_fr,
                prescription_name_nl, prescription_name_fr,
                medicine_type,
                delivery_modus_codes_json,
                delivery_modus_specification_codes_json,
                amp_data_json, ampp_data_json, dmpp_data_json,
                1.0::real AS match_score,
                'cnk'::text AS match_type
             FROM public.sam_public_products
             WHERE cnk = $1
             ORDER BY
                CASE WHEN ampp_status = 'AUTHORIZED' THEN 0 ELSE 1 END,
                amp_code, ampp_code
             LIMIT $2",
            [$cnk, $limit]
        );
    } else {
        $queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
        // Trigrams need at least three characters to provide useful indexed matches.
        if ($queryLength < 3) {
            return [];
        }

        $tokenQuery = function_exists('mb_strtolower')
            ? mb_strtolower($query, 'UTF-8')
            : strtolower($query);
        $tokenQuery = preg_replace('/([\p{L}])([\p{N}])/u', '$1 $2', $tokenQuery) ?? $tokenQuery;
        $tokenQuery = preg_replace('/([\p{N}])([\p{L}])/u', '$1 $2', $tokenQuery) ?? $tokenQuery;
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $tokenQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return [];
        }

        $nameFields = ['prescription_name_nl', 'prescription_name_fr'];
        $params = [$query];
        $termConditions = [];
        foreach ($tokens as $token) {
            $placeholder = '$' . (count($params) + 1);
            if (ctype_digit($token)) {
                // Match whole numeric components: searching for 50 must not
                // accidentally return a product containing 150.
                $params[] = '(^|[^0-9])' . $token . '([^0-9]|$)';
                $fieldConditions = array_map(
                    static fn (string $field): string => "COALESCE({$field}, '') ~* {$placeholder}",
                    $nameFields
                );
            } else {
                $params[] = '%' . $token . '%';
                $fieldConditions = array_map(
                    static fn (string $field): string => "lower({$field}) LIKE {$placeholder}",
                    $nameFields
                );
            }
            $termConditions[] = '(' . implode(' OR ', $fieldConditions) . ')';
        }
        $allTermsMatch = '(' . implode(' AND ', $termConditions) . ')';
        $limitPlaceholder = '$' . (count($params) + 1);
        $params[] = $limit;

        $result = pg_query_params(
            db(),
            "SELECT
                sam_export_version, sam_id, validity_date,
                amp_code, ampp_code, cnk,
                amp_status, ampp_status,
                official_name, name_nl, name_fr,
                prescription_name_nl, prescription_name_fr,
                medicine_type,
                delivery_modus_codes_json,
                delivery_modus_specification_codes_json,
                amp_data_json, ampp_data_json, dmpp_data_json,
                GREATEST(
                    COALESCE(similarity(lower(prescription_name_nl), lower($1)), 0),
                    COALESCE(similarity(lower(prescription_name_fr), lower($1)), 0)
                )::real AS match_score,
                CASE WHEN {$allTermsMatch} THEN 'terms' ELSE 'fuzzy' END::text AS match_type
             FROM public.sam_public_products
             WHERE {$allTermsMatch}
                OR lower(prescription_name_nl) % lower($1)
                OR lower(prescription_name_fr) % lower($1)
             ORDER BY
                ({$allTermsMatch}) DESC,
                match_score DESC,
                CASE WHEN ampp_status = 'AUTHORIZED' THEN 0 ELSE 1 END,
                amp_code, ampp_code
             LIMIT {$limitPlaceholder}",
            $params
        );
    }

    if ($result === false) {
        throw new RuntimeException('Unable to search the SAM product catalog.');
    }

    $matches = [];
    while ($row = pg_fetch_assoc($result)) {
        $row['match_score'] = (float) $row['match_score'];
        $matches[] = $row;
    }

    return $matches;
}
