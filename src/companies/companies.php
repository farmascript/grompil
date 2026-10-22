<?php
/**
 *
 * @date_cre: Wed 2026-10-14 22:45:51
 * @date_mut: Wed 2026-10-21 09:38:39
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages SaaS companies and their settings.
 *
 */

declare(strict_types=1);



require GROMPIL_ROOT . '/includes/bootstrap.php';
require_once GROMPIL_ROOT . '/includes/company_admin.php';
$currentUser = require_permission('companies.manage');
$currentUser = require_level_range(1, 100, $currentUser);
$messageKey = '';
$languages = ['NL', 'FR', 'EN', 'DE'];
$publicEnabled = false;
$companyId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$initialAdminEmail = strtolower(trim((string) ($_POST['initial_admin_email'] ?? '')));
$fields = [
    'slug' => trim((string) ($_POST['slug'] ?? '')),
    'name' => trim((string) ($_POST['name'] ?? '')),
    'enterprise_number' => trim((string) ($_POST['enterprise_number'] ?? '')),
    'vat_number' => trim((string) ($_POST['vat_number'] ?? '')),
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
        $result = pg_query_params(
            db(),
            'UPDATE companies SET is_public=$1, updated_at=now() WHERE id=$2',
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

        if (!$valid) {
            $messageKey = $companyId === 0 && !filter_var($initialAdminEmail, FILTER_VALIDATE_EMAIL)
                ? 'company_admin_email_invalid'
                : 'company_invalid';
        } else {
            $values = array_values($fields);
            if ($companyId > 0) {
                $values[] = $isActive;
                $values[] = $isPublic;
                $values[] = $companyId;
                $result = pg_query_params(
                    db(),
                    'UPDATE companies SET slug=$1, name=$2, enterprise_number=$3, vat_number=$4,
                        billing_email=$5, phone=$6, street=$7, house_number=$8, box_number=$9,
                        postal_code=$10, city=$11, address_reference_id=$12,
                        country_code=$13, default_language=$14, store_theme=$15, store_logo_file=$16,
                        store_hero_image_file=$17, is_active=$18, is_public=$19, updated_at=now()
                     WHERE id=$20',
                    $values
                );
            } else {
                $transaction = (bool) pg_query(db(), 'BEGIN');
                $insert = $transaction ? pg_query_params(
                    db(),
                    'INSERT INTO companies (
                        slug, name, enterprise_number, vat_number, billing_email, phone,
                        street, house_number, box_number, postal_code, city, address_reference_id,
                        country_code, default_language, store_theme, store_logo_file, store_hero_image_file,
                        is_active, is_public
                    ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19) RETURNING id',
                    [...$values, $isActive, $isPublic]
                ) : false;
                $createdCompany = $insert ? pg_fetch_assoc($insert) : false;
                $result = $createdCompany !== false;
                if ($result) {
                    set_database_tenant_context((int) $createdCompany['id'], (int) $currentUser['id'], true);
                    $result = seed_company_roles((int) $createdCompany['id'])
                        && create_initial_company_administrator(
                            (int) $createdCompany['id'],
                            $fields['name'],
                            $fields['slug'],
                            $initialAdminEmail,
                            $fields['default_language']
                        );
                }
                $result = $result && (bool) pg_query(db(), 'COMMIT');
                if (!$result && $transaction) {
                    pg_query(db(), 'ROLLBACK');
                }
            }
            $messageKey = $result ? 'company_saved' : 'company_save_failed';
            if ($result) {
                $fields = array_fill_keys(array_keys($fields), '');
                $fields['country_code'] = 'BE';
                $fields['default_language'] = 'NL';
                $fields['store_theme'] = 'default';
                $publicEnabled = false;
                $initialAdminEmail = '';
            }
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $editResult = pg_query_params(db(), 'SELECT * FROM companies WHERE id = $1', [(int) $_GET['edit']]);
    $editing = $editResult ? pg_fetch_assoc($editResult) : null;
    if ($editing) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $publicEnabled = $editing['is_public'] === 't';
        }
        foreach ($fields as $key => $_value) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $fields[$key] = (string) $editing[$key];
            }
        }
    }
}

$companyResult = pg_query(
    db(),
    'SELECT c.*, count(cu.user_id) AS users_count
     FROM companies AS c LEFT JOIN company_users AS cu ON cu.company_id = c.id
     GROUP BY c.id ORDER BY c.name'
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

page_start(fn_t('companies_heading'), $currentUser);
echo fn_templateRender('companies.html', [
    '_message_html' => $messageKey === '' ? '' : '<div class="alert alert-info" role="status">' . e(fn_t($messageKey)) . '</div>',
    'form_title' => fn_t($editing ? 'company_edit' : 'company_new'),
    'company_id' => (string) ($editing['id'] ?? ''),
    'slug_value' => (string) $fields['slug'],
    'name_value' => (string) $fields['name'],
    'enterprise_number_value' => (string) $fields['enterprise_number'],
    'vat_number_value' => (string) $fields['vat_number'],
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

