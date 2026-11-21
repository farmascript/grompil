<?php
declare(strict_types=1);

function grompil_dispensing_history_parse_date(mixed $value): ?string
{
    if (!is_scalar($value)) {
        return null;
    }
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Brussels'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Invalid dispensing history date.');
    }
    return $value;
}

function grompil_dispensing_history_date_boundary(?string $date, bool $exclusiveEnd = false): ?string
{
    if ($date === null) {
        return null;
    }
    $boundary = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('Europe/Brussels'));
    if ($exclusiveEnd) {
        $boundary = $boundary->modify('+1 day');
    }
    return $boundary->format('Y-m-d H:i:sP');
}

/**
 * Fetches completed dispensing sessions with company, customer/anonymous scope and text filters.
 * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
 */
function grompil_dispensing_history_fetch(
    int $companyId,
    string $query,
    string $customerCode,
    bool $anonymousOnly,
    int $page,
    int $perPage,
    string $language,
    ?string $dateFrom = null,
    ?string $dateTo = null
): array {
    $query = trim($query);
    $customerCode = strtoupper(trim($customerCode));
    $query = function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100);
    $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
    $page = max(1, $page);
    $dateFrom = grompil_dispensing_history_parse_date($dateFrom);
    $dateTo = grompil_dispensing_history_parse_date($dateTo);
    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
        throw new InvalidArgumentException('Dispensing history start date is after end date.');
    }
    $fromBoundary = grompil_dispensing_history_date_boundary($dateFrom);
    $toBoundary = grompil_dispensing_history_date_boundary($dateTo, true);

    $sessionWhere = <<<'SQL'
        history.company_id = $1
        AND ($5::timestamptz IS NULL OR history.completed_at >= $5::timestamptz)
        AND ($6::timestamptz IS NULL OR history.completed_at < $6::timestamptz)
        AND EXISTS (
            SELECT 1
            FROM st_dispensing_history_lines match_line
            WHERE match_line.history_id = history.id
              AND (
                (NULLIF($2::text, '') IS NOT NULL AND match_line.customer_code = NULLIF($2::text, ''))
                OR (NULLIF($2::text, '') IS NULL AND (NOT $4::boolean OR match_line.party_type = 'anonymous'))
              )
              AND (NULLIF($3::text, '') IS NULL OR concat_ws(' ',
                history.dispensing_session_id::text, match_line.party_name, match_line.customer_code,
                history.prescription_number::text, match_line.status_key, match_line.cnk,
                match_line.amp_code, match_line.ampp_code, match_line.product_name_nl,
                match_line.product_name_fr, match_line.package_display_nl, match_line.package_display_fr
              ) ILIKE '%' || $3::text || '%')
        )
        SQL;
    $baseParams = [$companyId, $customerCode, $query, $anonymousOnly ? 'true' : 'false', $fromBoundary, $toBoundary];

    $countResult = db_query_params(
        'dispensing.history_list_count',
        'SELECT count(*)
         FROM st_dispensing_history history
         WHERE ' . $sessionWhere,
        $baseParams
    );
    $total = (int) pg_fetch_result($countResult, 0, 0);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $result = db_query_params(
        'dispensing.history_list_page',
        'WITH page_history AS (
            SELECT history.id, history.dispensing_session_id, history.prescription_number, history.completed_at
            FROM st_dispensing_history history
            WHERE ' . $sessionWhere . '
            ORDER BY history.completed_at DESC, history.id DESC
            LIMIT $8::integer OFFSET $9::integer
         )
         SELECT history.id AS history_id, history.dispensing_session_id::text AS session_id,
                history.prescription_number,
                history.completed_at, line.party_type, line.customer_code, line.party_name,
                line.status_key,
                COALESCE(status.short_codes ->> $7, status."key", line.status_key) AS status_code,
                line.cnk, line.product_name_nl, line.product_name_fr,
                line.package_display_nl, line.package_display_fr, line.quantity
         FROM page_history history
         JOIN st_dispensing_history_lines line ON line.history_id = history.id
         LEFT JOIN ft_dd_varia status
           ON status.list_key = \'dispensing_statuses\' AND status."key" = line.status_key
         WHERE (NULLIF($2::text, \'\') IS NOT NULL AND line.customer_code = NULLIF($2::text, \'\'))
            OR (NULLIF($2::text, \'\') IS NULL AND (NOT $4::boolean OR line.party_type = \'anonymous\'))
         ORDER BY history.completed_at DESC, history.id DESC, line.sort_order, line.id',
        [$companyId, $customerCode, $query, $anonymousOnly ? 'true' : 'false', $fromBoundary, $toBoundary,
            strtoupper($language), $perPage, $offset]
    );
    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = [
            'history_id' => (int) $row['history_id'],
            'session_id' => (string) $row['session_id'],
            'prescription_number' => $row['prescription_number'] === null ? null : (int) $row['prescription_number'],
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
