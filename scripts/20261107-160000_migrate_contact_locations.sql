-- Backfill normalized user/company contacts and locations from the legacy columns.
-- Run schema.sql first so the four dt_* tables and dd_varia type keys exist.
-- This migration intentionally keeps the legacy columns for the deployment window.
BEGIN;

DO $$
BEGIN
    IF to_regclass('public.dt_user_contacts') IS NULL
       OR to_regclass('public.dt_company_contacts') IS NULL
       OR to_regclass('public.dt_user_locations') IS NULL
       OR to_regclass('public.dt_company_locations') IS NULL THEN
        RAISE EXCEPTION 'Run schema.sql before the contact/location data migration.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_users' AND column_name = 'email'
    ) THEN
        IF EXISTS (SELECT 1 FROM bt_users WHERE btrim(email) = '') THEN
            RAISE EXCEPTION 'A user has no legacy login email; resolve that record before migrating.';
        END IF;
        INSERT INTO dt_user_contacts (
            user_code, company_id, contact_type_key, purpose_key, contact_value,
            is_login_email, is_primary, is_active
        )
        SELECT user_code, company_id, 'email', 'login', lower(btrim(email)), TRUE, TRUE, TRUE
        FROM bt_users
        ON CONFLICT (user_code, contact_type_key, purpose_key) DO UPDATE
        SET company_id = EXCLUDED.company_id, contact_value = EXCLUDED.contact_value,
            is_login_email = TRUE, is_primary = TRUE, is_active = TRUE, updated_at = now();
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_users' AND column_name = 'street'
    ) THEN
        INSERT INTO dt_user_locations (
            user_code, company_id, address_type_key, street, house_number, box_number,
            postal_code, city, country_code, address_reference_id, is_primary, is_active
        )
        SELECT user_code, company_id, 'residential', street, house_number, box_number,
               postal_code, city, country_code, address_reference_id, TRUE, TRUE
        FROM bt_users
        WHERE coalesce(street, '') <> '' OR coalesce(house_number, '') <> ''
           OR coalesce(box_number, '') <> '' OR coalesce(postal_code, '') <> ''
           OR coalesce(city, '') <> '' OR coalesce(address_reference_id, '') <> ''
           OR country_code <> 'BE'
        ON CONFLICT (user_code, address_type_key) WHERE is_primary AND is_active DO UPDATE
        SET company_id = EXCLUDED.company_id, street = EXCLUDED.street,
            house_number = EXCLUDED.house_number, box_number = EXCLUDED.box_number,
            postal_code = EXCLUDED.postal_code, city = EXCLUDED.city,
            country_code = EXCLUDED.country_code,
            address_reference_id = EXCLUDED.address_reference_id,
            is_primary = TRUE, is_active = TRUE, updated_at = now();
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_companies' AND column_name = 'billing_email'
    ) THEN
        INSERT INTO dt_company_contacts (
            company_id, contact_type_key, purpose_key, contact_value, is_primary, is_active
        )
        SELECT id, 'email', 'billing', lower(btrim(billing_email)), TRUE, TRUE
        FROM bt_companies WHERE btrim(billing_email) <> ''
        ON CONFLICT (company_id, contact_type_key, purpose_key) DO UPDATE
        SET contact_value = EXCLUDED.contact_value, is_primary = TRUE,
            is_active = TRUE, updated_at = now();
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_companies' AND column_name = 'phone'
    ) THEN
        INSERT INTO dt_company_contacts (
            company_id, contact_type_key, purpose_key, contact_value, is_primary, is_active
        )
        SELECT id, 'phone', 'main', btrim(phone), TRUE, TRUE
        FROM bt_companies WHERE btrim(phone) <> ''
        ON CONFLICT (company_id, contact_type_key, purpose_key) DO UPDATE
        SET contact_value = EXCLUDED.contact_value, is_primary = TRUE,
            is_active = TRUE, updated_at = now();
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_companies' AND column_name = 'street'
    ) THEN
        INSERT INTO dt_company_locations (
            company_id, address_type_key, street, house_number, box_number,
            postal_code, city, country_code, address_reference_id, is_primary, is_public, is_active
        )
        SELECT id, 'headquarters', street, house_number, box_number,
               postal_code, city, country_code, address_reference_id, TRUE, TRUE, TRUE
        FROM bt_companies
        WHERE coalesce(street, '') <> '' OR coalesce(house_number, '') <> ''
           OR coalesce(box_number, '') <> '' OR coalesce(postal_code, '') <> ''
           OR coalesce(city, '') <> '' OR coalesce(address_reference_id, '') <> ''
           OR country_code <> 'BE'
        ON CONFLICT (company_id, address_type_key) WHERE is_primary AND is_active DO UPDATE
        SET street = EXCLUDED.street, house_number = EXCLUDED.house_number,
            box_number = EXCLUDED.box_number, postal_code = EXCLUDED.postal_code,
            city = EXCLUDED.city, country_code = EXCLUDED.country_code,
            address_reference_id = EXCLUDED.address_reference_id,
            is_primary = TRUE, is_public = TRUE, is_active = TRUE, updated_at = now();
    END IF;
END;
$$;

COMMIT;
