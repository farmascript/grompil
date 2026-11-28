<?php
/**
 * @date_cre: Sat 2026-11-28 16:56:21
 * @date_mut: Sat 2026-11-28 16:56:21
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Stores company-specific customer profile settings used in dispensing.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:customer_profile
 */

declare(strict_types=1);

require_once __DIR__ . '/kbo.php';

/**
 * Load the optional company-specific customer profile.
 *
 * @param int $companyId Company identifier.
 * @param string $customerCode Customer code.
 * @return array<string, mixed> The profile fields, with defaults when no row exists.
 */
function grompil_customer_profile_get(int $companyId, string $customerCode): array
{
    try {
        $result = db_query_params(
            'customer_profile.load',
            'SELECT profile.fixed_caregiver_code,
                    btrim(COALESCE(caregiver.name_first, \'\') || \' \' || COALESCE(caregiver.name_last, \'\')) AS fixed_caregiver_name,
                    profile.is_home_pharmacist, profile.print_bvac, profile.invoice_group, profile.vat_number
             FROM dt_company_customer_profiles AS profile
             LEFT JOIN bt_caregivers AS caregiver ON caregiver.caregiver_code = profile.fixed_caregiver_code
             WHERE profile.company_id = $1 AND profile.customer_code = $2',
            [$companyId, $customerCode]
        );
    } catch (Throwable $error) {
        error_log('Customer profile load failed: ' . get_class($error));
        return [
            'fixed_caregiver_code' => '', 'fixed_caregiver_name' => '',
            'is_home_pharmacist' => false, 'print_bvac' => false,
            'invoice_group' => '', 'vat_number' => '', 'has_data' => false,
        ];
    }
    $row = pg_fetch_assoc($result);
    if (!$row) {
        return [
            'fixed_caregiver_code' => '', 'fixed_caregiver_name' => '',
            'is_home_pharmacist' => false, 'print_bvac' => false,
            'invoice_group' => '', 'vat_number' => '', 'has_data' => false,
        ];
    }

    $profile = [
        'fixed_caregiver_code' => (string) ($row['fixed_caregiver_code'] ?? ''),
        'fixed_caregiver_name' => (string) ($row['fixed_caregiver_name'] ?? ''),
        'is_home_pharmacist' => ($row['is_home_pharmacist'] ?? 'f') === 't',
        'print_bvac' => ($row['print_bvac'] ?? 'f') === 't',
        'invoice_group' => (string) ($row['invoice_group'] ?? ''),
        'vat_number' => (string) ($row['vat_number'] ?? ''),
    ];
    $profile['has_data'] = $profile['fixed_caregiver_code'] !== ''
        || $profile['is_home_pharmacist'] || $profile['print_bvac']
        || $profile['invoice_group'] !== '' || $profile['vat_number'] !== '';
    return $profile;
}

/**
 * Save or clear a company-specific customer profile.
 *
 * @param int $companyId Company identifier.
 * @param string $customerCode Customer code.
 * @param array<string, mixed> $profile Submitted profile data.
 * @param string $actorCode Authenticated user code.
 * @return void
 */
function grompil_customer_profile_save(int $companyId, string $customerCode, array $profile, string $actorCode): void
{
    if (preg_match('/^[A-Z0-9]{6}$/D', $customerCode) !== 1) {
        throw new InvalidArgumentException('invalid_customer');
    }
    $customer = db_query_params(
        'customer_profile.customer_check',
        'SELECT 1 FROM bt_customers WHERE company_id = $1 AND customer_code = $2 AND is_active',
        [$companyId, $customerCode]
    );
    if (pg_num_rows($customer) !== 1) {
        throw new InvalidArgumentException('invalid_customer');
    }

    $caregiverValue = $profile['fixed_caregiver_code'] ?? '';
    if (!is_string($caregiverValue)) {
        throw new InvalidArgumentException('invalid_caregiver');
    }
    $caregiverCode = strtoupper(trim($caregiverValue));
    if ($caregiverCode !== '' && preg_match('/^[A-Z0-9]{6}$/D', $caregiverCode) !== 1) {
        throw new InvalidArgumentException('invalid_caregiver');
    }
    if ($caregiverCode !== '') {
        $caregiver = db_query_params(
            'customer_profile.caregiver_check',
            'SELECT 1 FROM bt_caregivers
             WHERE caregiver_code = $1 AND is_active
               AND (owner_company_id IS NULL OR owner_company_id = $2)
               AND caregiver_type_key IN (\'physician\', \'dentist\')',
            [$caregiverCode, $companyId]
        );
        if (pg_num_rows($caregiver) !== 1) {
            throw new InvalidArgumentException('invalid_caregiver');
        }
    }

    $isHomePharmacist = $profile['is_home_pharmacist'] ?? null;
    $printBvac = $profile['print_bvac'] ?? null;
    if (!is_bool($isHomePharmacist) || !is_bool($printBvac)) {
        throw new InvalidArgumentException('invalid_profile');
    }
    $invoiceGroupValue = $profile['invoice_group'] ?? '';
    $vatNumberValue = $profile['vat_number'] ?? '';
    if (!is_string($invoiceGroupValue) || !is_string($vatNumberValue)) {
        throw new InvalidArgumentException('invalid_profile');
    }
    $invoiceGroup = trim($invoiceGroupValue);
    $vatNumber = trim($vatNumberValue);
    if (mb_strlen($invoiceGroup) > 80 || mb_strlen($vatNumber) > 32) {
        throw new InvalidArgumentException('invalid_profile');
    }

    $hasData = $caregiverCode !== '' || $isHomePharmacist || $printBvac
        || $invoiceGroup !== '' || $vatNumber !== '';
    if (!$hasData) {
        db_query_params(
            'customer_profile.clear',
            'DELETE FROM dt_company_customer_profiles WHERE company_id = $1 AND customer_code = $2',
            [$companyId, $customerCode]
        );
        return;
    }

    db_query_params(
        'customer_profile.save',
        'INSERT INTO dt_company_customer_profiles (
            company_id, customer_code, fixed_caregiver_code, is_home_pharmacist,
            print_bvac, invoice_group, vat_number, created_by_user_code, updated_by_user_code
         ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$8)
         ON CONFLICT (company_id, customer_code) DO UPDATE SET
            fixed_caregiver_code = EXCLUDED.fixed_caregiver_code,
            is_home_pharmacist = EXCLUDED.is_home_pharmacist,
            print_bvac = EXCLUDED.print_bvac,
            invoice_group = EXCLUDED.invoice_group,
            vat_number = EXCLUDED.vat_number,
            updated_by_user_code = EXCLUDED.updated_by_user_code,
            updated_at = now()',
        [$companyId, $customerCode, $caregiverCode !== '' ? $caregiverCode : null,
            $isHomePharmacist ? 'true' : 'false', $printBvac ? 'true' : 'false',
            $invoiceGroup !== '' ? $invoiceGroup : null, $vatNumber !== '' ? $vatNumber : null, $actorCode]
    );
}

/**
 * Serve the dispensing screen's JSON API for one customer's profile.
 *
 * @param int $companyId Company identifier.
 * @param string $actorCode Authenticated user code.
 * @return never
 */
function grompil_customer_profile_api(int $companyId, string $actorCode): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $respond = static function (array $body, int $status = 200): never {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    };

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $payload = [];
    if ($method === 'POST') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || !is_string($payload['csrf_token'] ?? null)
            || !hash_equals(csrf_token(), $payload['csrf_token'])) {
            $respond(['error' => 'request_rejected'], 403);
        }
    } elseif ($method !== 'GET') {
        $respond(['error' => 'method_not_allowed'], 405);
    }

    $customerCodeValue = $payload['customer_code'] ?? $_GET['customer_code'] ?? '';
    if (!is_string($customerCodeValue)) {
        $respond(['error' => 'invalid_customer'], 400);
    }
    $customerCode = strtoupper(trim($customerCodeValue));
    if (preg_match('/^[A-Z0-9]{6}$/D', $customerCode) !== 1) {
        $respond(['error' => 'invalid_customer'], 400);
    }
    if ($method === 'POST' && ($payload['action'] ?? '') === 'kbo_lookup') {
        $vatNumber = is_string($payload['vat_number'] ?? null) ? trim($payload['vat_number']) : '';
        $digits = preg_replace('/\D+/', '', $vatNumber) ?? '';
        if (!preg_match('/^(?:0|1)?\d{9}$/D', $digits)) {
            $respond(['error' => 'invalid_vat_number', 'message' => fn_t('customer_profile_kbo_invalid')], 422);
        }
        try {
            $details = grompil_kbo_lookup($digits);
            $respond(['kbo' => $details, 'status_label' => fn_t('company_kbo_status')]);
        } catch (Throwable $error) {
            $message = $error->getMessage();
            $errorKey = match (true) {
                $message === 'kbo_not_configured' => 'company_kbo_not_configured',
                $message === 'kbo_weekly_limit_reached' => 'company_kbo_weekly_limit',
                $message === 'kbo_service_quota_exceeded' => 'company_kbo_service_quota_exceeded',
                $message === 'kbo_terms_not_accepted' => 'company_kbo_terms_not_accepted',
                $message === 'kbo_authentication_failed' => 'company_kbo_authentication_failed',
                str_starts_with($message, 'kbo_transport_error:') || str_starts_with($message, 'kbo_http_error:')
                    => 'company_kbo_service_unavailable',
                default => 'company_kbo_failed',
            };
            $respond(['error' => 'kbo_lookup_failed', 'message' => fn_t($errorKey)], 503);
        }
    }
    try {
        if ($method === 'POST') {
            grompil_customer_profile_save($companyId, $customerCode, $payload, $actorCode);
        }
        $respond(['profile' => grompil_customer_profile_get($companyId, $customerCode)]);
    } catch (InvalidArgumentException $error) {
        $respond(['error' => $error->getMessage()], 422);
    } catch (Throwable $error) {
        error_log('Customer profile request failed: ' . get_class($error));
        $respond(['error' => 'profile_unavailable'], 503);
    }
}
