<?php
/**
 *
 * @date_cre: Sat 2026-11-07 14:57:26
 * @date_mut: Wed 2026-11-11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Provides reusable access to user and company contacts and locations.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:contact_locations
 */

declare(strict_types=1);

/**
 * Return a user's active login email address.
 *
 * @param string $userCode The stable user code.
 * @return string
 */
function grompil_user_login_email(string $userCode): string
{
    $result = db_query_params(
        'contacts.user_login_email',
        'SELECT contact_value FROM dt_user_contacts
         WHERE user_code = $1 AND is_login_email AND is_active
         LIMIT 1',
        [$userCode]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ? (string) $row['contact_value'] : '';
}

/**
 * Save or replace the login email contact for a user.
 *
 * @param string $userCode The stable user code.
 * @param ?int $companyId The user's tenant, if any.
 * @param string $email The normalized email address.
 * @return bool
 */
function grompil_save_user_login_email(string $userCode, ?int $companyId, string $email): bool
{
    $result = db_query_params(
        'contacts.save_user_login_email',
        "INSERT INTO dt_user_contacts (
            user_code, company_id, contact_type_key, purpose_key, contact_value,
            is_login_email, is_primary, is_active
         ) VALUES ($1, $2, 'email', 'login', $3, TRUE, TRUE, TRUE)
         ON CONFLICT (user_code, contact_type_key, purpose_key) DO UPDATE
         SET company_id = EXCLUDED.company_id, contact_value = EXCLUDED.contact_value,
             is_login_email = TRUE, is_primary = TRUE, is_active = TRUE, updated_at = now()",
        [$userCode, $companyId, strtolower(trim($email))]
    );
    return $result !== false;
}

/**
 * Load the user's primary residential address for legacy single-address forms.
 *
 * @param string $userCode The stable user code.
 * @return array<string, string>
 */
function grompil_user_primary_address(string $userCode): array
{
    $empty = grompil_empty_address();
    $result = db_query_params(
        'contacts.user_primary_address',
        "SELECT street, house_number, box_number, postal_code, city, country_code, address_reference_id,
                lambert72_x, lambert72_y, wgs84_latitude, wgs84_longitude
         FROM dt_user_locations
         WHERE user_code = $1 AND address_type_key = 'residential' AND is_primary AND is_active
         LIMIT 1",
        [$userCode]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ? array_map('strval', $row) : $empty;
}

/**
 * Load the company's active head-office address for legacy single-address forms.
 *
 * @param int $companyId The company identifier.
 * @return array<string, string>
 */
function grompil_company_head_office(int $companyId): array
{
    $empty = grompil_empty_address();
    $result = db_query_params(
        'contacts.company_head_office',
        "SELECT street, house_number, box_number, postal_code, city, country_code, address_reference_id,
                lambert72_x, lambert72_y, wgs84_latitude, wgs84_longitude
         FROM dt_company_locations
         WHERE company_id = $1 AND address_type_key = 'headquarters' AND is_primary AND is_active
         LIMIT 1",
        [$companyId]
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    return $row ? array_map('strval', $row) : $empty;
}

/**
 * Return a consistent empty address structure.
 *
 * @return array<string, string>
 */
function grompil_empty_address(): array
{
    return [
        'street' => '', 'house_number' => '', 'box_number' => '', 'postal_code' => '',
        'city' => '', 'country_code' => 'BE', 'address_reference_id' => '',
        'lambert72_x' => '', 'lambert72_y' => '', 'wgs84_latitude' => '', 'wgs84_longitude' => '',
    ];
}

/**
 * Validate and normalize one coordinate received from an address form.
 *
 * @param mixed $value Submitted coordinate.
 * @param float $minimum Inclusive minimum.
 * @param float $maximum Inclusive maximum.
 * @param int $precision Decimal places to keep.
 * @return ?string
 */
function grompil_address_coordinate(mixed $value, float $minimum, float $maximum, int $precision): ?string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return is_finite($number) && $number >= $minimum && $number <= $maximum
        ? number_format($number, $precision, '.', '')
        : null;
}

/**
 * Build a Google Maps search URL from stored WGS84 coordinates.
 *
 * @param mixed $latitude Latitude.
 * @param mixed $longitude Longitude.
 * @return ?string
 */
function grompil_google_maps_url(mixed $latitude, mixed $longitude): ?string
{
    $latitude = grompil_address_coordinate($latitude, -90, 90, 7);
    $longitude = grompil_address_coordinate($longitude, -180, 180, 7);
    return $latitude === null || $longitude === null
        ? null
        : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latitude . ',' . $longitude);
}

/**
 * Save a user's residential address without disturbing any other address type.
 *
 * @param string $userCode The stable user code.
 * @param ?int $companyId The user's tenant, if any.
 * @param array<string, string> $address The address fields.
 * @return bool
 */
function grompil_save_user_primary_address(string $userCode, ?int $companyId, array $address): bool
{
    $clearOtherPrimary = db_query_params(
        'contacts.clear_user_other_primary_addresses',
        "UPDATE dt_user_locations
         SET is_primary = FALSE, updated_at = now()
         WHERE user_code = $1 AND address_type_key <> 'residential'
           AND is_primary AND is_active",
        [$userCode]
    );
    if ($clearOtherPrimary === false) {
        return false;
    }

    $result = db_query_params(
        'contacts.save_user_primary_address',
        "INSERT INTO dt_user_locations (
            user_code, company_id, address_type_key, street, house_number, box_number,
            postal_code, city, country_code, address_reference_id, lambert72_x, lambert72_y,
            wgs84_latitude, wgs84_longitude, is_primary, is_active
         ) VALUES ($1, $2, 'residential', $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, TRUE, TRUE)
         ON CONFLICT (user_code, address_type_key) WHERE is_primary AND is_active DO UPDATE
         SET company_id = EXCLUDED.company_id, street = EXCLUDED.street,
             house_number = EXCLUDED.house_number, box_number = EXCLUDED.box_number,
             postal_code = EXCLUDED.postal_code, city = EXCLUDED.city,
             country_code = EXCLUDED.country_code, address_reference_id = EXCLUDED.address_reference_id,
             lambert72_x = EXCLUDED.lambert72_x, lambert72_y = EXCLUDED.lambert72_y,
             wgs84_latitude = EXCLUDED.wgs84_latitude, wgs84_longitude = EXCLUDED.wgs84_longitude,
             is_primary = TRUE, is_active = TRUE, updated_at = now()",
        [
            $userCode, $companyId, $address['street'] ?? '', $address['house_number'] ?? '',
            $address['box_number'] ?? '', $address['postal_code'] ?? '', $address['city'] ?? '',
            $address['country_code'] ?? 'BE', $address['address_reference_id'] ?? '',
            grompil_address_coordinate($address['lambert72_x'] ?? '', -1000000, 1000000, 3),
            grompil_address_coordinate($address['lambert72_y'] ?? '', -1000000, 1000000, 3),
            grompil_address_coordinate($address['wgs84_latitude'] ?? '', -90, 90, 7),
            grompil_address_coordinate($address['wgs84_longitude'] ?? '', -180, 180, 7),
        ]
    );
    return $result !== false;
}

/**
 * Save the company's head-office address without disturbing its other locations.
 *
 * @param int $companyId The company identifier.
 * @param array<string, string> $address The address fields.
 * @return bool
 */
function grompil_save_company_head_office(int $companyId, array $address): bool
{
    $result = db_query_params(
        'contacts.save_company_head_office',
        "INSERT INTO dt_company_locations (
            company_id, address_type_key, street, house_number, box_number,
            postal_code, city, country_code, address_reference_id, lambert72_x, lambert72_y,
            wgs84_latitude, wgs84_longitude, is_primary, is_public, is_active
         ) VALUES ($1, 'headquarters', $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, TRUE, TRUE, TRUE)
         ON CONFLICT (company_id, address_type_key) WHERE is_primary AND is_active DO UPDATE
         SET street = EXCLUDED.street, house_number = EXCLUDED.house_number,
             box_number = EXCLUDED.box_number, postal_code = EXCLUDED.postal_code,
             city = EXCLUDED.city, country_code = EXCLUDED.country_code,
             address_reference_id = EXCLUDED.address_reference_id,
             lambert72_x = EXCLUDED.lambert72_x, lambert72_y = EXCLUDED.lambert72_y,
             wgs84_latitude = EXCLUDED.wgs84_latitude, wgs84_longitude = EXCLUDED.wgs84_longitude,
             is_primary = TRUE, is_public = TRUE, is_active = TRUE, updated_at = now()",
        [
            $companyId, $address['street'] ?? '', $address['house_number'] ?? '',
            $address['box_number'] ?? '', $address['postal_code'] ?? '', $address['city'] ?? '',
            $address['country_code'] ?? 'BE', $address['address_reference_id'] ?? '',
            grompil_address_coordinate($address['lambert72_x'] ?? '', -1000000, 1000000, 3),
            grompil_address_coordinate($address['lambert72_y'] ?? '', -1000000, 1000000, 3),
            grompil_address_coordinate($address['wgs84_latitude'] ?? '', -90, 90, 7),
            grompil_address_coordinate($address['wgs84_longitude'] ?? '', -180, 180, 7),
        ]
    );
    return $result !== false;
}

/**
 * Save or remove one company contact identified by its type and purpose.
 *
 * @param int $companyId The company identifier.
 * @param string $typeKey The dd_varia contact type key.
 * @param string $purposeKey The contact purpose key.
 * @param string $value The contact value.
 * @return bool
 */
function grompil_save_company_contact(int $companyId, string $typeKey, string $purposeKey, string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        $result = db_query_params(
            'contacts.delete_company_contact',
            'DELETE FROM dt_company_contacts WHERE company_id = $1 AND contact_type_key = $2 AND purpose_key = $3',
            [$companyId, $typeKey, $purposeKey]
        );
    } else {
        $result = db_query_params(
            'contacts.save_company_contact',
            'INSERT INTO dt_company_contacts (
                company_id, contact_type_key, purpose_key, contact_value, is_primary, is_active
             ) VALUES ($1, $2, $3, $4, TRUE, TRUE)
             ON CONFLICT (company_id, contact_type_key, purpose_key) DO UPDATE
             SET contact_value = EXCLUDED.contact_value, is_primary = TRUE,
                 is_active = TRUE, updated_at = now()',
            [$companyId, $typeKey, $purposeKey, $value]
        );
    }
    return $result !== false;
}
