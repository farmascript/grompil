<?php
/**
 *
 * @date_cre: Wed 2026-10-14 22:45:51
 * @date_mut: Sat 2026-11-07 17:28:50
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages SaaS companies and their settings.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:companies:companies
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
$companiesQuery = static function (string $operation, string $sql, array $params = []): PgSql\Result|false {
    try {
        return db_query_params($operation, $sql, $params);
    } catch (GrompilDatabaseQueryException) {
        return false;
    }
};
require_once GROMPIL_ROOT . '/includes/company_admin.php';
$currentUser = grompil_require_module_access('companies.php');
$messageKey = '';
$languages = ['NL', 'FR', 'EN', 'DE'];
$publicEnabled = false;
$companyId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$numberStatusOptions = dd_varia_get_options('company_number_status', '_root');
$allNumberStatusOptions = dd_varia_get_options('company_number_status', '_root', true);
$activeNumberStatusKeys = array_column($numberStatusOptions, 'key');
$existingNumberStatuses = [];
if ($companyId > 0) {
    $statusResult = $companiesQuery(
        'companies.load_number_status',
        'SELECT enterprise_number_status, vat_number_status FROM bt_companies WHERE id = $1',
        [$companyId]
    );
    $statusRow = $statusResult ? pg_fetch_assoc($statusResult) : false;
    if (is_array($statusRow)) {
        $existingNumberStatuses = $statusRow;
    }
}
$initialAdminEmail = strtolower(trim((string) ($_POST['initial_admin_email'] ?? '')));
$fields = [
    'slug' => trim((string) ($_POST['slug'] ?? '')),
    'name' => trim((string) ($_POST['name'] ?? '')),
    'enterprise_number' => trim((string) ($_POST['enterprise_number'] ?? '')),
    'enterprise_number_status' => trim((string) ($_POST['enterprise_number_status'] ?? 'unknown')),
    'vat_number' => trim((string) ($_POST['vat_number'] ?? '')),
    'vat_number_status' => trim((string) ($_POST['vat_number_status'] ?? 'unknown')),
    'billing_email' => strtolower(trim((string) ($_POST['billing_email'] ?? ''))),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'street' => trim((string) ($_POST['street'] ?? '')),
    'house_number' => trim((string) ($_POST['house_number'] ?? '')),
    'box_number' => trim((string) ($_POST['box_number'] ?? '')),
    'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
    'city' => trim((string) ($_POST['city'] ?? '')),
    'address_reference_id' => trim((string) ($_POST['address_reference_id'] ?? '')),
    'country_code' => strtoupper(trim((string) ($_POST['country_code'] ?? 'BE'))),
    'default_language' => strtoupper(trim((string) ($_POST['default_language'] ?? 'NL'))),
    'store_theme' => trim((string) ($_POST['store_theme'] ?? 'default')),
    'store_logo_file' => trim((string) ($_POST['store_logo_file'] ?? '')),
    'store_hero_image_file' => trim((string) ($_POST['store_hero_image_file'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'toggle_public') {
        $companyId = (int) ($_POST['id'] ?? 0);
        $isPublic = ($_POST['is_public'] ?? '') === '1';
        $result = $companiesQuery(
            'companies.toggle_public',
            'UPDATE bt_companies SET is_public=$1, updated_at=now() WHERE id=$2',
            [$isPublic ? 'true' : 'false', $companyId]
        );
        $messageKey = $result && pg_affected_rows($result) === 1
            ? ($isPublic ? 'company_public_enabled' : 'company_public_disabled')
            : 'company_public_update_failed';
    } else {
        $companyId = (int) ($_POST['id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 'true' : 'false';
        $isPublic = isset($_POST['is_public']) ? 'true' : 'false';
        $publicEnabled = isset($_POST['is_public']);
        $enterpriseStatusValid = in_array($fields['enterprise_number_status'], $activeNumberStatusKeys, true)
            || ($companyId > 0 && $fields['enterprise_number_status'] === ($existingNumberStatuses['enterprise_number_status'] ?? null));
        $vatStatusValid = in_array($fields['vat_number_status'], $activeNumberStatusKeys, true)
            || ($companyId > 0 && $fields['vat_number_status'] === ($existingNumberStatuses['vat_number_status'] ?? null));
        $numberStatusesValid = $enterpriseStatusValid && $vatStatusValid
            && ($fields['enterprise_number_status'] !== 'known' || $fields['enterprise_number'] !== '')
            && ($fields['vat_number_status'] !== 'known' || $fields['vat_number'] !== '');
        $valid = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $fields['slug']) === 1
            && strlen($fields['slug']) <= 80
            && $fields['name'] !== ''
            && (function_exists('mb_strlen') ? mb_strlen($fields['name']) <= 160 : strlen($fields['name']) <= 640)
            && in_array($fields['default_language'], $languages, true)
            && preg_match('/^[A-Z]{2}$/', $fields['country_code']) === 1
            && ($fields['billing_email'] === '' || filter_var($fields['billing_email'], FILTER_VALIDATE_EMAIL))
            && ($companyId > 0 || filter_var($initialAdminEmail, FILTER_VALIDATE_EMAIL))
            && array_key_exists($fields['store_theme'], ['default' => true] + array_fill_keys(array_map(
                static fn (string $path): string => basename(dirname($path)),
                glob(GROMPIL_ROOT . '/tmpl/themes/*/store.html') ?: []
            ), true))
            && preg_match('/^(?:|[A-Za-z0-9][A-Za-z0-9._-]{0,126}\\.(?:jpe?g|png|webp|gif))$/i', $fields['store_logo_file']) === 1
            && preg_match('/^(?:|[A-Za-z0-9][A-Za-z0-9._-]{0,126}\\.(?:jpe?g|png|webp|gif))$/i', $fields['store_hero_image_file']) === 1;

        if (!$valid || !$numberStatusesValid) {
            $messageKey = $companyId === 0 && !filter_var($initialAdminEmail, FILTER_VALIDATE_EMAIL)
                ? 'company_admin_email_invalid'
                : (!$numberStatusesValid ? 'company_number_status_invalid' : 'company_invalid');
        } else {
            $values = array_values($fields);
            if ($companyId > 0) {
                $transaction = (bool) $companiesQuery('companies.update.begin', 'BEGIN');
                $result = $transaction ? $companiesQuery(
                    'companies.update',
                    'UPDATE bt_companies SET slug=$1, name=$2, enterprise_number=$3, enterprise_number_status=$4,
                        vat_number=$5, vat_number_status=$6, default_language=$7, store_theme=$8,
                        store_logo_file=$9, store_hero_image_file=$10, is_active=$11, is_public=$12,
                        updated_at=now() WHERE id=$13',
                    [
                        $fields['slug'], $fields['name'], $fields['enterprise_number'], $fields['enterprise_number_status'],
                        $fields['vat_number'], $fields['vat_number_status'], $fields['default_language'],
                        $fields['store_theme'], $fields['store_logo_file'], $fields['store_hero_image_file'],
                        $isActive, $isPublic, $companyId,
                    ]
                ) : false;
                if ($result) {
                    set_database_tenant_context($companyId, (string) $currentUser['user_code'], true);
                    $result = grompil_save_company_contact($companyId, 'email', 'billing', $fields['billing_email'])
                        && grompil_save_company_contact($companyId, 'phone', 'main', $fields['phone'])
                        && grompil_save_company_head_office($companyId, $fields);
                }
                $result = $result && (bool) $companiesQuery('companies.update.commit', 'COMMIT');
                if (!$result && $transaction) {
                    $companiesQuery('companies.update.rollback', 'ROLLBACK');
                }
            } else {
                $transaction = (bool) $companiesQuery('companies.create.begin', 'BEGIN');
                $insert = $transaction ? $companiesQuery(
                    'companies.create.insert',
                    'INSERT INTO bt_companies (
                        slug, name, enterprise_number, enterprise_number_status, vat_number, vat_number_status,
                        default_language, store_theme, store_logo_file, store_hero_image_file,
                        is_active, is_public
                    ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12) RETURNING id',
                    [
                        $fields['slug'], $fields['name'], $fields['enterprise_number'], $fields['enterprise_number_status'],
                        $fields['vat_number'], $fields['vat_number_status'], $fields['default_language'],
                        $fields['store_theme'], $fields['store_logo_file'], $fields['store_hero_image_file'],
                        $isActive, $isPublic,
                    ]
                ) : false;
                $createdCompany = $insert ? pg_fetch_assoc($insert) : false;
                $result = $createdCompany !== false;
                if ($result) {
                    set_database_tenant_context((int) $createdCompany['id'], (string) $currentUser['user_code'], true);
                    $result = grompil_save_company_contact((int) $createdCompany['id'], 'email', 'billing', $fields['billing_email'])
                        && grompil_save_company_contact((int) $createdCompany['id'], 'phone', 'main', $fields['phone'])
                        && grompil_save_company_head_office((int) $createdCompany['id'], $fields)
                        && seed_company_roles((int) $createdCompany['id'])
                        && create_initial_company_administrator(
                            (int) $createdCompany['id'],
                            $fields['name'],
                            $fields['slug'],
                            $initialAdminEmail,
                            $fields['default_language']
                        );
                }
                $result = $result && (bool) $companiesQuery('companies.create.commit', 'COMMIT');
                if (!$result && $transaction) {
                    $companiesQuery('companies.create.rollback', 'ROLLBACK');
                }
            }
            $messageKey = $result ? 'company_saved' : 'company_save_failed';
            if ($result) {
                $fields = array_fill_keys(array_keys($fields), '');
                $fields['country_code'] = 'BE';
                $fields['default_language'] = 'NL';
                $fields['enterprise_number_status'] = 'unknown';
                $fields['vat_number_status'] = 'unknown';
                $fields['store_theme'] = 'default';
                $publicEnabled = false;
                $initialAdminEmail = '';
            }
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $editResult = $companiesQuery('companies.load_edit', 'SELECT * FROM bt_companies WHERE id = $1', [(int) $_GET['edit']]);
    $editing = $editResult ? pg_fetch_assoc($editResult) : null;
    if ($editing) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $publicEnabled = $editing['is_public'] === 't';
        }
        foreach ($fields as $key => $_value) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                if (array_key_exists($key, $editing)) {
                    $fields[$key] = (string) $editing[$key];
                }
            }
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            set_database_tenant_context((int) $editing['id'], (string) $currentUser['user_code'], true);
            $contactResult = $companiesQuery(
                'companies.load_contacts',
                "SELECT contact_type_key, purpose_key, contact_value FROM dt_company_contacts
                 WHERE company_id = $1 AND is_active",
                [(int) $editing['id']]
            );
            while ($contactResult && $contact = pg_fetch_assoc($contactResult)) {
                if ($contact['contact_type_key'] === 'email' && $contact['purpose_key'] === 'billing') {
                    $fields['billing_email'] = (string) $contact['contact_value'];
                } elseif ($contact['contact_type_key'] === 'phone' && $contact['purpose_key'] === 'main') {
                    $fields['phone'] = (string) $contact['contact_value'];
                }
            }
            $fields = array_replace($fields, grompil_company_head_office((int) $editing['id']));
        }
    }
}

$companyResult = $companiesQuery(
    'companies.list',
    "SELECT c.*, location.postal_code, location.city, count(cu.user_code) AS users_count
     FROM bt_companies AS c
     LEFT JOIN LATERAL (
        SELECT postal_code, city FROM dt_company_locations
        WHERE company_id = c.id AND address_type_key = 'headquarters' AND is_active
        LIMIT 1
     ) AS location ON TRUE
     LEFT JOIN ct_company_users AS cu ON cu.company_id = c.id
     GROUP BY c.id, location.postal_code, location.city ORDER BY c.name"
);
$rows = '';
while ($companyResult && $company = pg_fetch_assoc($companyResult)) {
    $isPublic = $company['is_public'] === 't';
    $publicForm = '<form method="post" action="companies.php" class="d-inline">' . csrf_field()
        . '<input type="hidden" name="action" value="toggle_public"><input type="hidden" name="id" value="'
        . (int) $company['id'] . '"><input type="hidden" name="is_public" value="' . ($isPublic ? '0' : '1') . '">'
        . '<button class="btn btn-sm ' . ($isPublic ? 'btn-outline-secondary' : 'btn-outline-success')
        . '" type="submit">' . e(fn_t($isPublic ? 'company_public_hide' : 'company_public_show')) . '</button></form>';
    $rows .= fn_templateRender('companies_row.html', [
        'company_name' => (string) $company['name'],
        'company_search' => implode(' ', array_filter([
            (string) $company['name'],
            (string) $company['slug'],
            (string) $company['enterprise_number'],
            (string) $company['vat_number'],
            (string) $company['postal_code'],
            (string) $company['city'],
        ], static fn (string $value): bool => $value !== '')),
        'company_slug' => (string) $company['slug'],
        'company_location' => trim((string) $company['postal_code'] . ' ' . (string) $company['city']),
        'company_language' => (string) $company['default_language'],
        'company_users_count' => (string) $company['users_count'],
        'company_status' => fn_t($company['is_active'] === 't' ? 'active' : 'inactive'),
        'company_public_status' => fn_t($isPublic ? 'company_public_status_on' : 'company_public_status_off'),
        'company_id' => (string) $company['id'],
        'edit' => fn_t('edit'),
        'company_members_link' => fn_t('company_members_link'),
        '_company_public_form_html' => $publicForm,
    ]);
}

$renderNumberStatusOptions = static function (string $selected, ?string $existingStatus) use ($numberStatusOptions, $allNumberStatusOptions): string {
    $options = $numberStatusOptions;
    $activeOptionKeys = array_column($options, 'key');
    $isStoredStatus = $existingStatus !== null && $selected === $existingStatus;
    if ($isStoredStatus && !in_array($selected, $activeOptionKeys, true)) {
        foreach ($allNumberStatusOptions as $option) {
            if ((string) $option['key'] === $selected) {
                $options[] = $option;
                break;
            }
        }
    }

    $html = '';
    foreach ($options as $option) {
        $key = (string) $option['key'];
        $label = (string) $option['label'];
        if (!$option['is_active']) {
            $label .= ' · ' . fn_t('inactive');
        }
        $html .= '<option value="' . e($key) . '"' . ($key === $selected ? ' selected' : '')
            . '>' . e($label) . '</option>';
    }
    return $html;
};

page_start(fn_t('companies_heading'), $currentUser);
echo fn_templateRender('companies.html', [
    '_message_html' => $messageKey === '' ? '' : '<div class="alert alert-info" role="status">' . e(fn_t($messageKey)) . '</div>',
    'form_title' => fn_t($editing ? 'company_edit' : 'company_new'),
    'company_id' => (string) ($editing['id'] ?? ''),
    'slug_value' => (string) $fields['slug'],
    'name_value' => (string) $fields['name'],
    'enterprise_number_value' => (string) $fields['enterprise_number'],
    '_enterprise_number_status_options_html' => $renderNumberStatusOptions(
        (string) $fields['enterprise_number_status'],
        isset($existingNumberStatuses['enterprise_number_status']) ? (string) $existingNumberStatuses['enterprise_number_status'] : null
    ),
    'vat_number_value' => (string) $fields['vat_number'],
    '_vat_number_status_options_html' => $renderNumberStatusOptions(
        (string) $fields['vat_number_status'],
        isset($existingNumberStatuses['vat_number_status']) ? (string) $existingNumberStatuses['vat_number_status'] : null
    ),
    'billing_email_value' => (string) $fields['billing_email'],
    'phone_value' => (string) $fields['phone'],
    'street_value' => (string) $fields['street'],
    'house_number_value' => (string) $fields['house_number'],
    'box_number_value' => (string) $fields['box_number'],
    'postal_code_value' => (string) $fields['postal_code'],
    'city_value' => (string) $fields['city'],
    'address_reference_id_value' => (string) $fields['address_reference_id'],
    'country_code_value' => (string) $fields['country_code'],
    '_address_fields_html' => fn_templateRender('address_fields.html', [
        'address_prefix' => 'company-address',
        'address_lookup_flow_help' => fn_t('address_lookup_flow_help'),
        'address_lookup_no_results' => fn_t('address_lookup_no_results'),
        'address_postal_reminder' => fn_t('address_postal_reminder'),
        'address_country_code_value' => (string) $fields['country_code'],
        'address_reference_id_value' => (string) $fields['address_reference_id'],
        'street_reference_id_value' => '',
        'street_value' => (string) $fields['street'],
        'house_number_value' => (string) $fields['house_number'],
        'box_number_value' => (string) $fields['box_number'],
        'postal_code_value' => (string) $fields['postal_code'],
        'city_value' => (string) $fields['city'],
    ]),
    'default_language_value' => (string) $fields['default_language'],
    'store_theme_value' => (string) $fields['store_theme'],
    'store_logo_file_value' => (string) $fields['store_logo_file'],
    'store_hero_image_file_value' => (string) $fields['store_hero_image_file'],
    '_store_theme_options_html' => fn_companyStoreThemeOptions((string) $fields['store_theme']),
    '_initial_admin_field_html' => $companyId > 0 ? ''
        : '<div class="mb-3"><label class="form-label" for="initial-admin-email">' . e(fn_t('company_admin_email_label')) . '</label>'
            . '<input class="form-control" id="initial-admin-email" name="initial_admin_email" type="email" maxlength="254" required value="'
            . e($initialAdminEmail) . '"><div class="form-text">' . e(fn_t('company_admin_email_help')) . '</div></div>',
    '_active_checked' => !$editing || $editing['is_active'] === 't' ? 'checked' : '',
    '_public_checked' => $publicEnabled ? 'checked' : '',
    '_language_options_html' => implode('', array_map(
        static fn (string $language): string => '<option value="' . $language . '"'
            . ($fields['default_language'] === $language ? ' selected' : '') . '>' . $language . '</option>',
        $languages
    )),
    '_company_rows_html' => $rows,
    '_csrf_field_html' => csrf_field(),
]);
page_end();

