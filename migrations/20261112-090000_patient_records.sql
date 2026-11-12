BEGIN;

CREATE TABLE IF NOT EXISTS bt_patients (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE RESTRICT,
    patient_code VARCHAR(6) NOT NULL UNIQUE
        CHECK (patient_code ~ '^[A-Z0-9]{6}$'
           AND length(regexp_replace(patient_code, '[A-Z]', '', 'g')) = 3
           AND length(regexp_replace(patient_code, '[0-9]', '', 'g')) = 3),
    name_first VARCHAR(120) NOT NULL,
    name_last VARCHAR(120) NOT NULL,
    date_of_birth DATE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    updated_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, patient_code)
);
CREATE INDEX IF NOT EXISTS bt_patients_company_name_idx
    ON bt_patients (company_id, lower(name_last), lower(name_first)) WHERE is_active;

CREATE TABLE IF NOT EXISTS dt_patient_locations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL,
    patient_code VARCHAR(6) NOT NULL,
    address_type_key VARCHAR(80) NOT NULL DEFAULT 'residential',
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
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    updated_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (company_id, patient_code) REFERENCES bt_patients(company_id, patient_code) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_patient_locations_one_primary_idx
    ON dt_patient_locations (company_id, patient_code) WHERE is_primary AND is_active;
CREATE INDEX IF NOT EXISTS dt_patient_locations_lookup_idx
    ON dt_patient_locations (company_id, patient_code, is_active, sort_order, id);

CREATE TABLE IF NOT EXISTS st_patient_audit_log (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE RESTRICT,
    patient_code VARCHAR(6) NOT NULL,
    entity_type VARCHAR(24) NOT NULL CHECK (entity_type IN ('patient', 'location')),
    action VARCHAR(12) NOT NULL CHECK (action IN ('insert', 'update')),
    changed_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    changed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    before_data JSONB,
    after_data JSONB NOT NULL,
    FOREIGN KEY (company_id, patient_code) REFERENCES bt_patients(company_id, patient_code) ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS st_patient_audit_log_patient_idx
    ON st_patient_audit_log (company_id, patient_code, changed_at DESC);

UPDATE ct_company_roles
SET permissions = permissions || '["pharmacy.dispense"]'::jsonb, updated_at = now()
WHERE lower(name) = 'apotheker'
  AND NOT (permissions ? 'pharmacy.dispense');
UPDATE ft_roles
SET permissions = permissions || '["pharmacy.dispense"]'::jsonb, updated_at = now()
WHERE lower(name) = 'apotheker'
  AND NOT (permissions ? 'pharmacy.dispense');

CREATE OR REPLACE FUNCTION grompil_protect_patient_identity()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Patient records cannot be deleted; set is_active = false instead.' USING ERRCODE = '55000';
    END IF;
    IF NEW.patient_code IS DISTINCT FROM OLD.patient_code OR NEW.company_id IS DISTINCT FROM OLD.company_id THEN
        RAISE EXCEPTION 'Patient code and company are immutable after creation.' USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS bt_patients_identity_guard ON bt_patients;
CREATE TRIGGER bt_patients_identity_guard
    BEFORE UPDATE OR DELETE ON bt_patients
    FOR EACH ROW EXECUTE FUNCTION grompil_protect_patient_identity();

CREATE OR REPLACE FUNCTION grompil_audit_patient_change()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    actor_code VARCHAR(6);
    row_data JSONB;
BEGIN
    actor_code := NULLIF(current_setting('grompil.user_code', true), '');
    IF actor_code IS NULL THEN
        RAISE EXCEPTION 'Patient changes require an authenticated user context.' USING ERRCODE = '42501';
    END IF;
    row_data := CASE WHEN TG_OP = 'DELETE' THEN to_jsonb(OLD) ELSE to_jsonb(NEW) END;
    INSERT INTO st_patient_audit_log (
        company_id, patient_code, entity_type, action, changed_by_user_code, before_data, after_data
    ) VALUES (
        (row_data->>'company_id')::BIGINT,
        row_data->>'patient_code',
        CASE WHEN TG_TABLE_NAME = 'bt_patients' THEN 'patient' ELSE 'location' END,
        lower(TG_OP), actor_code,
        CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END,
        row_data
    );
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS bt_patients_audit ON bt_patients;
CREATE TRIGGER bt_patients_audit AFTER INSERT OR UPDATE ON bt_patients
    FOR EACH ROW EXECUTE FUNCTION grompil_audit_patient_change();
DROP TRIGGER IF EXISTS dt_patient_locations_audit ON dt_patient_locations;
CREATE TRIGGER dt_patient_locations_audit AFTER INSERT OR UPDATE ON dt_patient_locations
    FOR EACH ROW EXECUTE FUNCTION grompil_audit_patient_change();

ALTER TABLE bt_patients ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_patient_locations ENABLE ROW LEVEL SECURITY;
ALTER TABLE st_patient_audit_log ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS bt_patients_company_scope ON bt_patients;
CREATE POLICY bt_patients_company_scope ON bt_patients FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = bt_patients.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = bt_patients.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    );

DROP POLICY IF EXISTS dt_patient_locations_company_scope ON dt_patient_locations;
CREATE POLICY dt_patient_locations_company_scope ON dt_patient_locations FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = dt_patient_locations.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = dt_patient_locations.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    );

DROP POLICY IF EXISTS st_patient_audit_log_company_scope ON st_patient_audit_log;
CREATE POLICY st_patient_audit_log_company_scope ON st_patient_audit_log FOR SELECT
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = st_patient_audit_log.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    );
DROP POLICY IF EXISTS st_patient_audit_log_insert ON st_patient_audit_log;
CREATE POLICY st_patient_audit_log_insert ON st_patient_audit_log FOR INSERT
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND changed_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );

COMMIT;
