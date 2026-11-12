BEGIN;

-- Seed the platform-managed company classification list.
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES ('company_classification', '_root', '@dd_varia_root_company_classification@', NULL,
        'Root for company classifications.', 0)
ON CONFLICT (list_key, "key") DO NOTHING;

WITH root AS (
    SELECT id FROM ft_dd_varia WHERE list_key = 'company_classification' AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT 'company_classification', seed.item_key, '@' || seed.translation_key || '@', root.id,
       seed.info, seed.sort_order
FROM root
CROSS JOIN (VALUES
    ('pharmacy', 'company_classification_pharmacy', 'Pharmacy; customers are patients.', 10),
    ('business', 'company_classification_business', 'Business; customers are clients.', 20)
) AS seed(item_key, translation_key, info, sort_order)
ON CONFLICT (list_key, "key") DO NOTHING;

ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS company_classification_id BIGINT;
UPDATE bt_companies
SET company_classification_id = (
    SELECT id FROM ft_dd_varia
    WHERE list_key = 'company_classification' AND "key" = 'pharmacy'
)
WHERE company_classification_id IS NULL;
ALTER TABLE bt_companies ALTER COLUMN company_classification_id SET NOT NULL;
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'bt_companies_classification_fk') THEN
        ALTER TABLE bt_companies ADD CONSTRAINT bt_companies_classification_fk
            FOREIGN KEY (company_classification_id) REFERENCES ft_dd_varia(id) ON DELETE RESTRICT;
    END IF;
END;
$$;
CREATE OR REPLACE FUNCTION grompil_validate_company_classification()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM ft_dd_varia
        WHERE id = NEW.company_classification_id
          AND list_key = 'company_classification'
          AND parent_id IS NOT NULL AND is_active
    ) THEN
        RAISE EXCEPTION 'Company classification must be an active child in company_classification.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
DROP TRIGGER IF EXISTS bt_companies_classification_guard ON bt_companies;
CREATE TRIGGER bt_companies_classification_guard BEFORE INSERT OR UPDATE ON bt_companies
    FOR EACH ROW EXECUTE FUNCTION grompil_validate_company_classification();

-- Preserve existing customer, address and audit rows while applying the new domain terms.
DO $$
BEGIN
    IF to_regclass('public.bt_patients') IS NOT NULL AND to_regclass('public.bt_customers') IS NULL THEN
        ALTER TABLE bt_patients RENAME TO bt_customers;
    END IF;
    IF to_regclass('public.dt_patient_locations') IS NOT NULL AND to_regclass('public.dt_customer_locations') IS NULL THEN
        ALTER TABLE dt_patient_locations RENAME TO dt_customer_locations;
    END IF;
    IF to_regclass('public.st_patient_audit_log') IS NOT NULL AND to_regclass('public.st_customer_audit_log') IS NULL THEN
        ALTER TABLE st_patient_audit_log RENAME TO st_customer_audit_log;
    END IF;
END;
$$;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='bt_customers' AND column_name='patient_code') THEN
        ALTER TABLE bt_customers RENAME COLUMN patient_code TO customer_code;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='dt_customer_locations' AND column_name='patient_code') THEN
        ALTER TABLE dt_customer_locations RENAME COLUMN patient_code TO customer_code;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='st_customer_audit_log' AND column_name='patient_code') THEN
        ALTER TABLE st_customer_audit_log RENAME COLUMN patient_code TO customer_code;
    END IF;
    IF to_regclass('public.bt_patients_id_seq') IS NOT NULL AND to_regclass('public.bt_customers_id_seq') IS NULL THEN
        ALTER SEQUENCE bt_patients_id_seq RENAME TO bt_customers_id_seq;
    END IF;
    IF to_regclass('public.dt_patient_locations_id_seq') IS NOT NULL AND to_regclass('public.dt_customer_locations_id_seq') IS NULL THEN
        ALTER SEQUENCE dt_patient_locations_id_seq RENAME TO dt_customer_locations_id_seq;
    END IF;
    IF to_regclass('public.st_patient_audit_log_id_seq') IS NOT NULL AND to_regclass('public.st_customer_audit_log_id_seq') IS NULL THEN
        ALTER SEQUENCE st_patient_audit_log_id_seq RENAME TO st_customer_audit_log_id_seq;
    END IF;
END;
$$;

ALTER TABLE st_customer_audit_log DROP CONSTRAINT IF EXISTS st_patient_audit_log_entity_type_check;
ALTER TABLE st_customer_audit_log DROP CONSTRAINT IF EXISTS st_customer_audit_entity_type_check;
ALTER TABLE st_customer_audit_log ADD CONSTRAINT st_customer_audit_entity_type_check
    CHECK (entity_type IN ('patient', 'customer', 'location'));

DO $$
BEGIN
    IF to_regclass('public.st_customer_audit_log_patient_idx') IS NOT NULL
       AND to_regclass('public.st_customer_audit_log_customer_idx') IS NULL THEN
        ALTER INDEX st_customer_audit_log_patient_idx RENAME TO st_customer_audit_log_customer_idx;
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION grompil_protect_customer_identity()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Customer records cannot be deleted; set is_active = false instead.' USING ERRCODE = '55000';
    END IF;
    IF NEW.customer_code IS DISTINCT FROM OLD.customer_code OR NEW.company_id IS DISTINCT FROM OLD.company_id THEN
        RAISE EXCEPTION 'Customer code and company are immutable after creation.' USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION grompil_audit_customer_change()
RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    actor_code VARCHAR(6);
    row_data JSONB;
BEGIN
    actor_code := NULLIF(current_setting('grompil.user_code', true), '');
    IF actor_code IS NULL THEN
        RAISE EXCEPTION 'Customer changes require an authenticated user context.' USING ERRCODE = '42501';
    END IF;
    row_data := CASE WHEN TG_OP = 'DELETE' THEN to_jsonb(OLD) ELSE to_jsonb(NEW) END;
    INSERT INTO st_customer_audit_log (
        company_id, customer_code, entity_type, action, changed_by_user_code, before_data, after_data
    ) VALUES (
        (row_data->>'company_id')::BIGINT,
        row_data->>'customer_code',
        CASE WHEN TG_TABLE_NAME = 'bt_customers' THEN 'customer' ELSE 'location' END,
        lower(TG_OP), actor_code,
        CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END,
        row_data
    );
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS bt_patients_identity_guard ON bt_customers;
DROP TRIGGER IF EXISTS bt_customers_identity_guard ON bt_customers;
CREATE TRIGGER bt_customers_identity_guard BEFORE UPDATE OR DELETE ON bt_customers
    FOR EACH ROW EXECUTE FUNCTION grompil_protect_customer_identity();
DROP TRIGGER IF EXISTS bt_patients_audit ON bt_customers;
DROP TRIGGER IF EXISTS bt_customers_audit ON bt_customers;
CREATE TRIGGER bt_customers_audit AFTER INSERT OR UPDATE ON bt_customers
    FOR EACH ROW EXECUTE FUNCTION grompil_audit_customer_change();
DROP TRIGGER IF EXISTS dt_patient_locations_audit ON dt_customer_locations;
DROP TRIGGER IF EXISTS dt_customer_locations_audit ON dt_customer_locations;
CREATE TRIGGER dt_customer_locations_audit AFTER INSERT OR UPDATE ON dt_customer_locations
    FOR EACH ROW EXECUTE FUNCTION grompil_audit_customer_change();

COMMIT;
