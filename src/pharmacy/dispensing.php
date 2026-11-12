<?php
/**
 *
 * @date_cre: Thu 2026-11-12 11:07:02
 * @date_mut: Thu 2026-11-12 12:19:30
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Company patient lookup, maintenance, and SAM product selection.
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
$messageKey = '';
$editingPatientCode = '';
$openPatientModal = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $patientCode = strtoupper(trim((string) ($_POST['patient_code'] ?? '')));
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
                        'INSERT INTO bt_patients (company_id, patient_code, name_first, name_last, date_of_birth, created_by_user_code, updated_by_user_code)
                         VALUES ($1, $2, $3, $4, $5::date, $6, $6)
                         ON CONFLICT (patient_code) DO NOTHING RETURNING patient_code',
                        [$companyId, $candidate, $firstName, $lastName, $birthDateValue, $actorCode]
                    );
                    $inserted = pg_fetch_assoc($insert);
                    if ($inserted) {
                        $patientCode = (string) $inserted['patient_code'];
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
                    'UPDATE bt_patients SET name_first=$1, name_last=$2, date_of_birth=$3::date,
                        updated_by_user_code=$4, updated_at=now()
                     WHERE company_id=$5 AND patient_code=$6 AND is_active RETURNING patient_code',
                    [$firstName, $lastName, $birthDateValue, $actorCode, $companyId, $patientCode]
                );
                if (pg_num_rows($update) !== 1) {
                    throw new RuntimeException('Patient record was not found in the active company.');
                }
            } else {
                throw new RuntimeException('Invalid patient operation.');
            }

            if ($locationId > 0) {
                $location = db_query_params(
                    'patients.location.update',
                    'UPDATE dt_patient_locations
                     SET street=$1, house_number=$2, box_number=$3, postal_code=$4, city=$5,
                         country_code=$6, address_reference_id=$7, lambert72_x=$8, lambert72_y=$9,
                         wgs84_latitude=$10, wgs84_longitude=$11, updated_by_user_code=$12, updated_at=now()
                     WHERE id=$13 AND company_id=$14 AND patient_code=$15 AND is_active',
                    [$street, $houseNumber, $boxNumber, $postalCode, $city, $countryCode, $addressReferenceId,
                        $lambert72X, $lambert72Y, $wgs84Latitude, $wgs84Longitude, $actorCode, $locationId, $companyId, $patientCode]
                );
                if (pg_affected_rows($location) !== 1) {
                    throw new RuntimeException('Patient address was not found in the active company.');
                }
            } elseif ($addressHasContent) {
                db_query_params(
                    'patients.location.create',
                    'INSERT INTO dt_patient_locations (
                        company_id, patient_code, street, house_number, box_number, postal_code, city,
                        country_code, address_reference_id, lambert72_x, lambert72_y, wgs84_latitude,
                        wgs84_longitude, is_primary, is_active, created_by_user_code, updated_by_user_code
                     ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,TRUE,TRUE,$14,$14)',
                    [$companyId, $patientCode, $street, $houseNumber, $boxNumber, $postalCode, $city, $countryCode,
                        $addressReferenceId, $lambert72X, $lambert72Y, $wgs84Latitude, $wgs84Longitude, $actorCode]
                );
            }
            db_query_raw('patients.transaction.commit', 'COMMIT');
            redirect('dispense.php?patient=' . rawurlencode($patientCode) . '&saved=1');
        } catch (Throwable $error) {
            try {
                db_query_raw('patients.transaction.rollback', 'ROLLBACK');
            } catch (Throwable) {
            }
            error_log('Patient record save failed: ' . get_class($error));
            $messageKey = 'patient_save_failed';
            $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
            $openPatientModal = true;
        }
    } else {
        $messageKey = 'patient_form_invalid';
        $openPatientModal = true;
        $editingPatientCode = $action === 'update_patient' ? $patientCode : '';
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$regexSearch = (string) ($_GET['regex'] ?? '') === '1';
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
            'SELECT p.patient_code, p.name_first, p.name_last, p.date_of_birth,
                    l.id AS location_id, l.street, l.house_number, l.box_number, l.postal_code,
                    l.city, l.country_code, l.address_reference_id, l.lambert72_x, l.lambert72_y,
                    l.wgs84_latitude, l.wgs84_longitude,
                    p.created_at, p.updated_at, creator.display_name AS created_by_name,
                    updater.display_name AS updated_by_name
             FROM bt_patients p
             LEFT JOIN LATERAL (
                SELECT * FROM dt_patient_locations
                WHERE company_id=p.company_id AND patient_code=p.patient_code AND is_active
                ORDER BY is_primary DESC, sort_order, id LIMIT 1
             ) l ON TRUE
             JOIN bt_users creator ON creator.user_code=p.created_by_user_code
             JOIN bt_users updater ON updater.user_code=p.updated_by_user_code
             WHERE p.company_id=$1 AND p.patient_code=$2 AND p.is_active',
            [$companyId, $selectedPatientCode]
        );
        $selectedPatient = pg_fetch_assoc($result) ?: null;
    }
    if (isset($_GET['edit']) && $_GET['edit'] === '1' && $selectedPatient !== null) {
        $editingPatientCode = $selectedPatientCode;
        $openPatientModal = true;
    }
    if (mb_strlen($query) >= 3) {
        $searchPattern = $regexSearch ? $query : '%' . $query . '%';
        $searchPredicate = $regexSearch
            ? "(p.patient_code ~* $2 OR (p.name_first || ' ' || p.name_last) ~* $2
                OR (p.name_last || ' ' || p.name_first) ~* $2)"
            : "(p.patient_code = upper($2) OR lower(p.name_first || ' ' || p.name_last) LIKE lower($3)
                OR lower(p.name_last || ' ' || p.name_first) LIKE lower($3))";
        $searchParams = $regexSearch
            ? [$companyId, $searchPattern]
            : [$companyId, $query, $searchPattern];
        $result = db_query_params(
            'patients.search',
            "SELECT p.patient_code, p.name_first, p.name_last, p.date_of_birth,
                    l.street, l.house_number, l.postal_code, l.city
             FROM bt_patients p
             LEFT JOIN LATERAL (
                SELECT street, house_number, postal_code, city FROM dt_patient_locations
                WHERE company_id=p.company_id AND patient_code=p.patient_code AND is_active
                ORDER BY is_primary DESC, sort_order, id LIMIT 1
             ) l ON TRUE
             WHERE p.company_id=$1 AND p.is_active AND $searchPredicate
             ORDER BY lower(p.name_last), lower(p.name_first), p.patient_code LIMIT 20",
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
            'error' => fn_t($regexSearch ? 'patient_regex_invalid' : 'patient_lookup_unavailable'),
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
                'patient_code' => (string) $patient['patient_code'],
                'patient_name' => trim((string) $patient['name_first'] . ' ' . (string) $patient['name_last']),
                'patient_address' => $address !== '' ? $address : fn_t('patient_no_address'),
            ];
        }
        echo json_encode(['results' => $suggestions], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

if ($editingPatientCode !== '' && $selectedPatient === null) {
    $selectedPatient = [
        'patient_code' => $editingPatientCode,
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

$companyResult = db_query_params(
    'dispense.company_identity',
    'SELECT id, name, store_logo_file FROM bt_companies WHERE id=$1 AND is_active',
    [$companyId]
);
$companyIdentity = pg_fetch_assoc($companyResult) ?: ['id' => $companyId, 'name' => $company['name'], 'store_logo_file' => ''];
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
        'patient_code' => (string) $patient['patient_code'],
        'patient_name' => trim($patient['name_first'] . ' ' . $patient['name_last']),
        'patient_address' => $address !== '' ? $address : fn_t('patient_no_address'),
    ]);
}

$patientAddress = $selectedPatient ? trim(implode(', ', array_filter([
    trim((string) ($selectedPatient['street'] ?? '') . ' ' . (string) ($selectedPatient['house_number'] ?? '')
        . ((string) ($selectedPatient['box_number'] ?? '') !== '' ? ' bus ' . $selectedPatient['box_number'] : '')),
    trim((string) ($selectedPatient['postal_code'] ?? '') . ' ' . (string) ($selectedPatient['city'] ?? '')),
    (string) ($selectedPatient['country_code'] ?? ''),
]))) : '';
$patientFormValues = $editingPatientCode !== '' ? ($selectedPatient ?? []) : [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $patientFormValues = array_replace($patientFormValues, [
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
    ]);
}
$formAction = $editingPatientCode !== '' ? 'update_patient' : 'create_patient';
$patientModalTitle = $editingPatientCode !== '' ? fn_t('patient_edit') : fn_t('patient_add');
$patientModalForm = fn_templateRender('dispensing_patient_form.html', [
    'csrf_token' => csrf_token(),
    'patient_form_action' => $formAction,
    'patient_code' => $editingPatientCode,
    'patient_location_id' => (string) ($patientFormValues['location_id'] ?? ''),
    'patient_first_name_value' => (string) ($patientFormValues['name_first'] ?? ''),
    'patient_last_name_value' => (string) ($patientFormValues['name_last'] ?? ''),
    'patient_birth_date_value' => (string) ($patientFormValues['date_of_birth'] ?? ''),
    'patient_country_value' => (string) ($patientFormValues['country_code'] ?? 'BE'),
    '_patient_address_fields_html' => fn_templateRender('address_fields.html', [
        'address_prefix' => 'patient-address',
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
    $patientMetadata = fn_templateRender('dispensing_patient_detail.html', [
        'patient_code' => (string) $selectedPatient['patient_code'],
        '_patient_birth_date_html' => $dob !== ''
            ? fn_templateRender('dispensing_patient_birth_date.html', ['patient_birth_date_value' => $dob])
            : '',
        'patient_address' => $patientAddress !== '' ? $patientAddress : fn_t('patient_no_address'),
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
    ], fn_t('patient_audit_line'))
    : '';
$messageHtml = $messageKey !== ''
    ? fn_templateRender('dispensing_alert.html', [
        'alert_type' => 'danger', 'alert_role' => 'alert', 'alert_message' => fn_t($messageKey),
    ])
    : (!empty($_GET['saved']) ? fn_templateRender('dispensing_alert.html', [
        'alert_type' => 'success', 'alert_role' => 'status', 'alert_message' => fn_t('patient_saved'),
    ]) : '');

$patientFeedback = $query === ''
    ? fn_t('patient_search_prompt')
    : (mb_strlen($query) < 3
        ? fn_t('patient_search_minimum')
        : ($patientMatches === [] ? fn_t('patient_search_empty') : ''));
$patientSuggestionClass = $patientMatches !== [] ? '' : 'd-none';
$patientTitleHtml = $selectedPatient
    ? fn_templateRender('dispensing_patient_title.html', [
        'patient_initials' => strtoupper(mb_substr((string) $selectedPatient['name_first'], 0, 1) . mb_substr((string) $selectedPatient['name_last'], 0, 1)),
        'patient_name' => trim($selectedPatient['name_first'] . ' ' . $selectedPatient['name_last']),
        'patient_code' => (string) $selectedPatient['patient_code'],
    ])
    : fn_templateRender('dispensing_no_patient_title.html');
$companyBrandHtml = $companyLogo !== ''
    ? fn_templateRender('dispensing_company_logo.html', [
        'company_logo_url' => $companyLogo, 'company_name' => (string) $companyIdentity['name'],
    ])
    : fn_templateRender('dispensing_company_monogram.html', [
        'company_monogram' => strtoupper(mb_substr((string) $companyIdentity['name'], 0, 1)),
    ]);
$patientEditButtonHtml = $selectedPatient
    ? fn_templateRender('dispensing_patient_edit_button.html', [
        'patient_code' => $selectedPatientCode,
        'patient_edit' => fn_t('patient_edit'),
    ])
    : '';
$addPatientButtonHtml = fn_templateRender('dispensing_add_patient_button.html', [
    'patient_add' => fn_t('patient_add'),
]);

page_start(fn_t('dispense_title'), $currentUser);
echo fn_templateRender('dispensing.html', [
    'dispense_title' => fn_t('dispense_title'),
    'dispense_language' => strtolower((string) ($_SESSION['language'] ?? 'NL')),
    'dispense_intro' => fn_t('dispense_intro'),
    'dispense_concept' => fn_t('dispense_concept'),
    'dispense_no_live_prescriptions' => fn_t('dispense_no_live_prescriptions'),
    'dispense_no_inventory' => fn_t('dispense_no_inventory'),
    'dispense_no_safety_data' => fn_t('dispense_no_safety_data'),
    'dispense_actions' => fn_t('dispense_actions'),
    'dispense_disabled_action' => fn_t('dispense_disabled_action'),
    'dispense_patient_search' => fn_t('dispense_patient_search'),
    'dispense_add' => fn_t('dispense_add'),
    'dispense_no_products' => fn_t('dispense_no_products'),
    'dispense_product_error' => fn_t('dispense_product_error'),
    'dispense_product_count' => fn_t('dispense_product_count'),
    'dispense_search' => fn_t('search'),
    'dispense_new_patient' => fn_t('patient_add'),
    'dispense_patient_matches' => fn_t('patient_search_results'),
    'dispense_patient_search_prompt' => fn_t('patient_search_prompt'),
    'dispense_patient_search_feedback' => $patientFeedback,
    'dispense_patient_search_minimum' => '3',
    'dispense_patient_search_regex' => fn_t('patient_search_regex'),
    'dispense_patient_lookup_unavailable' => fn_t('patient_lookup_unavailable'),
    'dispense_patient_no_results' => fn_t('patient_search_empty'),
    'dispense_patient' => fn_t('dispense_patient'),
    'dispense_select_patient' => fn_t('dispense_select_patient'),
    'dispense_edit_patient' => fn_t('patient_edit'),
    'dispense_product_search' => fn_t('dispense_product_search'),
    'dispense_product_help' => fn_t('dispense_product_help'),
    'dispense_patient_required' => fn_t('dispense_patient_required'),
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
    'dispense_product_endpoint' => fn_appBaseHref() . 'sam-products.php',
    'dispense_product_min_chars' => '3',
    'dispense_patient_min_chars' => '3',
    'dispense_patient_form_mode' => $editingPatientCode !== '' ? 'edit' : 'create',
    '_dispense_patient_search_feedback_html' => $patientFeedback !== ''
        ? fn_templateRender('dispensing_patient_feedback.html', ['patient_feedback' => $patientFeedback])
        : '',
    '_dispense_patient_count_html' => '',
    '_dispense_product_search_disabled' => $selectedPatient === null ? 'disabled' : '',
    '_dispense_patient_required_html' => $selectedPatient === null
        ? fn_templateRender('dispensing_patient_required.html', ['dispense_patient_required' => fn_t('dispense_patient_required')])
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
        : fn_templateRender('dispensing_no_patient_detail.html'),
    '_dispense_patient_title_html' => $patientTitleHtml,
    '_dispense_patient_edit_button_html' => $patientEditButtonHtml,
    '_dispense_brand_html' => $companyBrandHtml,
    '_dispense_alert_html' => $messageHtml,
    '_dispense_patient_add_open_html' => $addPatientButtonHtml,
]);
page_end();
