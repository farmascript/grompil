BEGIN;

CREATE TABLE IF NOT EXISTS dt_company_customer_profiles (
    company_id BIGINT NOT NULL,
    customer_code VARCHAR(6) NOT NULL,
    fixed_caregiver_code VARCHAR(6) REFERENCES bt_caregivers(caregiver_code) ON DELETE RESTRICT,
    is_home_pharmacist BOOLEAN NOT NULL DEFAULT FALSE,
    print_bvac BOOLEAN NOT NULL DEFAULT FALSE,
    invoice_group VARCHAR(80),
    vat_number VARCHAR(32),
    created_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    updated_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (company_id, customer_code),
    FOREIGN KEY (company_id, customer_code)
        REFERENCES bt_customers(company_id, customer_code) ON DELETE RESTRICT,
    CHECK (invoice_group IS NULL OR btrim(invoice_group) <> ''),
    CHECK (vat_number IS NULL OR btrim(vat_number) <> '')
);

CREATE INDEX IF NOT EXISTS dt_company_customer_profiles_caregiver_idx
    ON dt_company_customer_profiles (company_id, fixed_caregiver_code)
    WHERE fixed_caregiver_code IS NOT NULL;

ALTER TABLE dt_company_customer_profiles ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS dt_company_customer_profiles_company_scope ON dt_company_customer_profiles;
CREATE POLICY dt_company_customer_profiles_company_scope ON dt_company_customer_profiles FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = dt_company_customer_profiles.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = dt_company_customer_profiles.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
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
        EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON dt_company_customer_profiles TO %I', app_role);
    END IF;
END;
$$;

COMMIT;
