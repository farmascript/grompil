<?php
/**
 * @date_cre: Thu 2026-11-12 11:07:02
 * @date_mut: Fri 2026-11-27 16:35:37
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Company customer lookup, maintenance, and SAM product selection.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:pharmacy:dispensing
 */

declare(strict_types=1);

require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/customer_identifiers.php';
require_once GROMPIL_ROOT . '/includes/contact_manager.php';
require_once GROMPIL_ROOT . '/includes/dispensing_shortcuts.php';
require_once GROMPIL_ROOT . '/includes/dispensing_sessions.php';
require_once GROMPIL_ROOT . '/includes/dispensing_receipt.php';
require_once GROMPIL_ROOT . '/includes/dispensing_history.php';
$currentUser = require_login();
$company = require_company_context($currentUser);
if ($company === null || !grompil_can_access_module('dispense.php', $currentUser, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
}

$companyId = (int) $company['id'];
$actorCode = (string) $currentUser['user_code'];
$contactManagerModalOpen = (string) ($_GET['contacts'] ?? '') === '1';
$contactManagerStatus = (string) ($_GET['contact_status'] ?? '');
$contactAction = (string) ($_POST['contact_action'] ?? '');
if (in_array($contactAction, ['add', 'update', 'delete'], true)) {
    $contactOwnerCode = strtoupper(trim((string) ($_GET['patient'] ?? '')));
    $contactManagerStatus = grompil_contact_manager_handle_post('customer', $companyId, $contactOwnerCode) ?? 'invalid';
    redirect('dispense.php?patient=' . rawurlencode($contactOwnerCode)
        . '&dispense_status=' . rawurlencode((string) ($_GET['dispense_status'] ?? 'v'))
        . '&contacts=1&contact_status=' . rawurlencode($contactManagerStatus) . '#contact-manager-modal');
}
$companyIdentityResult = db_query_params(
    'dispense.company_identity',
    'SELECT c.id, c.name, c.store_logo_file, classification."key" AS company_classification_key
     FROM bt_companies c
     JOIN ft_dd_varia classification ON classification.id=c.company_classification_id
     WHERE c.id=$1 AND c.is_active',
    [$companyId]
);
$companyIdentity = pg_fetch_assoc($companyIdentityResult) ?: [
    'id' => $companyId, 'name' => $company['name'], 'store_logo_file' => '', 'company_classification_key' => 'pharmacy',
];
$isPharmacyCompany = $companyIdentity['company_classification_key'] === 'pharmacy';
$customerText = static function (string $key) use ($isPharmacyCompany): string {
    if (!$isPharmacyCompany && str_starts_with($key, 'patient_')) {
        $key = 'customer_' . substr($key, strlen('patient_'));
    }
    return fn_t($key);
};
$dispenseCustomerText = static function (string $key) use ($isPharmacyCompany): string {
    if (!$isPharmacyCompany) {
        $key = match ($key) {
            'dispense_patient_search' => 'dispense_customer_search',
            'dispense_patient' => 'dispense_customer',
            'dispense_select_patient' => 'dispense_select_customer',
            'dispense_no_patient' => 'dispense_no_customer',
            'dispense_patient_required' => 'dispense_customer_required',
            'dispense_patient_mode' => 'dispense_customer_mode',
            'dispense_walkin_title' => 'dispense_walkin_customer_title',
            'dispense_walkin_help' => 'dispense_walkin_customer_help',
            default => $key,
        };
    }
    return fn_t($key);
};
$customerAddressTypes = dd_varia_get_options('address_types', '_root');
$customerAddressTypeKeys = array_column($customerAddressTypes, 'key');
$customerAddressTypeLabels = array_column($customerAddressTypes, 'label', 'key');
$dispensingStatuses = dd_varia_get_options('dispensing_statuses', '_root');
$dispenseLanguage = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
if ($dispensingStatuses === []) {
    $dispensingStatuses = [
        ['key' => 'free_sale', 'short_code' => ['NL' => 'z', 'FR' => 'l', 'EN' => 'f', 'DE' => 'f'][$dispenseLanguage] ?? 'z', 'label' => fn_t('dispense_status_free')],
        ['key' => 'prescription', 'short_code' => ['NL' => 'v', 'FR' => 'o', 'EN' => 'p', 'DE' => 'r'][$dispenseLanguage] ?? 'v', 'label' => fn_t('dispense_status_prescription')],
        ['key' => 'incomplete_prescription', 'short_code' => ['NL' => 'o', 'FR' => 'i', 'EN' => 'ip', 'DE' => 'ur'][$dispenseLanguage] ?? 'o', 'label' => fn_t('dispense_status_incomplete_prescription')],
        ['key' => 'incomplete_attestation', 'short_code' => ['NL' => 'a', 'FR' => 'a', 'EN' => 'ia', 'DE' => 'ub'][$dispenseLanguage] ?? 'a', 'label' => fn_t('dispense_status_incomplete_attestation')],
        ['key' => 'written_request', 'short_code' => ['NL' => 's', 'FR' => 'd', 'EN' => 'w', 'DE' => 's'][$dispenseLanguage] ?? 's', 'label' => fn_t('dispense_status_written_request')],
        ['key' => 'veterinary', 'short_code' => ['NL' => 'e', 'FR' => 'v', 'EN' => 'v', 'DE' => 't'][$dispenseLanguage] ?? 'e', 'label' => fn_t('dispense_status_veterinary')],
    ];
}
$dispensingStatusByKey = [];
foreach ($dispensingStatuses as $statusOption) {
    $statusOptionKey = (string) ($statusOption['key'] ?? '');
    if (preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $statusOptionKey) === 1) {
        $dispensingStatusByKey[$statusOptionKey] = [
            'label' => (string) ($statusOption['label'] ?? $statusOptionKey),
            'short_code' => (string) ($statusOption['short_code'] ?? $statusOptionKey),
        ];
    }
}
$requestedDispenseStatus = strtolower(trim((string) ($_GET['dispense_status'] ?? $_POST['dispense_status'] ?? '')));
$legacyDispenseStatusKeys = [
    'z' => 'free_sale', 'v' => 'prescription', 'o' => 'incomplete_prescription',
    'a' => 'incomplete_attestation', 's' => 'written_request', 'e' => 'veterinary',
];
if (isset($legacyDispenseStatusKeys[$requestedDispenseStatus])) {
    $requestedDispenseStatus = $legacyDispenseStatusKeys[$requestedDispenseStatus];
}
if ($requestedDispenseStatus === '' && (string) ($_GET['mode'] ?? '') === 'walk_in') {
    $requestedDispenseStatus = 'free_sale';
}
$dispenseStatusKey = isset($dispensingStatusByKey[$requestedDispenseStatus])
    ? $requestedDispenseStatus
    : (isset($dispensingStatusByKey['free_sale'])
        ? 'free_sale'
        : (isset($dispensingStatusByKey['prescription']) ? 'prescription' : (string) array_key_first($dispensingStatusByKey)));
$dispenseMode = $dispenseStatusKey === 'free_sale' ? 'walk_in' : 'patient';
$dispenseStatusOptionsHtml = '';
foreach ($dispensingStatuses as $statusOption) {
    $statusOptionKey = (string) ($statusOption['key'] ?? '');
    if (!isset($dispensingStatusByKey[$statusOptionKey])) {
        continue;
    }
    $statusOptionLabel = $dispensingStatusByKey[$statusOptionKey]['label'];
    $statusOptionCode = $dispensingStatusByKey[$statusOptionKey]['short_code'];
    $requiresCaregiver = in_array($statusOptionKey, ['prescription', 'v'], true);
    $dispenseStatusOptionsHtml .= '<option value="' . e($statusOptionKey) . '" data-short-code="' . e($statusOptionCode) . '" data-requires-caregiver="' . ($requiresCaregiver ? 'true' : 'false') . '"'
        . ($statusOptionKey === $dispenseStatusKey ? ' selected' : '') . '>'
        . e($statusOptionCode . ' — ' . $statusOptionLabel) . '</option>';
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string) ($_GET['dispensing_session_api'] ?? '') === '1') {
    grompil_dispensing_session_api($companyId, $actorCode, $dispensingStatusByKey, $dispenseCustomerText('dispense_walkin_title'));
}
$messageKey = '';
$editingPatientCode = '';
$openPatientModal = false;
$duplicatePatientWarning = false;
$newPatientModalRequested = (string) ($_GET['new_patient'] ?? '') === '1';
if ($newPatientModalRequested) {
    $openPatientModal = true;
}

if (isset($_GET['product_suggest']) && (string) $_GET['product_suggest'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $rawQuery = $_GET['q'] ?? '';
    $productQuery = is_scalar($rawQuery) ? trim((string) $rawQuery) : '';
    if ($productQuery === '') {
        http_response_code(400);
        echo json_encode(['error' => 'query_required']);
        exit;
    }
    $searchStatusKey = strtolower(trim((string) ($_GET['dispense_status'] ?? $dispenseStatusKey)));
    if (isset($legacyDispenseStatusKeys[$searchStatusKey])) {
        $searchStatusKey = $legacyDispenseStatusKeys[$searchStatusKey];
    }
    if (!isset($dispensingStatusByKey[$searchStatusKey])) {
        $searchStatusKey = $dispenseStatusKey;
    }
    $searchDispenseMode = $searchStatusKey === 'free_sale' ? 'walk_in' : 'patient';
    if ($searchDispenseMode === 'patient') {
        $productPatientCode = strtoupper(trim((string) ($_GET['patient'] ?? '')));
        if (preg_match('/^[A-Z0-9]{6}$/D', $productPatientCode) !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'patient_required']);
            exit;
        }
        try {
            $patientCheck = db_query_params(
                'dispensing.product_search_patient_check',
                'SELECT date_of_death FROM bt_customers WHERE company_id=$1 AND customer_code=$2 AND is_active',
                [$companyId, $productPatientCode]
            );
        } catch (Throwable $error) {
            error_log('Dispensing product patient check failed: ' . get_class($error));
            http_response_code(503);
            echo json_encode(['error' => 'patient_check_unavailable']);
            exit;
        }
        $patientRecord = pg_fetch_assoc($patientCheck);
        if (!$patientRecord) {
            http_response_code(403);
            echo json_encode(['error' => 'patient_unavailable']);
            exit;
        }
        if (trim((string) ($patientRecord['date_of_death'] ?? '')) !== '') {
            http_response_code(409);
            echo json_encode(['error' => 'deceased_customer']);
            exit;
        }
    }
    require_once GROMPIL_ROOT . '/includes/sam_products.php';
    $maximumResults = grompil_sam_product_search_limit();
    $rawLimit = $_GET['limit'] ?? $maximumResults;
    $limit = is_scalar($rawLimit) && filter_var($rawLimit, FILTER_VALIDATE_INT) !== false
        ? min($maximumResults, max(1, (int) $rawLimit))
        : $maximumResults;
    $rawStatuses = $_GET['status'] ?? null;
    $statuses = array_key_exists('status_filter', $_GET)
        ? (is_array($rawStatuses) ? array_values(array_filter($rawStatuses, 'is_string')) : [])
        : (is_array($rawStatuses) ? array_values(array_filter($rawStatuses, 'is_string')) : null);
    $includeRevoked = (string) ($_GET['include_revoked'] ?? '') === '1';
    if (!$includeRevoked) {
        $statuses = $statuses === null
            ? ['AUTHORIZED', 'SUSPENDED']
            : array_values(array_diff(array_map('strtoupper', $statuses), ['REVOKED']));
    }
    try {
        $totalCount = 0;
        $matches = grompil_sam_product_search($productQuery, $limit, $statuses, $totalCount);
    } catch (Throwable $error) {
        error_log('Dispensing product lookup failed: ' . $error->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'search_unavailable']);
        exit;
    }
    $results = array_map(static fn(array $row): array => [
        'match_type' => $row['match_type'], 'cnk' => $row['cnk'],
        'amp_code' => $row['amp_code'], 'ampp_code' => $row['ampp_code'],
        'amp_status' => $row['amp_status'], 'ampp_status' => $row['ampp_status'],
        'official_name' => $row['official_name'], 'name_nl' => $row['name_nl'],
        'name_fr' => $row['name_fr'], 'prescription_name_nl' => $row['prescription_name_nl'],
        'prescription_name_fr' => $row['prescription_name_fr'], 'pack_display_nl' => $row['pack_display_nl'],
        'pack_display_fr' => $row['pack_display_fr'], 'atc_code' => $row['atc_code'],
        'atc_class_path' => $row['atc_class_path'],
    ], $matches);
    echo json_encode([
        'query' => $productQuery, 'count' => count($results), 'total_count' => $totalCount,
        'has_more' => $totalCount > count($results), 'results' => $results,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $patientCode = strtoupper(trim((string) ($_POST['customer_code'] ?? '')));
    $firstName = trim((string) ($_POST['name_first'] ?? ''));
    $lastName = trim((string) ($_POST['name_last'] ?? ''));
    $nationalNumber = trim((string) ($_POST['national_register_number'] ?? ''));
    $nationalNumberData = $nationalNumber === '' ? null : grompil_parse_national_register_number($nationalNumber);
    $nationalNumberValid = $nationalNumber === '' || $nationalNumberData !== null;
    $nationalNumberBirthDate = $nationalNumberData['date_of_birth'] ?? null;
    $birthDate = $nationalNumberBirthDate ?? trim((string) ($_POST['date_of_birth'] ?? ''));
    $birthDateValue = $birthDate === '' ? null : $birthDate;
    $deathDate = trim((string) ($_POST['date_of_death'] ?? ''));
    $deathDateValue = $deathDate === '' ? null : $deathDate;
    $street = trim((string) ($_POST['street'] ?? ''));
    $houseNumber = trim((string) ($_POST['house_number'] ?? ''));
    $boxNumber = trim((string) ($_POST['box_number'] ?? ''));
    $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $countryCode = strtoupper(trim((string) ($_POST['country_code'] ?? 'BE')));
    $locationId = max(0, (int) ($_POST['location_id'] ?? 0));
    $addressTypeKey = trim((string) ($_POST['address_type_key'] ?? 'residential'));
    $addressLabel = trim((string) ($_POST['address_label'] ?? ''));
    $addressSortOrder = max(0, (int) ($_POST['address_sort_order'] ?? 0));
    $addressIsPrimary = isset($_POST['location_is_primary']);
    $addAnotherAddress = (string) ($_POST['add_another_address'] ?? '') === '1';
    if ($addAnotherAddress) {
        $locationId = 0;
    }
    $addressReferenceId = trim((string) ($_POST['address_reference_id'] ?? ''));
    $lambert72X = grompil_address_coordinate($_POST['lambert72_x'] ?? '', -1000000, 1000000, 3);
    $lambert72Y = grompil_address_coordinate($_POST['lambert72_y'] ?? '', -1000000, 1000000, 3);
    $wgs84Latitude = grompil_address_coordinate($_POST['wgs84_latitude'] ?? '', -90, 90, 7);
    $wgs84Longitude = grompil_address_coordinate($_POST['wgs84_longitude'] ?? '', -180, 180, 7);
    $addressSelection = [
        'street' => $street, 'house_number' => $houseNumber, 'postal_code' => $postalCode,
        'city' => $city, 'country_code' => $countryCode, 'address_reference_id' => $addressReferenceId,
    ];
    $addressHasContent = implode('', [$street, $houseNumber, $boxNumber, $postalCode, $city]) !== '';
    $parsedBirthDate = $birthDate === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    $birthDateValid = $birthDate === '' || ($parsedBirthDate !== false
        && $parsedBirthDate->format('Y-m-d') === $birthDate
        && $birthDate <= date('Y-m-d'));
    $parsedDeathDate = $deathDate === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $deathDate);
    $deathDateValid = $deathDate === '' || ($parsedDeathDate !== false
        && $parsedDeathDate->format('Y-m-d') === $deathDate
        && $deathDate <= date('Y-m-d'));
    $dateValid = $birthDateValid && $deathDateValid
        && ($deathDate === '' || $birthDate === '' || $deathDate >= $birthDate);
    $valid = $nationalNumberValid && $firstName !== '' && mb_strlen($firstName) <= 120
        && $lastName !== '' && mb_strlen($lastName) <= 120
        && $dateValid && preg_match('/^[A-Z]{2}$/D', $countryCode) === 1
        && mb_strlen($street) <= 160 && mb_strlen($houseNumber) <= 32
        && mb_strlen($boxNumber) <= 32 && mb_strlen($postalCode) <= 20 && mb_strlen($city) <= 100
        && in_array($addressTypeKey, $customerAddressTypeKeys, true)
        && mb_strlen($addressLabel) <= 160
        && grompil_address_selection_allowed($addressSelection, isset($_POST['manual_address_without_coordinates']));

    if (in_array($action, ['create_patient', 'update_patient'], true) && $valid
        && $addressHasContent && (string) ($_POST['confirm_duplicate_patient'] ?? '') !== '1') {
        $duplicateCheck = db_query_params(
            'patients.duplicate_name_address_check',
            "SELECT 1
             FROM bt_customers existing
             JOIN dt_customer_locations location
               ON location.company_id=existing.company_id
              AND location.customer_code=existing.customer_code
              AND location.is_active
             WHERE existing.company_id=$1 AND existing.is_active
               AND lower(btrim(existing.name_first))=lower(btrim($2))
               AND lower(btrim(existing.name_last))=lower(btrim($3))
               AND lower(btrim(COALESCE(location.street, '')))=lower(btrim($4))
               AND lower(btrim(COALESCE(location.house_number, '')))=lower(btrim($5))
               AND lower(btrim(COALESCE(location.box_number, '')))=lower(btrim($6))
               AND lower(btrim(COALESCE(location.postal_code, '')))=lower(btrim($7))
               AND lower(btrim(COALESCE(location.city, '')))=lower(btrim($8))
               AND lower(btrim(COALESCE(location.country_code, '')))=lower(btrim($9))
               AND ($10='' OR existing.customer_code<>$10)
             LIMIT 1",
            [$companyId, $firstName, $lastName, $street, $houseNumber, $boxNumber,
                $postalCode, $city, $countryCode, $action === 'update_patient' ? $patientCode : '']
        );
        $duplicatePatientWarning = pg_num_rows($duplicateCheck) > 0;
    }

    if ($duplicatePatientWarning) {
        // Keep the submitted patient form available behind the confirmation dialog.
        $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
    } elseif (in_array($action, ['create_patient', 'update_patient'], true) && $valid) {
        try {
            db_query_raw('patients.transaction.begin', 'BEGIN');
            if ($action === 'create_patient') {
                $created = false;
                for ($attempt = 0; $attempt < 8; $attempt++) {
                    $candidate = grompil_generate_user_code();
                    $insert = db_query_params(
                        'patients.create',
                        'INSERT INTO bt_customers (company_id, customer_code, name_first, name_last, date_of_birth, date_of_death, created_by_user_code, updated_by_user_code)
                         VALUES ($1, $2, $3, $4, $5::date, $6::date, $7, $7)
                         ON CONFLICT (customer_code) DO NOTHING RETURNING customer_code',
                        [$companyId, $candidate, $firstName, $lastName, $birthDateValue, $deathDateValue, $actorCode]
                    );
                    $inserted = pg_fetch_assoc($insert);
                    if ($inserted) {
                        $patientCode = (string) $inserted['customer_code'];
                        $created = true;
                        break;
                    }
                }
                if (!$created) {
                    throw new RuntimeException('Could not allocate a unique patient code.');
                }
            } elseif ($action === 'update_patient' && preg_match('/^[A-Z0-9]{6}$/D', $patientCode) === 1) {
                $update = db_query_params(
                    'patients.update',
                    'UPDATE bt_customers SET name_first=$1, name_last=$2, date_of_birth=$3::date,
                        date_of_death=$4::date, updated_by_user_code=$5, updated_at=now()
                     WHERE company_id=$6 AND customer_code=$7 AND is_active RETURNING customer_code',
                    [$firstName, $lastName, $birthDateValue, $deathDateValue, $actorCode, $companyId, $patientCode]
                );
                if (pg_num_rows($update) !== 1) {
                    throw new RuntimeException('Patient record was not found in the active company.');
                }
            } else {
                throw new RuntimeException('Invalid patient operation.');
            }

            grompil_customer_national_register_number_save(
                $companyId,
                $patientCode,
                (string) ($nationalNumberData['number'] ?? ''),
                $actorCode
            );

            if ($locationId > 0) {
                if ($addressIsPrimary) {
                    db_query_params(
                        'customers.location.clear_primary',
                        'UPDATE dt_customer_locations SET is_primary=FALSE, updated_by_user_code=$1, updated_at=now()
                         WHERE company_id=$2 AND customer_code=$3 AND is_active AND is_primary AND id<>$4',
                        [$actorCode, $companyId, $patientCode, $locationId]
                    );
                }
                $location = db_query_params(
                    'patients.location.update',
                    'UPDATE dt_customer_locations
                     SET address_type_key=$1, label=$2, street=$3, house_number=$4, box_number=$5, postal_code=$6, city=$7,
                         country_code=$8, address_reference_id=$9, lambert72_x=$10, lambert72_y=$11,
                         wgs84_latitude=$12, wgs84_longitude=$13, is_primary=$14, sort_order=$15,
                         updated_by_user_code=$16, updated_at=now()
                     WHERE id=$17 AND company_id=$18 AND customer_code=$19 AND is_active',
                    [$addressTypeKey, $addressLabel, $street, $houseNumber, $boxNumber, $postalCode, $city, $countryCode,
                        $addressReferenceId, $lambert72X, $lambert72Y, $wgs84Latitude, $wgs84Longitude,
                        $addressIsPrimary ? 'true' : 'false',
                        $addressSortOrder, $actorCode, $locationId, $companyId, $patientCode]
                );
                if (pg_affected_rows($location) !== 1) {
                    throw new RuntimeException('Patient address was not found in the active company.');
                }
            } elseif ($addressHasContent) {
                $existingLocationStats = db_query_params(
                    'customers.location.next_order',
                    'SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_order,
                            COALESCE(bool_or(is_primary AND is_active), FALSE) AS has_primary
                     FROM dt_customer_locations WHERE company_id=$1 AND customer_code=$2',
                    [$companyId, $patientCode]
                );
                $locationStats = pg_fetch_assoc($existingLocationStats) ?: ['next_order' => 10, 'has_primary' => 'f'];
                $makePrimary = $addressIsPrimary || $locationStats['has_primary'] !== 't';
                if ($makePrimary) {
                    db_query_params(
                        'customers.location.clear_primary_before_insert',
                        'UPDATE dt_customer_locations SET is_primary=FALSE, updated_by_user_code=$1, updated_at=now()
                         WHERE company_id=$2 AND customer_code=$3 AND is_active AND is_primary',
                        [$actorCode, $companyId, $patientCode]
                    );
                }
                db_query_params(
                    'patients.location.create',
                    'INSERT INTO dt_customer_locations (
                        company_id, customer_code, address_type_key, label, street, house_number, box_number, postal_code, city,
                        country_code, address_reference_id, lambert72_x, lambert72_y, wgs84_latitude,
                        wgs84_longitude, is_primary, is_active, sort_order, created_by_user_code, updated_by_user_code
                     ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,TRUE,$17,$18,$18)',
                    [$companyId, $patientCode, $addressTypeKey, $addressLabel, $street, $houseNumber, $boxNumber, $postalCode, $city,
                        $countryCode, $addressReferenceId, $lambert72X, $lambert72Y, $wgs84Latitude, $wgs84Longitude,
                        $makePrimary ? 'true' : 'false',
                        $addressSortOrder > 0 ? $addressSortOrder : (int) $locationStats['next_order'], $actorCode]
                );
            }
            db_query_raw('patients.transaction.commit', 'COMMIT');
            $redirectUrl = 'dispense.php?patient=' . rawurlencode($patientCode)
                . '&dispense_status=' . rawurlencode($dispenseStatusKey) . '&saved=1';
            if ($action === 'update_patient') {
                $redirectUrl .= '&edit=1';
            }
            redirect($redirectUrl);
        } catch (GrompilDuplicateCustomerIdentifierException $error) {
            try {
                db_query_raw('patients.transaction.rollback', 'ROLLBACK');
            } catch (Throwable) {
            }
            $messageKey = 'patient_national_register_number_duplicate';
            $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
            $openPatientModal = true;
        } catch (GrompilCustomerIdentifierException $error) {
            try {
                db_query_raw('patients.transaction.rollback', 'ROLLBACK');
            } catch (Throwable) {
            }
            error_log('Customer identifier save unavailable: ' . get_class($error));
            $messageKey = 'patient_national_register_number_storage_unavailable';
            $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
            $openPatientModal = true;
        } catch (Throwable $error) {
            try {
                db_query_raw('patients.transaction.rollback', 'ROLLBACK');
            } catch (Throwable) {
            }
            error_log(sprintf(
                'Patient record save failed [action=%s, code=%s]: %s',
                $action,
                get_class($error),
                substr(preg_replace('/\\s+/', ' ', $error->getMessage()) ?? '', 0, 500)
            ));
            $messageKey = 'patient_save_failed';
            $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
            $openPatientModal = true;
        }
    } else {
        error_log(sprintf(
            'Patient form validation rejected [action=%s, address=%s, reference=%s, manual=%s, type_allowed=%s, name_valid=%s, date_valid=%s].',
            $action,
            $addressHasContent ? 'yes' : 'no',
            $addressReferenceId !== '' ? 'yes' : 'no',
            isset($_POST['manual_address_without_coordinates']) ? 'yes' : 'no',
            in_array($addressTypeKey, $customerAddressTypeKeys, true) ? 'yes' : 'no',
            $firstName !== '' && mb_strlen($firstName) <= 120 && $lastName !== '' && mb_strlen($lastName) <= 120 ? 'yes' : 'no',
            $dateValid ? 'yes' : 'no'
        ));
        $messageKey = !$nationalNumberValid ? 'patient_national_register_number_invalid' : 'patient_form_invalid';
        $openPatientModal = true;
        $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$regexSearch = (string) ($_GET['regex'] ?? '') === '1';
$includeDeceased = (string) ($_GET['include_deceased'] ?? '') === '1';
$patientSuggestionRequest = (string) ($_GET['patient_suggest'] ?? '') === '1';
$selectedPatientCode = strtoupper(trim((string) ($_GET['patient'] ?? '')));
if ($selectedPatientCode === '' && $editingPatientCode !== '') {
    $selectedPatientCode = $editingPatientCode;
}
$selectedPatient = null;
$patientMatches = [];
try {
    if ($selectedPatientCode !== '' && preg_match('/^[A-Z0-9]{6}$/D', $selectedPatientCode) === 1) {
        $result = db_query_params(
            'patients.load_selected',
            'SELECT p.customer_code, p.name_first, p.name_last, p.date_of_birth, p.date_of_death,
                    l.id AS location_id, l.address_type_key, l.label AS address_label, l.sort_order AS address_sort_order,
                    l.is_primary AS location_is_primary, l.street, l.house_number, l.box_number, l.postal_code,
                    l.city, l.country_code, l.address_reference_id, l.lambert72_x, l.lambert72_y,
                    l.wgs84_latitude, l.wgs84_longitude,
                    p.created_at, p.updated_at, creator.display_name AS created_by_name,
                    updater.display_name AS updated_by_name
             FROM bt_customers p
             LEFT JOIN LATERAL (
                SELECT * FROM dt_customer_locations
                WHERE company_id=p.company_id AND customer_code=p.customer_code AND is_active
                ORDER BY COALESCE(id = NULLIF($3, \'\')::BIGINT, FALSE) DESC, is_primary DESC, sort_order, id LIMIT 1
             ) l ON TRUE
             JOIN bt_users creator ON creator.user_code=p.created_by_user_code
             JOIN bt_users updater ON updater.user_code=p.updated_by_user_code
             WHERE p.company_id=$1 AND p.customer_code=$2 AND p.is_active',
            [$companyId, $selectedPatientCode, (string) ($_GET['location'] ?? '')]
        );
        $selectedPatient = pg_fetch_assoc($result) ?: null;
    }
    if (!$newPatientModalRequested && isset($_GET['edit']) && $_GET['edit'] === '1' && $selectedPatient !== null) {
        $editingPatientCode = $selectedPatientCode;
        $openPatientModal = true;
    }
    if (mb_strlen($query) >= 3) {
        $searchPattern = $regexSearch ? $query : '%' . $query . '%';
        $searchPredicate = $regexSearch
            ? "(p.customer_code ~* $2 OR (p.name_first || ' ' || p.name_last) ~* $2
                OR (p.name_last || ' ' || p.name_first) ~* $2)"
            : "(p.customer_code = upper($2) OR lower(p.name_first || ' ' || p.name_last) LIKE lower($3)
                OR lower(p.name_last || ' ' || p.name_first) LIKE lower($3))";
        $searchParams = $regexSearch
            ? [$companyId, $searchPattern]
            : [$companyId, $query, $searchPattern];
        $includeDeceasedParameter = count($searchParams) + 1;
        $searchParams[] = $includeDeceased ? 'true' : 'false';
        $includeDeceasedPredicate = '(p.date_of_death IS NULL OR $' . $includeDeceasedParameter . '::boolean)';
        $result = db_query_params(
            'patients.search',
            "SELECT p.customer_code, p.name_first, p.name_last, p.date_of_birth, p.date_of_death,
                    l.street, l.house_number, l.postal_code, l.city
             FROM bt_customers p
             LEFT JOIN LATERAL (
                SELECT street, house_number, postal_code, city FROM dt_customer_locations
                WHERE company_id=p.company_id AND customer_code=p.customer_code AND is_active
                ORDER BY is_primary DESC, sort_order, id LIMIT 1
             ) l ON TRUE
             WHERE p.company_id=$1 AND p.is_active AND $includeDeceasedPredicate AND $searchPredicate
             ORDER BY lower(p.name_last), lower(p.name_first), p.customer_code LIMIT 20",
            $searchParams
        );
        while ($row = pg_fetch_assoc($result)) {
            $patientMatches[] = $row;
        }
    }
} catch (Throwable $error) {
    error_log('Patient lookup failed: ' . get_class($error));
    $messageKey = $messageKey !== '' ? $messageKey : ($regexSearch ? 'patient_regex_invalid' : 'patient_lookup_unavailable');
}

if ($patientSuggestionRequest) {
    header('Content-Type: application/json; charset=UTF-8');
    if ($messageKey !== '') {
        http_response_code($regexSearch ? 400 : 503);
        echo json_encode([
            'error' => $customerText($regexSearch ? 'patient_regex_invalid' : 'patient_lookup_unavailable'),
            'results' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        $suggestions = [];
        foreach ($patientMatches as $patient) {
            $address = trim(implode(' ', array_filter([
                trim((string) ($patient['street'] ?? '') . ' ' . (string) ($patient['house_number'] ?? '')),
                (string) ($patient['postal_code'] ?? ''),
                (string) ($patient['city'] ?? ''),
            ])));
            $suggestions[] = [
                'customer_code' => (string) $patient['customer_code'],
                'patient_name' => grompil_dispensing_display_name(
                    (string) $patient['name_first'], (string) $patient['name_last']
                ),
                'patient_address' => $address !== '' ? $address : $customerText('patient_no_address'),
                'is_deceased' => !empty($patient['date_of_death']),
                'deceased_label' => !empty($patient['date_of_death']) ? $customerText('patient_deceased') : '',
                'death_date_display' => !empty($patient['date_of_death'])
                    ? fn_formatDateDisplay((string) $patient['date_of_death']) : '',
                'age_at_death' => fn_ageAtDate(
                    (string) ($patient['date_of_birth'] ?? ''), (string) ($patient['date_of_death'] ?? '')
                ),
            ];
        }
        echo json_encode(['results' => $suggestions], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

if ($editingPatientCode !== '' && $selectedPatient === null) {
    $selectedPatient = [
        'customer_code' => $editingPatientCode,
        'name_first' => (string) ($_POST['name_first'] ?? ''),
        'name_last' => (string) ($_POST['name_last'] ?? ''),
        'national_register_number' => (string) ($_POST['national_register_number'] ?? ''),
        'date_of_birth' => $nationalNumberBirthDate ?? (string) ($_POST['date_of_birth'] ?? ''),
        'date_of_death' => (string) ($_POST['date_of_death'] ?? ''),
        'location_id' => (string) ($_POST['location_id'] ?? ''),
        'street' => (string) ($_POST['street'] ?? ''),
        'house_number' => (string) ($_POST['house_number'] ?? ''),
        'box_number' => (string) ($_POST['box_number'] ?? ''),
        'postal_code' => (string) ($_POST['postal_code'] ?? ''),
        'city' => (string) ($_POST['city'] ?? ''),
        'country_code' => (string) ($_POST['country_code'] ?? 'BE'),
        'address_reference_id' => (string) ($_POST['address_reference_id'] ?? ''),
        'lambert72_x' => (string) ($_POST['lambert72_x'] ?? ''),
        'lambert72_y' => (string) ($_POST['lambert72_y'] ?? ''),
        'wgs84_latitude' => (string) ($_POST['wgs84_latitude'] ?? ''),
        'wgs84_longitude' => (string) ($_POST['wgs84_longitude'] ?? ''),
    ];
}

$customerTermKey = $companyIdentity['company_classification_key'] === 'pharmacy'
    ? 'company_customer_term_pharmacy'
    : 'company_customer_term_business';
$customerTerm = fn_t($customerTermKey);
$companyLogo = fn_companyStoreImageUrl($companyIdentity, 'store_logo_file');
$employeeDisplayName = (string) $currentUser['display_name'];
$employeeAcronym = trim((string) ($currentUser['acronym'] ?? ''));
$employeeTooltip = $employeeAcronym !== ''
    ? $employeeDisplayName . ' - ' . $employeeAcronym
    : $employeeDisplayName;
$employeeRoleName = trim((string) ($company['company_role_name'] ?? ''));
if ($employeeRoleName === '') {
    $employeeRoleName = is_platform_operator($currentUser)
        ? fn_t('manager_platform_operator')
        : trim((string) ($currentUser['role_name'] ?? ''));
}
$nameParts = preg_split('/\s+/u', trim((string) $currentUser['display_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = strtoupper(mb_substr($nameParts[0] ?? '?', 0, 1) . mb_substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));

$patientResultRows = '';
foreach ($patientMatches as $patient) {
    $address = trim(implode(' ', array_filter([
        trim((string) ($patient['street'] ?? '') . ' ' . (string) ($patient['house_number'] ?? '')),
        (string) ($patient['postal_code'] ?? ''),
        (string) ($patient['city'] ?? ''),
    ])));
    $patientResultRows .= fn_moduleTemplateRender('dispensing', 'dispensing_patient_result.html', [
        'customer_code' => (string) $patient['customer_code'],
        'patient_name' => grompil_dispensing_display_name(
            (string) $patient['name_first'], (string) $patient['name_last']
        ),
        'patient_address' => $address !== '' ? $address : $customerText('patient_no_address'),
        'patient_href' => e('dispense.php?' . http_build_query([
            'patient' => (string) $patient['customer_code'],
            'dispense_status' => $dispenseStatusKey,
            'include_deceased' => $includeDeceased ? '1' : null,
        ])),
        '_patient_deceased_class' => !empty($patient['date_of_death']) ? 'customer-deceased-option' : '',
        '_patient_deceased_html' => !empty($patient['date_of_death'])
            ? '<span class="d-block small customer-deceased-date">' . e($customerText('patient_deceased') . ' · '
                . fn_formatDeathDateDisplay(
                    (string) ($patient['date_of_birth'] ?? ''), (string) $patient['date_of_death']
                )) . '</span>' : '',
    ]);
}

$patientAddress = $selectedPatient ? trim(implode(', ', array_filter([
    trim((string) ($selectedPatient['street'] ?? '') . ' ' . (string) ($selectedPatient['house_number'] ?? '')
        . ((string) ($selectedPatient['box_number'] ?? '') !== '' ? ' bus ' . $selectedPatient['box_number'] : '')),
    trim((string) ($selectedPatient['postal_code'] ?? '') . ' ' . (string) ($selectedPatient['city'] ?? '')),
    (string) ($selectedPatient['country_code'] ?? ''),
]))) : '';
$patientFormValues = $editingPatientCode !== '' ? ($selectedPatient ?? []) : [];
if ($editingPatientCode !== '' && !array_key_exists('national_register_number', $patientFormValues)) {
    $patientFormValues['national_register_number'] = grompil_customer_national_register_number_get($companyId, $editingPatientCode) ?? '';
}
$newAddressMode = ((string) ($_GET['new_address'] ?? '') === '1'
    || (string) ($_POST['add_another_address'] ?? '') === '1') && $editingPatientCode !== '';
if ($newAddressMode) {
    foreach (['location_id', 'address_type_key', 'address_label', 'address_sort_order', 'location_is_primary',
        'street', 'house_number', 'box_number', 'postal_code', 'city', 'address_reference_id',
        'lambert72_x', 'lambert72_y', 'wgs84_latitude', 'wgs84_longitude'] as $addressField) {
        $patientFormValues[$addressField] = $addressField === 'address_type_key' ? 'residential'
            : ($addressField === 'address_sort_order' ? '' : '');
    }
    $patientFormValues['country_code'] = 'BE';
    $nextAddressOrderResult = db_query_params(
        'customers.location.next_order_for_form',
        'SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_order
         FROM dt_customer_locations WHERE company_id=$1 AND customer_code=$2',
        [$companyId, $editingPatientCode]
    );
    $nextAddressOrder = pg_fetch_result($nextAddressOrderResult, 0, 'next_order');
    $patientFormValues['address_sort_order'] = (string) ($nextAddressOrder ?: 10);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $patientFormValues = array_replace($patientFormValues, [
        'name_first' => (string) ($_POST['name_first'] ?? ''),
        'name_last' => (string) ($_POST['name_last'] ?? ''),
        'national_register_number' => (string) ($_POST['national_register_number'] ?? ''),
        'date_of_birth' => $nationalNumberBirthDate ?? (string) ($_POST['date_of_birth'] ?? ''),
        'date_of_death' => (string) ($_POST['date_of_death'] ?? ''),
        'location_id' => (string) ($_POST['location_id'] ?? ''),
        'address_type_key' => (string) ($_POST['address_type_key'] ?? 'residential'),
        'address_label' => (string) ($_POST['address_label'] ?? ''),
        'address_sort_order' => (string) ($_POST['address_sort_order'] ?? ''),
        'location_is_primary' => isset($_POST['location_is_primary']) ? 't' : 'f',
        'street' => (string) ($_POST['street'] ?? ''),
        'house_number' => (string) ($_POST['house_number'] ?? ''),
        'box_number' => (string) ($_POST['box_number'] ?? ''),
        'postal_code' => (string) ($_POST['postal_code'] ?? ''),
        'city' => (string) ($_POST['city'] ?? ''),
        'country_code' => (string) ($_POST['country_code'] ?? 'BE'),
        'address_reference_id' => (string) ($_POST['address_reference_id'] ?? ''),
        'lambert72_x' => (string) ($_POST['lambert72_x'] ?? ''),
        'lambert72_y' => (string) ($_POST['lambert72_y'] ?? ''),
        'wgs84_latitude' => (string) ($_POST['wgs84_latitude'] ?? ''),
        'wgs84_longitude' => (string) ($_POST['wgs84_longitude'] ?? ''),
    ]);
}
$formAction = $editingPatientCode !== '' ? 'update_patient' : 'create_patient';
$patientModalTitle = $editingPatientCode !== '' ? $customerText('patient_edit') : $customerText('patient_add');
$patientFormAlertHtml = $messageKey !== ''
    ? fn_moduleTemplateRender('dispensing', 'dispensing_alert.html', [
        'alert_type' => 'danger', 'alert_role' => 'alert',
        'alert_message' => str_starts_with($messageKey, 'patient_')
            ? $customerText($messageKey) : fn_t($messageKey),
    ])
    : '';
$customerAddressesHtml = '';
if ($editingPatientCode !== '') {
    $addressRows = db_query_params(
        'customers.addresses_for_edit',
        'SELECT id, address_type_key, label, street, house_number, box_number, postal_code, city,
                country_code, wgs84_latitude, wgs84_longitude, is_primary, sort_order
         FROM dt_customer_locations
         WHERE company_id=$1 AND customer_code=$2 AND is_active
         ORDER BY is_primary DESC, sort_order, id',
        [$companyId, $editingPatientCode]
    );
    $customerAddressesHtml = '<div class="col-12"><div class="rounded border bg-light-subtle p-3"><strong>'
        . e(fn_t('customer_addresses_heading')) . '</strong><div class="small text-secondary mb-2">'
        . e(fn_t('customer_addresses_help')) . '</div>';
    while ($addressRow = pg_fetch_assoc($addressRows)) {
        $addressText = trim(implode(', ', array_filter([
            trim((string) $addressRow['street'] . ' ' . (string) $addressRow['house_number']),
            (string) $addressRow['postal_code'] . ' ' . (string) $addressRow['city'],
            (string) $addressRow['country_code'],
        ])));
        $selectedAddress = (int) ($patientFormValues['location_id'] ?? 0) === (int) $addressRow['id'];
        $addressHref = 'dispense.php?patient=' . rawurlencode($editingPatientCode)
            . '&dispense_status=' . rawurlencode($dispenseStatusKey)
            . '&edit=1&location=' . (int) $addressRow['id'];
        $addressDescription = trim(implode(' · ', array_filter([
            (string) ($customerAddressTypeLabels[$addressRow['address_type_key']] ?? $addressRow['address_type_key']),
            (string) $addressRow['label'],
            $addressText,
        ], static fn(string $part): bool => $part !== '')));
        $customerAddressesHtml .= '<div class="d-flex align-items-center gap-2">'
            . '<a class="flex-grow-1 small py-1' . ($selectedAddress ? ' fw-semibold' : '') . '" href="'
            . e($addressHref) . '">#' . (int) $addressRow['sort_order'] . ' · ' . e($addressDescription)
            . ($addressRow['is_primary'] === 't' ? ' · ' . e(fn_t('address_default_badge')) : '') . '</a>'
            . grompil_google_maps_link(
                $addressRow['wgs84_latitude'],
                $addressRow['wgs84_longitude'],
                $addressText
            )
            . '</div>';
    }
    $customerAddressesHtml .= '<a class="btn btn-sm btn-outline-secondary mt-2" href="dispense.php?patient='
        . rawurlencode($editingPatientCode) . '&amp;dispense_status=' . rawurlencode($dispenseStatusKey)
        . '&amp;edit=1&amp;new_address=1">' . e(fn_t('customer_address_add')) . '</a></div></div>';
}
$addressTypeOptionsHtml = '';
foreach ($customerAddressTypes as $addressTypeOption) {
    $addressKey = (string) $addressTypeOption['key'];
    $addressTypeOptionsHtml .= '<option value="' . e($addressKey) . '"'
        . (($patientFormValues['address_type_key'] ?? 'residential') === $addressKey ? ' selected' : '') . '>'
        . e((string) $addressTypeOption['label']) . '</option>';
}
$patientModalForm = fn_moduleTemplateRender('dispensing', 'dispensing_patient_form.html', [
    'csrf_token' => csrf_token(),
    'patient_form_action' => $formAction,
    'dispense_status_key' => e($dispenseStatusKey),
    'customer_code' => $editingPatientCode,
    'patient_location_id' => (string) ($patientFormValues['location_id'] ?? ''),
    '_patient_form_alert_html' => $patientFormAlertHtml,
    '_customer_addresses_html' => $customerAddressesHtml,
    '_customer_address_type_options_html' => $addressTypeOptionsHtml,
    'account_address_type' => fn_t('account_address_type'),
    'account_address_label' => fn_t('account_address_label'),
    'account_address_order' => fn_t('account_address_order'),
    'account_address_make_default' => fn_t('account_address_make_default'),
    'patient_address_label_value' => (string) ($patientFormValues['address_label'] ?? ''),
    'patient_address_sort_order_value' => (string) ($patientFormValues['address_sort_order'] ?? ''),
    '_patient_location_primary_checked' => ($patientFormValues['location_is_primary'] ?? 'f') === 't' ? 'checked' : '',
    '_patient_add_address_value' => $newAddressMode ? '1' : '0',
    'confirm_duplicate_patient' => '0',
    'patient_first_name_value' => (string) ($patientFormValues['name_first'] ?? ''),
    'patient_last_name_value' => (string) ($patientFormValues['name_last'] ?? ''),
    'patient_national_register_number_value' => (string) ($patientFormValues['national_register_number'] ?? ''),
    'patient_birth_date_value' => (string) ($patientFormValues['date_of_birth'] ?? ''),
    'patient_death_date_value' => (string) ($patientFormValues['date_of_death'] ?? ''),
    'patient_first_name' => $customerText('patient_first_name'),
    'patient_last_name' => $customerText('patient_last_name'),
    'patient_national_register_number' => $customerText('patient_national_register_number'),
    'patient_national_register_number_help' => $customerText('patient_national_register_number_help'),
    'patient_national_register_number_invalid' => $customerText('patient_national_register_number_invalid'),
    'patient_birth_date' => $customerText('patient_birth_date'),
    'patient_death_date' => $customerText('patient_death_date'),
    'patient_country_value' => (string) ($patientFormValues['country_code'] ?? 'BE'),
    '_patient_address_fields_html' => fn_templateRender('address_fields.html', [
        'address_prefix' => 'patient-address',
        'address_autosave' => '1',
        'address_title' => fn_t('address_title'),
        'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
        'address_lookup_no_results' => fn_t('address_lookup_no_results'),
        'address_postal_reminder' => fn_t('address_postal_reminder'),
        'address_select_required' => fn_t('address_select_required'),
        'address_manual_prompt' => fn_t('address_manual_prompt'),
        'address_manual_required' => fn_t('address_manual_required'),
        'address_manual_confirm' => fn_t('address_manual_confirm'),
        'address_country_code_value' => (string) ($patientFormValues['country_code'] ?? 'BE'),
        'address_reference_id_value' => (string) ($patientFormValues['address_reference_id'] ?? ''),
        'lambert72_x_value' => (string) ($patientFormValues['lambert72_x'] ?? ''),
        'lambert72_y_value' => (string) ($patientFormValues['lambert72_y'] ?? ''),
        'wgs84_latitude_value' => (string) ($patientFormValues['wgs84_latitude'] ?? ''),
        'wgs84_longitude_value' => (string) ($patientFormValues['wgs84_longitude'] ?? ''),
        'street_reference_id_value' => '',
        'street_value' => (string) ($patientFormValues['street'] ?? ''),
        'house_number_value' => (string) ($patientFormValues['house_number'] ?? ''),
        'box_number_value' => (string) ($patientFormValues['box_number'] ?? ''),
        'postal_code_value' => (string) ($patientFormValues['postal_code'] ?? ''),
        'city_value' => (string) ($patientFormValues['city'] ?? ''),
    ]),
    'patient_date_max' => date('Y-m-d'),
]);

$patientMetadata = '';
$patientContactCount = 0;
$contactManagerModalHtml = '';
$patientContactButtonHtml = '';
$patientEditButtonHtml = '';
$patientIdentityTooltipText = '';
$patientAge = '';
$selectedPatientIsDeceased = false;
if ($selectedPatient) {
    $dob = trim((string) ($selectedPatient['date_of_birth'] ?? ''));
    $birthDate = $dob !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $dob) : false;
    $today = new DateTimeImmutable('today');
    $dod = trim((string) ($selectedPatient['date_of_death'] ?? ''));
    $selectedPatientIsDeceased = $dod !== '';
    $ageDate = $dod !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $dod) : $today;
    if ($birthDate !== false && $birthDate->format('Y-m-d') === $dob
        && $ageDate !== false && $ageDate->format('Y-m-d') === ($dod !== '' ? $dod : $today->format('Y-m-d'))
        && $birthDate <= $ageDate) {
        $age = $birthDate->diff($ageDate);
        if ($age->y < 2) {
            $months = ($age->y * 12) + $age->m;
            $patientAge = $months . ' ' . fn_t($months === 1 ? 'patient_age_month' : 'patient_age_months');
        } else {
            $patientAge = $age->y . ' ' . fn_t($age->y === 1 ? 'patient_age_year' : 'patient_age_years');
        }
    }
    $patientCodeLabel = fn_t($companyIdentity['company_classification_key'] === 'pharmacy'
        ? 'company_customer_code_label_pharmacy' : 'company_customer_code_label_business');
    $patientIdentityTooltipText = e($patientCodeLabel . ': ' . (string) $selectedPatient['customer_code']);
    if ($dob !== '') {
        $patientIdentityTooltipText .= '&#10;' . e($customerText('patient_birth_date') . ': ' . fn_formatDateDisplay($dob));
    }
    if ($dod !== '') {
        $patientIdentityTooltipText .= '&#10;' . e($customerText('patient_death_date') . ': '
            . fn_formatDeathDateDisplay($dob, $dod));
    }
    if (isset($selectedPatient['created_at'])) {
        $patientName = grompil_dispensing_display_name(
            (string) $selectedPatient['name_first'], (string) $selectedPatient['name_last']
        );
        $contactManagerActionUrl = 'dispense.php?patient=' . rawurlencode((string) $selectedPatient['customer_code'])
            . '&dispense_status=' . rawurlencode($dispenseStatusKey) . '&contacts=1#contact-manager-modal';
        $contactManagerData = grompil_contact_manager_render_modal(
            'customer', $companyId, (string) $selectedPatient['customer_code'], $patientName,
            $patientCodeLabel, $contactManagerActionUrl, $contactManagerStatus
        );
        $patientContactCount = $contactManagerData['count'];
        $contactManagerModalHtml = $contactManagerData['html'];
        $patientContactButtonHtml = fn_moduleTemplateRender('contacts', 'contact_manager_trigger.html', [
            'contact_manager_count' => fn_t('contact_manager_count'),
            'contact_manager_count_number' => (string) $patientContactCount,
        ]);
        $patientEditButtonHtml = fn_moduleTemplateRender('dispensing', 'dispensing_patient_edit_button.html', [
            'customer_code' => e((string) $selectedPatient['customer_code']),
            'dispense_status_key' => e($dispenseStatusKey),
            'patient_edit' => e($customerText('patient_edit')),
        ]);
    }
    $patientBarAddress = trim(implode(', ', array_filter([
        trim((string) ($selectedPatient['street'] ?? '') . ' ' . (string) ($selectedPatient['house_number'] ?? '')
            . ((string) ($selectedPatient['box_number'] ?? '') !== '' ? ' bus ' . $selectedPatient['box_number'] : '')),
        trim((string) ($selectedPatient['postal_code'] ?? '') . ' ' . (string) ($selectedPatient['city'] ?? '')),
    ])));
    $patientMetadata = fn_moduleTemplateRender('dispensing', 'dispensing_patient_detail.html', [
        'patient_address' => $patientBarAddress !== '' ? $patientBarAddress : $customerText('patient_no_address'),
        '_patient_map_link_html' => grompil_google_maps_link(
            $selectedPatient['wgs84_latitude'] ?? null,
            $selectedPatient['wgs84_longitude'] ?? null,
            $patientAddress
        ),
        '_patient_contact_button_html' => $patientContactButtonHtml,
        '_patient_edit_button_html' => $patientEditButtonHtml,
    ]);
}
if ($patientMetadata === '') {
    $patientMetadata = fn_moduleTemplateRender('dispensing', 'dispensing_patient_detail.html', [
        'patient_address' => '',
        '_patient_map_link_html' => '',
        '_patient_contact_button_html' => '',
        '_patient_edit_button_html' => '',
    ]);
}
$auditLine = $selectedPatient
    ? str_replace(['{creator}', '{created}', '{updater}', '{updated}'], [
        (string) ($selectedPatient['created_by_name'] ?? ''), fn_formatDateDisplay((string) ($selectedPatient['created_at'] ?? '')),
        (string) ($selectedPatient['updated_by_name'] ?? ''), fn_formatDateDisplay((string) ($selectedPatient['updated_at'] ?? '')),
    ], $customerText('patient_audit_line'))
    : '';
$auditParts = explode(';', $auditLine, 2);
$auditTooltipText = e(trim($auditParts[0] ?? $auditLine));
if (isset($auditParts[1])) {
    $auditTooltipText .= '&#10;' . e(trim($auditParts[1]));
}
$messageHtml = $messageKey !== ''
    ? fn_moduleTemplateRender('dispensing', 'dispensing_alert.html', [
        'alert_type' => 'danger', 'alert_role' => 'alert', 'alert_message' => str_starts_with($messageKey, 'patient_')
            ? $customerText($messageKey) : fn_t($messageKey),
    ])
    : (!empty($_GET['saved']) ? fn_moduleTemplateRender('dispensing', 'dispensing_alert.html', [
        'alert_type' => 'success', 'alert_role' => 'status', 'alert_message' => $customerText('patient_saved'),
    ]) : '');

$patientFeedback = $query === ''
    ? $customerText('patient_search_prompt')
    : (mb_strlen($query) < 3
        ? $customerText('patient_search_minimum')
        : ($patientMatches === [] ? $customerText('patient_search_empty') : ''));
$patientSuggestionClass = $patientMatches !== [] ? '' : 'd-none';
$patientTitleHtml = $selectedPatient
    ? fn_moduleTemplateRender('dispensing', 'dispensing_patient_title.html', [
        'patient_initials' => strtoupper(mb_substr((string) $selectedPatient['name_first'], 0, 1) . mb_substr((string) $selectedPatient['name_last'], 0, 1)),
        'patient_name' => grompil_dispensing_display_name(
            (string) $selectedPatient['name_first'], (string) $selectedPatient['name_last']
        ),
        '_patient_audit_tooltip_text' => $auditTooltipText,
        '_patient_identity_tooltip_text' => $patientIdentityTooltipText,
        '_patient_age_html' => $selectedPatientIsDeceased
            ? '<span class="text-danger small">(' . e(grompil_dispensing_deceased_display($dod, $patientAge)) . ')</span>'
            : ($patientAge !== '' ? '<span class="text-secondary small">(' . e($patientAge) . ')</span>' : ''),
        '_patient_deceased_badge_html' => $selectedPatientIsDeceased
            ? '<span class="badge text-bg-danger">' . e(fn_t('dispense_no_dispensing_possible')) . '</span>' : '',
    ])
    : '';
$companyBrandHtml = $companyLogo !== ''
    ? fn_moduleTemplateRender('dispensing', 'dispensing_company_logo.html', [
        'company_logo_url' => $companyLogo, 'company_name' => (string) $companyIdentity['name'],
    ])
    : fn_moduleTemplateRender('dispensing', 'dispensing_company_monogram.html', [
        'company_monogram' => strtoupper(mb_substr((string) $companyIdentity['name'], 0, 1)),
    ]);
$addPatientButtonHtml = fn_moduleTemplateRender('dispensing', 'dispensing_add_patient_button.html', [
    'patient_add' => $customerText('patient_add'),
    'patient_add_url' => e(fn_appBaseHref() . 'dispense.php?dispense_status=' . rawurlencode($dispenseStatusKey) . '&new_patient=1'
        . ($selectedPatientCode !== '' ? '&patient=' . rawurlencode($selectedPatientCode) : '')),
]);
$patientPanelHtml = fn_moduleTemplateRender('dispensing', 'dispensing_patient_panel.html', [
        'dispense_patient_search' => $dispenseCustomerText('dispense_patient_search'),
        'dispense_select_patient' => $dispenseCustomerText('dispense_select_patient'),
        'dispense_query_value' => e($query),
        'dispense_search' => $customerText('patient_search_button'),
        'dispense_patient_search_regex' => $customerText('patient_search_regex'),
        'patient_show_deceased' => $customerText('patient_show_deceased'),
        '_patient_search_help_html' => fn_dokuwikiIcon('users', '629318'),
        '_dispense_deceased_checked' => $includeDeceased ? 'checked' : '',
        '_dispense_regex_checked' => $regexSearch ? 'checked' : '',
        '_dispense_patient_suggestion_class' => $patientSuggestionClass,
        '_dispense_patient_results_html' => $patientResultRows,
        'dispense_patient_search_feedback' => e($patientFeedback),
        'dispense_walkin_title' => $dispenseCustomerText('dispense_walkin_title'),
        'dispense_status_key' => e($dispenseStatusKey),
    ]);

$shortcutConfiguration = fn_loadDispensingShortcutConfiguration();
$shortcutConfiguration = $shortcutConfiguration === false ? [] : $shortcutConfiguration;
$shortcutActions = fn_dispensingShortcutActions();
$shortcutLanguage = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
$functionKeysByLayer = ['normal' => '', 'ctrl' => '', 'shift' => ''];
foreach (fn_dispensingShortcutSlots() as $shortcutSlot) {
    $keyCode = $shortcutSlot['key_code'];
    $shortcut = $shortcutConfiguration[$keyCode] ?? ['action_key' => 'none', 'labels' => []];
    $storedActionKey = (string) ($shortcut['action_key'] ?? 'none');
    $actionConfig = is_array($shortcut['action_config'] ?? null) ? $shortcut['action_config'] : [];
    if ($storedActionKey === 'open_company_website') {
        $actionConfig = [
            'url' => fn_appBaseHref() . 'index.php?c=' . rawurlencode((string) ($company['slug'] ?? '')),
            'new_tab' => true,
        ];
    }
    $moduleTarget = fn_dispensingShortcutModulePath($storedActionKey);
    $actionKey = $moduleTarget !== null
        ? $storedActionKey
        : (array_key_exists($storedActionKey, $shortcutActions) ? $storedActionKey : 'none');
    $actionDefinitionKey = $moduleTarget !== null ? 'open_module' : $actionKey;
    $labels = $shortcut['labels'][$shortcutLanguage] ?? [];
    $labels = is_array($labels) ? $labels : [];
    $shortcutTitle = trim((string) ($labels['title'] ?? ''));
    if ($shortcutTitle === '') {
        $shortcutTitle = fn_t($shortcutActions[$actionDefinitionKey]['title']);
    }
    $shortcutTooltip = trim((string) ($labels['tooltip'] ?? ''));
    if ($shortcutTooltip === '') {
        $shortcutTooltip = fn_t($shortcutActions[$actionDefinitionKey]['tooltip']);
    }
    $ariaKey = $shortcutSlot['layer'] === 'ctrl'
        ? 'Control+F' . $shortcutSlot['number']
        : ($shortcutSlot['layer'] === 'shift'
            ? 'Shift+F' . $shortcutSlot['number']
            : 'F' . $shortcutSlot['number']);
    $functionKeysByLayer[$shortcutSlot['layer']] .= fn_moduleTemplateRender('dispensing', 'dispensing_function_key.html', [
        'shortcut_key_code' => $keyCode,
        'shortcut_action_key' => $actionKey,
        'shortcut_action_config' => (string) json_encode($actionConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        'shortcut_aria_key' => $ariaKey,
        'shortcut_accessible_label' => $shortcutSlot['key_label'] . ': ' . $shortcutTitle . '. ' . $shortcutTooltip,
        'shortcut_key_label' => $shortcutSlot['key_label'],
        'shortcut_title' => $shortcutTitle,
        'shortcut_tooltip' => $shortcutTooltip,
    ]);
}
$functionBarHtml = fn_moduleTemplateRender('dispensing', 'dispensing_function_bar.html', [
    'dispensing_shortcuts_bar_aria' => fn_t('dispensing_shortcuts_bar_aria'),
    'dispensing_shortcuts_bar_help' => fn_t('dispensing_shortcuts_bar_help'),
    'dispensing_shortcuts_touch_layers_aria' => fn_t('dispensing_shortcuts_touch_layers_aria'),
    'dispensing_shortcuts_layer_normal' => fn_t('dispensing_shortcuts_layer_normal'),
    'dispensing_shortcuts_layer_ctrl' => fn_t('dispensing_shortcuts_layer_ctrl'),
    'dispensing_shortcuts_layer_shift' => fn_t('dispensing_shortcuts_layer_shift'),
    '_normal_function_keys_html' => $functionKeysByLayer['normal'],
    '_ctrl_function_keys_html' => $functionKeysByLayer['ctrl'],
    '_shift_function_keys_html' => $functionKeysByLayer['shift'],
]);

$dispensingTemplateVariant = fn_moduleTemplateVariant('dispensing');
$dispensingAssetPaths = [
    GROMPIL_ROOT . '/assets/dispensing.css',
    GROMPIL_ROOT . '/assets/dispensing-wide.css',
    GROMPIL_ROOT . '/assets/dispensing.js',
];
$dispensingAssetHashes = [];
foreach ($dispensingAssetPaths as $assetPath) {
    $assetHash = is_file($assetPath) ? hash_file('sha256', $assetPath) : false;
    if (is_string($assetHash)) {
        $dispensingAssetHashes[] = $assetHash;
    }
}
$dispensingAssetVersion = $dispensingAssetHashes !== []
    ? substr(hash('sha256', implode('|', $dispensingAssetHashes)), 0, 16)
    : '0.18.35';
header('Cache-Control: no-store, private');
page_start(fn_t('dispense_title'), $currentUser, $dispensingTemplateVariant === 'wide');
echo fn_moduleTemplateRender('dispensing', 'dispensing.html', [
    'dispense_title' => fn_t('dispense_title'),
    'dispense_mode' => $dispenseMode,
    'dispense_status_key' => e($dispenseStatusKey),
    '_dispense_status_options_html' => $dispenseStatusOptionsHtml,
    '_dispense_status_disabled' => $selectedPatientIsDeceased ? 'disabled' : '',
    'dispense_customer_code' => $selectedPatient ? (string) $selectedPatient['customer_code'] : '',
    '_dispense_patient_deceased' => $selectedPatientIsDeceased ? 'true' : 'false',
    'dispense_language' => strtolower((string) ($_SESSION['language'] ?? 'NL')),
    'dispense_history_title' => fn_t('dispensing_history_title'),
    'dispense_history_anonymous_title' => fn_t('dispensing_history_anonymous_title'),
    'dispense_history_prescription_number' => fn_t('dispensing_history_prescription_number'),
    'dispense_history_url' => fn_appBaseHref() . 'dispensing-history.php',
    'dispense_history_no_patient' => fn_t('dispensing_history_no_patient'),
    'dispense_history_error' => fn_t('dispensing_history_unavailable'),
    'dispense_history_empty' => fn_t('dispensing_history_empty'),
    'dispense_history_date' => fn_t('dispensing_history_date'),
    'dispense_history_session' => fn_t('dispensing_history_session'),
    'dispense_history_status' => fn_t('dispensing_history_status'),
    'dispense_history_cnk' => fn_t('dispensing_history_cnk'),
    'dispense_history_product' => fn_t('dispensing_history_product'),
    'dispense_history_package' => fn_t('dispensing_history_package'),
    'dispense_history_quantity' => fn_t('dispensing_history_quantity'),
    'dispense_history_first' => fn_t('dispensing_history_first'),
    'dispense_history_previous' => fn_t('dispensing_history_previous'),
    'dispense_history_next' => fn_t('dispensing_history_next'),
    'dispense_history_last' => fn_t('dispensing_history_last'),
    'dispense_history_search_label' => fn_t('dispensing_history_search_label'),
    'dispense_history_search_minimum' => fn_t('dispensing_history_search_minimum'),
    'dispense_history_per_page' => fn_t('dispensing_history_per_page'),
    'dispense_history_count' => fn_t('dispensing_history_count_short'),
    'dispense_history_customer_name' => $selectedPatient ? grompil_dispensing_display_name(
        (string) $selectedPatient['name_first'], (string) $selectedPatient['name_last']
    ) : '',
    'dispense_intro' => fn_t('dispense_intro'),
    'dispense_concept' => fn_t('dispense_concept'),
    'dispense_no_live_prescriptions' => fn_t('dispense_no_live_prescriptions'),
    'dispense_no_inventory' => fn_t('dispense_no_inventory'),
    'dispense_no_safety_data' => fn_t('dispense_no_safety_data'),
    'dispense_actions' => fn_t('dispense_actions'),
    'dispense_disabled_action' => fn_t('dispense_disabled_action'),
    'dispense_patient_search' => $dispenseCustomerText('dispense_patient_search'),
    'dispense_add' => fn_t('dispense_add'),
    'dispense_no_products' => fn_t('dispense_no_products'),
    'dispense_product_error' => fn_t('dispense_product_error'),
    'dispense_product_count' => fn_t('dispense_product_count'),
    'dispense_search' => $customerText('patient_search_button'),
    'dispense_new_patient' => $customerText('patient_add'),
    'dispense_patient_matches' => $customerText('patient_search_results'),
    'dispense_patient_search_prompt' => $customerText('patient_search_prompt'),
    'dispense_patient_search_feedback' => $patientFeedback,
    'dispense_patient_search_minimum' => '3',
    'dispense_patient_search_regex' => $customerText('patient_search_regex'),
    'dispense_patient_lookup_unavailable' => $customerText('patient_lookup_unavailable'),
    'dispense_patient_no_results' => $customerText('patient_search_empty'),
    'dispense_patient' => ucfirst($customerTerm),
    'dispense_no_patient' => $dispenseCustomerText('dispense_no_patient'),
    'dispense_select_patient' => $dispenseCustomerText('dispense_select_patient'),
    'dispense_edit_patient' => $customerText('patient_edit'),
    'dispense_product_search' => fn_t('dispense_product_search'),
    'dispense_withdrawn_filter' => fn_t('dispense_withdrawn_filter'),
    'dispense_product_help' => fn_t('dispense_product_help'),
    'dispense_patient_required' => $dispenseCustomerText('dispense_patient_required'),
    'dispense_no_dispensing_possible' => fn_t('dispense_no_dispensing_possible'),
    'dispense_status' => fn_t('dispense_status'),
    'dispense_current_items' => fn_t('dispense_current_items'),
    'dispense_parties_label' => fn_t('dispense_parties_label'),
    'dispense_add_party' => fn_t('dispense_add_party'),
    'dispense_session_error' => fn_t('dispense_session_error'),
    'dispense_discard_confirm' => fn_t('dispense_discard_confirm'),
    'dispense_discard_action' => fn_t('dispensing_shortcut_discard_dispensing'),
    'dispense_session_reference' => fn_t('dispense_session_reference'),
    'dispense_copy_session' => fn_t('dispense_copy_session'),
    'dispense_session_copied' => fn_t('dispense_session_copied'),
    'dispense_empty_basket' => fn_t('dispense_empty_basket'),
    'dispense_quantity' => fn_t('dispense_quantity'),
    'dispense_line_edit' => fn_t('dispense_line_edit'),
    'dispense_line_edit_save' => fn_t('dispense_line_edit_save'),
    'dispense_line_edit_error' => fn_t('dispense_line_edit_error'),
    'dispense_caregiver_search' => fn_t('dispense_caregiver_search'),
    'dispense_caregiver_minimum' => fn_t('dispense_caregiver_minimum'),
    'dispense_caregiver_unavailable' => fn_t('dispense_caregiver_unavailable'),
    'dispense_caregiver_missing_title' => fn_t('dispense_caregiver_missing_title'),
    'dispense_caregiver_missing_message' => fn_t('dispense_caregiver_missing_message'),
    'dispense_caregiver_continue' => fn_t('dispense_caregiver_continue'),
    'dispense_caregiver_selected' => fn_t('dispense_caregiver_selected'),
    'dispense_caregiver_add' => fn_t('dispense_caregiver_add'),
    'dispense_new_prescription' => fn_t('dispensing_new_prescription'),
    'dispense_prescription_keep_caregiver' => fn_t('dispensing_prescription_keep_caregiver'),
    'dispense_prescription_choose_caregiver' => fn_t('dispensing_prescription_choose_caregiver'),
    'dispense_prescription_start' => fn_t('dispensing_prescription_start'),
    'dispense_prescriber_required_title' => fn_t('dispensing_prescriber_required_title'),
    'dispense_prescriber_continue' => fn_t('dispensing_prescriber_continue'),
    'dispense_caregiver_endpoint' => fn_appBaseHref() . 'mb_caregivers.php?ajax=search',
    'dispense_remove' => fn_t('delete'),
    'dispense_price_unavailable' => fn_t('dispense_price_unavailable'),
    'dispense_logged_in' => fn_t('dispense_logged_in'),
    'dispense_prescriptions' => fn_t('dispense_prescriptions'),
    'dispense_right_panel' => fn_t('dispense_right_panel'),
    'dispense_stock' => fn_t('dispense_stock'),
    'dispense_safety' => fn_t('dispense_safety'),
    'dispense_billing' => fn_t('dispense_billing'),
    'dispense_payment' => fn_t('dispense_payment'),
    'dispense_ready' => fn_t('dispense_ready'),
    'dispense_close' => fn_t('dispense_close'),
    'dispense_receipt_title' => fn_t('dispense_receipt_title'),
    'dispense_receipt_cancel' => fn_t('dispense_receipt_cancel'),
    'dispense_receipt_confirm' => fn_t('dispense_receipt_confirm'),
    'dispense_patient_modal_title' => $patientModalTitle,
    'dispense_company' => (string) $companyIdentity['name'],
    'dispense_employee_role' => $employeeRoleName,
    'dispense_employee' => (string) $currentUser['display_name'],
    'dispense_employee_title' => $employeeTooltip,
    'dispense_employee_initials' => $initials,
    'dispense_logo_url' => $companyLogo,
    'dispense_query_value' => $query,
    'dispense_product_endpoint' => fn_appBaseHref() . 'dispense.php',
    'dispense_session_endpoint' => fn_appBaseHref() . 'dispense.php?dispensing_session_api=1',
    'dispense_company_id' => (string) $companyId,
    'dispense_actor_code' => $actorCode,
    'dispense_csrf_token' => csrf_token(),
    'asset_version' => $dispensingAssetVersion,
    'dispense_product_min_chars' => '3',
    'dispense_patient_min_chars' => '3',
    'dispense_patient_form_mode' => $editingPatientCode !== '' ? 'edit' : 'create',
    '_dispense_patient_search_feedback_html' => $patientFeedback !== ''
        ? fn_moduleTemplateRender('dispensing', 'dispensing_patient_feedback.html', ['patient_feedback' => $patientFeedback])
        : '',
    '_dispense_patient_count_html' => '',
    '_dispense_product_search_disabled' => $selectedPatientIsDeceased
        || ($selectedPatient === null && $dispenseStatusKey !== 'free_sale') ? 'disabled' : '',
    '_dispense_patient_required_html' => fn_moduleTemplateRender('dispensing', 'dispensing_patient_required.html', [
        'dispense_patient_required' => $dispenseCustomerText('dispense_patient_required'),
    '_dispense_patient_required_class' => !$selectedPatientIsDeceased && $selectedPatient === null && $dispenseStatusKey !== 'free_sale' ? '' : 'd-none',
    ]),
    '_dispense_patient_results_html' => $patientResultRows,
    '_dispense_patient_suggestion_class' => $patientSuggestionClass,
    '_dispense_regex_checked' => $regexSearch ? 'checked' : '',
    '_dispense_deceased_checked' => $includeDeceased ? 'checked' : '',
    'patient_show_deceased' => $customerText('patient_show_deceased'),
    'dispense_patient_endpoint' => fn_appBaseHref() . 'dispense.php',
    '_dispense_patient_modal_form_html' => $patientModalForm,
    '_dispense_patient_modal_open' => $openPatientModal ? 'true' : 'false',
    '_dispense_duplicate_warning_open' => $duplicatePatientWarning ? 'true' : 'false',
    'patient_duplicate_warning_title' => $customerText('patient_duplicate_warning_title'),
    'patient_duplicate_warning_text' => $customerText('patient_duplicate_warning_text'),
    'patient_duplicate_warning_back' => $customerText('patient_duplicate_warning_back'),
    'patient_duplicate_warning_continue' => $customerText('patient_duplicate_warning_continue'),
    '_dispense_patient_detail_html' => fn_moduleTemplateRender('dispensing', 'dispensing_patient_audit.html', [
        '_patient_metadata_html' => $patientMetadata,
    ]),
    'dispense_patient_no_address' => $customerText('patient_no_address'),
    'dispense_contact_details' => fn_t('contact_manager_count'),
    'dispense_google_maps' => fn_t('address_google_maps'),
    '_dispense_patient_title_html' => $patientTitleHtml,
    '_contact_manager_modal_html' => $contactManagerModalHtml,
    '_contact_manager_modal_open' => $contactManagerModalOpen && $selectedPatient !== null ? 'true' : 'false',
    '_dispense_brand_html' => $companyBrandHtml,
    '_dispense_alert_html' => $messageHtml,
    '_dispense_function_bar_html' => $functionBarHtml,
    '_dispense_patient_actions_html' => $addPatientButtonHtml,
    '_dispense_patient_panel_html' => $patientPanelHtml,
]);
page_end();
