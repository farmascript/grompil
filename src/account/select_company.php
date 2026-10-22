<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Wed 2026-10-21 09:38:39
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Searches companies and supports platform administrators with selection and creation.
 *
 */

declare(strict_types=1);

if (!defined('GROMPIL_ROOT')) {
    http_response_code(404);
    $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($documentRoot !== '') {
        require rtrim($documentRoot, '/\\') . DIRECTORY_SEPARATOR . 'not_allowed.php';
    }
    exit;
}



require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/kbo.php';
require_once GROMPIL_ROOT . '/includes/company_admin.php';
$user = require_login();
$platformOperator = is_platform_operator($user);
$faggRegistryOnly = (string) ($_GET['fagg_registry'] ?? $_POST['fagg_registry'] ?? '') === '1';
$ajaxAction = (string) ($_GET['ajax'] ?? '');
if ($ajaxAction === 'pharmacy_search') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$platformOperator) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    $query = trim((string) ($_GET['pharmacy_q'] ?? ''));
    if ((function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) < 2) {
        echo json_encode(['items' => []]);
        exit;
    }

    $pattern = '%' . $query . '%';
    $matches = pg_query_params(
        db(),
        'SELECT "Vergunningsnummer", "Naam", "Adres", "Postcode", "Gemeente"
         FROM pharmacies
         WHERE "Naam" ILIKE $1 OR "Adres" ILIKE $1 OR "Postcode" ILIKE $1 OR "Gemeente" ILIKE $1
            OR "Vergunninghouder" ILIKE $1 OR "Vergunningsnummer" ILIKE $1
         ORDER BY "Naam", "Gemeente"
         LIMIT 10',
        [$pattern]
    );
    if (!$matches) {
        http_response_code(500);
        echo json_encode(['error' => 'search_failed']);
        exit;
    }

    $items = [];
    while ($row = pg_fetch_assoc($matches)) {
        $items[] = [
            'permit' => (string) $row['Vergunningsnummer'],
            'name' => (string) $row['Naam'],
            'address' => trim((string) $row['Adres']),
            'location' => trim((string) $row['Postcode'] . ' ' . (string) $row['Gemeente']),
        ];
    }
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$message = '';
$selected = null;
$kboDetails = null;
$generalKboResults = [];
$search = $faggRegistryOnly
    ? trim((string) ($_GET['pharmacy_q'] ?? $_POST['pharmacy_q'] ?? ''))
    : trim((string) ($_GET['company_q'] ?? $_POST['company_q'] ?? $_GET['q'] ?? $_POST['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 20;
$offset = ($page - 1) * $pageSize;
$selectedPermit = $faggRegistryOnly ? trim((string) ($_GET['pharmacy'] ?? $_POST['pharmacy'] ?? '')) : '';
$selectedEnterpriseNumber = !$faggRegistryOnly
    ? (string) preg_replace('/\D+/', '', (string) ($_GET['enterprise_number'] ?? $_POST['enterprise_number'] ?? ''))
    : '';

function company_registry_entry_by_permit(string $permit): ?array
{
    $result = pg_query_params(
        db(),
        'SELECT "Vergunningsnummer", "Naam", "Adres", "Postcode", "Gemeente", "Status", "Vergunninghouder", "Uitbater"
         FROM pharmacies WHERE "Vergunningsnummer" = $1',
        [$permit]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ?: null;
}

function company_registry_enterprise_number(array $registryEntry): string
{
    if (preg_match('/KBO\s*([0-9][0-9.\s-]{8,14})/iu', (string) ($registryEntry['Vergunninghouder'] ?? ''), $match)) {
        return preg_replace('/\D+/', '', $match[1]) ?? '';
    }
    return '';
}

if ($platformOperator && $faggRegistryOnly && $selectedPermit !== '') {
    $selected = company_registry_entry_by_permit($selectedPermit);
    if (!$selected) {
        $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_registry_not_found')) . '</div>';
        $selectedPermit = '';
    } else {
        $kboNumber = company_registry_enterprise_number($selected);
        $cached = $_SESSION['pharmacy_kbo_lookup'] ?? null;
        if (is_array($cached) && ($cached['permit'] ?? '') === $selectedPermit
            && ($cached['number'] ?? '') === $kboNumber) {
            $kboDetails = $cached['details'] ?? null;
        }
    }
}

if ($platformOperator && !$faggRegistryOnly && $selectedEnterpriseNumber !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $cached = $_SESSION['company_kbo_lookups'][$selectedEnterpriseNumber] ?? null;
    if (is_array($cached) && is_array($cached['details'] ?? null)) {
        $kboDetails = $cached['details'];
    } else {
        try {
            $kboDetails = grompil_kbo_lookup($selectedEnterpriseNumber);
            $_SESSION['company_kbo_lookups'][$selectedEnterpriseNumber] = ['details' => $kboDetails];
        } catch (Throwable $exception) {
            error_log('Grompil KBO lookup failed (' . get_class($exception) . '): ' . $exception->getMessage());
            $messageKey = match ($exception->getMessage()) {
                'kbo_weekly_limit_reached' => 'company_kbo_weekly_limit',
                'kbo_service_quota_exceeded' => 'company_kbo_service_quota_exceeded',
                'kbo_terms_not_accepted' => 'company_kbo_terms_not_accepted',
                'kbo_authentication_failed' => 'company_kbo_authentication_failed',
                'kbo_not_configured' => 'company_kbo_not_configured',
                'kbo_service_unavailable' => 'company_kbo_service_unavailable',
                default => str_starts_with($exception->getMessage(), 'kbo_transport_error:')
                    || str_starts_with($exception->getMessage(), 'kbo_http_error:')
                    ? 'company_kbo_service_unavailable'
                    : 'company_kbo_failed',
            };
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t($messageKey)) . '</div>';
            $selectedEnterpriseNumber = '';
        }
    }
    if (is_array($kboDetails)) {
        $canonicalNumber = (string) preg_replace('/\D+/', '', (string) ($kboDetails['number'] ?? $selectedEnterpriseNumber));
        if (preg_match('/^(?:0|1)?\d{9}$/', $canonicalNumber)) {
            $_SESSION['company_kbo_lookups'][$canonicalNumber] = ['details' => $kboDetails];
            $selectedEnterpriseNumber = $canonicalNumber;
        }
    }
} elseif ($platformOperator && !$faggRegistryOnly && $search !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $digits = (string) preg_replace('/\D+/', '', $search);
    if (preg_match('/^(?:0|1)?\d{9}$/', $digits)) {
        $selectedEnterpriseNumber = $digits;
        redirect('select-company.php?company_q=' . rawurlencode($search) . '&enterprise_number=' . rawurlencode($digits));
    }
    $cachedSearch = $_SESSION['company_kbo_searches_v2'][$search] ?? null;
    if (is_array($cachedSearch) && is_array($cachedSearch['results'] ?? null)) {
        $generalKboResults = $cachedSearch['results'];
    } else {
        try {
            $generalKboResults = grompil_kbo_search($search);
            $_SESSION['company_kbo_searches_v2'][$search] = ['results' => $generalKboResults];
        } catch (Throwable $exception) {
            error_log('Grompil KBO search failed (' . get_class($exception) . '): ' . $exception->getMessage());
            $messageKey = match ($exception->getMessage()) {
                'kbo_weekly_limit_reached' => 'company_kbo_weekly_limit',
                'kbo_service_quota_exceeded' => 'company_kbo_service_quota_exceeded',
                'kbo_terms_not_accepted' => 'company_kbo_terms_not_accepted',
                'kbo_authentication_failed' => 'company_kbo_authentication_failed',
                'kbo_not_configured' => 'company_kbo_not_configured',
                'kbo_search_query_invalid' => 'company_kbo_search_query_invalid',
                default => str_starts_with($exception->getMessage(), 'kbo_transport_error:')
                    || str_starts_with($exception->getMessage(), 'kbo_http_error:')
                    ? 'company_kbo_service_unavailable'
                    : 'company_kbo_failed',
            };
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t($messageKey)) . '</div>';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['company_id'])) {
        if (set_active_company((int) $_POST['company_id'], $user)) {
            redirect('dashboard.php');
        }
        http_response_code(403);
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('access_denied')) . '</div>';
    } elseif (!$platformOperator) {
        http_response_code(403);
        $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('access_denied')) . '</div>';
    } elseif ((string) ($_POST['action'] ?? '') === 'create_company' && !$faggRegistryOnly) {
        $number = (string) preg_replace('/\D+/', '', (string) ($_POST['enterprise_number'] ?? ''));
        $cached = $_SESSION['company_kbo_lookups'][$number] ?? null;
        $details = is_array($cached) && is_array($cached['details'] ?? null) ? $cached['details'] : null;
        $kboDetails = $details;
        $canonicalNumber = $details !== null ? (string) preg_replace('/\D+/', '', (string) ($details['number'] ?? '')) : '';
        if (!preg_match('/^(?:0|1)?\d{9}$/', $number) || $details === null
            || !preg_match('/^(?:0|1)?\d{9}$/', $canonicalNumber) || ltrim($canonicalNumber, '0') !== ltrim($number, '0')) {
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_kbo_lookup_first')) . '</div>';
        } else {
            $number = $canonicalNumber;
            $initialAdminEmail = strtolower(trim((string) ($_POST['initial_admin_email'] ?? ($details['email'] ?? ''))));
            if (!filter_var($initialAdminEmail, FILTER_VALIDATE_EMAIL)) {
                $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_admin_email_invalid')) . '</div>';
            } else {
                $existingResult = pg_query_params(
                    db(),
                    'SELECT id FROM companies WHERE enterprise_number = $1 AND company_permit_number = \'\' ORDER BY id LIMIT 1',
                    [$number]
                );
                $existing = $existingResult ? pg_fetch_assoc($existingResult) : false;
                if ($existing) {
                    if (set_active_company((int) $existing['id'], $user)) {
                        redirect('dashboard.php');
                    }
                    $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_already_created')) . '</div>';
                } else {
                    $companyName = trim((string) ($details['name'] ?? ''));
                    $slugBase = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $companyName) ?: 'company');
                    $slugBase = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slugBase), '-');
                    $suffix = substr($number, -10);
                    $slugBase = substr($slugBase !== '' ? $slugBase : 'company', 0, max(1, 78 - strlen($suffix)));
                    $slug = $slugBase . '-' . $suffix;
                    $street = trim((string) ($details['street'] ?? ''));
                    $houseNumber = trim((string) ($details['house_number'] ?? ''));
                    $begun = (bool) pg_query(db(), 'BEGIN');
                    $insert = $begun ? pg_query_params(
                        db(),
                        'INSERT INTO companies (
                            slug, name, legal_name, enterprise_number, billing_email, phone, street, house_number,
                            postal_code, city, country_code, default_language, is_active, is_public, company_permit_number
                         ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,\'BE\',\'NL\',TRUE,FALSE,\'\') RETURNING id',
                        [$slug, $companyName, $companyName, $number, (string) ($details['email'] ?? ''),
                            (string) ($details['phone'] ?? ''), $street, $houseNumber,
                            (string) ($details['postal_code'] ?? ''), (string) ($details['city'] ?? '')]
                    ) : false;
                    $created = $insert ? pg_fetch_assoc($insert) : false;
                    $saved = $created !== false;
                    if ($saved) {
                        set_database_tenant_context((int) $created['id'], (int) $user['id'], true);
                        $saved = seed_company_roles((int) $created['id'])
                            && create_initial_company_administrator(
                                (int) $created['id'],
                                $companyName,
                                $slug,
                                $initialAdminEmail,
                                'NL'
                            );
                    }
                    $saved = $saved && (bool) pg_query(db(), 'COMMIT');
                    if (!$saved && $begun) {
                        pg_query(db(), 'ROLLBACK');
                    }
                    if ($saved && set_active_company((int) $created['id'], $user)) {
                        unset($_SESSION['company_kbo_lookups'][$number]);
                        redirect('dashboard.php');
                    }
                    $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('company_create_failed')) . '</div>';
                }
            }
        }
    } elseif ($selected === null) {
        $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_registry_not_found')) . '</div>';
    } elseif ((string) ($_POST['action'] ?? '') === 'lookup_kbo') {
        $number = company_registry_enterprise_number($selected);
        try {
            if ($number === '') {
                throw new RuntimeException('company_kbo_number_missing');
            }
            $kboDetails = grompil_kbo_lookup($number);
            $_SESSION['pharmacy_kbo_lookup'] = [
                'permit' => $selectedPermit,
                'number' => $number,
                'details' => $kboDetails,
            ];
        } catch (Throwable $exception) {
            error_log('Grompil KBO lookup failed (' . get_class($exception) . '): ' . $exception->getMessage());
            $messageKey = match ($exception->getMessage()) {
                'company_kbo_number_missing' => 'company_kbo_number_missing',
                'kbo_weekly_limit_reached' => 'company_kbo_weekly_limit',
                'kbo_service_quota_exceeded' => 'company_kbo_service_quota_exceeded',
                'kbo_terms_not_accepted' => 'company_kbo_terms_not_accepted',
                'kbo_authentication_failed' => 'company_kbo_authentication_failed',
                'kbo_not_configured' => 'company_kbo_not_configured',
                'kbo_service_unavailable' => 'company_kbo_service_unavailable',
                default => str_starts_with($exception->getMessage(), 'kbo_transport_error:')
                        || str_starts_with($exception->getMessage(), 'kbo_http_error:')
                        ? 'company_kbo_service_unavailable'
                        : 'company_kbo_failed',
            };
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t($messageKey)) . '</div>';
        }
    } elseif ((string) ($_POST['action'] ?? '') === 'create_company') {
        $number = company_registry_enterprise_number($selected);
        $cached = $_SESSION['pharmacy_kbo_lookup'] ?? null;
        if ($number === '' || !is_array($cached) || ($cached['permit'] ?? '') !== $selectedPermit
            || ($cached['number'] ?? '') !== $number || !is_array($cached['details'] ?? null)) {
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_kbo_lookup_first')) . '</div>';
        } elseif (!filter_var(strtolower(trim((string) ($_POST['initial_admin_email'] ?? ($cached['details']['email'] ?? '')))), FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_admin_email_invalid')) . '</div>';
        } else {
            $initialAdminEmail = strtolower(trim((string) ($_POST['initial_admin_email'] ?? ($cached['details']['email'] ?? ''))));
                $existingPermit = pg_query_params(db(), 'SELECT id FROM companies WHERE company_permit_number = $1', [$selectedPermit]);
            $existing = $existingPermit ? pg_fetch_assoc($existingPermit) : false;
            if ($existing) {
                if (set_active_company((int) $existing['id'], $user)) {
                    redirect('dashboard.php');
                }
                $message = '<div class="alert alert-warning" role="alert">' . e(fn_t('company_already_created')) . '</div>';
            } else {
                $companyName = trim((string) $selected['Naam']);
                $slugBase = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $companyName) ?: 'company');
                $slugBase = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slugBase), '-');
                $permitSuffix = preg_replace('/\D+/', '', $selectedPermit) ?: substr(hash('sha256', $selectedPermit), 0, 10);
                $slugBase = substr($slugBase !== '' ? $slugBase : 'company', 0, max(1, 78 - strlen($permitSuffix)));
                $slug = $slugBase . '-' . $permitSuffix;
                $address = trim((string) $selected['Adres']);
                $street = $address;
                $houseNumber = '';
                if (preg_match('/^(.+?)\s+(\d+[a-zA-Z]?(?:\s*[-\/]\s*\w+)?)$/u', $address, $addressMatch)) {
                    $street = trim($addressMatch[1]);
                    $houseNumber = trim($addressMatch[2]);
                }
                $details = $cached['details'];
                $begun = (bool) pg_query(db(), 'BEGIN');
                $insert = $begun ? pg_query_params(
                    db(),
                    'INSERT INTO companies (
                        slug, name, legal_name, enterprise_number, billing_email, phone, street, house_number,
                        postal_code, city, country_code, default_language, is_active, is_public, company_permit_number
                     ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,\'BE\',\'NL\',TRUE,FALSE,$11) RETURNING id',
                    [$slug, $companyName, (string) ($details['name'] ?? ''), $number,
                        (string) ($details['email'] ?? ''), (string) ($details['phone'] ?? ''),
                        $street, $houseNumber, trim((string) $selected['Postcode']), trim((string) $selected['Gemeente']), $selectedPermit]
                ) : false;
                $created = $insert ? pg_fetch_assoc($insert) : false;
                $saved = $created !== false;
                if ($saved) {
                    set_database_tenant_context((int) $created['id'], (int) $user['id'], true);
                    $saved = seed_company_roles((int) $created['id'])
                        && create_initial_company_administrator(
                            (int) $created['id'],
                            $companyName,
                            $slug,
                            $initialAdminEmail,
                            'NL'
                        );
                }
                $saved = $saved && (bool) pg_query(db(), 'COMMIT');
                if (!$saved && $begun) {
                    pg_query(db(), 'ROLLBACK');
                }
                if ($saved && set_active_company((int) $created['id'], $user)) {
                    unset($_SESSION['pharmacy_kbo_lookup']);
                    redirect('dashboard.php');
                }
                $message = '<div class="alert alert-danger" role="alert">' . e(fn_t('company_create_failed')) . '</div>';
            }
        }
    }
    if ($selected !== null && $kboDetails === null) {
        $cached = $_SESSION['pharmacy_kbo_lookup'] ?? null;
        if (is_array($cached) && ($cached['permit'] ?? '') === $selectedPermit) {
            $kboDetails = $cached['details'] ?? null;
        }
    }
}

$companies = available_companies($user);
$activeCompanyId = (int) ($_SESSION['active_company_id'] ?? 0);
$activeCompanyName = '';
if ($activeCompanyId > 0) {
    $activeCompanyResult = pg_query_params(db(), 'SELECT name FROM companies WHERE id = $1', [$activeCompanyId]);
    $activeCompanyRow = $activeCompanyResult ? pg_fetch_assoc($activeCompanyResult) : false;
    $activeCompanyName = (string) ($activeCompanyRow['name'] ?? '');
}
$language = strtoupper((string) ($_SESSION['language'] ?? 'NL'));
$companySwitchWarnings = [
    'NL' => 'De actieve company wordt gedeeld door alle tabbladen in deze browser. Als je doorgaat, verandert de company-context ook in je andere tabbladen. Wil je toch wisselen?',
    'FR' => 'La société active est partagée par tous les onglets de ce navigateur. Si vous continuez, le contexte changera également dans vos autres onglets. Voulez-vous continuer ?',
    'EN' => 'The active company is shared by all tabs in this browser. Continuing also changes the company context in your other tabs. Do you want to continue?',
    'DE' => 'Das aktive Unternehmen wird von allen Tabs in diesem Browser gemeinsam genutzt. Wenn Sie fortfahren, ändert sich der Unternehmenskontext auch in Ihren anderen Tabs. Möchten Sie fortfahren?',
];
$companySwitchWarning = $companySwitchWarnings[$language] ?? $companySwitchWarnings['NL'];
$companyContextNotices = [
    'NL' => ['Actieve company: ', '. Alle tabbladen in deze browser delen deze sessie. Een companywissel verandert de context in alle tabs.'],
    'FR' => ['Société active : ', '. Tous les onglets de ce navigateur partagent cette session. Changer de société modifie le contexte dans tous les onglets.'],
    'EN' => ['Active company: ', '. All tabs in this browser share this session. Switching companies changes the context in every tab.'],
    'DE' => ['Aktives Unternehmen: ', '. Alle Tabs in diesem Browser verwenden dieselbe Sitzung. Ein Unternehmenswechsel ändert den Kontext in allen Tabs.'],
];
$companyContextNotice = $companyContextNotices[$language] ?? $companyContextNotices['NL'];
$choices = '';
foreach ($companies as $company) {
    $choices .= fn_templateRender('company_choice.html', [
        'company_name' => (string) $company['name'],
        'company_slug' => (string) $company['slug'],
        'company_search' => (string) $company['name'] . ' ' . (string) $company['slug'],
        'company_id' => (string) $company['id'],
        'active_company_id' => (string) $activeCompanyId,
        'company_switch_warning' => $companySwitchWarning,
        '_csrf_field_html' => csrf_field(),
    ]);
}
$companyPicker = '';
if (count($companies) > 1) {
    $noCompanyResults = [
        'NL' => 'Geen resultaten',
        'FR' => 'Aucun résultat',
        'EN' => 'No results',
        'DE' => 'Keine Ergebnisse',
    ][$language] ?? 'No results';
    $companyPicker = '<div class="mb-3" data-company-picker>'
        . '<label class="form-label" for="company-picker-search">' . e(fn_t('tenant_select_title')) . '</label>'
        . '<input class="form-control" id="company-picker-search" type="search" autocomplete="off"'
        . ' aria-autocomplete="list" aria-controls="company-picker-results" aria-expanded="false"'
        . ' placeholder="' . e(fn_t('company_name_label')) . '" data-no-results="' . e($noCompanyResults) . '">'
        . '<div class="mt-2" id="company-picker-results" role="listbox" hidden></div>'
        . '</div>';
}

$directoryHtml = '';
if ($platformOperator) {
    $quotaUsage = grompil_kbo_weekly_usage();
    $registryTitle = $faggRegistryOnly ? fn_t('company_registry_fagg_title') : fn_t('company_registry_title');
    $directoryHtml = '<section class="mt-5"><h2 class="h4">' . e($registryTitle) . '</h2>'
        . '<div class="alert alert-info" role="status"><strong>' . e(fn_t('company_kbo_quota_label')) . ':</strong> '
        . e((string) $quotaUsage) . ' / 100 · ' . e(fn_t('company_kbo_quota_period')) . '</div>'
        . '<form method="get" action="select-company.php" class="mb-3">'
        . '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="fagg-registry-only" name="fagg_registry" value="1"'
        . ($faggRegistryOnly ? ' checked' : '') . '><label class="form-check-label" for="fagg-registry-only">' . e(fn_t('company_registry_fagg_only')) . '</label></div>'
        . '<p class="text-secondary">' . e($faggRegistryOnly ? fn_t('company_registry_fagg_help') : fn_t('company_registry_help')) . '</p>'
        . '<div class="row g-2"><div class="col"><label class="visually-hidden" for="registry-search">' . e(fn_t('company_registry_search_label')) . '</label>'
        . '<input class="form-control" id="' . ($faggRegistryOnly ? 'pharmacy-search' : 'registry-search') . '" name="'
        . ($faggRegistryOnly ? 'pharmacy_q' : 'company_q') . '" type="search" autocomplete="off" value="' . e($search)
        . '" placeholder="' . e(fn_t($faggRegistryOnly ? 'company_registry_fagg_search_placeholder' : 'company_registry_search_placeholder')) . '"'
        . ($faggRegistryOnly ? ' aria-autocomplete="list" aria-controls="pharmacy-search-results" aria-expanded="false"' : '')
        . '>' . ($faggRegistryOnly ? '<div class="list-group mt-1" id="pharmacy-search-results" role="listbox" hidden></div>' : '')
        . '</div><div class="col-auto"><button class="btn btn-primary" type="submit">' . e(fn_t('company_registry_search_button')) . '</button></div></div>'
        . '</form>';

    if ($faggRegistryOnly) {
        $where = '';
        $params = [];
        if ($search !== '') {
            $params[] = '%' . $search . '%';
            $where = ' WHERE "Naam" ILIKE $1 OR "Adres" ILIKE $1 OR "Postcode" ILIKE $1 OR "Gemeente" ILIKE $1 OR "Vergunninghouder" ILIKE $1 OR "Vergunningsnummer" ILIKE $1';
        }
        $countResult = pg_query_params(db(), 'SELECT count(*) AS total FROM pharmacies' . $where, $params);
        $total = $countResult ? (int) pg_fetch_result($countResult, 0, 'total') : 0;
        $limitParameter = count($params) + 1;
        $offsetParameter = count($params) + 2;
        $result = pg_query_params(
            db(),
            'SELECT "Vergunningsnummer", "Naam", "Adres", "Postcode", "Gemeente", "Vergunninghouder"
             FROM pharmacies' . $where . ' ORDER BY "Naam", "Gemeente" LIMIT $' . $limitParameter . ' OFFSET $' . $offsetParameter,
            [...$params, $pageSize, $offset]
        );
        if (!$countResult || !$result) {
            $directoryHtml .= '<div class="alert alert-danger" role="alert">' . e(fn_t('company_registry_unavailable')) . '</div>';
        }
        $items = '';
        while ($result && $row = pg_fetch_assoc($result)) {
            $permit = (string) $row['Vergunningsnummer'];
            $items .= '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3" href="select-company.php?fagg_registry=1&amp;pharmacy='
                . rawurlencode($permit) . '&amp;pharmacy_q=' . rawurlencode($search) . '&amp;page=' . $page . '"><span><strong>'
                . e((string) $row['Naam']) . '</strong><br><span class="small text-secondary">'
                . e(trim((string) $row['Postcode'] . ' ' . (string) $row['Gemeente'])) . ' · ' . e((string) $row['Adres'])
                . '</span></span><span class="badge text-bg-light">' . e(company_registry_enterprise_number($row)) . '</span></a>';
        }
        $pages = max(1, (int) ceil($total / $pageSize));
        $pagination = '<div class="d-flex justify-content-between align-items-center mt-3"><span class="small text-secondary">'
            . e(sprintf(fn_t('company_registry_result_count'), (string) $total, (string) $page, (string) $pages)) . '</span><nav aria-label="'
            . e(fn_t('company_registry_pagination')) . '"><ul class="pagination pagination-sm mb-0">';
        foreach (['previous' => $page - 1, 'next' => $page + 1] as $direction => $targetPage) {
            $disabled = $targetPage < 1 || $targetPage > $pages;
            $label = fn_t($direction === 'previous' ? 'company_registry_previous' : 'company_registry_next');
            $url = 'select-company.php?fagg_registry=1&amp;pharmacy_q=' . rawurlencode($search) . '&amp;page=' . $targetPage;
            $pagination .= '<li class="page-item' . ($disabled ? ' disabled' : '') . '"><a class="page-link" href="'
                . ($disabled ? '#' : $url) . '">' . e($label) . '</a></li>';
        }
        $pagination .= '</ul></nav></div>';
        if ($selected !== null) {
            $kboNumber = company_registry_enterprise_number($selected);
            $directoryHtml .= '<div class="card card-body mb-3"><h3 class="h5">' . e((string) $selected['Naam']) . '</h3><p class="mb-1">'
                . e(trim((string) $selected['Adres'] . ', ' . (string) $selected['Postcode'] . ' ' . (string) $selected['Gemeente']))
                . '</p><p class="small text-secondary">' . e(fn_t('pharmacy_permit')) . ': ' . e((string) $selected['Vergunningsnummer'])
                . ' · KBO: ' . e($kboNumber !== '' ? $kboNumber : fn_t('company_kbo_unavailable')) . '</p>';
            if (is_array($kboDetails)) {
                $initialAdminEmail = strtolower(trim((string) ($_POST['initial_admin_email'] ?? ($kboDetails['email'] ?? ''))));
                $directoryHtml .= '<div class="border-top pt-3"><h4 class="h6">' . e(fn_t('company_kbo_details')) . '</h4><dl class="row mb-3">'
                    . '<dt class="col-sm-3">' . e(fn_t('company_name_label')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['name'] ?? '')) . '</dd>'
                    . '<dt class="col-sm-3">' . e(fn_t('company_kbo_status')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['status'] ?? '')) . '</dd>'
                    . '<dt class="col-sm-3">' . e(fn_t('company_kbo_address')) . '</dt><dd class="col-sm-9">' . e(trim((string) ($kboDetails['street'] ?? '') . ' ' . (string) ($kboDetails['house_number'] ?? '') . ', ' . (string) ($kboDetails['postal_code'] ?? '') . ' ' . (string) ($kboDetails['city'] ?? ''), ', ')) . '</dd>'
                    . '<dt class="col-sm-3">' . e(fn_t('company_phone')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['phone'] ?? '')) . '</dd>'
                    . '<dt class="col-sm-3">' . e(fn_t('company_billing_email')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['email'] ?? '')) . '</dd></dl>'
                    . '<form method="post" action="select-company.php?fagg_registry=1&amp;pharmacy=' . rawurlencode($selectedPermit) . '&amp;pharmacy_q=' . rawurlencode($search) . '">'
                    . csrf_field() . '<input type="hidden" name="fagg_registry" value="1"><input type="hidden" name="pharmacy" value="' . e($selectedPermit) . '"><input type="hidden" name="pharmacy_q" value="' . e($search)
                    . '"><div class="mb-3"><label class="form-label" for="initial-admin-email">' . e(fn_t('company_admin_email_label')) . '</label>'
                    . '<input class="form-control" id="initial-admin-email" name="initial_admin_email" type="email" maxlength="254" required value="' . e($initialAdminEmail) . '">'
                    . '<div class="form-text">' . e(fn_t('company_admin_email_help')) . '</div></div>'
                    . '<button class="btn btn-success" name="action" value="create_company">' . e(fn_t('company_create_button')) . '</button></form></div>';
            } else {
                $directoryHtml .= '<form method="post" action="select-company.php?fagg_registry=1&amp;pharmacy=' . rawurlencode($selectedPermit) . '&amp;pharmacy_q=' . rawurlencode($search) . '">'
                    . csrf_field() . '<input type="hidden" name="fagg_registry" value="1"><input type="hidden" name="pharmacy" value="' . e($selectedPermit) . '"><input type="hidden" name="pharmacy_q" value="' . e($search)
                    . '"><button class="btn btn-outline-primary" name="action" value="lookup_kbo">' . e(fn_t('company_kbo_lookup')) . '</button></form>';
            }
            $directoryHtml .= '</div>';
        }
        $directoryHtml .= '<div class="list-group">' . ($items !== '' ? $items : '<div class="alert alert-info mb-0">' . e(fn_t('company_registry_no_results')) . '</div>')
            . '</div>' . $pagination;
    } else {
        $items = '';
        foreach (array_slice($generalKboResults, 0, 400) as $result) {
            $number = (string) ($result['number'] ?? '');
            $name = (string) ($result['name'] ?? '');
            if ($number === '' || $name === '') {
                continue;
            }
            $address = trim((string) ($result['street'] ?? '') . ' ' . (string) ($result['house_number'] ?? '') . ', '
                . (string) ($result['postal_code'] ?? '') . ' ' . (string) ($result['city'] ?? ''), ' ,');
            $url = 'select-company.php?company_q=' . rawurlencode($search) . '&amp;enterprise_number=' . rawurlencode($number);
            $items .= '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-3" href="' . $url . '"><span><strong>'
                . e($name) . '</strong><br><span class="small text-secondary">' . e($address) . '</span></span><span class="badge text-bg-light">'
                . e($number) . '</span>'
                . ((string) ($result['establishment_number'] ?? '') !== ''
                    ? '<span class="d-block small text-secondary">' . e(fn_t('company_kbo_establishment_unit')) . ': '
                        . e((string) $result['establishment_number']) . '</span>'
                    : '')
                . '</a>';
        }
        if ($search !== '') {
            $directoryHtml .= '<div class="list-group mb-3">' . ($items !== '' ? $items : '<div class="alert alert-info mb-0">' . e(fn_t('company_registry_no_results')) . '</div>') . '</div>';
        }
        if ($kboDetails !== null && $selectedEnterpriseNumber !== '') {
            $initialAdminEmail = strtolower(trim((string) ($_POST['initial_admin_email'] ?? ($kboDetails['email'] ?? ''))));
            $directoryHtml .= '<div class="card card-body"><h3 class="h5">' . e((string) ($kboDetails['name'] ?? '')) . '</h3><p class="mb-1">'
                . e(trim((string) ($kboDetails['street'] ?? '') . ' ' . (string) ($kboDetails['house_number'] ?? '') . ', '
                    . (string) ($kboDetails['postal_code'] ?? '') . ' ' . (string) ($kboDetails['city'] ?? ''), ' ,')) . '</p><p class="small text-secondary">'
                . e(fn_t('company_enterprise_number')) . ': ' . e($selectedEnterpriseNumber) . '</p><dl class="row mb-3">'
                . '<dt class="col-sm-3">' . e(fn_t('company_kbo_status')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['status'] ?? '')) . '</dd>'
                . '<dt class="col-sm-3">' . e(fn_t('company_phone')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['phone'] ?? '')) . '</dd>'
                . '<dt class="col-sm-3">' . e(fn_t('company_billing_email')) . '</dt><dd class="col-sm-9">' . e((string) ($kboDetails['email'] ?? '')) . '</dd></dl>'
                . '<form method="post" action="select-company.php"><input type="hidden" name="fagg_registry" value="0">'
                . '<input type="hidden" name="company_q" value="' . e($search) . '"><input type="hidden" name="enterprise_number" value="' . e($selectedEnterpriseNumber) . '">'
                . csrf_field() . '<div class="mb-3"><label class="form-label" for="initial-admin-email">' . e(fn_t('company_admin_email_label')) . '</label>'
                . '<input class="form-control" id="initial-admin-email" name="initial_admin_email" type="email" maxlength="254" required value="' . e($initialAdminEmail) . '">'
                . '<div class="form-text">' . e(fn_t('company_admin_email_help')) . '</div></div><button class="btn btn-success" name="action" value="create_company">'
                . e(fn_t('company_create_button')) . '</button></form></div>';
        }
    }
    $directoryHtml .= '</section>';
}

page_start(fn_t('tenant_select_title'), $user);
echo fn_templateRender('select_company.html', [
    '_message_html' => $message,
    '_company_choices_html' => $choices,
    '_company_picker_html' => $companyPicker,
    '_company_context_notice_html' => $activeCompanyName !== ''
        ? '<div class="alert alert-info" role="status">' . e($companyContextNotice[0]) . '<strong>' . e($activeCompanyName)
            . '</strong>' . e($companyContextNotice[1]) . '</div>'
        : '',
    '_directory_html' => $directoryHtml,
    '_empty_notice_html' => $companies === []
        ? '<p class="alert alert-info" role="status">' . e(fn_t('tenant_none_available')) . '</p>'
        : '',
]);
page_end();

