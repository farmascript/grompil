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
 * against the displayed prescription name and package display. PostgreSQL
 * pg_trgm similarity is added when the extension is available.
 * A CNK can belong to multiple AMP/AMPP registrations, so CNK lookups may
 * return multiple rows.
 *
 * Callers must enforce the appropriate authentication and company context.
 *
 * @return list<array<string, mixed>>
 */
function grompil_sam_product_search(string $query, int $limit = 20, ?array $statuses = null, ?int &$totalCount = null): array
{
    $query = trim($query);
    $totalCount = 0;
    if ($query === '' || strlen($query) > 200) {
        return [];
    }

    $limit = max(1, min($limit, grompil_sam_product_search_limit()));
    $allowedStatuses = ['AUTHORIZED', 'REVOKED', 'SUSPENDED'];
    $requestedStatuses = $statuses === null
        ? $allowedStatuses
        : array_map('strtoupper', array_filter($statuses, 'is_string'));
    $statuses = $statuses === null
        ? $requestedStatuses
        : array_values(array_unique(array_intersect($allowedStatuses, $requestedStatuses)));
    if ($statuses === []) {
        return [];
    }

    $makeStatusFilter = static function (array &$params) use ($statuses): string {
        $placeholders = [];
        foreach ($statuses as $status) {
            $placeholder = '$' . (count($params) + 1);
            $params[] = $status;
            $placeholders[] = $placeholder;
        }
        $selected = implode(', ', $placeholders);
        return "(amp_status IN ({$selected}) AND ampp_status IN ({$selected}))";
    };

    // CNKs may be visually grouped with spaces, dots, or hyphens. Preserve
    // leading zeroes and query only the exact normalized code.
    if (preg_match('/^[0-9 .-]+$/', $query) === 1) {
        $cnk = preg_replace('/[ .-]/', '', $query) ?? '';
        if ($cnk === '' || strlen($cnk) > 7) {
            return [];
        }

        $params = [$cnk, $limit];
        $statusFilter = $makeStatusFilter($params);
        $result = pg_query_params(
            db(),
            "SELECT
                sam_export_version, sam_id, validity_date,
                count(*) OVER() AS total_count,
                amp_code, ampp_code, cnk,
                amp_status, ampp_status,
                official_name, name_nl, name_fr,
                prescription_name_nl, prescription_name_fr,
                pack_display_nl, pack_display_fr,
                medicine_type,
                COALESCE(ampp_data_json -> 'Atc' ->> '@code', amp_data_json -> 'Atc' ->> '@code') AS atc_code,
                " . samAtcClassPathSql() . " AS atc_class_path,
                delivery_modus_codes_json,
                delivery_modus_specification_codes_json,
                amp_data_json, ampp_data_json, dmpp_data_json,
                1.0::real AS match_score,
                'cnk'::text AS match_type
             FROM public.sam_public_products
             WHERE cnk = $1
               AND {$statusFilter}
             ORDER BY
                CASE WHEN ampp_status = 'AUTHORIZED' THEN 0 ELSE 1 END,
                amp_code, ampp_code
             LIMIT $2",
            $params
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

        $nameFields = [
            "COALESCE(NULLIF(prescription_name_nl, ''), prescription_name_fr)",
            "COALESCE(NULLIF(pack_display_nl, ''), pack_display_fr)",
        ];
        // A compact alphabetic query can be an abbreviation made by taking
        // the start of successive words (for example "dafcod" for
        // "Dafalgan Codeine"). Keep the full token as a candidate and also
        // try word-prefix splits for compact abbreviations.
        $alternatives = [[
            'terms' => array_map(
                static fn (string $token): array => ['value' => $token, 'word_prefix' => false],
                $tokens
            ),
        ]];
        $compactQuery = preg_match('/^[\p{L}\p{N}]+$/u', $query) === 1;
        if ($compactQuery) {
            foreach ($tokens as $tokenIndex => $token) {
                $isLetters = preg_match('/^\p{L}{6,}$/u', $token) === 1;
                $isLongNumber = ctype_digit($token) && strlen($token) >= 4;
                if (!$isLetters && !$isLongNumber) {
                    continue;
                }
                $length = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);
                for ($splitAt = 2; $splitAt <= $length - 2 && count($alternatives) < 30; $splitAt++) {
                    $left = function_exists('mb_substr') ? mb_substr($token, 0, $splitAt, 'UTF-8') : substr($token, 0, $splitAt);
                    $right = function_exists('mb_substr') ? mb_substr($token, $splitAt, null, 'UTF-8') : substr($token, $splitAt);
                    $candidate = array_map(
                        static fn (string $part): array => ['value' => $part, 'word_prefix' => false],
                        $tokens
                    );
                    array_splice(
                        $candidate,
                        $tokenIndex,
                        1,
                        [
                            ['value' => $left, 'word_prefix' => $isLetters],
                            ['value' => $right, 'word_prefix' => $isLetters],
                        ]
                    );
                    $alternatives[] = ['terms' => $candidate];
                }
            }
        }

        $params = [$query];
        $alternativeConditions = [];
        foreach ($alternatives as $alternative) {
            $termConditions = [];
            foreach ($alternative['terms'] as $term) {
                $token = $term['value'];
                $placeholder = '$' . (count($params) + 1);
                if (ctype_digit($token)) {
                    // Match whole numeric components so 50 does not match 150.
                    $params[] = '(^|[^0-9])' . $token . '([^0-9]|$)';
                    $fieldConditions = array_map(
                        static fn (string $field): string => "COALESCE({$field}, '') ~* {$placeholder}",
                        $nameFields
                    );
                } elseif ($term['word_prefix']) {
                    $params[] = '(^|[^[:alnum:]])' . $token;
                    $fieldConditions = array_map(
                        static fn (string $field): string => "lower(COALESCE({$field}, '')) ~* {$placeholder}",
                        $nameFields
                    );
                } else {
                    $params[] = '%' . $token . '%';
                    $fieldConditions = array_map(
                        static fn (string $field): string => "lower(COALESCE({$field}, '')) LIKE {$placeholder}",
                        $nameFields
                    );
                    if ($compactQuery && preg_match('/^\p{L}+$/u', $token) === 1) {
                        $normalizedPlaceholder = '$' . (count($params) + 1);
                        $params[] = '%' . $token . '%';
                        $fieldConditions = array_merge(
                            $fieldConditions,
                            array_map(
                                static fn (string $field): string => "regexp_replace(lower(COALESCE({$field}, '')), '[^[:alnum:]]', '', 'g') LIKE {$normalizedPlaceholder}",
                                $nameFields
                            )
                        );
                    }
                }
                $termConditions[] = '(' . implode(' OR ', $fieldConditions) . ')';
            }
            $alternativeConditions[] = '(' . implode(' AND ', $termConditions) . ')';
        }
        $allTermsMatch = '(' . implode(' OR ', $alternativeConditions) . ')';
        $limitPlaceholder = '$' . (count($params) + 1);
        $params[] = $limit;
        $statusFilter = $makeStatusFilter($params);

        $extensionResult = pg_query(db(), "SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm')");
        $trigramsAvailable = $extensionResult !== false
            && pg_fetch_result($extensionResult, 0, 0) === 't';
        $displayName = $nameFields[0];
        $displayPackage = $nameFields[1];
        $similaritySelect = $trigramsAvailable
            ? "GREATEST(
                    COALESCE(similarity(lower({$displayName}), lower($1)), 0),
                    COALESCE(similarity(lower({$displayPackage}), lower($1)), 0)
                )::real"
            : "CASE WHEN lower(COALESCE({$displayName}, '')) LIKE lower($1) || '%'
                       OR lower(COALESCE({$displayPackage}, '')) LIKE lower($1) || '%'
                   THEN 1.0 ELSE 0.0 END::real";
        $fuzzyConditions = $trigramsAvailable
            ? "OR lower({$displayName}) % lower($1)
                    OR lower({$displayPackage}) % lower($1)"
            : '';
        if (count($tokens) > 1) {
            $fuzzyConditions = '';
        }

        $result = pg_query_params(
            db(),
            "SELECT
                sam_export_version, sam_id, validity_date,
                count(*) OVER() AS total_count,
                amp_code, ampp_code, cnk,
                amp_status, ampp_status,
                official_name, name_nl, name_fr,
                prescription_name_nl, prescription_name_fr,
                pack_display_nl, pack_display_fr,
                medicine_type,
                COALESCE(ampp_data_json -> 'Atc' ->> '@code', amp_data_json -> 'Atc' ->> '@code') AS atc_code,
                " . samAtcClassPathSql() . " AS atc_class_path,
                delivery_modus_codes_json,
                delivery_modus_specification_codes_json,
                amp_data_json, ampp_data_json, dmpp_data_json,
                {$similaritySelect} AS match_score,
                CASE WHEN {$allTermsMatch} THEN 'terms' ELSE 'fuzzy' END::text AS match_type
             FROM public.sam_public_products
             WHERE (
                    {$allTermsMatch}
                    {$fuzzyConditions}
                )
               AND {$statusFilter}
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
        $totalCount = (int) $row['total_count'];
        unset($row['total_count']);
        $row['match_score'] = (float) $row['match_score'];
        $matches[] = $row;
    }

    return $matches;
}

/** Maximum rows per SAM product search, configurable per installation. */
function grompil_sam_product_search_limit(): int
{
    try {
        $settings = grompil_deployment_settings(GROMPIL_ROOT);
        $configured = $settings['sam_product_search_limit'] ?? 20;
        if (is_string($configured) && ctype_digit($configured)) {
            $configured = (int) $configured;
        }
        if (!is_int($configured) || $configured < 1) {
            return 20;
        }
        return min($configured, 100);
    } catch (Throwable) {
        return 20;
    }
}

/** Return the readable ATC hierarchy through the pharmacological subgroup. */
function samAtcClassPathSql(): string
{
    $atcCode = "COALESCE(ampp_data_json -> 'Atc' ->> '@code', amp_data_json -> 'Atc' ->> '@code')";
    return "COALESCE((
        SELECT string_agg(classification.description, ' · ' ORDER BY atc_level.level_no)
        FROM (VALUES
            (1, left({$atcCode}, 1)),
            (2, left({$atcCode}, 3)),
            (3, left({$atcCode}, 4)),
            (4, left({$atcCode}, 5))
        ) AS atc_level(level_no, code)
        JOIN public.sam_atc_classifications AS classification
          ON classification.atc_code = atc_level.code
    ), '')";
}
