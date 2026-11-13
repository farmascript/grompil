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
 * @description: Read-only lookup helpers for compounding raw materials and RIZIV codes.
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:compounding_products
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Search the raw-material catalogue and combine its source and reimbursement data.
 * Numeric terms match CNK codes exactly; text terms match names, synonyms and
 * Dutch/French reimbursement descriptions.
 *
 * @param string $query CNK or partial name/description.
 * @param int $limit Maximum number of distinct raw materials to return.
 * @param int|null $totalCount Receives the total number of matching materials.
 * @return list<array<string, mixed>>
 */
function grompil_compounding_product_search(string $query, int $limit = 20, ?int &$totalCount = null): array
{
    $query = trim($query);
    $totalCount = 0;
    if ($query === '' || strlen($query) > 200) {
        return [];
    }

    $limit = max(1, min($limit, 100));
    $isCnk = preg_match('/^[0-9 .-]+$/', $query) === 1;
    if ($isCnk) {
        $cnk = preg_replace('/[ .-]/', '', $query) ?? '';
        if ($cnk === '' || strlen($cnk) > 7) {
            return [];
        }
        $matchSql = "EXISTS (
            SELECT 1 FROM public.xt_compounding_raw_material_sources s
            WHERE s.raw_material_id = m.id AND s.external_id = $1
        ) OR EXISTS (
            SELECT 1 FROM public.xt_compounding_reimbursement_codes r
            WHERE r.raw_material_id = m.id AND r.reimbursement_code = $1
        )";
        $params = [$cnk, $limit];
    } else {
        $length = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
        if ($length < 2) {
            return [];
        }
        $matchSql = "m.preferred_name_nl ILIKE '%' || $1 || '%'
            OR m.preferred_name_fr ILIKE '%' || $1 || '%'
            OR array_to_string(m.synonyms_nl || m.synonyms_fr, ' ') ILIKE '%' || $1 || '%'
            OR EXISTS (
                SELECT 1 FROM public.xt_compounding_reimbursement_codes r
                WHERE r.raw_material_id = m.id
                  AND (r.description_nl ILIKE '%' || $1 || '%' OR r.description_fr ILIKE '%' || $1 || '%')
            )";
        $params = [$query, $limit];
    }

    $result = db_query_params(
        'compounding_products.search',
        "WITH matched AS (
            SELECT m.id,
                   count(*) OVER() AS total_count,
                   CASE WHEN $1::text ~ '^[0-9]+$' AND (
                       EXISTS (SELECT 1 FROM public.xt_compounding_raw_material_sources s
                               WHERE s.raw_material_id = m.id AND s.external_id = $1)
                       OR EXISTS (SELECT 1 FROM public.xt_compounding_reimbursement_codes r
                                  WHERE r.raw_material_id = m.id AND r.reimbursement_code = $1)
                   ) THEN 0 ELSE 1 END AS match_rank
            FROM public.xt_compounding_raw_materials m
            WHERE {$matchSql}
            ORDER BY match_rank, lower(m.preferred_name_nl), lower(m.preferred_name_fr), m.id
            LIMIT $2
        )
        SELECT m.id, m.preferred_name_nl, m.preferred_name_fr,
               m.preferred_name_en, m.preferred_name_de,
               to_jsonb(m.synonyms_nl)::text AS synonyms_nl,
               to_jsonb(m.synonyms_fr)::text AS synonyms_fr,
               m.cas_number, m.ec_number,
               m.chemical_formula, m.molecular_mass::text AS molecular_mass,
               m.pharmacopoeia_reference, m.is_active, matched.total_count,
               COALESCE(src.items, '[]'::jsonb) AS source_records,
               COALESCE(reimb.items, '[]'::jsonb) AS reimbursement_records
        FROM matched
        JOIN public.xt_compounding_raw_materials m ON m.id = matched.id
        LEFT JOIN LATERAL (
            SELECT jsonb_agg(jsonb_build_object(
                'source_system', s.source_system,
                'source_record_type', s.source_record_type,
                'external_id', s.external_id,
                'source_version', s.source_version,
                'source_status', s.source_status,
                'valid_from', s.valid_from,
                'valid_to', s.valid_to
            ) ORDER BY s.source_system, s.source_record_type, s.external_id, s.source_version) AS items
            FROM public.xt_compounding_raw_material_sources s
            WHERE s.raw_material_id = m.id
        ) src ON TRUE
        LEFT JOIN LATERAL (
            SELECT jsonb_agg(jsonb_build_object(
                'chapter_code', r.chapter_code,
                'cnk', r.reimbursement_code,
                'list_number', r.list_number,
                'description_nl', r.description_nl,
                'description_fr', r.description_fr,
                'conditions_nl', r.conditions_nl,
                'conditions_fr', r.conditions_fr,
                'valid_from', r.valid_from,
                'valid_to', r.valid_to,
                'source_version', r.source_version
            ) ORDER BY r.chapter_code, r.reimbursement_code, r.list_number, r.valid_from DESC) AS items
            FROM public.xt_compounding_reimbursement_codes r
            WHERE r.raw_material_id = m.id
        ) reimb ON TRUE
        ORDER BY matched.match_rank, lower(m.preferred_name_nl), lower(m.preferred_name_fr), m.id",
        $params
    );

    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $totalCount = max($totalCount, (int) ($row['total_count'] ?? 0));
        foreach (['synonyms_nl', 'synonyms_fr', 'source_records', 'reimbursement_records'] as $jsonField) {
            $decoded = json_decode((string) ($row[$jsonField] ?? '[]'), true);
            $row[$jsonField] = is_array($decoded) ? $decoded : [];
        }
        $rows[] = $row;
    }
    pg_free_result($result);
    return $rows;
}
