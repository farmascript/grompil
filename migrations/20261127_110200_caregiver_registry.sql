-- Shared caregiver identity with company-private details and identifiers.
BEGIN;

CREATE TABLE IF NOT EXISTS bt_caregivers (
    caregiver_code VARCHAR(6) PRIMARY KEY
        CHECK (caregiver_code ~ '^[A-Z0-9]{6}$'
           AND length(regexp_replace(caregiver_code, '[A-Z]', '', 'g')) = 3
           AND length(regexp_replace(caregiver_code, '[0-9]', '', 'g')) = 3),
    owner_company_id BIGINT REFERENCES bt_companies(id) ON DELETE RESTRICT,
    caregiver_type_key VARCHAR(32) NOT NULL
        CHECK (caregiver_type_key IN ('physician', 'dentist', 'veterinarian', 'midwife', 'other')),
    name_first VARCHAR(120) NOT NULL DEFAULT '',
    name_last VARCHAR(160) NOT NULL,
    title VARCHAR(120) NOT NULL DEFAULT '',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    source_key VARCHAR(80) NOT NULL DEFAULT 'manual',
    source_reference TEXT NOT NULL DEFAULT '',
    created_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    updated_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (btrim(name_last) <> '')
);
CREATE INDEX IF NOT EXISTS bt_caregivers_shared_name_idx
    ON bt_caregivers (lower(name_last), lower(name_first))
    WHERE owner_company_id IS NULL AND is_active;
CREATE INDEX IF NOT EXISTS bt_caregivers_company_name_idx
    ON bt_caregivers (owner_company_id, lower(name_last), lower(name_first))
    WHERE owner_company_id IS NOT NULL AND is_active;

CREATE TABLE IF NOT EXISTS dt_caregiver_identifiers (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    caregiver_code VARCHAR(6) NOT NULL REFERENCES bt_caregivers(caregiver_code) ON DELETE RESTRICT,
    company_id BIGINT REFERENCES bt_companies(id) ON DELETE RESTRICT,
    identifier_type_key VARCHAR(40) NOT NULL
        CHECK (identifier_type_key IN ('riziv', 'veterinary', 'professional', 'other')),
    identifier_value VARCHAR(100) NOT NULL,
    issuer VARCHAR(160) NOT NULL DEFAULT '',
    source_key VARCHAR(80) NOT NULL DEFAULT 'manual',
    source_reference TEXT NOT NULL DEFAULT '',
    valid_from DATE,
    valid_until DATE,
    verified_at TIMESTAMPTZ,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    updated_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (btrim(identifier_value) <> ''),
    CHECK (identifier_type_key <> 'riziv' OR identifier_value ~ '^[0-9]{11}$'),
    CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_caregiver_identifiers_shared_unique_idx
    ON dt_caregiver_identifiers (identifier_type_key, identifier_value)
    WHERE company_id IS NULL AND is_active;
CREATE UNIQUE INDEX IF NOT EXISTS dt_caregiver_identifiers_company_unique_idx
    ON dt_caregiver_identifiers (company_id, identifier_type_key, identifier_value)
    WHERE company_id IS NOT NULL AND is_active;
CREATE INDEX IF NOT EXISTS dt_caregiver_identifiers_caregiver_idx
    ON dt_caregiver_identifiers (caregiver_code, company_id, is_active);

CREATE TABLE IF NOT EXISTS dt_caregiver_contacts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    caregiver_code VARCHAR(6) NOT NULL REFERENCES bt_caregivers(caregiver_code) ON DELETE RESTRICT,
    company_id BIGINT REFERENCES bt_companies(id) ON DELETE RESTRICT,
    contact_type_list_key VARCHAR(80) NOT NULL DEFAULT 'contact_types'
        CHECK (contact_type_list_key = 'contact_types'),
    contact_type_key VARCHAR(80) NOT NULL,
    purpose_key VARCHAR(80) NOT NULL DEFAULT '',
    contact_value VARCHAR(1024) NOT NULL,
    label VARCHAR(160) NOT NULL DEFAULT '',
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    source_key VARCHAR(80) NOT NULL DEFAULT 'manual',
    source_reference TEXT NOT NULL DEFAULT '',
    verified_at TIMESTAMPTZ,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    updated_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (contact_type_list_key, contact_type_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT,
    CHECK (btrim(contact_value) <> '')
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_caregiver_contacts_shared_unique_idx
    ON dt_caregiver_contacts (caregiver_code, contact_type_key, purpose_key)
    WHERE company_id IS NULL AND is_active;
CREATE UNIQUE INDEX IF NOT EXISTS dt_caregiver_contacts_company_unique_idx
    ON dt_caregiver_contacts (company_id, caregiver_code, contact_type_key, purpose_key)
    WHERE company_id IS NOT NULL AND is_active;
CREATE INDEX IF NOT EXISTS dt_caregiver_contacts_lookup_idx
    ON dt_caregiver_contacts (caregiver_code, company_id, is_active, sort_order, id);

CREATE TABLE IF NOT EXISTS dt_caregiver_locations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    caregiver_code VARCHAR(6) NOT NULL REFERENCES bt_caregivers(caregiver_code) ON DELETE RESTRICT,
    company_id BIGINT REFERENCES bt_companies(id) ON DELETE RESTRICT,
    address_type_list_key VARCHAR(80) NOT NULL DEFAULT 'address_types'
        CHECK (address_type_list_key = 'address_types'),
    address_type_key VARCHAR(80) NOT NULL,
    label VARCHAR(160) NOT NULL DEFAULT '',
    street VARCHAR(160) NOT NULL DEFAULT '',
    house_number VARCHAR(32) NOT NULL DEFAULT '',
    box_number VARCHAR(32) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    country_code CHAR(2) NOT NULL DEFAULT 'BE' CHECK (country_code ~ '^[A-Z]{2}$'),
    address_reference_id TEXT NOT NULL DEFAULT '',
    lambert72_x NUMERIC(12,3),
    lambert72_y NUMERIC(12,3),
    wgs84_latitude NUMERIC(10,7) CHECK (wgs84_latitude IS NULL OR wgs84_latitude BETWEEN -90 AND 90),
    wgs84_longitude NUMERIC(10,7) CHECK (wgs84_longitude IS NULL OR wgs84_longitude BETWEEN -180 AND 180),
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    source_key VARCHAR(80) NOT NULL DEFAULT 'manual',
    source_reference TEXT NOT NULL DEFAULT '',
    verified_at TIMESTAMPTZ,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    updated_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (address_type_list_key, address_type_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_caregiver_locations_shared_primary_idx
    ON dt_caregiver_locations (caregiver_code, address_type_key)
    WHERE company_id IS NULL AND is_primary AND is_active;
CREATE UNIQUE INDEX IF NOT EXISTS dt_caregiver_locations_company_primary_idx
    ON dt_caregiver_locations (company_id, caregiver_code, address_type_key)
    WHERE company_id IS NOT NULL AND is_primary AND is_active;
CREATE INDEX IF NOT EXISTS dt_caregiver_locations_lookup_idx
    ON dt_caregiver_locations (caregiver_code, company_id, is_active, sort_order, id);

ALTER TABLE bt_caregivers ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_caregiver_identifiers ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_caregiver_contacts ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_caregiver_locations ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS bt_caregivers_scope ON bt_caregivers;
DROP POLICY IF EXISTS bt_caregivers_select ON bt_caregivers;
DROP POLICY IF EXISTS bt_caregivers_write ON bt_caregivers;
CREATE POLICY bt_caregivers_select ON bt_caregivers FOR SELECT
    USING (owner_company_id IS NULL OR owner_company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu WHERE cu.company_id=bt_caregivers.owner_company_id
              AND cu.user_code=NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status='approved')));
CREATE POLICY bt_caregivers_write ON bt_caregivers FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true'
        OR (owner_company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT AND EXISTS (
            SELECT 1 FROM ct_company_users cu WHERE cu.company_id=bt_caregivers.owner_company_id
              AND cu.user_code=NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status='approved')))
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true'
        OR (owner_company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT AND EXISTS (
            SELECT 1 FROM ct_company_users cu WHERE cu.company_id=bt_caregivers.owner_company_id
              AND cu.user_code=NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status='approved')));

DROP POLICY IF EXISTS dt_caregiver_identifiers_scope ON dt_caregiver_identifiers;
DROP POLICY IF EXISTS dt_caregiver_identifiers_select ON dt_caregiver_identifiers;
DROP POLICY IF EXISTS dt_caregiver_identifiers_write ON dt_caregiver_identifiers;
CREATE POLICY dt_caregiver_identifiers_select ON dt_caregiver_identifiers FOR SELECT
    USING (
        (company_id IS NULL AND (current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_identifiers.caregiver_code AND bc.owner_company_id IS NULL)))
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_identifiers.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_identifiers.company_id)))
    );
CREATE POLICY dt_caregiver_identifiers_write ON dt_caregiver_identifiers FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true'
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_identifiers.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_identifiers.company_id))))
    WITH CHECK (
        (company_id IS NULL AND current_setting('grompil.platform_operator', true) = 'true')
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_identifiers.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_identifiers.company_id)))
    );

DROP POLICY IF EXISTS dt_caregiver_contacts_scope ON dt_caregiver_contacts;
DROP POLICY IF EXISTS dt_caregiver_contacts_select ON dt_caregiver_contacts;
DROP POLICY IF EXISTS dt_caregiver_contacts_write ON dt_caregiver_contacts;
CREATE POLICY dt_caregiver_contacts_select ON dt_caregiver_contacts FOR SELECT
    USING (
        (company_id IS NULL AND (current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_contacts.caregiver_code AND bc.owner_company_id IS NULL)))
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_contacts.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_contacts.company_id)))
    );
CREATE POLICY dt_caregiver_contacts_write ON dt_caregiver_contacts FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true'
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_contacts.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_contacts.company_id))))
    WITH CHECK (
        (company_id IS NULL AND current_setting('grompil.platform_operator', true) = 'true')
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_contacts.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_contacts.company_id)))
    );

DROP POLICY IF EXISTS dt_caregiver_locations_scope ON dt_caregiver_locations;
DROP POLICY IF EXISTS dt_caregiver_locations_select ON dt_caregiver_locations;
DROP POLICY IF EXISTS dt_caregiver_locations_write ON dt_caregiver_locations;
CREATE POLICY dt_caregiver_locations_select ON dt_caregiver_locations FOR SELECT
    USING (
        (company_id IS NULL AND (current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_locations.caregiver_code AND bc.owner_company_id IS NULL)))
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_locations.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_locations.company_id)))
    );
CREATE POLICY dt_caregiver_locations_write ON dt_caregiver_locations FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true'
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_locations.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_locations.company_id))))
    WITH CHECK (
        (company_id IS NULL AND current_setting('grompil.platform_operator', true) = 'true')
        OR (company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (SELECT 1 FROM bt_caregivers bc WHERE bc.caregiver_code = dt_caregiver_locations.caregiver_code
                AND (bc.owner_company_id IS NULL OR bc.owner_company_id = dt_caregiver_locations.company_id)))
    );

DO $$
DECLARE
    app_role NAME;
BEGIN
    SELECT rolname INTO app_role
    FROM pg_roles
    WHERE rolname::text = current_database() || '_app'
       OR rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app')
    ORDER BY CASE WHEN rolname::text = current_database() || '_app' THEN 0 ELSE 1 END
    LIMIT 1;
    IF app_role IS NOT NULL THEN
        EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON bt_caregivers, dt_caregiver_identifiers, dt_caregiver_contacts, dt_caregiver_locations TO %I', app_role);
        EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE dt_caregiver_identifiers_id_seq, dt_caregiver_contacts_id_seq, dt_caregiver_locations_id_seq TO %I', app_role);
    END IF;
END;
$$;

COMMIT;
