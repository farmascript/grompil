<?php
/**
 * @date_cre: Fri 2026-11-27 11:02:00
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Searches and maintains company caregivers and their details.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:companies:caregivers
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/do_6_char_code.php';
$actor = require_login();
$isPlatformOperator = is_platform_operator($actor);
$company = current_company_context($actor);
if ($company === null && !$isPlatformOperator) {
    $company = require_company_context($actor);
}
$actorCode = (string) $actor['user_code'];
if ($company === null && $isPlatformOperator) {
    set_database_tenant_context(0, $actorCode, true);
}
$globalScope = $company === null && $isPlatformOperator;
$isLookupRequest = ($_GET['ajax'] ?? '') === 'search';
$canManageCaregivers = grompil_can_access_module('mb_caregivers.php', $actor, $company);
$canLookupCaregivers = $isLookupRequest && $company !== null && has_company_permission('pharmacy.dispense', $company, $actor);
if (!$canManageCaregivers && !$canLookupCaregivers) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Geen toegang');
}
$companyId = $company === null ? null : (int) $company['id'];
$detailCompanyId = $globalScope ? null : $companyId;

if (($_GET['ajax'] ?? '') === 'search') {
    header('Cache-Control: no-store, private');
    header('Content-Type: application/json; charset=utf-8');
    $rawQuery = $_GET['q'] ?? '';
    $query = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
    $queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
    if ($queryLength < 2 || $queryLength > 100) {
        echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query);
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalized);
    $pattern = '%' . $escaped . '%';
    $code = preg_match('/^[A-Z0-9]{6}$/i', $query) === 1 ? strtoupper($query) : '';
    try {
        $result = db_query_params(
            'caregivers.search',
            "SELECT bc.caregiver_code, bc.caregiver_type_key, bc.name_first, bc.name_last,
                    COALESCE(string_agg(DISTINCT ci.identifier_value, ', ' ORDER BY ci.identifier_value), '') AS identifiers
             FROM bt_caregivers bc
             LEFT JOIN dt_caregiver_identifiers ci ON ci.caregiver_code = bc.caregiver_code
                AND ci.is_active AND (ci.company_id IS NULL OR ci.company_id = $1)
             WHERE (bc.owner_company_id IS NULL OR bc.owner_company_id = $1)
               AND bc.is_active
               AND (lower(bc.name_first) LIKE $2 OR lower(bc.name_last) LIKE $2
                    OR lower(bc.name_first || ' ' || bc.name_last) LIKE $2
                    OR lower(bc.name_last || ' ' || bc.name_first) LIKE $2
                    OR bc.caregiver_code = NULLIF($3, '')
                    OR EXISTS (SELECT 1 FROM dt_caregiver_identifiers ix
                               WHERE ix.caregiver_code=bc.caregiver_code AND ix.is_active
                                 AND (ix.company_id IS NULL OR ix.company_id=$1)
                                 AND ix.identifier_value ILIKE $2))
             GROUP BY bc.caregiver_code, bc.caregiver_type_key, bc.name_first, bc.name_last
             ORDER BY lower(bc.name_last), lower(bc.name_first), bc.caregiver_code LIMIT 20",
            [$companyId, $pattern, $code]
        );
    } catch (GrompilDatabaseQueryException) {
        $result = false;
    }
    if (!$result) {
        http_response_code(503);
        echo json_encode(['results' => [], 'error' => 'search_unavailable']);
        exit;
    }
    $matches = [];
    while ($row = pg_fetch_assoc($result)) {
        $matches[] = [
            'caregiver_code' => (string) $row['caregiver_code'],
            'name' => trim((string) $row['name_first'] . ' ' . (string) $row['name_last']),
            'type' => (string) $row['caregiver_type_key'],
            'identifiers' => (string) $row['identifiers'],
        ];
    }
    echo json_encode(['results' => $matches], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$messageKey = '';
$selectedCodeRaw = $_GET['code'] ?? '';
$selectedCode = is_scalar($selectedCodeRaw) ? strtoupper(trim((string) $selectedCodeRaw)) : '';
if (preg_match('/^[A-Z0-9]{3}[A-Z0-9]{3}$/', $selectedCode) !== 1) {
    $selectedCode = '';
}
$queryRaw = $_GET['q'] ?? '';
$query = is_scalar($queryRaw) ? trim((string) $queryRaw) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
    $postedCode = strtoupper(trim((string) ($_POST['caregiver_code'] ?? '')));
    $postedCode = preg_match('/^[A-Z0-9]{3}[A-Z0-9]{3}$/', $postedCode) === 1 ? $postedCode : '';
    $success = false;

    try {
        if ($action === 'deactivate_caregiver' && $postedCode !== '') {
            $result = db_query_params('caregivers.deactivate',
                'UPDATE bt_caregivers SET is_active=FALSE, updated_by_user_code=$1, updated_at=now()
                 WHERE caregiver_code=$2 AND owner_company_id IS NOT DISTINCT FROM $3 AND is_active',
                [$actorCode, $postedCode, $detailCompanyId]);
            $success = $result !== false && pg_affected_rows($result) === 1;
            $selectedCode = '';
            $messageKey = $success ? 'caregivers_deactivated' : 'caregivers_save_failed';
        } elseif ($action === 'save_caregiver') {
            $type = (string) ($_POST['caregiver_type_key'] ?? '');
            $firstName = trim((string) ($_POST['name_first'] ?? ''));
            $lastName = trim((string) ($_POST['name_last'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $validTypes = ['physician', 'dentist', 'veterinarian', 'midwife', 'other'];
            if ($lastName === '' || !in_array($type, $validTypes, true)) {
                $messageKey = 'caregivers_invalid';
            } elseif ($postedCode !== '') {
                $result = db_query_params(
                    'caregivers.update',
                    'UPDATE bt_caregivers SET caregiver_type_key=$1, name_first=$2, name_last=$3,
                        title=$4, updated_by_user_code=$5, updated_at=now()
                     WHERE caregiver_code=$6 AND owner_company_id IS NOT DISTINCT FROM $7',
                    [$type, $firstName, $lastName, $title, $actorCode, $postedCode, $detailCompanyId]
                );
                $success = $result !== false && pg_affected_rows($result) === 1;
                $selectedCode = $postedCode;
            } else {
                for ($attempt = 0; $attempt < 8 && !$success; $attempt++) {
                    $newCode = getalNaarCode(random_int(0, 351519999));
                    $result = db_query_params(
                        'caregivers.create',
                        'INSERT INTO bt_caregivers (caregiver_code, owner_company_id, caregiver_type_key,
                            name_first, name_last, title, created_by_user_code, updated_by_user_code)
                         VALUES ($1,$2,$3,$4,$5,$6,$7,$7) ON CONFLICT (caregiver_code) DO NOTHING',
                         [$newCode, $detailCompanyId, $type, $firstName, $lastName, $title, $actorCode]
                    );
                    $success = $result !== false && pg_affected_rows($result) === 1;
                    if ($success) {
                        $selectedCode = $newCode;
                    }
                }
            }
            $messageKey = $success ? 'caregivers_saved' : ($messageKey ?: 'caregivers_save_failed');
        } elseif (in_array($action, ['save_identifier', 'save_contact', 'save_location', 'deactivate_identifier', 'deactivate_contact', 'deactivate_location'], true)
            && $postedCode !== '') {
            $ownerResult = db_query_params(
                'caregivers.detail_owner',
                'SELECT caregiver_code FROM bt_caregivers
                 WHERE caregiver_code=$1 AND (owner_company_id IS NULL OR owner_company_id=$2)',
                [$postedCode, $companyId]
            );
            if (!$ownerResult || pg_num_rows($ownerResult) !== 1) {
                $messageKey = 'caregivers_not_found';
            } else {
                $selectedCode = $postedCode;
                if (str_starts_with($action, 'deactivate_')) {
                    [$table, $entity] = match ($action) {
                        'deactivate_identifier' => ['dt_caregiver_identifiers', 'identifier'],
                        'deactivate_contact' => ['dt_caregiver_contacts', 'contact'],
                        default => ['dt_caregiver_locations', 'location'],
                    };
                    $id = filter_var($_POST['detail_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($id) {
                        $result = db_query_params(
                            'caregivers.deactivate_detail',
                            "UPDATE {$table} SET is_active=FALSE, updated_by_user_code=$1, updated_at=now()
                             WHERE id=$2 AND caregiver_code=$3 AND company_id IS NOT DISTINCT FROM $4 AND is_active",
                            [$actorCode, $id, $postedCode, $detailCompanyId]
                        );
                        $success = $result !== false && pg_affected_rows($result) === 1;
                    }
                } else {
                    $detailId = filter_var($_POST['detail_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($action === 'save_identifier') {
                        $kind = (string) ($_POST['identifier_type_key'] ?? '');
                        $value = trim((string) ($_POST['identifier_value'] ?? ''));
                        $issuer = trim((string) ($_POST['issuer'] ?? ''));
                        $source = trim((string) ($_POST['source_key'] ?? 'manual')) ?: 'manual';
                        $sourceRef = trim((string) ($_POST['source_reference'] ?? ''));
                        if (!in_array($kind, ['riziv', 'veterinary', 'professional', 'other'], true)
                            || $value === '' || ($kind === 'riziv' && preg_match('/^[0-9]{11}$/', $value) !== 1)) {
                            $messageKey = 'caregivers_invalid';
                        } else {
                            $result = $detailId
                                ? db_query_params('caregivers.identifier_update',
                                    'UPDATE dt_caregiver_identifiers SET identifier_type_key=$1, identifier_value=$2, issuer=$3,
                                        updated_by_user_code=$4, updated_at=now()
                                     WHERE id=$5 AND caregiver_code=$6 AND company_id IS NOT DISTINCT FROM $7',
                                    [$kind, $value, $issuer, $actorCode, $detailId, $postedCode, $detailCompanyId])
                                : db_query_params('caregivers.identifier_create',
                                    'INSERT INTO dt_caregiver_identifiers (caregiver_code, company_id, identifier_type_key,
                                        identifier_value, issuer, source_key, source_reference, created_by_user_code, updated_by_user_code)
                                     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$8)',
                                    [$postedCode, $detailCompanyId, $kind, $value, $issuer, $source, $sourceRef, $actorCode]);
                            $success = $result !== false;
                        }
                    } elseif ($action === 'save_contact') {
                        $kind = (string) ($_POST['contact_type_key'] ?? '');
                        $purpose = trim((string) ($_POST['purpose_key'] ?? ''));
                        $value = trim((string) ($_POST['contact_value'] ?? ''));
                        $label = trim((string) ($_POST['label'] ?? ''));
                        if (!preg_match('/^[a-z0-9_]{1,80}$/', $kind) || $value === '') {
                            $messageKey = 'caregivers_invalid';
                        } else {
                            $result = $detailId
                                ? db_query_params('caregivers.contact_update',
                                    'UPDATE dt_caregiver_contacts SET contact_type_key=$1, purpose_key=$2, contact_value=$3,
                                        label=$4, updated_by_user_code=$5, updated_at=now()
                                     WHERE id=$6 AND caregiver_code=$7 AND company_id IS NOT DISTINCT FROM $8',
                                    [$kind, $purpose, $value, $label, $actorCode, $detailId, $postedCode, $detailCompanyId])
                                : db_query_params('caregivers.contact_create',
                                    'INSERT INTO dt_caregiver_contacts (caregiver_code, company_id, contact_type_key,
                                        purpose_key, contact_value, label, created_by_user_code, updated_by_user_code)
                                     VALUES ($1,$2,$3,$4,$5,$6,$7,$7)',
                                    [$postedCode, $detailCompanyId, $kind, $purpose, $value, $label, $actorCode]);
                            $success = $result !== false;
                        }
                    } else {
                        $type = (string) ($_POST['address_type_key'] ?? '');
                        $address = array_map(static fn (string $key): string => trim((string) ($_POST[$key] ?? '')),
                            ['street', 'house_number', 'box_number', 'postal_code', 'city', 'country_code', 'label', 'address_reference_id']);
                        $address['country_code'] = strtoupper($address['country_code']);
                        $coordinates = [
                            grompil_address_coordinate($_POST['lambert72_x'] ?? '', -1000000, 1000000, 3),
                            grompil_address_coordinate($_POST['lambert72_y'] ?? '', -1000000, 1000000, 3),
                            grompil_address_coordinate($_POST['wgs84_latitude'] ?? '', -90, 90, 7),
                            grompil_address_coordinate($_POST['wgs84_longitude'] ?? '', -180, 180, 7),
                        ];
                        $hasAddress = implode('', array_slice($address, 0, 5)) !== '';
                        $addressAllowed = grompil_address_selection_allowed(
                            $address,
                            isset($_POST['manual_address_without_coordinates'])
                        );
                        if (!preg_match('/^[a-z0-9_]{1,80}$/', $type) || !preg_match('/^[A-Z]{2}$/', $address['country_code'])
                            || !$hasAddress || !$addressAllowed) {
                            $messageKey = 'caregivers_invalid';
                        } else {
                            $result = $detailId
                                ? db_query_params('caregivers.location_update',
                                    'UPDATE dt_caregiver_locations SET address_type_key=$1, street=$2, house_number=$3,
                                        box_number=$4, postal_code=$5, city=$6, country_code=$7, label=$8,
                                        address_reference_id=$9, lambert72_x=$10::numeric, lambert72_y=$11::numeric,
                                        wgs84_latitude=$12::numeric, wgs84_longitude=$13::numeric,
                                        updated_by_user_code=$14, updated_at=now()
                                     WHERE id=$15 AND caregiver_code=$16 AND company_id IS NOT DISTINCT FROM $17',
                                    [$type, $address['street'], $address['house_number'], $address['box_number'], $address['postal_code'], $address['city'], $address['country_code'], $address['label'], $address['address_reference_id'], $coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3], $actorCode, $detailId, $postedCode, $detailCompanyId])
                                : db_query_params('caregivers.location_create',
                                    'INSERT INTO dt_caregiver_locations (caregiver_code, company_id, address_type_key,
                                        street, house_number, box_number, postal_code, city, country_code, label,
                                        address_reference_id, lambert72_x, lambert72_y, wgs84_latitude, wgs84_longitude,
                                        created_by_user_code, updated_by_user_code)
                                     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12::numeric,$13::numeric,$14::numeric,$15::numeric,$16,$16)',
                                    [$postedCode, $detailCompanyId, $type, $address['street'], $address['house_number'], $address['box_number'], $address['postal_code'], $address['city'], $address['country_code'], $address['label'], $address['address_reference_id'], $coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3], $actorCode]);
                            $success = $result !== false;
                        }
                    }
                }
                if ($messageKey === '') {
                    $messageKey = $success ? (str_starts_with($action, 'deactivate_') ? 'caregivers_detail_deactivated' : 'caregivers_saved') : 'caregivers_save_failed';
                }
            }
        }
    } catch (GrompilDatabaseQueryException) {
        $messageKey = 'caregivers_save_failed';
    }
    if ($success) {
        $isAddition = $action === 'save_caregiver' && $postedCode === '';
        if (in_array($action, ['save_identifier', 'save_contact', 'save_location'], true)
            && trim((string) ($_POST['detail_id'] ?? '')) === '') {
            $isAddition = true;
        }
        if ($isAddition) {
            $messageKey = 'caregivers_added';
        }
        $_SESSION['caregivers_flash_message'] = $messageKey;
        $returnQuery = $selectedCode !== '' ? '?code=' . rawurlencode($selectedCode) : ($query !== '' ? '?q=' . rawurlencode($query) : '');
        header('Location: mb_caregivers.php' . $returnQuery, true, 303);
        exit;
    }
}
$flashMessage = is_scalar($_SESSION['caregivers_flash_message'] ?? null) ? (string) $_SESSION['caregivers_flash_message'] : '';
unset($_SESSION['caregivers_flash_message']);
if ($messageKey === '') $messageKey = $flashMessage;
$successModalHtml = in_array($messageKey, [
    'caregivers_added', 'caregivers_saved', 'caregivers_deactivated', 'caregivers_detail_deactivated',
], true)
    ? '<div class="modal fade" id="caregiver-save-success-modal" tabindex="-1" aria-labelledby="caregiver-save-success-title" aria-hidden="true" data-caregiver-success-modal><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="caregiver-save-success-title">'
        . e(fn_t('caregivers_saved_modal_title')) . '</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . e(fn_t('close')) . '"></button></div><div class="modal-body">'
        . e(fn_t($messageKey)) . '</div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">' . e(fn_t('close')) . '</button></div></div></div></div>'
    : '';

$caregiverTypes = [
    'physician' => 'caregivers_type_physician', 'dentist' => 'caregivers_type_dentist',
    'veterinarian' => 'caregivers_type_veterinarian', 'midwife' => 'caregivers_type_midwife', 'other' => 'caregivers_type_other',
];
$identifierTypes = ['riziv' => 'caregivers_identifier_riziv', 'veterinary' => 'caregivers_identifier_veterinary',
    'professional' => 'caregivers_identifier_professional', 'other' => 'caregivers_type_other'];
$contactTypes = [];
$addressTypes = [];
try {
    $result = db_query_params('caregivers.contact_types',
        "SELECT \"key\", content FROM ft_dd_varia WHERE list_key='contact_types' AND parent_id IS NOT NULL AND is_active ORDER BY sort_order, \"key\"", []);
    while ($result && $row = pg_fetch_assoc($result)) $contactTypes[(string) $row['key']] = str_starts_with((string) $row['content'], '@') ? fn_t(trim((string) $row['content'], '@')) : (string) $row['content'];
    $result = db_query_params('caregivers.address_types',
        "SELECT \"key\", content FROM ft_dd_varia WHERE list_key='address_types' AND parent_id IS NOT NULL AND is_active ORDER BY sort_order, \"key\"", []);
    while ($result && $row = pg_fetch_assoc($result)) $addressTypes[(string) $row['key']] = str_starts_with((string) $row['content'], '@') ? fn_t(trim((string) $row['content'], '@')) : (string) $row['content'];
} catch (GrompilDatabaseQueryException) {
    $contactTypes = ['email' => 'Email', 'phone' => 'Telefoon', 'mobile' => 'Mobiel', 'other' => 'Andere'];
    $addressTypes = ['residential' => 'Privéadres', 'work' => 'Werkadres', 'other' => 'Andere'];
}

$selected = null;
if ($selectedCode !== '') {
    try {
        $result = db_query_params('caregivers.load',
            'SELECT bc.*, (bc.owner_company_id=$2 OR $3::boolean) AS is_editable FROM bt_caregivers bc
             WHERE bc.caregiver_code=$1 AND (bc.owner_company_id IS NULL OR bc.owner_company_id=$2)',
            [$selectedCode, $companyId, $globalScope ? 'true' : 'false']);
        $selected = $result ? pg_fetch_assoc($result) : false;
        if ($selected === false) $selected = null;
    } catch (GrompilDatabaseQueryException) { $selected = null; }
}

$listRows = '';
$escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtolower($query));
$pattern = '%' . $escapedQuery . '%';
try {
    $list = db_query_params('caregivers.list',
        "SELECT bc.caregiver_code, bc.caregiver_type_key, bc.name_first, bc.name_last, bc.owner_company_id,
                COALESCE(string_agg(DISTINCT ci.identifier_value, ', ' ORDER BY ci.identifier_value), '') AS identifiers
         FROM bt_caregivers bc LEFT JOIN dt_caregiver_identifiers ci ON ci.caregiver_code=bc.caregiver_code
           AND ci.is_active AND (ci.company_id IS NULL OR ci.company_id=$1)
         WHERE (bc.owner_company_id IS NULL OR bc.owner_company_id=$1) AND bc.is_active
           AND ($2='' OR lower(bc.name_first || ' ' || bc.name_last) LIKE $3
                OR lower(bc.name_last || ' ' || bc.name_first) LIKE $3 OR bc.caregiver_code ILIKE $3
                OR EXISTS (SELECT 1 FROM dt_caregiver_identifiers ix WHERE ix.caregiver_code=bc.caregiver_code
                    AND ix.is_active AND (ix.company_id IS NULL OR ix.company_id=$1) AND ix.identifier_value ILIKE $3))
         GROUP BY bc.caregiver_code, bc.caregiver_type_key, bc.name_first, bc.name_last, bc.owner_company_id
         ORDER BY lower(bc.name_last), lower(bc.name_first), bc.caregiver_code LIMIT 100",
        [$companyId, $query, $pattern]);
    while ($list && $row = pg_fetch_assoc($list)) {
        $isLocal = $row['owner_company_id'] !== null;
        $listRows .= '<a class="list-group-item list-group-item-action" href="mb_caregivers.php?code=' . e((string) $row['caregiver_code']) . '&q=' . rawurlencode($query) . '">'
            . '<strong>' . e(trim((string) $row['name_first'] . ' ' . (string) $row['name_last'])) . '</strong>'
            . ' <span class="badge text-bg-light">' . e(fn_t($caregiverTypes[(string) $row['caregiver_type_key']] ?? 'caregivers_type_other')) . '</span>'
            . ' <span class="small text-secondary">' . e((string) $row['caregiver_code']) . ($row['identifiers'] !== '' ? ' · ' . e((string) $row['identifiers']) : '')
            . ' · ' . e(fn_t($isLocal ? 'caregivers_scope_company' : 'caregivers_scope_shared')) . '</span></a>';
    }
} catch (GrompilDatabaseQueryException) { $listRows = ''; }
if ($listRows === '') $listRows = '<div class="list-group-item text-secondary">' . e(fn_t('caregivers_empty')) . '</div>';

$typeOptions = '';
foreach ($caregiverTypes as $key => $translationKey) {
    $typeOptions .= '<option value="' . e($key) . '"' . (($selected['caregiver_type_key'] ?? 'physician') === $key ? ' selected' : '') . '>' . e(fn_t($translationKey)) . '</option>';
}
$identityForm = '<section class="card shadow-sm mb-4"><div class="card-header fw-semibold">' . e(fn_t($selected ? 'caregivers_edit' : 'caregivers_add')) . '</div><div class="card-body">'
    . '<form method="post" action="mb_caregivers.php' . ($query !== '' ? '?q=' . rawurlencode($query) : '') . '">' . csrf_field()
    . '<input type="hidden" name="action" value="save_caregiver"><input type="hidden" name="caregiver_code" value="' . e((string) ($selected['caregiver_code'] ?? '')) . '">'
    . '<div class="row g-3"><div class="col-md-4"><label class="form-label" for="caregiver-type">' . e(fn_t('caregivers_type')) . '</label><select class="form-select" id="caregiver-type" name="caregiver_type_key" required>' . $typeOptions . '</select></div>'
    . '<div class="col-md-4"><label class="form-label" for="caregiver-first">' . e(fn_t('patient_first_name')) . '</label><input class="form-control" id="caregiver-first" name="name_first" maxlength="120" value="' . e((string) ($selected['name_first'] ?? '')) . '"></div>'
    . '<div class="col-md-4"><label class="form-label" for="caregiver-last">' . e(fn_t('patient_last_name')) . '</label><input class="form-control" id="caregiver-last" name="name_last" maxlength="160" required value="' . e((string) ($selected['name_last'] ?? '')) . '"></div>'
    . '<div class="col-md-6"><label class="form-label" for="caregiver-title">' . e(fn_t('caregivers_professional_title')) . '</label><input class="form-control" id="caregiver-title" name="title" maxlength="120" value="' . e((string) ($selected['title'] ?? '')) . '"><div class="form-text">' . e(fn_t('caregivers_professional_title_help')) . '</div></div></div>'
    . '<div class="d-flex gap-2 align-items-center mt-3"><button class="btn btn-primary" type="submit">' . e(fn_t('save')) . '</button>'
    . (!empty($selected['caregiver_code']) ? '<span class="small text-secondary">' . e(fn_t('caregivers_code')) . ': ' . e((string) $selected['caregiver_code']) . '</span>' : '')
    . (!empty($selected) && ($selected['is_editable'] ?? 'f') !== 't' ? '<span class="small text-secondary">' . e(fn_t('caregivers_shared_read_only')) . '</span>' : '')
    . '</div></form></div></section>';
if (!empty($selected) && ($selected['is_editable'] ?? 'f') === 't') {
    $identityForm = str_replace('</div></form></div></section>', '</div></form><form method="post" action="mb_caregivers.php">' . csrf_field()
        . '<input type="hidden" name="action" value="deactivate_caregiver"><input type="hidden" name="caregiver_code" value="' . e((string) $selected['caregiver_code']) . '">'
        . '<button class="btn btn-sm btn-outline-danger mt-2" type="submit">' . e(fn_t('caregivers_deactivate')) . '</button></form></div></section>', $identityForm);
}
if (!empty($selected) && ($selected['is_editable'] ?? 'f') !== 't') {
    $identityForm = '<section class="card shadow-sm mb-4"><div class="card-header fw-semibold">' . e(fn_t('caregivers_profile')) . '</div><div class="card-body">'
        . '<div class="text-secondary small mb-2">' . e(fn_t('caregivers_shared_read_only')) . '</div><h2 class="h5">' . e(trim((string) $selected['name_first'] . ' ' . (string) $selected['name_last'])) . '</h2>'
        . '<div>' . e(fn_t($caregiverTypes[(string) $selected['caregiver_type_key']] ?? 'caregivers_type_other')) . ($selected['title'] !== '' ? ' · ' . e((string) $selected['title']) : '') . '</div>'
        . '<div class="small text-secondary mt-2">' . e(fn_t('caregivers_code')) . ': ' . e((string) $selected['caregiver_code']) . '</div></div></section>';
}

$detailHtml = '';
if ($selected !== null) {
    foreach ([
        ['dt_caregiver_identifiers', 'caregivers_identifiers_heading', 'identifier', 'caregiver_identifiers'],
        ['dt_caregiver_contacts', 'caregivers_contacts_heading', 'contact', 'caregiver_contacts'],
        ['dt_caregiver_locations', 'caregivers_locations_heading', 'location', 'caregiver_locations'],
    ] as [$table, $headingKey, $entity, $resultName]) {
        try {
            $rows = db_query_params('caregivers.' . $resultName,
                "SELECT * FROM {$table} WHERE caregiver_code=$1 AND is_active AND (company_id IS NULL OR company_id=$2) ORDER BY company_id NULLS FIRST, sort_order, id",
                [$selectedCode, $detailCompanyId]);
        } catch (GrompilDatabaseQueryException) { $rows = false; }
        $cards = '';
        while ($rows && $detail = pg_fetch_assoc($rows)) {
            $isShared = $detail['company_id'] === null && !$globalScope;
            $scopeText = fn_t($isShared ? 'caregivers_scope_shared' : 'caregivers_scope_company');
            $fields = '';
            if ($entity === 'identifier') {
                $opts = '';
                foreach ($identifierTypes as $key => $labelKey) $opts .= '<option value="' . e($key) . '"' . ($detail['identifier_type_key'] === $key ? ' selected' : '') . '>' . e(fn_t($labelKey)) . '</option>';
                $fields = '<div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_identifier_type')) . '</label><select class="form-select" name="identifier_type_key"' . ($isShared ? ' disabled' : '') . '>' . $opts . '</select></div>'
                    . '<div class="col-md-4"><label class="form-label">' . e(fn_t('caregivers_identifier_value')) . '</label><input class="form-control" name="identifier_value" value="' . e((string) $detail['identifier_value']) . '"' . ($isShared ? ' disabled' : '') . '></div>'
                    . '<div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_identifier_issuer')) . '</label><input class="form-control" name="issuer" value="' . e((string) $detail['issuer']) . '"' . ($isShared ? ' disabled' : '') . '></div>';
            } elseif ($entity === 'contact') {
                $opts = '';
                foreach ($contactTypes as $key => $label) $opts .= '<option value="' . e($key) . '"' . ($detail['contact_type_key'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
                $fields = '<div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_contact_type')) . '</label><select class="form-select" name="contact_type_key"' . ($isShared ? ' disabled' : '') . '>' . $opts . '</select></div>'
                    . '<div class="col-md-4"><label class="form-label">' . e(fn_t('caregivers_contact_value')) . '</label><input class="form-control" name="contact_value" value="' . e((string) $detail['contact_value']) . '"' . ($isShared ? ' disabled' : '') . '></div>'
                    . '<div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_label')) . '</label><input class="form-control" name="label" value="' . e((string) $detail['label']) . '"' . ($isShared ? ' disabled' : '') . '></div>'
                    . '<input type="hidden" name="purpose_key" value="' . e((string) $detail['purpose_key']) . '">';
            } else {
                $opts = '';
                foreach ($addressTypes as $key => $label) $opts .= '<option value="' . e($key) . '"' . ($detail['address_type_key'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
                $addressFields = fn_templateRender('address_fields.html', [
                    'address_prefix' => 'caregiver-address-' . (int) $detail['id'],
                    'address_autosave' => '0',
                    'address_title' => fn_t('caregivers_address_lookup_title'),
                    'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
                    'address_lookup_no_results' => fn_t('address_lookup_no_results'),
                    'address_postal_reminder' => fn_t('address_postal_reminder'),
                    'address_select_required' => fn_t('address_select_required'),
                    'address_manual_prompt' => fn_t('address_manual_prompt'),
                    'address_manual_required' => fn_t('address_manual_required'),
                    'address_manual_confirm' => fn_t('address_manual_confirm'),
                    'address_country_code_value' => (string) $detail['country_code'],
                    'address_reference_id_value' => (string) $detail['address_reference_id'],
                    'lambert72_x_value' => (string) ($detail['lambert72_x'] ?? ''),
                    'lambert72_y_value' => (string) ($detail['lambert72_y'] ?? ''),
                    'wgs84_latitude_value' => (string) ($detail['wgs84_latitude'] ?? ''),
                    'wgs84_longitude_value' => (string) ($detail['wgs84_longitude'] ?? ''),
                    'street_reference_id_value' => '',
                    'street_value' => (string) $detail['street'],
                    'house_number_value' => (string) $detail['house_number'],
                    'box_number_value' => (string) $detail['box_number'],
                    'postal_code_value' => (string) $detail['postal_code'],
                    'city_value' => (string) $detail['city'],
                    'company_city_label' => fn_t('address_city'),
                    'company_postal_code' => fn_t('address_postal_code'),
                    'company_street' => fn_t('address_street'),
                    'address_house_number' => fn_t('address_house_number'),
                    'address_box_number' => fn_t('address_box_number'),
                ]);
                $fields = '<div class="col-md-4"><label class="form-label">' . e(fn_t('caregivers_address_type')) . '</label><select class="form-select" name="address_type_key"' . ($isShared ? ' disabled' : '') . '>' . $opts . '</select></div>'
                    . '<div class="col-md-5"><label class="form-label">' . e(fn_t('caregivers_label')) . '</label><input class="form-control" name="label" value="' . e((string) $detail['label']) . '"' . ($isShared ? ' disabled' : '') . '></div>'
                    . '<input type="hidden" name="country_code" value="' . e((string) $detail['country_code']) . '">'
                    . ($isShared ? '<fieldset class="col-12 border-0 p-0 m-0" disabled>' . $addressFields . '</fieldset>' : $addressFields);
            }
            $cards .= '<div class="border rounded p-3 mb-2"><div class="small text-secondary mb-2">' . e($scopeText) . '</div><form method="post" action="mb_caregivers.php?code=' . rawurlencode($selectedCode) . '">' . csrf_field()
                . '<input type="hidden" name="action" value="save_' . e($entity) . '"><input type="hidden" name="caregiver_code" value="' . e($selectedCode) . '"><input type="hidden" name="detail_id" value="' . (int) $detail['id'] . '">'
                . '<div class="row g-2 align-items-end">' . $fields . '<div class="col-md-auto">' . ($isShared ? '' : '<button class="btn btn-primary" type="submit">' . e(fn_t('save')) . '</button>') . '</div></div></form>'
                . ($isShared ? '' : '<form method="post" action="mb_caregivers.php?code=' . rawurlencode($selectedCode) . '" class="mt-2">' . csrf_field()
                    . '<input type="hidden" name="action" value="deactivate_' . e($entity) . '"><input type="hidden" name="caregiver_code" value="' . e($selectedCode) . '"><input type="hidden" name="detail_id" value="' . (int) $detail['id'] . '"><button class="btn btn-sm btn-outline-danger" type="submit">' . e(fn_t('caregivers_deactivate')) . '</button></form>')
                . '</div>';
        }
        $addFields = '';
        if ($entity === 'identifier') {
            foreach ($identifierTypes as $key => $labelKey) $addFields .= '<option value="' . e($key) . '">' . e(fn_t($labelKey)) . '</option>';
            $addFields = '<div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_identifier_type')) . '</label><select class="form-select" name="identifier_type_key">' . $addFields . '</select></div><div class="col-md-4"><label class="form-label">' . e(fn_t('caregivers_identifier_value')) . '</label><input class="form-control" name="identifier_value" required></div><div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_identifier_issuer')) . '</label><input class="form-control" name="issuer"></div>';
        } elseif ($entity === 'contact') {
            $opts = '';
            foreach ($contactTypes as $key => $label) $opts .= '<option value="' . e($key) . '">' . e($label) . '</option>';
            $addFields = '<div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_contact_type')) . '</label><select class="form-select" name="contact_type_key">' . $opts . '</select></div><div class="col-md-4"><label class="form-label">' . e(fn_t('caregivers_contact_value')) . '</label><input class="form-control" name="contact_value" required></div><div class="col-md-3"><label class="form-label">' . e(fn_t('caregivers_label')) . '</label><input class="form-control" name="label"></div><input type="hidden" name="purpose_key" value="">';
        } else {
            $opts = '';
            foreach ($addressTypes as $key => $label) $opts .= '<option value="' . e($key) . '">' . e($label) . '</option>';
            $addressFields = fn_templateRender('address_fields.html', [
                'address_prefix' => 'caregiver-address-new',
                'address_autosave' => '0',
                'address_title' => fn_t('caregivers_address_lookup_title'),
                'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
                'address_lookup_no_results' => fn_t('address_lookup_no_results'),
                'address_postal_reminder' => fn_t('address_postal_reminder'),
                'address_select_required' => fn_t('address_select_required'),
                'address_manual_prompt' => fn_t('address_manual_prompt'),
                'address_manual_required' => fn_t('address_manual_required'),
                'address_manual_confirm' => fn_t('address_manual_confirm'),
                'address_country_code_value' => 'BE',
                'address_reference_id_value' => '',
                'lambert72_x_value' => '',
                'lambert72_y_value' => '',
                'wgs84_latitude_value' => '',
                'wgs84_longitude_value' => '',
                'street_reference_id_value' => '',
                'street_value' => '',
                'house_number_value' => '',
                'box_number_value' => '',
                'postal_code_value' => '',
                'city_value' => '',
                'company_city_label' => fn_t('address_city'),
                'company_postal_code' => fn_t('address_postal_code'),
                'company_street' => fn_t('address_street'),
                'address_house_number' => fn_t('address_house_number'),
                'address_box_number' => fn_t('address_box_number'),
            ]);
            $addForm = '<form method="post" action="mb_caregivers.php?code=' . rawurlencode($selectedCode) . '" class="border rounded p-3 mt-3">' . csrf_field()
                . '<input type="hidden" name="action" value="save_location"><input type="hidden" name="caregiver_code" value="' . e($selectedCode) . '">'
                . '<input type="hidden" name="country_code" value="BE"><div class="row g-2 align-items-end">'
                . '<div class="col-md-4"><label class="form-label">' . e(fn_t('caregivers_address_type')) . '</label><select class="form-select" name="address_type_key">' . $opts . '</select></div>'
                . '<div class="col-md-5"><label class="form-label">' . e(fn_t('caregivers_label')) . '</label><input class="form-control" name="label"></div></div>'
                . '<div class="row g-2 mt-1">' . $addressFields . '</div><button class="btn btn-outline-primary mt-3" type="submit">' . e(fn_t('caregivers_add_detail')) . '</button></form>';
        }
        if ($entity !== 'location') {
            $addForm = '<form method="post" action="mb_caregivers.php?code=' . rawurlencode($selectedCode) . '" class="border rounded p-3 mt-3">' . csrf_field()
                . '<input type="hidden" name="action" value="save_' . e($entity) . '"><input type="hidden" name="caregiver_code" value="' . e($selectedCode) . '"><div class="row g-2 align-items-end">'
                . $addFields . '<div class="col-md-auto"><button class="btn btn-outline-primary" type="submit">' . e(fn_t('caregivers_add_detail')) . '</button></div></div></form>';
        } else {
            $addForm = '<div data-caregiver-address-creator><button class="btn btn-outline-primary mt-3" type="button" data-caregiver-address-toggle aria-expanded="false">' . e(fn_t('caregivers_new_address')) . '</button>'
                . '<div class="d-none" data-caregiver-address-form>' . $addForm . '</div></div>';
        }
        $detailHtml .= '<section class="card shadow-sm mb-3"><div class="card-header fw-semibold">' . e(fn_t($headingKey)) . '</div><div class="card-body">'
            . ($cards !== '' ? $cards : '<p class="text-secondary">' . e(fn_t('caregivers_no_details')) . '</p>') . $addForm . '</div></section>';
    }
}

page_start(fn_t('caregivers_title'), $actor);
echo fn_templateRender('mb_caregivers.html', [
    'caregivers_title' => fn_t('caregivers_title'),
    'caregivers_intro' => fn_t('caregivers_intro'),
    '_caregivers_message_html' => $messageKey === '' || $successModalHtml !== '' ? '' : '<div class="alert alert-info" role="status">' . e(fn_t($messageKey)) . '</div>',
    '_caregivers_search_html' => '<form method="get" action="mb_caregivers.php"><label class="form-label" for="caregiver-query">' . e(fn_t('caregivers_search')) . '</label><div class="input-group">'
        . '<input class="form-control" id="caregiver-query" type="search" name="q" value="' . e($query) . '" placeholder="' . e(fn_t('caregivers_search_placeholder')) . '"><button class="btn btn-outline-primary" type="submit">' . e(fn_t('caregivers_search_button')) . '</button></div></form>',
    '_caregivers_list_html' => $listRows,
    '_caregivers_add_button_html' => '<a class="btn btn-success mt-3" href="mb_caregivers.php">' . e(fn_t('caregivers_add')) . '</a>',
    '_caregivers_identity_html' => $identityForm,
    '_caregivers_details_html' => $detailHtml,
    '_caregivers_success_modal_html' => $successModalHtml,
]);
page_end();
