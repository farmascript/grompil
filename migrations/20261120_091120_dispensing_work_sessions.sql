BEGIN;

CREATE TABLE IF NOT EXISTS dt_dispensing_sessions (
    id UUID PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE RESTRICT,
    created_by_user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE RESTRICT,
    status_list_key VARCHAR(80) NOT NULL DEFAULT 'dispensing_statuses'
        CHECK (status_list_key = 'dispensing_statuses'),
    status_key VARCHAR(80) NOT NULL DEFAULT 'prescription',
    state VARCHAR(16) NOT NULL DEFAULT 'open' CHECK (state IN ('open', 'completed', 'cancelled')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    completed_at TIMESTAMPTZ,
    UNIQUE (company_id, id),
    FOREIGN KEY (status_list_key, status_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS dt_dispensing_sessions_open_idx
    ON dt_dispensing_sessions (company_id, created_by_user_code, updated_at DESC)
    WHERE state = 'open';

CREATE TABLE IF NOT EXISTS dt_dispensing_parties (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL,
    dispensing_session_id UUID NOT NULL,
    party_type VARCHAR(16) NOT NULL CHECK (party_type IN ('customer', 'anonymous')),
    customer_code VARCHAR(6),
    display_name VARCHAR(260) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, dispensing_session_id, id),
    FOREIGN KEY (company_id, dispensing_session_id)
        REFERENCES dt_dispensing_sessions(company_id, id) ON DELETE CASCADE,
    FOREIGN KEY (company_id, customer_code)
        REFERENCES bt_customers(company_id, customer_code) ON DELETE RESTRICT,
    CHECK (
        (party_type = 'customer' AND customer_code IS NOT NULL)
        OR (party_type = 'anonymous' AND customer_code IS NULL)
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS dt_dispensing_parties_customer_uidx
    ON dt_dispensing_parties (company_id, dispensing_session_id, customer_code)
    WHERE customer_code IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS dt_dispensing_parties_anonymous_uidx
    ON dt_dispensing_parties (company_id, dispensing_session_id)
    WHERE party_type = 'anonymous';

CREATE TABLE IF NOT EXISTS dt_dispensing_lines (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL,
    dispensing_session_id UUID NOT NULL,
    party_id BIGINT NOT NULL,
    status_list_key VARCHAR(80) NOT NULL DEFAULT 'dispensing_statuses'
        CHECK (status_list_key = 'dispensing_statuses'),
    status_key VARCHAR(80) NOT NULL,
    cnk VARCHAR(7) NOT NULL,
    amp_code VARCHAR(80) NOT NULL DEFAULT '',
    ampp_code VARCHAR(80) NOT NULL DEFAULT '',
    product_name_nl VARCHAR(500) NOT NULL,
    product_name_fr VARCHAR(500) NOT NULL DEFAULT '',
    package_display_nl VARCHAR(250) NOT NULL DEFAULT '',
    package_display_fr VARCHAR(250) NOT NULL DEFAULT '',
    quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity BETWEEN 1 AND 999),
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (company_id, dispensing_session_id)
        REFERENCES dt_dispensing_sessions(company_id, id) ON DELETE CASCADE,
    FOREIGN KEY (company_id, dispensing_session_id, party_id)
        REFERENCES dt_dispensing_parties(company_id, dispensing_session_id, id) ON DELETE CASCADE,
    FOREIGN KEY (status_list_key, status_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT
);

CREATE UNIQUE INDEX IF NOT EXISTS dt_dispensing_lines_product_uidx
    ON dt_dispensing_lines (
        company_id, dispensing_session_id, party_id, cnk, amp_code, ampp_code, status_key
    );
CREATE INDEX IF NOT EXISTS dt_dispensing_lines_session_idx
    ON dt_dispensing_lines (company_id, dispensing_session_id, party_id, sort_order, id);

ALTER TABLE dt_dispensing_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_dispensing_parties ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_dispensing_lines ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS dt_dispensing_sessions_owner_scope ON dt_dispensing_sessions;
CREATE POLICY dt_dispensing_sessions_owner_scope ON dt_dispensing_sessions FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
        AND state = 'open'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );

DROP POLICY IF EXISTS dt_dispensing_parties_owner_scope ON dt_dispensing_parties;
CREATE POLICY dt_dispensing_parties_owner_scope ON dt_dispensing_parties FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')
        ::BIGINT AND EXISTS (
            SELECT 1 FROM dt_dispensing_sessions session
            WHERE session.id = dt_dispensing_parties.dispensing_session_id
              AND session.company_id = dt_dispensing_parties.company_id
              AND session.created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND session.state = 'open'
        )
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')
        ::BIGINT AND EXISTS (
            SELECT 1 FROM dt_dispensing_sessions session
            WHERE session.id = dt_dispensing_parties.dispensing_session_id
              AND session.company_id = dt_dispensing_parties.company_id
              AND session.created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND session.state = 'open'
        )
    );

DROP POLICY IF EXISTS dt_dispensing_lines_owner_scope ON dt_dispensing_lines;
CREATE POLICY dt_dispensing_lines_owner_scope ON dt_dispensing_lines FOR ALL
    USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')
        ::BIGINT AND EXISTS (
            SELECT 1 FROM dt_dispensing_sessions session
            WHERE session.id = dt_dispensing_lines.dispensing_session_id
              AND session.company_id = dt_dispensing_lines.company_id
              AND session.created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND session.state = 'open'
        )
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')
        ::BIGINT AND EXISTS (
            SELECT 1 FROM dt_dispensing_sessions session
            WHERE session.id = dt_dispensing_lines.dispensing_session_id
              AND session.company_id = dt_dispensing_lines.company_id
              AND session.created_by_user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND session.state = 'open'
        )
    );

DO $$
DECLARE
    app_role TEXT;
BEGIN
    FOR app_role IN
        SELECT rolname FROM pg_roles
        WHERE rolname IN ('grompil_app', 'grompil_test_app', 'grompilc_app')
    LOOP
        EXECUTE format(
            'GRANT SELECT, INSERT, UPDATE, DELETE ON dt_dispensing_sessions, dt_dispensing_parties, dt_dispensing_lines TO %I',
            app_role
        );
        EXECUTE format(
            'GRANT USAGE, SELECT ON SEQUENCE dt_dispensing_parties_id_seq, dt_dispensing_lines_id_seq TO %I',
            app_role
        );
    END LOOP;
END;
$$;

COMMENT ON TABLE dt_dispensing_sessions IS
    'Open company dispensing work sessions. Each browser tab receives its own UUID.';
COMMENT ON TABLE dt_dispensing_parties IS
    'Customer or anonymous recipient groups belonging to an open dispensing session.';
COMMENT ON TABLE dt_dispensing_lines IS
    'Editable SAM product lines in an open dispensing session.';

COMMIT;

