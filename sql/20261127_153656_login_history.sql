BEGIN;

CREATE TABLE IF NOT EXISTS st_login_history (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT REFERENCES bt_companies(id) ON DELETE RESTRICT,
    user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    session_audit_id UUID NOT NULL,
    event_key VARCHAR(16) NOT NULL CHECK (event_key IN ('login', 'logout')),
    ip_address VARCHAR(64) NOT NULL,
    event_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS st_login_history_company_time_idx
    ON st_login_history (company_id, event_at DESC);
CREATE INDEX IF NOT EXISTS st_login_history_company_user_time_idx
    ON st_login_history (company_id, user_code, event_at DESC);

ALTER TABLE st_login_history ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS st_login_history_select ON st_login_history;
CREATE POLICY st_login_history_select ON st_login_history FOR SELECT
    USING (
        current_setting('grompil.platform_operator', true) = 'true'
        OR (
            company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
            AND EXISTS (
                SELECT 1
                FROM ct_company_users cu
                JOIN ct_company_roles cr ON cr.id = cu.company_role_id AND cr.company_id = cu.company_id
                WHERE cu.company_id = st_login_history.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.permissions ? 'company.users.manage'
            )
        )
    );
DROP POLICY IF EXISTS st_login_history_insert ON st_login_history;
CREATE POLICY st_login_history_insert ON st_login_history FOR INSERT
    WITH CHECK (
        company_id IS NOT DISTINCT FROM NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND user_code = NULLIF(current_setting('grompil.user_code', true), '')
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR (
                company_id IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM ct_company_users cu
                    WHERE cu.company_id = st_login_history.company_id
                      AND cu.user_code = st_login_history.user_code
                      AND cu.is_active AND cu.approval_status = 'approved'
                )
            )
        )
    );

DO $$
DECLARE
    app_role TEXT;
BEGIN
    FOR app_role IN
        SELECT rolname FROM pg_roles
        WHERE rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app', 'grompilc_test_app')
    LOOP
        EXECUTE format('GRANT SELECT, INSERT ON st_login_history TO %I', app_role);
        EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE st_login_history_id_seq TO %I', app_role);
    END LOOP;
END;
$$;

COMMIT;
