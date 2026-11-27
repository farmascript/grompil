-- Store customer identifiers as encrypted, typed detail records.
-- Run as the database owner before deploying code that reads customer identifiers.

CREATE TABLE IF NOT EXISTS dt_customer_identifiers (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL,
    customer_code VARCHAR(6) NOT NULL,
    identifier_type_key VARCHAR(80) NOT NULL
        CHECK (identifier_type_key ~ '^[a-z][a-z0-9_-]{0,79}$'),
    encrypted_value TEXT NOT NULL,
    lookup_hash CHAR(64) NOT NULL CHECK (lookup_hash ~ '^[0-9a-f]{64}$'),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    updated_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (company_id, customer_code) REFERENCES bt_customers(company_id, customer_code)
        ON DELETE RESTRICT
);

CREATE UNIQUE INDEX IF NOT EXISTS dt_customer_identifiers_customer_type_active_idx
    ON dt_customer_identifiers (company_id, customer_code, identifier_type_key) WHERE is_active;
CREATE UNIQUE INDEX IF NOT EXISTS dt_customer_identifiers_company_type_hash_active_idx
    ON dt_customer_identifiers (company_id, identifier_type_key, lookup_hash) WHERE is_active;
CREATE INDEX IF NOT EXISTS dt_customer_identifiers_lookup_idx
    ON dt_customer_identifiers (company_id, customer_code, is_active);

ALTER TABLE dt_customer_identifiers ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS dt_customer_identifiers_company_scope ON dt_customer_identifiers;
CREATE POLICY dt_customer_identifiers_company_scope ON dt_customer_identifiers FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = dt_customer_identifiers.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (current_setting('grompil.platform_operator', true) = 'true' OR EXISTS (
            SELECT 1 FROM ct_company_users cu
            WHERE cu.company_id = dt_customer_identifiers.company_id
              AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        ))
    );

DO $$
DECLARE
    app_role TEXT;
BEGIN
    FOR app_role IN
        SELECT rolname FROM pg_roles
        WHERE rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app', 'grompilc_test_app')
    LOOP
        EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON dt_customer_identifiers TO %I', app_role);
        EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE dt_customer_identifiers_id_seq TO %I', app_role);
    END LOOP;
END;
$$;
