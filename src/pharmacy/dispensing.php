<?php
/**
 *
 * @date_cre: Thu 2026-11-12 11:07:02
 * @date_mut: Thu 2026-11-12 17:34:00
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
$currentUser = require_login();
$company = require_company_context($currentUser);
if ($company === null || !grompil_can_access_module('dispense.php', $currentUser, $company)) {
    grompil_show_error(403, 40152, fn_t('access_denied'), 'Access denied');
}

$companyId = (int) $company['id'];
$actorCode = (string) $currentUser['user_code'];
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
$dispenseMode = (string) ($_GET['mode'] ?? 'patient') === 'walk_in' ? 'walk_in' : 'patient';
$messageKey = '';
$editingPatientCode = '';
$openPatientModal = false;
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
    if ($dispenseMode === 'patient') {
        $productPatientCode = strtoupper(trim((string) ($_GET['patient'] ?? '')));
        if (preg_match('/^[A-Z0-9]{6}$/D', $productPatientCode) !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'patient_required']);
            exit;
        }
        try {
            $patientCheck = db_query_params(
                'dispensing.product_search_patient_check',
                'SELECT 1 FROM bt_customers WHERE company_id=$1 AND customer_code=$2 AND is_active',
                [$companyId, $productPatientCode]
            );
        } catch (Throwable $error) {
            error_log('Dispensing product patient check failed: ' . get_class($error));
            http_response_code(503);
            echo json_encode(['error' => 'patient_check_unavailable']);
            exit;
        }
        if (pg_num_rows($patientCheck) !== 1) {
            http_response_code(403);
            echo json_encode(['error' => 'patient_unavailable']);
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
    $birthDate = trim((string) ($_POST['date_of_birth'] ?? ''));
    $birthDateValue = $birthDate === '' ? null : $birthDate;
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
    $dateValid = $birthDate === '' || ($parsedBirthDate !== false
        && $parsedBirthDate->format('Y-m-d') === $birthDate
        && $birthDate <= date('Y-m-d'));
    $valid = $firstName !== '' && mb_strlen($firstName) <= 120
        && $lastName !== '' && mb_strlen($lastName) <= 120
        && $dateValid && preg_match('/^[A-Z]{2}$/D', $countryCode) === 1
        && mb_strlen($street) <= 160 && mb_strlen($houseNumber) <= 32
        && mb_strlen($boxNumber) <= 32 && mb_strlen($postalCode) <= 20 && mb_strlen($city) <= 100
        && in_array($addressTypeKey, $customerAddressTypeKeys, true)
        && mb_strlen($addressLabel) <= 160
        && grompil_address_selection_allowed($addressSelection, isset($_POST['manual_address_without_coordinates']));

    if (in_array($action, ['create_patient', 'update_patient'], true) && $valid) {
        try {
            db_query_raw('patients.transaction.begin', 'BEGIN');
            if ($action === 'create_patient') {
                $created = false;
                for ($attempt = 0; $attempt < 8; $attempt++) {
                    $candidate = grompil_generate_user_code();
                    $insert = db_query_params(
                        'patients.create',
                        'INSERT INTO bt_customers (company_id, customer_code, name_first, name_last, date_of_birth, created_by_user_code, updated_by_user_code)
                         VALUES ($1, $2, $3, $4, $5::date, $6, $6)
                         ON CONFLICT (customer_code) DO NOTHING RETURNING customer_code',
                        [$companyId, $candidate, $firstName, $lastName, $birthDateValue, $actorCode]
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
                        updated_by_user_code=$4, updated_at=now()
                     WHERE company_id=$5 AND customer_code=$6 AND is_active RETURNING customer_code',
                    [$firstName, $lastName, $birthDateValue, $actorCode, $companyId, $patientCode]
                );
                if (pg_num_rows($update) !== 1) {
                    throw new RuntimeException('Patient record was not found in the active company.');
                }
            } else {
                throw new RuntimeException('Invalid patient operation.');
            }

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
            $redirectUrl = 'dispense.php?patient=' . rawurlencode($patientCode) . '&saved=1';
            if ($action === 'update_patient') {
                $redirectUrl .= '&edit=1';
            }
            redirect($redirectUrl);
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
        $messageKey = 'patient_form_invalid';
        $openPatientModal = true;
        $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$regexSearch = (string) ($_GET['regex'] ?? '') === '1';
$patientSuggestionRequest = (string) ($_GET['patient_suggest'] ?? '') === '1';
$selectedPatientCode = $dispenseMode === 'patient' ? strtoupper(trim((string) ($_GET['patient'] ?? ''))) : '';
if ($selectedPatientCode === '' && $editingPatientCode !== '') {
    $selectedPatientCode = $editingPatientCode;
}
$selectedPatient = null;
$patientMatches = [];
try {
    if ($selectedPatientCode !== '' && preg_match('/^[A-Z0-9]{6}$/D', $selectedPatientCode) === 1) {
        $result = db_query_params(
            'patients.load_selected',
            'SELECT p.customer_code, p.name_first, p.name_last, p.date_of_birth,
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
    if ($dispenseMode === 'patient' && mb_strlen($query) >= 3) {
        $searchPattern = $regexSearch ? $query : '%' . $query . '%';
        $searchPredicate = $regexSearch
            ? "(p.customer_code ~* $2 OR (p.name_first || ' ' || p.name_last) ~* $2
                OR (p.name_last || ' ' || p.name_first) ~* $2)"
            : "(p.customer_code = upper($2) OR lower(p.name_first || ' ' || p.name_last) LIKE lower($3)
                OR lower(p.name_last || ' ' || p.name_first) LIKE lower($3))";
        $searchParams = $regexSearch
            ? [$companyId, $searchPattern]
            : [$companyId, $query, $searchPattern];
        $result = db_query_params(
            'patients.search',
            "SELECT p.customer_code, p.name_first, p.name_last, p.date_of_birth,
                    l.street, l.house_number, l.postal_code, l.city
             FROM bt_customers p
             LEFT JOIN LATERAL (
                SELECT street, house_number, postal_code, city FROM dt_customer_locations
                WHERE company_id=p.company_id AND customer_code=p.customer_code AND is_active
                ORDER BY is_primary DESC, sort_order, id LIMIT 1
             ) l ON TRUE
             WHERE p.company_id=$1 AND p.is_active AND $searchPredicate
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

if ($patientSuggestionRequest && $dispenseMode === 'patient') {
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
                'patient_name' => trim((string) $patient['name_first'] . ' ' . (string) $patient['name_last']),
                'patient_address' => $address !== '' ? $address : $customerText('patient_no_address'),
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
        'date_of_birth' => (string) ($_POST['date_of_birth'] ?? ''),
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
$nameParts = preg_split('/\s+/u', trim((string) $currentUser['display_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = strtoupper(mb_substr($nameParts[0] ?? '?', 0, 1) . mb_substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));

$patientResultRows = '';
foreach ($patientMatches as $patient) {
    $address = trim(implode(' ', array_filter([
        trim((string) ($patient['street'] ?? '') . ' ' . (string) ($patient['house_number'] ?? '')),
        (string) ($patient['postal_code'] ?? ''),
        (string) ($patient['city'] ?? ''),
    ])));
    $patientResultRows .= fn_templateRender('dispensing_patient_result.html', [
        'customer_code' => (string) $patient['customer_code'],
        'patient_name' => trim($patient['name_first'] . ' ' . $patient['name_last']),
        'patient_address' => $address !== '' ? $address : $customerText('patient_no_address'),
    ]);
}

$patientAddress = $selectedPatient ? trim(implode(', ', array_filter([
    trim((string) ($selectedPatient['street'] ?? '') . ' ' . (string) ($selectedPatient['house_number'] ?? '')
        . ((string) ($selectedPatient['box_number'] ?? '') !== '' ? ' bus ' . $selectedPatient['box_number'] : '')),
    trim((string) ($selectedPatient['postal_code'] ?? '') . ' ' . (string) ($selectedPatient['city'] ?? '')),
    (string) ($selectedPatient['country_code'] ?? ''),
]))) : '';
$patientFormValues = $editingPatientCode !== '' ? ($selectedPatient ?? []) : [];
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
        'date_of_birth' => (string) ($_POST['date_of_birth'] ?? ''),
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
$customerAddressesHtml = '';
if ($editingPatientCode !== '') {
    $addressRows = db_query_params(
        'customers.addresses_for_edit',
        'SELECT id, address_type_key, label, street, house_number, box_number, postal_code, city,
                country_code, is_primary, sort_order
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
        $addressHref = 'dispense.php?patient=' . rawurlencode($editingPatientCode) . '&edit=1&location=' . (int) $addressRow['id'];
        $addressDescription = trim(implode(' · ', array_filter([
            (string) ($customerAddressTypeLabels[$addressRow['address_type_key']] ?? $addressRow['address_type_key']),
            (string) $addressRow['label'],
            $addressText,
        ], static fn(string $part): bool => $part !== '')));
        $customerAddressesHtml .= '<a class="d-block small py-1' . ($selectedAddress ? ' fw-semibold' : '') . '" href="'
            . e($addressHref) . '">#' . (int) $addressRow['sort_order'] . ' · ' . e($addressDescription)
            . ($addressRow['is_primary'] === 't' ? ' · ' . e(fn_t('address_default_badge')) : '') . '</a>';
    }
    $customerAddressesHtml .= '<a class="btn btn-sm btn-outline-secondary mt-2" href="dispense.php?patient='
        . rawurlencode($editingPatientCode) . '&amp;edit=1&amp;new_address=1">' . e(fn_t('customer_address_add')) . '</a></div></div>';
}
$addressTypeOptionsHtml = '';
foreach ($customerAddressTypes as $addressTypeOption) {
    $addressKey = (string) $addressTypeOption['key'];
    $addressTypeOptionsHtml .= '<option value="' . e($addressKey) . '"'
        . (($patientFormValues['address_type_key'] ?? 'residential') === $addressKey ? ' selected' : '') . '>'
        . e((string) $addressTypeOption['label']) . '</option>';
}
$patientModalForm = fn_templateRender('dispensing_patient_form.html', [
    'csrf_token' => csrf_token(),
    'patient_form_action' => $formAction,
    'customer_code' => $editingPatientCode,
    'patient_location_id' => (string) ($patientFormValues['location_id'] ?? ''),
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
    'patient_first_name_value' => (string) ($patientFormValues['name_first'] ?? ''),
    'patient_last_name_value' => (string) ($patientFormValues['name_last'] ?? ''),
    'patient_birth_date_value' => (string) ($patientFormValues['date_of_birth'] ?? ''),
    'patient_first_name' => $customerText('patient_first_name'),
    'patient_last_name' => $customerText('patient_last_name'),
    'patient_birth_date' => $customerText('patient_birth_date'),
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
if ($selectedPatient) {
    $dob = trim((string) ($selectedPatient['date_of_birth'] ?? ''));
    $birthDate = $dob !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $dob) : false;
    $today = new DateTimeImmutable('today');
    $age = $birthDate !== false && $birthDate->format('Y-m-d') === $dob && $birthDate <= $today
        ? (string) $birthDate->diff($today)->y
        : '';
    $patientMetadata = fn_templateRender('dispensing_patient_detail.html', [
        'customer_code' => (string) $selectedPatient['customer_code'],
        'customer_code_label' => fn_t($companyIdentity['company_classification_key'] === 'pharmacy'
            ? 'company_customer_code_label_pharmacy' : 'company_customer_code_label_business'),
        '_patient_birth_date_html' => $dob !== ''
            ? fn_templateRender('dispensing_patient_birth_date.html', [
                'patient_birth_date_value' => $dob,
                'patient_age_value' => $age !== '' ? '(' . $age . ')' : '',
            ])
            : '',
        'patient_address' => $patientAddress !== '' ? $patientAddress : $customerText('patient_no_address'),
        '_patient_map_link_html' => grompil_google_maps_link(
            $selectedPatient['wgs84_latitude'] ?? null,
            $selectedPatient['wgs84_longitude'] ?? null,
            $patientAddress
        ),
    ]);
}
$auditLine = $selectedPatient
    ? str_replace(['{creator}', '{created}', '{updater}', '{updated}'], [
        (string) ($selectedPatient['created_by_name'] ?? ''), (string) ($selectedPatient['created_at'] ?? ''),
        (string) ($selectedPatient['updated_by_name'] ?? ''), (string) ($selectedPatient['updated_at'] ?? ''),
    ], $customerText('patient_audit_line'))
    : '';
$messageHtml = $messageKey !== ''
    ? fn_templateRender('dispensing_alert.html', [
        'alert_type' => 'danger', 'alert_role' => 'alert', 'alert_message' => str_starts_with($messageKey, 'patient_')
            ? $customerText($messageKey) : fn_t($messageKey),
    ])
    : (!empty($_GET['saved']) ? fn_templateRender('dispensing_alert.html', [
        'alert_type' => 'success', 'alert_role' => 'status', 'alert_message' => $customerText('patient_saved'),
    ]) : '');

$patientFeedback = $query === ''
    ? $customerText('patient_search_prompt')
    : (mb_strlen($query) < 3
        ? $customerText('patient_search_minimum')
        : ($patientMatches === [] ? $customerText('patient_search_empty') : ''));
$patientSuggestionClass = $patientMatches !== [] ? '' : 'd-none';
$patientTitleHtml = $selectedPatient
    ? fn_templateRender('dispensing_patient_title.html', [
        'patient_initials' => strtoupper(mb_substr((string) $selectedPatient['name_first'], 0, 1) . mb_substr((string) $selectedPatient['name_last'], 0, 1)),
        'patient_name' => trim($selectedPatient['name_first'] . ' ' . $selectedPatient['name_last']),
        'customer_code' => (string) $selectedPatient['customer_code'],
    ])
    : ($dispenseMode === 'walk_in'
    ? fn_templateRender('dispensing_walkin_title.html', ['dispense_walkin_title' => $dispenseCustomerText('dispense_walkin_title')])
        : fn_templateRender('dispensing_no_patient_title.html', [
            'dispense_no_patient' => $dispenseCustomerText('dispense_no_patient'),
        ]));
$companyBrandHtml = $companyLogo !== ''
    ? fn_templateRender('dispensing_company_logo.html', [
        'company_logo_url' => $companyLogo, 'company_name' => (string) $companyIdentity['name'],
    ])
    : fn_templateRender('dispensing_company_monogram.html', [
        'company_monogram' => strtoupper(mb_substr((string) $companyIdentity['name'], 0, 1)),
    ]);
$patientEditButtonHtml = $selectedPatient
    ? fn_templateRender('dispensing_patient_edit_button.html', [
        'customer_code' => $selectedPatientCode,
        'patient_edit' => $customerText('patient_edit'),
    ])
    : '';
$addPatientButtonHtml = fn_templateRender('dispensing_add_patient_button.html', [
    'patient_add' => $customerText('patient_add'),
    'patient_add_url' => e(fn_appBaseHref() . 'dispense.php?mode=patient&new_patient=1'
        . ($selectedPatientCode !== '' ? '&patient=' . rawurlencode($selectedPatientCode) : '')),
]);
$patientModeUrl = fn_appBaseHref() . 'dispense.php?mode=patient';
$walkInModeUrl = fn_appBaseHref() . 'dispense.php?mode=walk_in';
$modeSwitchHtml = $dispenseMode === 'walk_in'
    ? '<a class="btn btn-outline-primary" href="' . e($patientModeUrl) . '">' . e($dispenseCustomerText('dispense_patient_mode')) . '</a>'
    : '';
$patientPanelHtml = $dispenseMode === 'walk_in'
    ? fn_templateRender('dispensing_walkin_panel.html', [
        'dispense_walkin_title' => $dispenseCustomerText('dispense_walkin_title'),
        'dispense_walkin_help' => $dispenseCustomerText('dispense_walkin_help'),
    ])
    : fn_templateRender('dispensing_patient_panel.html', [
        'dispense_patient_search' => $dispenseCustomerText('dispense_patient_search'),
        'dispense_select_patient' => $dispenseCustomerText('dispense_select_patient'),
        'dispense_query_value' => e($query),
        'dispense_search' => $customerText('patient_search_button'),
        'dispense_patient_search_regex' => $customerText('patient_search_regex'),
        '_dispense_regex_checked' => $regexSearch ? 'checked' : '',
        '_dispense_patient_suggestion_class' => $patientSuggestionClass,
        '_dispense_patient_results_html' => $patientResultRows,
        'dispense_patient_search_feedback' => e($patientFeedback),
        'dispense_walkin_title' => $dispenseCustomerText('dispense_walkin_title'),
        'dispense_walkin_mode_url' => e($walkInModeUrl),
    ]);

page_start(fn_t('dispense_title'), $currentUser);
echo fn_templateRender('dispensing.html', [
    'dispense_title' => fn_t('dispense_title'),
    'dispense_mode' => $dispenseMode,
    'dispense_customer_code' => $selectedPatient ? (string) $selectedPatient['customer_code'] : '',
    'dispense_language' => strtolower((string) ($_SESSION['language'] ?? 'NL')),
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
    'dispense_product_help' => fn_t('dispense_product_help'),
    'dispense_patient_required' => $dispenseCustomerText('dispense_patient_required'),
    'dispense_mode_label' => $dispenseCustomerText($dispenseMode === 'walk_in' ? 'dispense_walkin_title' : 'dispense_patient_mode'),
    'dispense_current_items' => fn_t('dispense_current_items'),
    'dispense_empty_basket' => fn_t('dispense_empty_basket'),
    'dispense_quantity' => fn_t('dispense_quantity'),
    'dispense_remove' => fn_t('delete'),
    'dispense_price_unavailable' => fn_t('dispense_price_unavailable'),
    'dispense_working_user' => fn_t('dispense_working_user'),
    'dispense_logged_in' => fn_t('dispense_logged_in'),
    'dispense_prescriptions' => fn_t('dispense_prescriptions'),
    'dispense_right_panel' => fn_t('dispense_right_panel'),
    'dispense_stock' => fn_t('dispense_stock'),
    'dispense_safety' => fn_t('dispense_safety'),
    'dispense_billing' => fn_t('dispense_billing'),
    'dispense_payment' => fn_t('dispense_payment'),
    'dispense_ready' => fn_t('dispense_ready'),
    'dispense_patient_modal_title' => $patientModalTitle,
    'dispense_company' => (string) $companyIdentity['name'],
    'dispense_employee' => (string) $currentUser['display_name'],
    'dispense_employee_title' => $employeeTooltip,
    'dispense_employee_initials' => $initials,
    'dispense_logo_url' => $companyLogo,
    'dispense_query_value' => $query,
    'dispense_product_endpoint' => fn_appBaseHref() . 'dispense.php',
    'asset_version' => '0.18.17',
    'dispense_product_min_chars' => '3',
    'dispense_patient_min_chars' => '3',
    'dispense_patient_form_mode' => $editingPatientCode !== '' ? 'edit' : 'create',
    '_dispense_patient_search_feedback_html' => $patientFeedback !== ''
        ? fn_templateRender('dispensing_patient_feedback.html', ['patient_feedback' => $patientFeedback])
        : '',
    '_dispense_patient_count_html' => '',
    '_dispense_product_search_disabled' => $selectedPatient === null && $dispenseMode !== 'walk_in' ? 'disabled' : '',
    '_dispense_patient_required_html' => $selectedPatient === null && $dispenseMode !== 'walk_in'
        ? fn_templateRender('dispensing_patient_required.html', ['dispense_patient_required' => $dispenseCustomerText('dispense_patient_required')])
        : '',
    '_dispense_patient_results_html' => $patientResultRows,
    '_dispense_patient_suggestion_class' => $patientSuggestionClass,
    '_dispense_regex_checked' => $regexSearch ? 'checked' : '',
    'dispense_patient_endpoint' => fn_appBaseHref() . 'dispense.php',
    '_dispense_patient_modal_form_html' => $patientModalForm,
    '_dispense_patient_modal_open' => $openPatientModal ? 'true' : 'false',
    '_dispense_patient_detail_html' => $selectedPatient
        ? fn_templateRender('dispensing_patient_audit.html', [
            '_patient_metadata_html' => $patientMetadata,
            'patient_audit_line' => $auditLine,
        ])
        : ($dispenseMode === 'walk_in'
            ? ''
            : fn_templateRender('dispensing_no_patient_detail.html', [
                'dispense_select_patient' => $dispenseCustomerText('dispense_select_patient'),
            ])),
    '_dispense_patient_title_html' => $patientTitleHtml,
    '_dispense_patient_edit_button_html' => $patientEditButtonHtml,
    '_dispense_brand_html' => $companyBrandHtml,
    '_dispense_alert_html' => $messageHtml,
    '_dispense_patient_actions_html' => $dispenseMode === 'walk_in' ? $modeSwitchHtml : $addPatientButtonHtml,
    '_dispense_patient_panel_html' => $patientPanelHtml,
]);
page_end();
