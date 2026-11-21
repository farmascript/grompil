<?php
declare(strict_types=1);

/**
 * Fetches completed dispensing lines with company, customer/anonymous scope and text filters.
 * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
 */
function grompil_dispensing_history_fetch(
    int $companyId,
    string $query,
    string $customerCode,
    bool $anonymousOnly,
    int $page,
    int $perPage,
    string $language
): array {
    $query = trim($query);
    $customerCode = strtoupper(trim($customerCode));
    $query = function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100);
    $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
    $page = max(1, $page);

    $where = <<<'SQL'
        history.company_id = $1
        AND (
            (NULLIF($2::text, '') IS NOT NULL AND line.customer_code = NULLIF($2::text, ''))
            OR (NULLIF($2::text, '') IS NULL AND (NOT $4::boolean OR line.party_type = 'anonymous'))
        )
        AND (NULLIF($3::text, '') IS NULL OR concat_ws(' ',
            history.dispensing_session_id::text, line.party_name, line.customer_code,
            line.status_key, line.cnk, line.amp_code, line.ampp_code,
            line.product_name_nl, line.product_name_fr,
            line.package_display_nl, line.package_display_fr
        ) ILIKE '%' || $3::text || '%')
        SQL;
    $baseParams = [$companyId, $customerCode, $query, $anonymousOnly ? 'true' : 'false'];

    $countResult = db_query_params(
        'dispensing.history_list_count',
        'SELECT count(*)
         FROM st_dispensing_history_lines line
         JOIN st_dispensing_history history ON history.id = line.history_id
         WHERE ' . $where,
        $baseParams
    );
    $total = (int) pg_fetch_result($countResult, 0, 0);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $result = db_query_params(
        'dispensing.history_list_page',
        'SELECT history.id AS history_id, history.dispensing_session_id::text AS session_id,
                history.completed_at, line.party_type, line.customer_code, line.party_name,
                line.status_key,
                COALESCE(status.short_codes ->> $5, status."key", line.status_key) AS status_code,
                line.cnk, line.product_name_nl, line.product_name_fr,
                line.package_display_nl, line.package_display_fr, line.quantity
         FROM st_dispensing_history_lines line
         JOIN st_dispensing_history history ON history.id = line.history_id
         LEFT JOIN ft_dd_varia status
           ON status.list_key = \'dispensing_statuses\' AND status."key" = line.status_key
         WHERE ' . $where . '
         ORDER BY history.completed_at DESC, history.id DESC, line.sort_order, line.id
         LIMIT $6::integer OFFSET $7::integer',
        [$companyId, $customerCode, $query, $anonymousOnly ? 'true' : 'false', strtoupper($language), $perPage, $offset]
    );
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = [
            'history_id' => (int) $row['history_id'],
            'session_id' => (string) $row['session_id'],
            'completed_at' => (string) $row['completed_at'],
            'completed_at_display' => fn_formatDateDisplay((string) $row['completed_at']),
            'party_type' => (string) $row['party_type'],
            'customer_code' => (string) ($row['customer_code'] ?? ''),
            'party_name' => (string) $row['party_name'],
            'status_key' => (string) $row['status_key'],
            'status_code' => (string) $row['status_code'],
            'cnk' => (string) $row['cnk'],
            'product_name_nl' => (string) $row['product_name_nl'],
            'product_name_fr' => (string) $row['product_name_fr'],
            'package_display_nl' => (string) $row['package_display_nl'],
            'package_display_fr' => (string) $row['package_display_fr'],
            'quantity' => (int) $row['quantity'],
        ];
    }

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
}
