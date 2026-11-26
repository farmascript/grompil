<?php
/**
 * @date_cre: Sun 2026-11-15 18:42:41
 * @date_mut: Thu 2026-11-26
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Provides tenant-scoped reusable contact management for customers and users.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:contact_manager
 */

declare(strict_types=1);

/**
 * Load active contact methods for a customer or user within the current company.
 * Callers must authorize access to the selected owner before using this helper.
 *
 * @param string $ownerType Either customer or user.
 * @param int $companyId The active company identifier.
 * @param string $ownerCode The customer code or user code.
 * @return array<int, array<string, mixed>>
 */
function grompil_contact_manager_load(string $ownerType, int $companyId, string $ownerCode): array
{
    if ($companyId < 1 || preg_match('/^[A-Z0-9]{6}$/D', $ownerCode) !== 1) {
        return [];
    }

    if ($ownerType === 'customer') {
        $sql = 'SELECT id, contact_type_key, contact_value, label, is_primary, is_public, sort_order
                FROM dt_user_contacts
                WHERE company_id = $1 AND customer_code = $2 AND user_code IS NULL AND is_active
                ORDER BY is_primary DESC, sort_order, id';
    } elseif ($ownerType === 'user') {
        $sql = 'SELECT id, contact_type_key, contact_value, label, is_primary, is_public, sort_order
                FROM dt_user_contacts
                WHERE company_id = $1 AND user_code = $2 AND customer_code IS NULL
                  AND is_active AND NOT is_login_email
                ORDER BY is_primary DESC, sort_order, id';
    } else {
        return [];
    }

    $result = db_query_params('contact_manager.load', $sql, [$companyId, $ownerCode]);
    $contacts = [];
    while ($row = pg_fetch_assoc($result)) {
        $contacts[] = $row;
    }
    return $contacts;
}

/**
 * Save a contact action for a customer or user owner. Removal is soft deletion.
 * Callers must authorize access to the selected owner before using this helper.
 *
 * @param string $ownerType Either customer or user.
 * @param int $companyId The active company identifier.
 * @param string $ownerCode The customer code or user code.
 * @return ?string A result key, or null when this is not a contact form request.
 */
function grompil_contact_manager_handle_post(string $ownerType, int $companyId, string $ownerCode): ?string
{
    $action = (string) ($_POST['contact_action'] ?? '');
    if (!in_array($action, ['add', 'update', 'delete'], true)) {
        return null;
    }

    verify_csrf();
    if (!in_array($ownerType, ['customer', 'user'], true) || $companyId < 1
        || preg_match('/^[A-Z0-9]{6}$/D', $ownerCode) !== 1) {
        return 'not_found';
    }

    try {
        $ownerResult = $ownerType === 'customer'
            ? db_query_params(
                'contact_manager.customer_owner',
                'SELECT 1 FROM bt_customers WHERE company_id = $1 AND customer_code = $2 AND is_active',
                [$companyId, $ownerCode]
            )
            : db_query_params(
                'contact_manager.user_owner',
                'SELECT 1 FROM bt_users WHERE company_id = $1 AND user_code = $2 AND is_active',
                [$companyId, $ownerCode]
            );
        if (pg_num_rows($ownerResult) !== 1) {
            return 'not_found';
        }

        $ownerColumn = $ownerType === 'customer' ? 'customer_code' : 'user_code';
        $otherOwnerColumn = $ownerType === 'customer' ? 'user_code' : 'customer_code';
        $ownerClause = $ownerColumn . ' = $2 AND ' . $otherOwnerColumn . ' IS NULL';

        if ($action === 'delete') {
            $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
            if ($contactId === false || $contactId < 1) {
                return 'invalid';
            }
            $result = db_query_params(
                'contact_manager.delete',
                'UPDATE dt_user_contacts SET is_active = FALSE, updated_at = now()
                 WHERE company_id = $1 AND ' . $ownerClause . ' AND id = $3 AND is_active AND NOT is_login_email',
                [$companyId, $ownerCode, $contactId]
            );
            return pg_affected_rows($result) === 1 ? 'deleted' : 'not_found';
        }

        $typeKey = trim((string) ($_POST['contact_type_key'] ?? ''));
        $value = trim((string) ($_POST['contact_value'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $availableTypes = array_column(dd_varia_get_options('contact_types', '_root'), 'key');
        if (!in_array($typeKey, $availableTypes, true) || $value === '' || mb_strlen($value) > 1024
            || mb_strlen($label) > 160) {
            return 'invalid';
        }
        $isPrimary = isset($_POST['is_primary']) ? 'true' : 'false';
        $isPublic = isset($_POST['is_public']) ? 'true' : 'false';

        if ($action === 'add') {
            $purposeKey = 'contact_' . bin2hex(random_bytes(12));
            $addSubqueryOwnerClause = $ownerType === 'customer'
                ? 'customer_code = $9::VARCHAR(6) AND user_code IS NULL'
                : 'user_code = $9::VARCHAR(6) AND customer_code IS NULL';
            $result = db_query_params(
                'contact_manager.add',
                'INSERT INTO dt_user_contacts (
                    company_id, user_code, customer_code, contact_type_key, purpose_key, contact_value,
                    label, is_login_email, is_primary, is_public, is_active, sort_order
                 ) VALUES (
                    $1, ' . ($ownerType === 'user' ? '$2::VARCHAR(6), NULL' : 'NULL, $2::VARCHAR(6)') . ', $3, $4, $5, $6,
                    FALSE, $7::BOOLEAN, $8::BOOLEAN, TRUE,
                    COALESCE((SELECT MAX(sort_order) + 10 FROM dt_user_contacts
                              WHERE company_id = $1 AND ' . $addSubqueryOwnerClause . '), 10)
                 )',
                [$companyId, $ownerCode, $typeKey, $purposeKey, $value, $label, $isPrimary, $isPublic, $ownerCode]
            );
            return $result ? 'saved' : 'unavailable';
        }

        $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
        if ($contactId === false || $contactId < 1) {
            return 'invalid';
        }
        $result = db_query_params(
            'contact_manager.update',
            'UPDATE dt_user_contacts SET contact_type_key = $4, contact_value = $5, label = $6,
                    is_primary = $7::BOOLEAN, is_public = $8::BOOLEAN, updated_at = now()
             WHERE company_id = $1 AND ' . $ownerClause . ' AND id = $3 AND is_active AND NOT is_login_email',
            [$companyId, $ownerCode, $contactId, $typeKey, $value, $label, $isPrimary, $isPublic]
        );
        return pg_affected_rows($result) === 1 ? 'saved' : 'not_found';
    } catch (Throwable $error) {
        error_log('Contact manager action failed [' . $ownerType . ', ' . $action . ']: ' . get_class($error));
        return 'unavailable';
    }
}

/**
 * Render the shared Bootstrap contact manager modal for a customer or user.
 *
 * @param string $ownerType Either customer or user.
 * @param int $companyId The active company identifier.
 * @param string $ownerCode The customer code or user code.
 * @param string $ownerName Display name for the modal heading.
 * @param string $ownerLabel Localized label for the owner code.
 * @param string $actionUrl URL of the page handling the form POST.
 * @param string $status A prior action result key.
 * @return array{count:int, html:string}
 */
function grompil_contact_manager_render_modal(
    string $ownerType,
    int $companyId,
    string $ownerCode,
    string $ownerName,
    string $ownerLabel,
    string $actionUrl,
    string $status = ''
): array {
    $contacts = grompil_contact_manager_load($ownerType, $companyId, $ownerCode);
    $contactTypes = dd_varia_get_options('contact_types', '_root');
    $typeOptions = static function (string $selectedKey) use ($contactTypes): string {
        $html = '<option value="">' . e(fn_t('contact_manager_choose_type')) . '</option>';
        foreach ($contactTypes as $type) {
            $key = (string) $type['key'];
            $html .= '<option value="' . e($key) . '"' . ($key === $selectedKey ? ' selected' : '') . '>'
                . e((string) $type['label']) . '</option>';
        }
        return $html;
    };

    $rowsHtml = '';
    foreach ($contacts as $contact) {
        $rowsHtml .= fn_moduleTemplateRender('contacts', 'contact_manager_row.html', [
            'contact_id' => (string) $contact['id'],
            '_contact_type_options_html' => $typeOptions((string) $contact['contact_type_key']),
            'contact_value' => (string) $contact['contact_value'],
            'contact_label' => (string) $contact['label'],
            '_contact_primary_checked' => $contact['is_primary'] === 't' ? 'checked' : '',
            '_contact_public_checked' => $contact['is_public'] === 't' ? 'checked' : '',
            'contact_manager_save' => fn_t('save'),
            'contact_manager_delete' => fn_t('delete'),
            'csrf_token' => csrf_token(),
            'contact_manager_action_url' => $actionUrl,
        ]);
    }

    $feedbackMessages = [
        'saved' => ['success', 'contact_manager_saved'],
        'deleted' => ['success', 'contact_manager_deleted'],
        'invalid' => ['warning', 'contact_manager_invalid'],
        'not_found' => ['warning', 'contact_manager_not_found'],
        'unavailable' => ['danger', 'contact_manager_unavailable'],
    ];
    $feedback = '';
    if (isset($feedbackMessages[$status])) {
        [$type, $key] = $feedbackMessages[$status];
        $feedback = '<div class="alert alert-' . e($type) . '" role="status">' . e(fn_t($key)) . '</div>';
    }

    return [
        'count' => count($contacts),
        'html' => fn_moduleTemplateRender('contacts', 'contact_manager_modal.html', [
            'contact_manager_title' => fn_t($ownerType === 'customer' ? 'contact_manager_customer_title' : 'contact_manager_user_title'),
            'contact_manager_owner_name' => $ownerName,
            'contact_manager_intro' => fn_t('contact_manager_intro'),
            'contact_manager_add_title' => fn_t('contact_manager_add_title'),
            'contact_manager_add_toggle' => fn_t('contact_manager_add_toggle'),
            'contact_manager_add_expanded' => $status !== '' && !in_array($status, ['saved', 'deleted'], true) ? 'true' : 'false',
            'contact_manager_type' => fn_t('contact_manager_type'),
            'contact_manager_value' => fn_t('contact_manager_value'),
            'contact_manager_label' => fn_t('contact_manager_label'),
            'contact_manager_primary' => fn_t('contact_manager_primary'),
            'contact_manager_public' => fn_t('contact_manager_public'),
            'contact_manager_add' => fn_t('contact_manager_add'),
            'contact_manager_close' => fn_t('close'),
            'contact_manager_empty' => fn_t('contact_manager_empty'),
            'contact_manager_owner_label' => $ownerLabel,
            'contact_manager_owner_code' => $ownerCode,
            'csrf_token' => csrf_token(),
            'contact_manager_action_url' => $actionUrl,
            '_contact_manager_rows_html' => $rowsHtml,
            '_contact_manager_empty_html' => $contacts === []
                ? '<p class="text-secondary" data-contact-empty>' . e(fn_t('contact_manager_empty')) . '</p>' : '',
            '_contact_manager_type_options_html' => $typeOptions(''),
            '_contact_manager_feedback_html' => $feedback,
            '_contact_manager_add_show' => $status !== '' && !in_array($status, ['saved', 'deleted'], true) ? 'show' : '',
        ]),
    ];
}
