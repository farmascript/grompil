<?php
/**
 * @date_cre: Fri 2026-11-20 11:24:48
 * @date_mut: Sat 2026-11-28 13:13:28
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages per-tab dispensing sessions and their customer groups and product lines.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:dispensing_sessions
 */
declare(strict_types=1);

/**
 * Format a customer's display name with a sentence-case first name and uppercase surname.
 *
 * @param string $firstName The customer's first name.
 * @param string $lastName The customer's surname.
 *
 * @return string The formatted display name.
 */
function grompil_dispensing_display_name(string $firstName, string $lastName): string
{
    $firstName = mb_strtolower(trim($firstName), 'UTF-8');
    if ($firstName !== '') {
        $firstName = mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($firstName, 1, null, 'UTF-8');
    }
    $lastName = mb_strtoupper(trim($lastName), 'UTF-8');
    return trim($firstName . ' ' . $lastName);
}

/**
 * Identify prescription status keys that require a prescriber.
 *
 * @param string $statusKey Stable dispensing status key.
 * @return bool Whether the status represents a prescription.
 */
function grompil_dispensing_status_requires_caregiver(string $statusKey): bool
{
    return in_array(strtolower(trim($statusKey)), ['prescription', 'v'], true);
}

/**
 * Allocate the next prescription number for a company.
 *
 * @param int $companyId Company identifier.
 *
 * @return int The allocated prescription number.
 */
function grompil_dispensing_next_prescription_number(int $companyId): int
{
    $counter = db_query_params(
        'dispensing.prescription_number_next',
        'INSERT INTO dt_company_prescription_counters (company_id, last_number)
         VALUES ($1, 1)
         ON CONFLICT (company_id) DO UPDATE
             SET last_number = dt_company_prescription_counters.last_number + 1
         RETURNING last_number',
        [$companyId]
    );
    $counterRow = pg_fetch_assoc($counter);
    if (!$counterRow) throw new RuntimeException('Could not allocate prescription number.');
    return (int) $counterRow['last_number'];
}

/**
 * Build the localized deceased-customer notice.
 *
 * @param string $deathDate The death date.
 * @param string $ageDisplay The age display.
 *
 * @return string The resulting text.
 */
function grompil_dispensing_deceased_display(string $deathDate, string $ageDisplay): string
{
    $formattedDate = fn_formatDateDisplay($deathDate);
    $formattedDate = preg_replace('/(\\d{2})-(\\d{2})-(\\d{4})/', '$1/$2/$3', $formattedDate) ?? $formattedDate;
    $template = fn_t($ageDisplay !== '' ? 'dispense_deceased_age_summary' : 'dispense_deceased_summary');
    return strtr($template, ['{date}' => $formattedDate, '{age}' => $ageDisplay]);
}

/**
 * JSON API for per-tab dispensing drafts and their customer groups/lines.
 *
 * @param int $companyId Company identifier.
 * @param string $actorCode The actor code.
 * @param array $statusByKey Dispensing statuses indexed by stable key.
 * @param string $walkinTitle Localized label for anonymous walk-in customers.
 *
 * @return never This function does not return.
 */
function grompil_dispensing_session_api(int $companyId, string $actorCode, array $statusByKey, string $walkinTitle): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $respond = static function (array $body, int $status = 200): never {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    };

    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)
        || !is_string($payload['csrf_token'] ?? null)
        || !hash_equals(csrf_token(), $payload['csrf_token'])) {
        $respond(['error' => 'request_rejected'], 403);
    }

    $sessionId = strtolower(trim((string) ($payload['session_id'] ?? '')));
    if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $sessionId) !== 1) {
        $respond(['error' => 'invalid_session'], 400);
    }
    $action = (string) ($payload['action'] ?? '');
    $requestedStatusKey = strtolower(trim((string) ($payload['status_key'] ?? '')));
    if ($action === 'status_set' && !isset($statusByKey[$requestedStatusKey])) {
        $respond(['error' => 'invalid_status'], 400);
    }
    $statusKey = isset($statusByKey[$requestedStatusKey])
        ? $requestedStatusKey
        : (isset($statusByKey['free_sale']) ? 'free_sale' : (string) array_key_first($statusByKey));
    $transactionStarted = false;

    try {
        $session = false;
        if ($action === 'load') {
            $created = db_query_params(
                'dispensing.session_create',
                'INSERT INTO dt_dispensing_sessions (id, company_id, created_by_user_code, status_key)
                 VALUES ($1::UUID, $2, $3, $4)
                 ON CONFLICT (id) DO NOTHING RETURNING id',
                [$sessionId, $companyId, $actorCode, $statusKey]
            );
            $isNewSession = pg_num_rows($created) === 1;
            $session = db_query_params(
                'dispensing.session_load',
                'SELECT id, status_key FROM dt_dispensing_sessions
                 WHERE id = $1::UUID AND company_id = $2 AND created_by_user_code = $3 AND state = \'open\'',
                [$sessionId, $companyId, $actorCode]
            );
            if (pg_num_rows($session) !== 1) {
                $respond(['error' => 'session_unavailable'], 404);
            }
            if (!$isNewSession) {
                db_query_params(
                    'dispensing.session_touch',
                    'UPDATE dt_dispensing_sessions SET updated_at = now()
                     WHERE id = $1::UUID AND company_id = $2 AND created_by_user_code = $3 AND state = \'open\'',
                    [$sessionId, $companyId, $actorCode]
                );
            }

            $initialCustomerCode = strtoupper(trim((string) ($payload['customer_code'] ?? '')));
            if ($initialCustomerCode !== '') {
                grompil_dispensing_session_add_customer($companyId, $sessionId, $initialCustomerCode);
            }
            db_query_params(
                'dispensing.session_add_anonymous',
                'INSERT INTO dt_dispensing_parties (company_id, dispensing_session_id, party_type, display_name)
                 VALUES ($1, $2::UUID, \'anonymous\', $3)
                 ON CONFLICT (company_id, dispensing_session_id) WHERE party_type = \'anonymous\' DO NOTHING',
                [$companyId, $sessionId, fn_t('dispense_walkin_title')]
            );
            $statusKey = (string) (pg_fetch_result($session, 0, 'status_key') ?: $statusKey);
        } else {
            $session = db_query_params(
                'dispensing.session_require_open',
                'SELECT id, status_key FROM dt_dispensing_sessions
                 WHERE id = $1::UUID AND company_id = $2 AND created_by_user_code = $3 AND state = \'open\'',
                [$sessionId, $companyId, $actorCode]
            );
            if (pg_num_rows($session) !== 1) {
                $respond(['error' => 'session_unavailable'], 404);
            }
        }

        if ($action === 'status_set') {
            if (!isset($statusByKey[$statusKey])) {
                $respond(['error' => 'invalid_status'], 400);
            }
            db_query_params(
                'dispensing.session_status_update',
                'UPDATE dt_dispensing_sessions SET status_key = $1, updated_at = now()
                 WHERE id = $2::UUID AND company_id = $3 AND created_by_user_code = $4 AND state = \'open\'',
                [$statusKey, $sessionId, $companyId, $actorCode]
            );
            $respond(['status_key' => $statusKey]);
        }

        if ($action === 'party_add') {
            $customerCode = strtoupper(trim((string) ($payload['customer_code'] ?? '')));
            $partyId = grompil_dispensing_session_add_customer($companyId, $sessionId, $customerCode);
            $respond([
                'parties' => grompil_dispensing_session_parties($companyId, $sessionId, $walkinTitle),
                'active_party_id' => $partyId,
                'lines' => grompil_dispensing_session_lines($companyId, $sessionId, $walkinTitle),
            ]);
        }

        if ($action === 'prescription_copy') {
            $rawPrescriptionNumber = trim((string) ($payload['prescription_number'] ?? ''));
            $normalizedPrescriptionNumber = ltrim($rawPrescriptionNumber, '0');
            $normalizedPrescriptionNumber = $normalizedPrescriptionNumber === '' ? '0' : $normalizedPrescriptionNumber;
            if (preg_match('/^[0-9]{1,19}$/D', $rawPrescriptionNumber) !== 1
                || strlen($normalizedPrescriptionNumber) > 19
                || (strlen($normalizedPrescriptionNumber) === 19
                    && strcmp($normalizedPrescriptionNumber, '9223372036854775807') > 0)) {
                $respond(['error' => 'invalid_prescription_number'], 400);
            }
            $prescriptionNumber = (int) $normalizedPrescriptionNumber;
            db_query_params('dispensing.prescription_copy_begin', 'BEGIN');
            $transactionStarted = true;
            $sessionState = db_query_params(
                'dispensing.prescription_copy_session_state',
                'SELECT session.source_history_id,
                        EXISTS (
                            SELECT 1 FROM dt_dispensing_lines line
                            WHERE line.company_id = session.company_id
                              AND line.dispensing_session_id = session.id
                        ) AS has_lines
                 FROM dt_dispensing_sessions session
                 WHERE session.company_id = $1 AND session.id = $2::UUID
                   AND session.created_by_user_code = $3 AND session.state = \'open\'
                 FOR UPDATE',
                [$companyId, $sessionId, $actorCode]
            );
            $sessionStateRow = pg_fetch_assoc($sessionState);
            if (!$sessionStateRow) {
                db_query_params('dispensing.prescription_copy_rollback_session', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'session_unavailable'], 404);
            }
            if ($sessionStateRow['source_history_id'] !== null || $sessionStateRow['has_lines'] === 't') {
                db_query_params('dispensing.prescription_copy_rollback_not_empty', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'session_not_empty'], 409);
            }
            $historyRows = db_query_params(
                'dispensing.prescription_copy_source',
                'SELECT history.id AS history_id, line.party_type, line.customer_code, line.party_name, line.status_key,
                        line.caregiver_code, line.cnk, line.amp_code, line.ampp_code,
                        line.product_name_nl, line.product_name_fr,
                        line.package_display_nl, line.package_display_fr, line.quantity, line.sort_order
                 FROM st_dispensing_history history
                 JOIN st_dispensing_history_lines line ON line.history_id = history.id
                 WHERE history.company_id = $1 AND history.prescription_number = $2::BIGINT
                 ORDER BY history.completed_at, history.id, line.sort_order, line.id',
                [$companyId, $prescriptionNumber]
            );
            $sourceLines = [];
            while ($sourceLine = pg_fetch_assoc($historyRows)) {
                $sourceLines[] = $sourceLine;
            }
            if ($sourceLines === []) {
                db_query_params('dispensing.prescription_copy_rollback_missing', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'prescription_not_found'], 404);
            }
            $historyId = (int) $sourceLines[0]['history_id'];
            $alreadyBeingCorrected = db_query_params(
                'dispensing.prescription_copy_already_open',
                'SELECT 1 FROM dt_dispensing_sessions
                 WHERE company_id = $1 AND source_history_id = $2 AND state = \'open\' AND id <> $3::UUID
                 LIMIT 1',
                [$companyId, $historyId, $sessionId]
            );
            if (pg_num_rows($alreadyBeingCorrected) > 0) {
                db_query_params('dispensing.prescription_copy_rollback_open', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'prescription_already_open'], 409);
            }

            $activePartyId = 0;
            $defaultStatusKey = '';
            $activePartyIsPrescription = false;
            foreach ($sourceLines as $sourceLine) {
                $lineStatusKey = strtolower(trim((string) $sourceLine['status_key']));
                $isPrescriptionLine = grompil_dispensing_status_requires_caregiver($lineStatusKey);
                $isPrescriptionCustomerLine = $isPrescriptionLine && $sourceLine['party_type'] === 'customer';
                if (!isset($statusByKey[$lineStatusKey])) {
                    throw new InvalidArgumentException('prescription_status_unavailable');
                }
                if ($defaultStatusKey === '') {
                    $defaultStatusKey = $lineStatusKey;
                }

                if ($sourceLine['party_type'] === 'anonymous') {
                    db_query_params(
                        'dispensing.prescription_copy_anonymous_party_add',
                        'INSERT INTO dt_dispensing_parties (company_id, dispensing_session_id, party_type, display_name)
                         VALUES ($1, $2::UUID, \'anonymous\', $3)
                         ON CONFLICT (company_id, dispensing_session_id) WHERE party_type = \'anonymous\' DO NOTHING',
                        [$companyId, $sessionId, $walkinTitle]
                    );
                    $partyResult = db_query_params(
                        'dispensing.prescription_copy_anonymous_party_load',
                        'SELECT id FROM dt_dispensing_parties
                         WHERE company_id = $1 AND dispensing_session_id = $2::UUID AND party_type = \'anonymous\'',
                        [$companyId, $sessionId]
                    );
                } else {
                    $customerCode = strtoupper(trim((string) ($sourceLine['customer_code'] ?? '')));
                    if (preg_match('/^[A-Z0-9]{6}$/D', $customerCode) !== 1) {
                        throw new InvalidArgumentException('prescription_customer_unavailable');
                    }
                    $customerResult = db_query_params(
                        'dispensing.prescription_copy_customer_check',
                        'SELECT customer_code FROM bt_customers WHERE company_id = $1 AND customer_code = $2',
                        [$companyId, $customerCode]
                    );
                    if (pg_num_rows($customerResult) !== 1) {
                        throw new InvalidArgumentException('prescription_customer_unavailable');
                    }
                    db_query_params(
                        'dispensing.prescription_copy_customer_party_add',
                        'INSERT INTO dt_dispensing_parties (company_id, dispensing_session_id, party_type, customer_code, display_name)
                         VALUES ($1, $2::UUID, \'customer\', $3, $4)
                         ON CONFLICT (company_id, dispensing_session_id, customer_code)
                             WHERE customer_code IS NOT NULL DO NOTHING',
                        [$companyId, $sessionId, $customerCode, (string) $sourceLine['party_name']]
                    );
                    $partyResult = db_query_params(
                        'dispensing.prescription_copy_customer_party_load',
                        'SELECT id FROM dt_dispensing_parties
                         WHERE company_id = $1 AND dispensing_session_id = $2::UUID AND customer_code = $3',
                        [$companyId, $sessionId, $customerCode]
                    );
                }
                $partyId = (int) (pg_fetch_result($partyResult, 0, 'id') ?: 0);
                if ($partyId < 1) {
                    throw new RuntimeException('Could not create copied prescription party.');
                }
                if ($activePartyId === 0 || ($isPrescriptionCustomerLine && !$activePartyIsPrescription)) {
                    $activePartyId = $partyId;
                    $activePartyIsPrescription = $isPrescriptionCustomerLine;
                }
                if ($isPrescriptionLine && $sourceLine['party_type'] === 'customer') {
                    $defaultStatusKey = $lineStatusKey;
                }

                db_query_params(
                    'dispensing.prescription_copy_line',
                    'INSERT INTO dt_dispensing_lines (
                        company_id, dispensing_session_id, party_id, status_key, caregiver_code,
                        cnk, amp_code, ampp_code, product_name_nl, product_name_fr,
                        package_display_nl, package_display_fr, quantity, sort_order
                     ) VALUES ($1, $2::UUID, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)
                     ON CONFLICT (
                        company_id, dispensing_session_id, party_id, cnk, amp_code, ampp_code, status_key,
                        (COALESCE(caregiver_code, \'\'::VARCHAR))
                     ) DO UPDATE SET quantity = LEAST(999, dt_dispensing_lines.quantity + EXCLUDED.quantity), updated_at = now()',
                    [$companyId, $sessionId, $partyId, $lineStatusKey, $sourceLine['caregiver_code'],
                        (string) $sourceLine['cnk'], (string) $sourceLine['amp_code'], (string) $sourceLine['ampp_code'],
                        (string) $sourceLine['product_name_nl'], (string) $sourceLine['product_name_fr'],
                        (string) $sourceLine['package_display_nl'], (string) $sourceLine['package_display_fr'],
                        (int) $sourceLine['quantity'], (int) $sourceLine['sort_order']]
                );
            }
            db_query_params(
                'dispensing.prescription_copy_mark_session',
                'UPDATE dt_dispensing_sessions SET source_history_id = $1, updated_at = now()
                 WHERE company_id = $2 AND id = $3::UUID AND created_by_user_code = $4 AND state = \'open\'',
                [$historyId, $companyId, $sessionId, $actorCode]
            );
            db_query_params('dispensing.prescription_copy_commit', 'COMMIT');
            $transactionStarted = false;
            $respond([
                'copied' => true,
                'prescription_number' => $prescriptionNumber,
                'source_history_id' => $historyId,
                'default_status_key' => $defaultStatusKey,
                'active_party_id' => $activePartyId,
                'parties' => grompil_dispensing_session_parties($companyId, $sessionId, $walkinTitle),
                'lines' => grompil_dispensing_session_lines($companyId, $sessionId, $walkinTitle),
            ]);
        }

        if ($action === 'line_add') {
            $partyId = filter_var($payload['party_id'] ?? null, FILTER_VALIDATE_INT);
            $lineStatus = strtolower(trim((string) ($payload['status_key'] ?? '')));
            $requestedCaregiverCode = strtoupper(trim((string) ($payload['caregiver_code'] ?? '')));
            $caregiverCode = grompil_dispensing_status_requires_caregiver($lineStatus) && $requestedCaregiverCode !== ''
                ? $requestedCaregiverCode
                : null;
            $cnk = preg_replace('/[ .-]/', '', trim((string) ($payload['cnk'] ?? ''))) ?? '';
            $ampCode = trim((string) ($payload['amp_code'] ?? ''));
            $amppCode = trim((string) ($payload['ampp_code'] ?? ''));
            if ($partyId === false || $partyId === null
                || !isset($statusByKey[$lineStatus])
                || preg_match('/^[0-9]{1,7}$/D', $cnk) !== 1
                || $ampCode === '' || strlen($ampCode) > 80
                || strlen($amppCode) > 80) {
                $respond(['error' => 'invalid_line'], 400);
            }
            $partyCheck = db_query_params(
                'dispensing.session_line_party_check',
                'SELECT party.party_type, customer.date_of_death
                 FROM dt_dispensing_parties party
                 LEFT JOIN bt_customers customer
                   ON customer.company_id = party.company_id AND customer.customer_code = party.customer_code
                 WHERE party.id = $1 AND party.company_id = $2 AND party.dispensing_session_id = $3::UUID',
                [$partyId, $companyId, $sessionId]
            );
            $partyRow = pg_fetch_assoc($partyCheck);
            if (!$partyRow) {
                $respond(['error' => 'party_unavailable'], 409);
            }
            if ($caregiverCode !== null) {
                $caregiverCheck = db_query_params(
                    'dispensing.session_line_add_caregiver_validate',
                    'SELECT caregiver_code FROM bt_caregivers
                     WHERE caregiver_code = $1 AND is_active
                       AND caregiver_type_key IN (\'physician\', \'dentist\')
                       AND (owner_company_id IS NULL OR owner_company_id = $2)',
                    [$caregiverCode, $companyId]
                );
                if (pg_num_rows($caregiverCheck) !== 1) {
                    $respond(['error' => 'invalid_caregiver'], 400);
                }
            }
            if (($partyRow['party_type'] ?? '') === 'customer' && trim((string) ($partyRow['date_of_death'] ?? '')) !== '') {
                $respond(['error' => 'deceased_customer'], 409);
            }
            $productResult = db_query_params(
                'dispensing.session_product_validate',
                'SELECT cnk, amp_code, ampp_code,
                        COALESCE(NULLIF(prescription_name_nl, \'\'), prescription_name_fr, official_name) AS product_name_nl,
                        COALESCE(prescription_name_fr, \'\') AS product_name_fr,
                        COALESCE(pack_display_nl, \'\') AS package_display_nl,
                        COALESCE(pack_display_fr, \'\') AS package_display_fr
                 FROM public.xt_sam_public_products
                 WHERE cnk = $1 AND amp_code = $2 AND ampp_code = $3
                   AND amp_status IN (\'AUTHORIZED\', \'REVOKED\', \'SUSPENDED\')
                   AND ampp_status IN (\'AUTHORIZED\', \'REVOKED\', \'SUSPENDED\')
                 ORDER BY CASE WHEN ampp_status = \'AUTHORIZED\' THEN 0 ELSE 1 END
                 LIMIT 1',
                [$cnk, $ampCode, $amppCode]
            );
            $product = pg_fetch_assoc($productResult);
            if (!$product) {
                $respond(['error' => 'product_unavailable'], 404);
            }
            $lineResult = db_query_params(
                'dispensing.session_line_add',
                'INSERT INTO dt_dispensing_lines (
                    company_id, dispensing_session_id, party_id, status_key, caregiver_code, cnk, amp_code, ampp_code,
                    product_name_nl, product_name_fr, package_display_nl, package_display_fr, quantity
                 ) SELECT $1, $2::UUID, party.id, $3, $4, $5, $6, $7, $8, $9, $10, $11, 1
                   FROM dt_dispensing_parties party
                   WHERE party.company_id = $1 AND party.dispensing_session_id = $2::UUID AND party.id = $12
                 ON CONFLICT (
                    company_id, dispensing_session_id, party_id, cnk, amp_code, ampp_code, status_key,
                    (COALESCE(caregiver_code, \'\'::VARCHAR))
                 )
                 DO UPDATE SET quantity = LEAST(999, dt_dispensing_lines.quantity + 1), updated_at = now()
                 RETURNING id, party_id, status_key, cnk, amp_code, ampp_code,
                           product_name_nl, product_name_fr, package_display_nl, package_display_fr, quantity,
                           caregiver_code',
                [$companyId, $sessionId, $lineStatus, $caregiverCode, $cnk, $ampCode, $amppCode,
                    (string) $product['product_name_nl'], (string) $product['product_name_fr'],
                    (string) $product['package_display_nl'], (string) $product['package_display_fr'], $partyId]
            );
            $line = pg_fetch_assoc($lineResult);
            if (!$line) {
                $respond(['error' => 'party_unavailable'], 409);
            }
            $partyResult = db_query_params(
                'dispensing.session_line_party',
                'SELECT party.display_name, party.party_type, party.customer_code, customer.name_first, customer.name_last
                 FROM dt_dispensing_parties party
                 LEFT JOIN bt_customers customer
                   ON customer.company_id = party.company_id AND customer.customer_code = party.customer_code
                 WHERE party.id = $1 AND party.company_id = $2',
                [(int) $line['party_id'], $companyId]
            );
            $party = pg_fetch_assoc($partyResult) ?: ['display_name' => '', 'customer_code' => null];
            if (($party['party_type'] ?? '') === 'anonymous') {
                $party['display_name'] = $walkinTitle;
            } elseif (($party['customer_code'] ?? null) !== null) {
                $party['display_name'] = grompil_dispensing_display_name(
                    (string) ($party['name_first'] ?? ''), (string) ($party['name_last'] ?? '')
                );
            }
            if (($line['caregiver_code'] ?? '') !== '') {
                $caregiverNameResult = db_query_params(
                    'dispensing.session_line_add_caregiver_name',
                    'SELECT name_first, name_last FROM bt_caregivers WHERE caregiver_code = $1',
                    [(string) $line['caregiver_code']]
                );
                $caregiverName = pg_fetch_assoc($caregiverNameResult) ?: [];
                $line['caregiver_first'] = (string) ($caregiverName['name_first'] ?? '');
                $line['caregiver_last'] = (string) ($caregiverName['name_last'] ?? '');
            }
            $statusResult = db_query_params(
                'dispensing.session_line_status',
                'SELECT COALESCE(short_codes ->> $2, \'\') FROM ft_dd_varia
                 WHERE list_key = \'dispensing_statuses\' AND "key" = $1',
                [$lineStatus, strtoupper((string) ($_SESSION['language'] ?? 'NL'))]
            );
            $respond(['line' => grompil_dispensing_session_line_data($line, $party, (string) pg_fetch_result($statusResult, 0, 0))]);
        }

        if ($action === 'line_update') {
            $lineId = filter_var($payload['line_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($payload['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($lineId === false || $lineId === null || $quantity === false || $quantity === null || $quantity < 1 || $quantity > 999) {
                $respond(['error' => 'invalid_quantity'], 400);
            }
            $requestedLineStatus = array_key_exists('status_key', $payload)
                ? strtolower(trim((string) $payload['status_key']))
                : null;
            if ($requestedLineStatus !== null && !isset($statusByKey[$requestedLineStatus])) {
                $respond(['error' => 'invalid_status'], 400);
            }
            $requestedPartyId = array_key_exists('party_id', $payload)
                ? filter_var($payload['party_id'], FILTER_VALIDATE_INT)
                : null;
            if ($requestedPartyId === false || ($requestedPartyId !== null && $requestedPartyId < 1)) {
                $respond(['error' => 'invalid_party'], 400);
            }
            db_query_params('dispensing.session_line_update_begin', 'BEGIN');
            $transactionStarted = true;
            $targetResult = db_query_params(
                'dispensing.session_line_update_target',
                'SELECT id, party_id, status_key, caregiver_code, cnk, amp_code, ampp_code, quantity
                 FROM dt_dispensing_lines
                 WHERE id = $1 AND company_id = $2 AND dispensing_session_id = $3::UUID
                 FOR UPDATE',
                [$lineId, $companyId, $sessionId]
            );
            $target = pg_fetch_assoc($targetResult);
            if (!$target) {
                db_query_params('dispensing.session_line_update_rollback_missing', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['updated' => false]);
            }
            $newStatus = $requestedLineStatus ?? (string) $target['status_key'];
            $newPartyId = $requestedPartyId ?? (int) $target['party_id'];
            $oldCaregiverCode = (string) ($target['caregiver_code'] ?? '');
            $requestedCaregiverCode = array_key_exists('caregiver_code', $payload)
                ? strtoupper(trim((string) $payload['caregiver_code']))
                : $oldCaregiverCode;
            $newCaregiverCode = grompil_dispensing_status_requires_caregiver($newStatus) && $requestedCaregiverCode !== ''
                ? $requestedCaregiverCode
                : null;
            if ($newCaregiverCode !== null && $newCaregiverCode !== $oldCaregiverCode) {
                $caregiverCheck = db_query_params(
                    'dispensing.session_line_caregiver_validate',
                    'SELECT caregiver_code FROM bt_caregivers
                     WHERE caregiver_code = $1 AND is_active
                       AND caregiver_type_key IN (\'physician\', \'dentist\')
                       AND (owner_company_id IS NULL OR owner_company_id = $2)',
                    [$newCaregiverCode, $companyId]
                );
                if (pg_num_rows($caregiverCheck) !== 1) {
                    db_query_params('dispensing.session_line_update_rollback_caregiver', 'ROLLBACK');
                    $transactionStarted = false;
                    $respond(['error' => 'invalid_caregiver'], 400);
                }
            }
            $partyResult = db_query_params(
                'dispensing.session_line_update_party_validate',
                'SELECT party.id, party.party_type, customer.date_of_death
                 FROM dt_dispensing_parties party
                 LEFT JOIN bt_customers customer
                   ON customer.company_id = party.company_id AND customer.customer_code = party.customer_code
                 WHERE party.id = $1 AND party.company_id = $2 AND party.dispensing_session_id = $3::UUID',
                [$newPartyId, $companyId, $sessionId]
            );
            $newParty = pg_fetch_assoc($partyResult);
            if (!$newParty) {
                db_query_params('dispensing.session_line_update_rollback_party', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'invalid_party'], 400);
            }
            if (($newParty['party_type'] ?? '') === 'customer' && trim((string) ($newParty['date_of_death'] ?? '')) !== '') {
                db_query_params('dispensing.session_line_update_rollback_deceased', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'deceased_customer'], 409);
            }
            $lineMoved = $newStatus !== (string) $target['status_key']
                || $newPartyId !== (int) $target['party_id']
                || ($newCaregiverCode ?? '') !== $oldCaregiverCode;
            if ($lineMoved) {
                $duplicateResult = db_query_params(
                    'dispensing.session_line_update_duplicate',
                    'SELECT id, quantity FROM dt_dispensing_lines
                     WHERE company_id = $1 AND dispensing_session_id = $2::UUID AND party_id = $3
                       AND status_key = $4 AND cnk = $5 AND amp_code = $6 AND ampp_code = $7
                       AND caregiver_code IS NOT DISTINCT FROM $8 AND id <> $9
                     FOR UPDATE',
                    [$companyId, $sessionId, $newPartyId, $newStatus, $target['cnk'], $target['amp_code'], $target['ampp_code'], $newCaregiverCode, $lineId]
                );
                $duplicate = pg_fetch_assoc($duplicateResult);
                if ($duplicate) {
                    $mergedQuantity = (int) $duplicate['quantity'] + $quantity;
                    if ($mergedQuantity > 999) {
                        db_query_params('dispensing.session_line_update_rollback_quantity', 'ROLLBACK');
                        $transactionStarted = false;
                        $respond(['error' => 'invalid_quantity'], 409);
                    }
                    db_query_params(
                        'dispensing.session_line_update_merge',
                        'UPDATE dt_dispensing_lines SET quantity = $1, updated_at = now() WHERE id = $2',
                        [$mergedQuantity, (int) $duplicate['id']]
                    );
                    db_query_params(
                        'dispensing.session_line_update_remove_source',
                        'DELETE FROM dt_dispensing_lines WHERE id = $1 AND company_id = $2 AND dispensing_session_id = $3::UUID',
                        [$lineId, $companyId, $sessionId]
                    );
                } else {
                    db_query_params(
                        'dispensing.session_line_update',
                        'UPDATE dt_dispensing_lines SET quantity = $1, status_key = $2, party_id = $3,
                         caregiver_code = $4, updated_at = now()
                         WHERE id = $5 AND company_id = $6 AND dispensing_session_id = $7::UUID',
                        [$quantity, $newStatus, $newPartyId, $newCaregiverCode, $lineId, $companyId, $sessionId]
                    );
                }
            } else {
                db_query_params(
                    'dispensing.session_line_update',
                    'UPDATE dt_dispensing_lines SET quantity = $1, caregiver_code = $2, updated_at = now()
                     WHERE id = $3 AND company_id = $4 AND dispensing_session_id = $5::UUID',
                    [$quantity, $newCaregiverCode, $lineId, $companyId, $sessionId]
                );
            }
            db_query_params('dispensing.session_line_update_commit', 'COMMIT');
            $transactionStarted = false;
            $respond([
                'updated' => true,
                'lines' => grompil_dispensing_session_lines($companyId, $sessionId, $walkinTitle),
            ]);
        }

        if ($action === 'line_remove') {
            $lineId = filter_var($payload['line_id'] ?? null, FILTER_VALIDATE_INT);
            if ($lineId === false || $lineId === null) {
                $respond(['error' => 'invalid_line'], 400);
            }
            $removed = db_query_params(
                'dispensing.session_line_remove',
                'DELETE FROM dt_dispensing_lines
                 WHERE id = $1 AND company_id = $2 AND dispensing_session_id = $3::UUID',
                [$lineId, $companyId, $sessionId]
            );
            $respond(['removed' => pg_affected_rows($removed) === 1]);
        }

        if ($action === 'discard') {
            $begin = db_query_params('dispensing.session_discard_begin', 'BEGIN');
            if (!$begin) throw new RuntimeException('Could not start dispensing discard transaction.');
            $transactionStarted = true;
            $deleted = db_query_params(
                'dispensing.session_discard',
                'DELETE FROM dt_dispensing_sessions
                 WHERE id = $1::UUID AND company_id = $2
                   AND created_by_user_code = $3 AND state = \'open\'
                 RETURNING id',
                [$sessionId, $companyId, $actorCode]
            );
            if (pg_num_rows($deleted) !== 1) {
                db_query_params('dispensing.session_discard_rollback_missing', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'session_unavailable'], 404);
            }
            $commit = db_query_params('dispensing.session_discard_commit', 'COMMIT');
            if (!$commit) throw new RuntimeException('Could not commit dispensing discard.');
            $transactionStarted = false;
            $respond(['discarded' => true]);
        }

        if (in_array($action, ['receipt_preview', 'complete'], true)) {
            if ($action === 'receipt_preview') {
                $begin = db_query_params('dispensing.receipt_preview_begin', 'BEGIN');
                if (!$begin) throw new RuntimeException('Could not start receipt preview transaction.');
                $transactionStarted = true;
                $lockedSession = db_query_params(
                    'dispensing.receipt_preview_lock',
                    'SELECT prescription_number, source_history_id FROM dt_dispensing_sessions
                     WHERE id = $1::UUID AND company_id = $2 AND created_by_user_code = $3 AND state = \'open\'
                     FOR UPDATE',
                    [$sessionId, $companyId, $actorCode]
                );
                $lockedSessionRow = pg_fetch_assoc($lockedSession);
                if (!$lockedSessionRow) {
                    db_query_params('dispensing.receipt_preview_rollback_missing', 'ROLLBACK');
                    $transactionStarted = false;
                    $respond(['error' => 'session_unavailable'], 404);
                }
                $receipt = grompil_dispensing_receipt_data($companyId, $sessionId, $walkinTitle);
                if ($receipt['lines'] === []) {
                    db_query_params('dispensing.receipt_preview_rollback_empty', 'ROLLBACK');
                    $transactionStarted = false;
                    $respond(['error' => 'empty_session'], 409);
                }
                $hasPrescriptionLine = false;
                $missingCaregiverLines = [];
                foreach ($receipt['lines'] as $receiptLine) {
                    if (!grompil_dispensing_status_requires_caregiver((string) ($receiptLine['status'] ?? ''))) continue;
                    $hasPrescriptionLine = true;
                    if (trim((string) ($receiptLine['caregiver_code'] ?? '')) === '') {
                        $missingCaregiverLines[] = [
                            'id' => (int) $receiptLine['id'],
                            'party_name' => (string) $receiptLine['party_name'],
                            'cnk' => (string) $receiptLine['cnk'],
                            'product_name' => (string) ($receiptLine['name_nl'] ?? ''),
                            'quantity' => (int) $receiptLine['quantity'],
                        ];
                    }
                }
                if ($missingCaregiverLines !== []) {
                    db_query_params('dispensing.receipt_preview_rollback_missing_caregiver', 'ROLLBACK');
                    $transactionStarted = false;
                    $respond(['error' => 'missing_caregiver', 'lines' => $missingCaregiverLines], 409);
                }
                if ($lockedSessionRow['source_history_id'] !== null) {
                    $prescriptionNumber = $receipt['prescription_number'];
                    if ($prescriptionNumber === null) {
                        db_query_params('dispensing.receipt_preview_rollback_missing_history', 'ROLLBACK');
                        $transactionStarted = false;
                        $respond(['error' => 'prescription_not_found'], 404);
                    }
                } else {
                    $prescriptionNumber = $lockedSessionRow['prescription_number'] === null
                        ? null
                        : (int) $lockedSessionRow['prescription_number'];
                    if ($hasPrescriptionLine && $prescriptionNumber === null) {
                        $prescriptionNumber = grompil_dispensing_next_prescription_number($companyId);
                        db_query_params(
                            'dispensing.receipt_preview_reserve_prescription_number',
                            'UPDATE dt_dispensing_sessions
                             SET prescription_number = $1, updated_at = now()
                             WHERE id = $2::UUID AND company_id = $3 AND created_by_user_code = $4 AND state = \'open\'',
                            [$prescriptionNumber, $sessionId, $companyId, $actorCode]
                        );
                    }
                }
                $receipt['prescription_number'] = $lockedSessionRow['source_history_id'] !== null
                    ? (int) $prescriptionNumber
                    : ($hasPrescriptionLine ? $prescriptionNumber : null);
                $commit = db_query_params('dispensing.receipt_preview_commit', 'COMMIT');
                if (!$commit) throw new RuntimeException('Could not commit receipt preview reservation.');
                $transactionStarted = false;
                $pdf = grompil_dispensing_receipt_pdf($receipt, strtoupper((string) ($_SESSION['language'] ?? 'NL')));
                $respond(['pdf_base64' => base64_encode($pdf)]);
            }

            $begin = db_query_params('dispensing.session_complete_begin', 'BEGIN');
            if (!$begin) throw new RuntimeException('Could not start receipt transaction.');
            $transactionStarted = true;
            $lockedSession = db_query_params(
                'dispensing.session_complete_lock',
                'SELECT id, status_key, prescription_number, source_history_id FROM dt_dispensing_sessions
                 WHERE id = $1::UUID AND company_id = $2 AND created_by_user_code = $3 AND state = \'open\'
                 FOR UPDATE',
                [$sessionId, $companyId, $actorCode]
            );
            $lockedSessionRow = pg_fetch_assoc($lockedSession);
            if (!$lockedSessionRow) {
                db_query_params('dispensing.session_complete_rollback_empty', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'session_unavailable'], 404);
            }
            db_query_params(
                'dispensing.session_complete_lock_lines',
                'SELECT id FROM dt_dispensing_lines
                 WHERE company_id = $1 AND dispensing_session_id = $2::UUID
                 ORDER BY id FOR UPDATE',
                [$companyId, $sessionId]
            );
            $receipt = grompil_dispensing_receipt_data($companyId, $sessionId, $walkinTitle);
            if ($receipt['lines'] === []) {
                db_query_params('dispensing.session_complete_rollback_empty', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'empty_session'], 409);
            }
            $hasPrescriptionLine = false;
            $missingCaregiverLines = [];
            foreach ($receipt['lines'] as $receiptLine) {
                if (grompil_dispensing_status_requires_caregiver((string) ($receiptLine['status'] ?? ''))) {
                    $hasPrescriptionLine = true;
                    if (trim((string) ($receiptLine['caregiver_code'] ?? '')) === '') {
                        $missingCaregiverLines[] = [
                            'id' => (int) $receiptLine['id'],
                            'party_name' => (string) $receiptLine['party_name'],
                            'cnk' => (string) $receiptLine['cnk'],
                            'product_name' => (string) ($receiptLine['name_nl'] ?? ''),
                            'quantity' => (int) $receiptLine['quantity'],
                        ];
                    }
                }
            }
            if ($missingCaregiverLines !== []) {
                db_query_params('dispensing.session_complete_rollback_missing_caregiver', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'missing_caregiver', 'lines' => $missingCaregiverLines], 409);
            }
            $sourceHistoryId = $lockedSessionRow['source_history_id'] === null
                ? null
                : (int) $lockedSessionRow['source_history_id'];
            $prescriptionNumber = null;
            if ($sourceHistoryId !== null) {
                $sourceHistory = db_query_params(
                    'dispensing.correction_history_lock',
                    'SELECT id, prescription_number FROM st_dispensing_history
                     WHERE company_id = $1 AND id = $2 FOR UPDATE',
                    [$companyId, $sourceHistoryId]
                );
                $sourceHistoryRow = pg_fetch_assoc($sourceHistory);
                if (!$sourceHistoryRow || $sourceHistoryRow['prescription_number'] === null) {
                    db_query_params('dispensing.session_complete_rollback_missing_history', 'ROLLBACK');
                    $transactionStarted = false;
                    $respond(['error' => 'prescription_not_found'], 404);
                }
                $prescriptionNumber = (int) $sourceHistoryRow['prescription_number'];
            } elseif ($hasPrescriptionLine) {
                $prescriptionNumber = $lockedSessionRow['prescription_number'] === null
                    ? grompil_dispensing_next_prescription_number($companyId)
                    : (int) $lockedSessionRow['prescription_number'];
                if ($lockedSessionRow['prescription_number'] === null) {
                    db_query_params(
                        'dispensing.session_complete_reserve_prescription_number',
                        'UPDATE dt_dispensing_sessions
                         SET prescription_number = $1, updated_at = now()
                         WHERE id = $2::UUID AND company_id = $3 AND created_by_user_code = $4 AND state = \'open\'',
                        [$prescriptionNumber, $sessionId, $companyId, $actorCode]
                    );
                }
            }
            $receipt['prescription_number'] = $prescriptionNumber;
            $snapshotJson = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $historyUpdated = false;
            if ($sourceHistoryId !== null) {
                $changedRows = db_query_params(
                    'dispensing.correction_history_content_changed',
                    'SELECT 1
                     FROM (
                         (SELECT party_type, customer_code, status_key, caregiver_code,
                                 cnk, amp_code, ampp_code, product_name_nl, product_name_fr,
                                 package_display_nl, package_display_fr, quantity, sort_order
                          FROM st_dispensing_history_lines WHERE history_id = $1
                          EXCEPT
                          SELECT party.party_type, party.customer_code, line.status_key, line.caregiver_code,
                                 line.cnk, line.amp_code, line.ampp_code,
                                 line.product_name_nl, line.product_name_fr, line.package_display_nl,
                                 line.package_display_fr, line.quantity, line.sort_order
                          FROM dt_dispensing_lines line
                          JOIN dt_dispensing_parties party
                            ON party.company_id = line.company_id AND party.dispensing_session_id = line.dispensing_session_id
                           AND party.id = line.party_id
                          WHERE line.company_id = $2 AND line.dispensing_session_id = $3::UUID)
                         UNION ALL
                         (SELECT party.party_type, party.customer_code, line.status_key, line.caregiver_code,
                                 line.cnk, line.amp_code, line.ampp_code,
                                 line.product_name_nl, line.product_name_fr, line.package_display_nl,
                                 line.package_display_fr, line.quantity, line.sort_order
                          FROM dt_dispensing_lines line
                          JOIN dt_dispensing_parties party
                            ON party.company_id = line.company_id AND party.dispensing_session_id = line.dispensing_session_id
                           AND party.id = line.party_id
                          WHERE line.company_id = $2 AND line.dispensing_session_id = $3::UUID
                          EXCEPT
                          SELECT party_type, customer_code, status_key, caregiver_code,
                                 cnk, amp_code, ampp_code, product_name_nl, product_name_fr,
                                 package_display_nl, package_display_fr, quantity, sort_order
                          FROM st_dispensing_history_lines WHERE history_id = $1)
                     ) difference LIMIT 1',
                    [$sourceHistoryId, $companyId, $sessionId]
                );
                if (pg_num_rows($changedRows) > 0) {
                    db_query_params(
                        'dispensing.correction_history_header_update',
                        'UPDATE st_dispensing_history
                         SET status_key = $1, receipt_thank_you = $2, receipt_snapshot = $3::jsonb,
                             corrected_at = now(), corrected_by_user_code = $4
                         WHERE company_id = $5 AND id = $6',
                        [(string) $lockedSessionRow['status_key'], (string) $receipt['thank_you'],
                            (string) $snapshotJson, $actorCode, $companyId, $sourceHistoryId]
                    );
                    $oldHistoryLineMaxResult = db_query_params(
                        'dispensing.correction_history_lines_max_id',
                        'SELECT COALESCE(MAX(id), 0) AS max_id
                         FROM st_dispensing_history_lines WHERE history_id = $1',
                        [$sourceHistoryId]
                    );
                    $oldHistoryLineMax = (int) (pg_fetch_result($oldHistoryLineMaxResult, 0, 'max_id') ?: 0);
                    db_query_params(
                        'dispensing.correction_history_lines_replace',
                        'INSERT INTO st_dispensing_history_lines
                            (history_id, party_type, customer_code, party_name, status_key, caregiver_code, caregiver_name,
                             cnk, amp_code, ampp_code, product_name_nl, product_name_fr, package_display_nl, package_display_fr,
                             quantity, sort_order)
                         SELECT $1, party.party_type, party.customer_code,
                                COALESCE(previous_line.party_name,
                                    CASE WHEN party.party_type = \'anonymous\' THEN $4::VARCHAR(260) ELSE party.display_name END),
                                line.status_key, line.caregiver_code,
                                COALESCE(previous_line.caregiver_name,
                                    btrim(COALESCE(caregiver.name_first, \'\') || \' \' || COALESCE(caregiver.name_last, \'\'))),
                                line.cnk, line.amp_code, line.ampp_code, line.product_name_nl, line.product_name_fr,
                                line.package_display_nl, line.package_display_fr, line.quantity, line.sort_order
                         FROM dt_dispensing_lines line
                         JOIN dt_dispensing_parties party
                           ON party.company_id = line.company_id AND party.dispensing_session_id = line.dispensing_session_id
                          AND party.id = line.party_id
                         LEFT JOIN bt_caregivers caregiver ON caregiver.caregiver_code = line.caregiver_code
                         LEFT JOIN LATERAL (
                             SELECT old_line.party_name, old_line.caregiver_name
                             FROM st_dispensing_history_lines old_line
                             WHERE old_line.history_id = $1
                               AND old_line.party_type = party.party_type
                               AND old_line.customer_code IS NOT DISTINCT FROM party.customer_code
                               AND old_line.status_key = line.status_key
                               AND old_line.caregiver_code IS NOT DISTINCT FROM line.caregiver_code
                               AND old_line.cnk = line.cnk AND old_line.amp_code = line.amp_code
                               AND old_line.ampp_code = line.ampp_code
                             ORDER BY old_line.id LIMIT 1
                         ) previous_line ON TRUE
                         WHERE line.company_id = $2 AND line.dispensing_session_id = $3::UUID',
                        [$sourceHistoryId, $companyId, $sessionId, $walkinTitle]
                    );
                    db_query_params(
                        'dispensing.correction_history_lines_clear',
                        'DELETE FROM st_dispensing_history_lines WHERE history_id = $1 AND id <= $2',
                        [$sourceHistoryId, $oldHistoryLineMax]
                    );
                    $historyUpdated = true;
                }
            } else {
                $history = db_query_params(
                    'dispensing.history_create',
                    'INSERT INTO st_dispensing_history
                        (company_id, dispensing_session_id, created_by_user_code, status_key, prescription_number,
                         receipt_thank_you, receipt_snapshot)
                     SELECT $1, session.id, $3::VARCHAR(6), session.status_key, $6::BIGINT, $4, $5::jsonb
                     FROM dt_dispensing_sessions session
                     WHERE session.id = $2::UUID AND session.company_id = $1
                       AND session.created_by_user_code = $3::VARCHAR(6) AND session.state = \'open\'
                     RETURNING id',
                    [$companyId, $sessionId, $actorCode, (string) $receipt['thank_you'], (string) $snapshotJson, $prescriptionNumber]
                );
                $historyRow = pg_fetch_assoc($history);
                if (!$historyRow) throw new RuntimeException('Could not create dispensing history header.');
                $historyId = (int) $historyRow['id'];
                db_query_params(
                    'dispensing.history_lines_create',
                    'INSERT INTO st_dispensing_history_lines
                        (history_id, party_type, customer_code, party_name, status_key, caregiver_code, caregiver_name,
                         cnk, amp_code, ampp_code, product_name_nl, product_name_fr, package_display_nl, package_display_fr,
                         quantity, sort_order)
                     SELECT $1, party.party_type, party.customer_code,
                            CASE WHEN party.party_type = \'anonymous\' THEN $4::VARCHAR(260) ELSE party.display_name END,
                            line.status_key, line.caregiver_code,
                            btrim(COALESCE(caregiver.name_first, \'\') || \' \' || COALESCE(caregiver.name_last, \'\')),
                            line.cnk, line.amp_code, line.ampp_code, line.product_name_nl, line.product_name_fr,
                            line.package_display_nl, line.package_display_fr, line.quantity, line.sort_order
                     FROM dt_dispensing_lines line
                     JOIN dt_dispensing_parties party
                       ON party.company_id = line.company_id AND party.dispensing_session_id = line.dispensing_session_id
                      AND party.id = line.party_id
                     LEFT JOIN bt_caregivers caregiver ON caregiver.caregiver_code = line.caregiver_code
                     WHERE line.company_id = $2 AND line.dispensing_session_id = $3::UUID',
                    [$historyId, $companyId, $sessionId, $walkinTitle]
                );
            }
            db_query_params(
                'dispensing.session_lines_clear',
                'DELETE FROM dt_dispensing_lines WHERE company_id = $1 AND dispensing_session_id = $2::UUID',
                [$companyId, $sessionId]
            );
            db_query_params(
                'dispensing.session_parties_clear',
                'DELETE FROM dt_dispensing_parties WHERE company_id = $1 AND dispensing_session_id = $2::UUID',
                [$companyId, $sessionId]
            );
            db_query_params(
                'dispensing.session_mark_complete',
                'UPDATE dt_dispensing_sessions SET state = \'completed\', completed_at = now(), updated_at = now()
                 WHERE company_id = $1 AND id = $2::UUID AND created_by_user_code = $3 AND state = \'open\'',
                [$companyId, $sessionId, $actorCode]
            );
            $commit = db_query_params('dispensing.session_complete_commit', 'COMMIT');
            if (!$commit) throw new RuntimeException('Could not commit dispensing history.');
            $transactionStarted = false;
            $respond(['completed' => true, 'history_updated' => $historyUpdated]);
        }

        if ($action !== 'load') {
            $respond(['error' => 'unknown_action'], 400);
        }
        $respond([
            'session_id' => $sessionId,
            'status_key' => $statusKey,
            'parties' => grompil_dispensing_session_parties($companyId, $sessionId, $walkinTitle),
            'lines' => grompil_dispensing_session_lines($companyId, $sessionId, $walkinTitle),
        ]);
    } catch (InvalidArgumentException $error) {
        if ($transactionStarted) db_query_params('dispensing.session_complete_rollback', 'ROLLBACK');
        $respond(['error' => $error->getMessage()], 400);
    } catch (Throwable $error) {
        if ($transactionStarted) db_query_params('dispensing.session_complete_rollback', 'ROLLBACK');
        error_log('Dispensing session request failed: ' . get_class($error));
        $respond(['error' => 'session_unavailable'], 503);
    }
}

/**
 * Load the company and product data for a dispensing receipt.
 *
 * @param int $companyId Company identifier.
 * @param string $sessionId Dispensing session identifier.
 * @param string $walkinTitle Localized label for anonymous walk-in customers.
 *
 * @return array<string, mixed> The resulting data.
 */
function grompil_dispensing_receipt_data(int $companyId, string $sessionId, string $walkinTitle): array
{
    $companyResult = db_query_params(
        'dispensing.receipt_company',
        'SELECT company.name, company.legal_name, company.enterprise_number, company.vat_number,
                location.street, location.house_number, location.box_number, location.postal_code,
                location.city, location.country_code
         FROM bt_companies company
         LEFT JOIN LATERAL (
             SELECT * FROM dt_company_locations
             WHERE company_id = company.id AND is_active AND is_primary
             ORDER BY sort_order, id LIMIT 1
         ) location ON TRUE
         WHERE company.id = $1',
        [$companyId]
    );
    $company = pg_fetch_assoc($companyResult) ?: [];
    $sessionResult = db_query_params(
        'dispensing.receipt_session',
        'SELECT session.created_at, session.status_key, session.prescription_number,
                source_history.prescription_number AS source_prescription_number
         FROM dt_dispensing_sessions session
         LEFT JOIN st_dispensing_history source_history
           ON source_history.company_id = session.company_id AND source_history.id = session.source_history_id
         WHERE session.company_id = $1 AND session.id = $2::UUID AND session.state = \'open\'',
        [$companyId, $sessionId]
    );
    $session = pg_fetch_assoc($sessionResult);
    if (!$session) throw new RuntimeException('Dispensing session is unavailable for receipt preview.');
    $thankYouResult = db_query_params(
        'dispensing.receipt_company_thank_you',
        'SELECT dispensing_receipt_thank_you FROM ct_company_preferences WHERE company_id = $1',
        [$companyId]
    );
    $thankYouRow = pg_fetch_assoc($thankYouResult) ?: [];
    $thankYou = trim((string) ($thankYouRow['dispensing_receipt_thank_you'] ?? ''));
    if ($thankYou === '') $thankYou = fn_t('dispense_receipt_thank_you_default');

    $receiptAt = new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels'));
    $lang = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
    $weekdays = [
        'NL' => ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'],
        'FR' => ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
        'EN' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        'DE' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
    ][$lang] ?? ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $addressParts = [trim((string) ($company['street'] ?? '') . ' ' . (string) ($company['house_number'] ?? '')
        . ((string) ($company['box_number'] ?? '') !== '' ? ' ' . (string) $company['box_number'] : '')),
        trim((string) ($company['postal_code'] ?? '') . ' ' . (string) ($company['city'] ?? '')),
        strtoupper((string) ($company['country_code'] ?? ''))];
    $groupsById = [];
    foreach (grompil_dispensing_session_parties($companyId, $sessionId, $walkinTitle) as $party) {
        $groupsById[(int) $party['id']] = [
            'name' => (string) $party['name'],
            'customer_code' => (string) $party['customer_code'],
            'type' => (string) $party['type'],
            'lines' => [],
        ];
    }
    $lines = grompil_dispensing_session_lines($companyId, $sessionId, $walkinTitle);
    foreach ($lines as $line) {
        $partyId = (int) $line['party_id'];
        if (!isset($groupsById[$partyId])) {
            $groupsById[$partyId] = [
                'name' => (string) $line['party_name'],
                'customer_code' => (string) $line['customer_code'],
                'type' => (string) ($line['customer_code'] === '' ? 'anonymous' : 'customer'),
                'lines' => [],
            ];
        }
        $groupsById[$partyId]['lines'][] = $line;
    }
    $groups = array_values(array_filter($groupsById, static fn(array $group): bool => $group['lines'] !== []));
    return [
        'company_name' => (string) ($company['name'] ?? ''),
        'legal_name' => (string) ($company['legal_name'] ?? ''),
        'enterprise_number' => trim((string) ($company['enterprise_number'] ?? '')),
        'vat_number' => trim((string) ($company['vat_number'] ?? '')),
        'address' => implode(' · ', array_values(array_filter($addressParts, static fn(string $part): bool => $part !== ''))),
        'session_id' => $sessionId,
        'prescription_number' => $session['source_prescription_number'] !== null
            ? (int) $session['source_prescription_number']
            : ($session['prescription_number'] === null ? null : (int) $session['prescription_number']),
        'status_key' => (string) $session['status_key'],
        'date' => $weekdays[(int) $receiptAt->format('w')] . ' ' . $receiptAt->format('d-m-Y H:i'),
        'thank_you' => $thankYou,
        'groups' => $groups,
        'lines' => $lines,
        'total_quantity' => array_sum(array_map(static fn(array $line): int => (int) $line['quantity'], $lines)),
    ];
}

/**
 * Add a customer to an open dispensing session.
 *
 * @param int $companyId Company identifier.
 * @param string $sessionId Dispensing session identifier.
 * @param string $customerCode Customer code.
 *
 * @return int The resulting identifier.
 */
function grompil_dispensing_session_add_customer(int $companyId, string $sessionId, string $customerCode): int
{
    $customerCode = strtoupper(trim($customerCode));
    if (preg_match('/^[A-Z0-9]{6}$/D', $customerCode) !== 1) {
        throw new InvalidArgumentException('invalid_customer');
    }
    $customerResult = db_query_params(
        'dispensing.session_customer',
        'SELECT customer_code, name_first, name_last FROM bt_customers
         WHERE company_id = $1 AND customer_code = $2 AND is_active',
        [$companyId, $customerCode]
    );
    $customer = pg_fetch_assoc($customerResult);
    if (!$customer) {
        throw new InvalidArgumentException('customer_unavailable');
    }
    $displayName = grompil_dispensing_display_name(
        (string) $customer['name_first'], (string) $customer['name_last']
    );
    $insert = db_query_params(
        'dispensing.session_party_add',
        'INSERT INTO dt_dispensing_parties (company_id, dispensing_session_id, party_type, customer_code, display_name)
         VALUES ($1, $2::UUID, \'customer\', $3, $4)
         ON CONFLICT (company_id, dispensing_session_id, customer_code)
             WHERE customer_code IS NOT NULL DO NOTHING
         RETURNING id',
        [$companyId, $sessionId, $customerCode, $displayName]
    );
    $row = pg_fetch_assoc($insert);
    if ($row) {
        return (int) $row['id'];
    }
    $existing = db_query_params(
        'dispensing.session_party_find',
        'SELECT id FROM dt_dispensing_parties
         WHERE company_id = $1 AND dispensing_session_id = $2::UUID AND customer_code = $3',
        [$companyId, $sessionId, $customerCode]
    );
    $row = pg_fetch_assoc($existing);
    if (!$row) {
        throw new RuntimeException('Could not load dispensing party.');
    }
    return (int) $row['id'];
}

/**
 * Load the parties and their product lines in a dispensing session.
 *
 * @param int $companyId Company identifier.
 * @param string $sessionId Dispensing session identifier.
 * @param string $walkinTitle Localized label for anonymous walk-in customers.
 *
 * @return array The resulting data.
 */
function grompil_dispensing_session_parties(int $companyId, string $sessionId, string $walkinTitle = ''): array
{
    $result = db_query_params(
        'dispensing.session_parties_load',
        'SELECT party.id, party.party_type, party.customer_code, party.display_name,
                customer.name_first, customer.name_last, customer.date_of_birth, customer.date_of_death,
                location.street, location.house_number, location.box_number, location.postal_code,
                location.city, location.country_code, location.wgs84_latitude, location.wgs84_longitude,
                (SELECT COUNT(*) FROM dt_user_contacts contact
                 WHERE contact.company_id = party.company_id AND contact.customer_code = party.customer_code
                   AND contact.user_code IS NULL AND contact.is_active) AS contact_count
         FROM dt_dispensing_parties party
         LEFT JOIN bt_customers customer
           ON customer.company_id = party.company_id AND customer.customer_code = party.customer_code
         LEFT JOIN LATERAL (
             SELECT street, house_number, box_number, postal_code, city, country_code, wgs84_latitude, wgs84_longitude
             FROM dt_customer_locations
             WHERE company_id = party.company_id AND customer_code = party.customer_code AND is_active
             ORDER BY is_primary DESC, sort_order, id LIMIT 1
         ) location ON TRUE
         WHERE party.company_id = $1 AND party.dispensing_session_id = $2::UUID
         ORDER BY party.sort_order, party.id',
        [$companyId, $sessionId]
    );
    $parties = [];
    while ($row = pg_fetch_assoc($result)) {
        $isCustomer = $row['party_type'] === 'customer';
        $birthDate = trim((string) ($row['date_of_birth'] ?? ''));
        $deathDate = trim((string) ($row['date_of_death'] ?? ''));
        $referenceDate = $deathDate !== '' ? $deathDate : (new DateTimeImmutable('today'))->format('Y-m-d');
        $ageDisplay = '';
        $birth = $birthDate !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate) : false;
        $ageDate = DateTimeImmutable::createFromFormat('!Y-m-d', $referenceDate);
        if ($birth !== false && $birth->format('Y-m-d') === $birthDate
            && $ageDate !== false && $ageDate->format('Y-m-d') === $referenceDate && $birth <= $ageDate) {
            $age = $birth->diff($ageDate);
            if ($age->y < 2) {
                $months = $age->y * 12 + $age->m;
                $ageDisplay = $months . ' ' . fn_t($months === 1 ? 'patient_age_month' : 'patient_age_months');
            } else {
                $ageDisplay = $age->y . ' ' . fn_t($age->y === 1 ? 'patient_age_year' : 'patient_age_years');
            }
        }
        $address = trim(implode(', ', array_filter([
            trim((string) ($row['street'] ?? '') . ' ' . (string) ($row['house_number'] ?? '')
                . ((string) ($row['box_number'] ?? '') !== '' ? ' bus ' . $row['box_number'] : '')),
            trim((string) ($row['postal_code'] ?? '') . ' ' . (string) ($row['city'] ?? '')),
        ])));
        $parties[] = [
            'id' => (int) $row['id'],
            'type' => (string) $row['party_type'],
            'customer_code' => (string) ($row['customer_code'] ?? ''),
            'name' => $row['party_type'] === 'customer'
                ? grompil_dispensing_display_name((string) $row['name_first'], (string) $row['name_last'])
                : ($walkinTitle !== '' ? $walkinTitle : (string) $row['display_name']),
            'first_name' => (string) ($row['name_first'] ?? ''),
            'last_name' => (string) ($row['name_last'] ?? ''),
            'age_display' => $isCustomer ? $ageDisplay : '',
            'is_deceased' => $isCustomer && $deathDate !== '',
            'deceased_display' => $isCustomer && $deathDate !== ''
                ? grompil_dispensing_deceased_display($deathDate, $ageDisplay) : '',
            'address' => $isCustomer ? $address : '',
            'latitude' => (string) ($row['wgs84_latitude'] ?? ''),
            'longitude' => (string) ($row['wgs84_longitude'] ?? ''),
            'contact_count' => $isCustomer ? (int) ($row['contact_count'] ?? 0) : 0,
        ];
    }
    return $parties;
}

/**
 * Normalize a dispensing product line for display.
 *
 * @param array<string, mixed> $line The line.
 * @param array $party The party.
 * @param string $statusCode The status code.
 *
 * @return array The resulting data.
 */
function grompil_dispensing_session_line_data(array $line, array $party, string $statusCode): array
{
    return [
        'id' => (int) $line['id'],
        'party_id' => (int) $line['party_id'],
        'party_name' => (string) ($party['display_name'] ?? ''),
        'customer_code' => (string) ($party['customer_code'] ?? ''),
        'status' => (string) $line['status_key'],
        'requires_caregiver' => grompil_dispensing_status_requires_caregiver((string) $line['status_key']),
        'statusShortCode' => $statusCode,
        'caregiver_code' => (string) ($line['caregiver_code'] ?? ''),
        'caregiver_name' => trim((string) ($line['caregiver_first'] ?? '') . ' ' . (string) ($line['caregiver_last'] ?? '')),
        'cnk' => (string) $line['cnk'],
        'amp_code' => (string) $line['amp_code'],
        'ampp_code' => (string) $line['ampp_code'],
        'name_nl' => (string) $line['product_name_nl'],
        'name_fr' => (string) $line['product_name_fr'],
        'package_nl' => (string) $line['package_display_nl'],
        'package_fr' => (string) $line['package_display_fr'],
        'quantity' => (int) $line['quantity'],
    ];
}

/**
 * Load the product lines for a dispensing session.
 *
 * @param int $companyId Company identifier.
 * @param string $sessionId Dispensing session identifier.
 * @param string $walkinTitle Localized label for anonymous walk-in customers.
 *
 * @return array The resulting data.
 */
function grompil_dispensing_session_lines(int $companyId, string $sessionId, string $walkinTitle = ''): array
{
    $result = db_query_params(
        'dispensing.session_lines_load',
        'SELECT line.id, line.party_id, line.status_key, line.caregiver_code,
                caregiver.name_first AS caregiver_first, caregiver.name_last AS caregiver_last,
                line.cnk, line.amp_code, line.ampp_code,
                line.product_name_nl, line.product_name_fr,
                line.package_display_nl, line.package_display_fr, line.quantity,
                COALESCE(status.short_codes ->> $3, \'\') AS status_short_code,
                party.display_name, party.party_type, party.customer_code
         FROM dt_dispensing_lines line
         JOIN dt_dispensing_parties party
           ON party.company_id = line.company_id AND party.dispensing_session_id = line.dispensing_session_id
          AND party.id = line.party_id
         LEFT JOIN ft_dd_varia status
           ON status.list_key = \'dispensing_statuses\' AND status."key" = line.status_key
         LEFT JOIN bt_caregivers caregiver ON caregiver.caregiver_code = line.caregiver_code
         WHERE line.company_id = $1 AND line.dispensing_session_id = $2::UUID
         ORDER BY party.sort_order, party.id, line.sort_order, line.id',
        [$companyId, $sessionId, strtoupper((string) ($_SESSION['language'] ?? 'NL'))]
    );
    $lines = [];
    while ($row = pg_fetch_assoc($result)) {
        if ($row['party_type'] === 'anonymous' && $walkinTitle !== '') {
            $row['display_name'] = $walkinTitle;
        }
        $lines[] = grompil_dispensing_session_line_data($row, $row, (string) $row['status_short_code']);
    }
    return $lines;
}

