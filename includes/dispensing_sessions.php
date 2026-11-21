<?php
declare(strict_types=1);

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

/** JSON API for per-tab dispensing drafts and their customer groups/lines. */
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

        if ($action === 'line_add') {
            $partyId = filter_var($payload['party_id'] ?? null, FILTER_VALIDATE_INT);
            $lineStatus = strtolower(trim((string) ($payload['status_key'] ?? '')));
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
                    company_id, dispensing_session_id, party_id, status_key, cnk, amp_code, ampp_code,
                    product_name_nl, product_name_fr, package_display_nl, package_display_fr, quantity
                 ) SELECT $1, $2::UUID, party.id, $3, $4, $5, $6, $7, $8, $9, $10, 1
                   FROM dt_dispensing_parties party
                   WHERE party.company_id = $1 AND party.dispensing_session_id = $2::UUID AND party.id = $11
                 ON CONFLICT (company_id, dispensing_session_id, party_id, cnk, amp_code, ampp_code, status_key)
                 DO UPDATE SET quantity = LEAST(999, dt_dispensing_lines.quantity + 1), updated_at = now()
                 RETURNING id, party_id, status_key, cnk, amp_code, ampp_code,
                           product_name_nl, product_name_fr, package_display_nl, package_display_fr, quantity',
                [$companyId, $sessionId, $lineStatus, $cnk, $ampCode, $amppCode,
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
            $updated = db_query_params(
                'dispensing.session_line_update',
                'UPDATE dt_dispensing_lines SET quantity = $1, updated_at = now()
                 WHERE id = $2 AND company_id = $3 AND dispensing_session_id = $4::UUID RETURNING id',
                [$quantity, $lineId, $companyId, $sessionId]
            );
            $respond(['updated' => pg_num_rows($updated) === 1]);
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
                $receipt = grompil_dispensing_receipt_data($companyId, $sessionId, $walkinTitle);
                if ($receipt['lines'] === []) $respond(['error' => 'empty_session'], 409);
                $pdf = grompil_dispensing_receipt_pdf($receipt, strtoupper((string) ($_SESSION['language'] ?? 'NL')));
                $respond(['pdf_base64' => base64_encode($pdf)]);
            }

            $begin = db_query_params('dispensing.session_complete_begin', 'BEGIN');
            if (!$begin) throw new RuntimeException('Could not start receipt transaction.');
            $transactionStarted = true;
            $lockedSession = db_query_params(
                'dispensing.session_complete_lock',
                'SELECT id, status_key FROM dt_dispensing_sessions
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
            $receipt = grompil_dispensing_receipt_data($companyId, $sessionId, $walkinTitle);
            if ($receipt['lines'] === []) {
                db_query_params('dispensing.session_complete_rollback_empty', 'ROLLBACK');
                $transactionStarted = false;
                $respond(['error' => 'empty_session'], 409);
            }
            $hasPrescriptionLine = false;
            foreach ($receipt['lines'] as $receiptLine) {
                if (($receiptLine['status'] ?? '') === 'prescription') {
                    $hasPrescriptionLine = true;
                    break;
                }
            }
            $prescriptionNumber = null;
            if ($hasPrescriptionLine) {
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
                $prescriptionNumber = (int) $counterRow['last_number'];
            }
            $snapshotJson = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
                    (history_id, party_type, customer_code, party_name, status_key, cnk, amp_code, ampp_code,
                     product_name_nl, product_name_fr, package_display_nl, package_display_fr, quantity, sort_order)
                 SELECT $1, party.party_type, party.customer_code,
                        CASE WHEN party.party_type = \'anonymous\' THEN $4::VARCHAR(260) ELSE party.display_name END,
                        line.status_key, line.cnk,
                        line.amp_code, line.ampp_code, line.product_name_nl, line.product_name_fr,
                        line.package_display_nl, line.package_display_fr, line.quantity, line.sort_order
                 FROM dt_dispensing_lines line
                 JOIN dt_dispensing_parties party
                   ON party.company_id = line.company_id AND party.dispensing_session_id = line.dispensing_session_id
                  AND party.id = line.party_id
                 WHERE line.company_id = $2 AND line.dispensing_session_id = $3::UUID',
                [$historyId, $companyId, $sessionId, $walkinTitle]
            );
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
            $respond(['completed' => true]);
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

/** @return array<string, mixed> */
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
        'SELECT created_at, status_key FROM dt_dispensing_sessions
         WHERE company_id = $1 AND id = $2::UUID AND state = \'open\'',
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
        'status_key' => (string) $session['status_key'],
        'date' => $weekdays[(int) $receiptAt->format('w')] . ' ' . $receiptAt->format('d-m-Y H:i'),
        'thank_you' => $thankYou,
        'groups' => $groups,
        'lines' => $lines,
        'total_quantity' => array_sum(array_map(static fn(array $line): int => (int) $line['quantity'], $lines)),
    ];
}

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

function grompil_dispensing_session_parties(int $companyId, string $sessionId, string $walkinTitle = ''): array
{
    $result = db_query_params(
        'dispensing.session_parties_load',
        'SELECT party.id, party.party_type, party.customer_code, party.display_name,
                customer.name_first, customer.name_last
         FROM dt_dispensing_parties party
         LEFT JOIN bt_customers customer
           ON customer.company_id = party.company_id AND customer.customer_code = party.customer_code
         WHERE party.company_id = $1 AND party.dispensing_session_id = $2::UUID
         ORDER BY party.sort_order, party.id',
        [$companyId, $sessionId]
    );
    $parties = [];
    while ($row = pg_fetch_assoc($result)) {
        $parties[] = [
            'id' => (int) $row['id'],
            'type' => (string) $row['party_type'],
            'customer_code' => (string) ($row['customer_code'] ?? ''),
            'name' => $row['party_type'] === 'customer'
                ? grompil_dispensing_display_name((string) $row['name_first'], (string) $row['name_last'])
                : ($walkinTitle !== '' ? $walkinTitle : (string) $row['display_name']),
        ];
    }
    return $parties;
}

/** @param array<string, mixed> $line */
function grompil_dispensing_session_line_data(array $line, array $party, string $statusCode): array
{
    return [
        'id' => (int) $line['id'],
        'party_id' => (int) $line['party_id'],
        'party_name' => (string) ($party['display_name'] ?? ''),
        'customer_code' => (string) ($party['customer_code'] ?? ''),
        'status' => (string) $line['status_key'],
        'statusShortCode' => $statusCode,
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

function grompil_dispensing_session_lines(int $companyId, string $sessionId, string $walkinTitle = ''): array
{
    $result = db_query_params(
        'dispensing.session_lines_load',
        'SELECT line.id, line.party_id, line.status_key, line.cnk, line.amp_code, line.ampp_code,
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

