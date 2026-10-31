/**
 *
 * @date_cre: Wed 2026-10-14 19:27:16
 * @date_mut: Wed 2026-10-21 18:39:38
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Creates and updates PostgreSQL tables, constraints, policies, and grants.
 *
 */

-- Voer dit bestand in Adminer uit nadat je de database [ grompil  | grompilc | grompilc_test ] hebt geselecteerd.
-- Maak de PostgreSQL-rol [ grompil_app | grompilc_app | grompilc_test_app ] vooraf aan in Adminer.
BEGIN;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(240) NOT NULL DEFAULT '',
    level INTEGER NOT NULL DEFAULT 200 CHECK (level >= 0),
    permissions JSONB NOT NULL DEFAULT '[]'::jsonb
        CHECK (jsonb_typeof(permissions) = 'array'),
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    name_first VARCHAR(120) NOT NULL DEFAULT '',
    name_last VARCHAR(120) NOT NULL DEFAULT '',
    street VARCHAR(160) NOT NULL DEFAULT '',
    house_number VARCHAR(32) NOT NULL DEFAULT '',
    box_number VARCHAR(32) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    country_code CHAR(2) NOT NULL DEFAULT 'BE' CHECK (country_code ~ '^[A-Z]{2}$'),
    address_reference_id TEXT NOT NULL DEFAULT '',
    acronym VARCHAR(80),
    password_hash VARCHAR(255) NOT NULL,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    approval_status VARCHAR(12) NOT NULL DEFAULT 'approved'
        CHECK (approval_status IN ('pending', 'approved', 'rejected')),
    approved_at TIMESTAMPTZ,
    approved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE
        CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
    name VARCHAR(160) NOT NULL,
    legal_name VARCHAR(255) NOT NULL DEFAULT '',
    enterprise_number VARCHAR(32) NOT NULL DEFAULT '',
    vat_number VARCHAR(32) NOT NULL DEFAULT '',
    billing_email VARCHAR(254) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    street VARCHAR(160) NOT NULL DEFAULT '',
    house_number VARCHAR(32) NOT NULL DEFAULT '',
    box_number VARCHAR(32) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    address_reference_id TEXT NOT NULL DEFAULT '',
    country_code CHAR(2) NOT NULL DEFAULT 'BE'
        CHECK (country_code ~ '^[A-Z]{2}$'),
    default_language VARCHAR(2) NOT NULL DEFAULT 'NL'
        CHECK (default_language IN ('NL', 'FR', 'EN', 'DE')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- A company may have an external permit number; keep the tenant field generic.
DROP INDEX IF EXISTS companies_enterprise_number_unique_idx;
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'companies' AND column_name = 'pharmacy_permit_number'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'companies' AND column_name = 'company_permit_number'
    ) THEN
        ALTER TABLE companies RENAME COLUMN pharmacy_permit_number TO company_permit_number;
    ELSIF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'companies' AND column_name = 'pharmacy_permit_number'
    ) THEN
        UPDATE companies
        SET company_permit_number = pharmacy_permit_number
        WHERE company_permit_number = '' AND pharmacy_permit_number <> '';
        ALTER TABLE companies DROP COLUMN pharmacy_permit_number;
    END IF;
END;
$$;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS company_permit_number VARCHAR(32) NOT NULL DEFAULT '';
DROP INDEX IF EXISTS companies_pharmacy_permit_number_unique_idx;
CREATE UNIQUE INDEX IF NOT EXISTS companies_company_permit_number_unique_idx
    ON companies (company_permit_number)
    WHERE company_permit_number <> '';

ALTER TABLE companies ADD COLUMN IF NOT EXISTS is_public BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS legal_name VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS store_theme VARCHAR(64) NOT NULL DEFAULT 'default';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS store_logo_file VARCHAR(128) NOT NULL DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS store_hero_image_file VARCHAR(128) NOT NULL DEFAULT '';

ALTER TABLE users ADD COLUMN IF NOT EXISTS company_id BIGINT
    REFERENCES companies(id) ON DELETE RESTRICT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_key;
CREATE UNIQUE INDEX IF NOT EXISTS users_company_id_id_unique_idx
    ON users (company_id, id);

CREATE TABLE IF NOT EXISTS company_domains (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    domain VARCHAR(253) NOT NULL UNIQUE,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (domain = lower(domain)),
    CHECK (domain !~ '[/@: ]')
);
CREATE INDEX IF NOT EXISTS company_domains_company_lookup_idx
    ON company_domains (company_id, is_active, is_verified);

CREATE UNIQUE INDEX IF NOT EXISTS company_domains_one_primary_idx
    ON company_domains (company_id) WHERE is_primary AND is_active AND is_verified;

CREATE TABLE IF NOT EXISTS company_pages (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    page_key VARCHAR(32) NOT NULL CHECK (page_key IN ('home', 'services', 'about_us', 'contact')),
    language_code VARCHAR(2) NOT NULL CHECK (language_code IN ('NL', 'FR', 'EN', 'DE')),
    title VARCHAR(200) NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    is_published BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, page_key, language_code)
);
CREATE INDEX IF NOT EXISTS company_pages_public_lookup_idx
    ON company_pages (company_id, page_key, language_code) WHERE is_published;

CREATE TABLE IF NOT EXISTS company_roles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(240) NOT NULL DEFAULT '',
    level INTEGER NOT NULL DEFAULT 200 CHECK (level >= 0),
    permissions JSONB NOT NULL DEFAULT '[]'::jsonb
        CHECK (jsonb_typeof(permissions) = 'array'),
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, name),
    UNIQUE (company_id, id)
);

CREATE TABLE IF NOT EXISTS company_users (
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    user_id BIGINT NOT NULL,
    company_role_id BIGINT NOT NULL REFERENCES company_roles(id) ON DELETE RESTRICT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    approval_status VARCHAR(12) NOT NULL DEFAULT 'approved'
        CHECK (approval_status IN ('pending', 'approved', 'rejected')),
    approved_at TIMESTAMPTZ,
    approved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (company_id, user_id)
);
CREATE INDEX IF NOT EXISTS company_users_user_lookup_idx
    ON company_users (user_id, company_id);

ALTER TABLE company_users ADD COLUMN IF NOT EXISTS company_role_id BIGINT
    REFERENCES company_roles(id) ON DELETE RESTRICT;

ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_company_role_same_company_fk;
ALTER TABLE company_users ADD CONSTRAINT company_users_company_role_same_company_fk
    FOREIGN KEY (company_id, company_role_id)
    REFERENCES company_roles (company_id, id) ON DELETE RESTRICT;

ALTER TABLE roles ADD COLUMN IF NOT EXISTS level INTEGER NOT NULL DEFAULT 200;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approval_status VARCHAR(12) NOT NULL DEFAULT 'approved';
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at TIMESTAMPTZ;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by BIGINT REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS name_first VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS name_last VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS street VARCHAR(160) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS house_number VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS box_number VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS country_code CHAR(2) NOT NULL DEFAULT 'BE';
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_country_code_check;
ALTER TABLE users ADD CONSTRAINT users_country_code_check CHECK (country_code ~ '^[A-Z]{2}$');
ALTER TABLE users ADD COLUMN IF NOT EXISTS address_reference_id TEXT NOT NULL DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS house_number VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS box_number VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE companies ADD COLUMN IF NOT EXISTS address_reference_id TEXT NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN IF NOT EXISTS acronym VARCHAR(80);
ALTER TABLE users ALTER COLUMN display_name TYPE VARCHAR(255);

CREATE TABLE IF NOT EXISTS company_navigation_preferences (
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    application VARCHAR(80) NOT NULL
        CHECK (application ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
    configuration JSONB NOT NULL DEFAULT '{}'::jsonb
        CHECK (jsonb_typeof(configuration) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (company_id, application)
);

CREATE TABLE IF NOT EXISTS platform_navigation_preferences (
    application VARCHAR(80) PRIMARY KEY
        CHECK (application ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
    configuration JSONB NOT NULL DEFAULT '{"groups": [], "assignments": {}}'::jsonb
        CHECK (jsonb_typeof(configuration) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS platform_system_settings (
    "key" VARCHAR(80) PRIMARY KEY CHECK ("key" ~ '^[a-z][a-z0-9_]{0,79}$'),
    value_integer INTEGER NOT NULL CHECK (value_integer > 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE platform_system_settings IS 'Non-secret platform runtime parameters; do not store credentials or private keys here.';
INSERT INTO platform_system_settings ("key", value_integer)
VALUES ('password_reset_max_requests', 3), ('password_reset_window_minutes', 15)
ON CONFLICT ("key") DO NOTHING;

CREATE TABLE IF NOT EXISTS dd_varia (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    list_key VARCHAR(80) NOT NULL
        CHECK (list_key ~ '^[a-z][a-z0-9_-]{0,79}$'),
    "key" VARCHAR(80) NOT NULL
        CHECK ("key" ~ '^(_root|[a-z][a-z0-9_-]{0,79})$'),
    content VARCHAR(130) NOT NULL
        CHECK (content ~ '^@[A-Za-z0-9_.-]{1,120}@$'),
    parent_id BIGINT,
    info TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (list_key, id),
    UNIQUE (list_key, "key"),
    CONSTRAINT dd_varia_parent_same_list_fk
        FOREIGN KEY (list_key, parent_id) REFERENCES dd_varia (list_key, id) ON DELETE RESTRICT,
    CONSTRAINT dd_varia_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id)
);
COMMENT ON TABLE dd_varia IS 'Platform-managed hierarchical lookup values. content stores an @translation_key@ token.';
CREATE INDEX IF NOT EXISTS dd_varia_lookup_idx
    ON dd_varia (list_key, parent_id, is_active, sort_order, id);
UPDATE roles SET level = 10 WHERE lower(name) = 'beheerder' AND level = 200;
UPDATE roles SET level = 110 WHERE lower(name) = 'apotheker' AND level = 200;
UPDATE roles SET level = 120 WHERE lower(name) = 'medewerker' AND level = 200;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_approval_status_check;
ALTER TABLE users ADD CONSTRAINT users_approval_status_check
    CHECK (approval_status IN ('pending', 'approved', 'rejected'));

CREATE TABLE IF NOT EXISTS access_ip_allowlist (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT REFERENCES companies(id) ON DELETE CASCADE,
    scope VARCHAR(12) NOT NULL CHECK (scope IN ('admin', 'staff')),
    network INET NOT NULL,
    description VARCHAR(160) NOT NULL DEFAULT '',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (scope, network)
);

ALTER TABLE access_ip_allowlist ADD COLUMN IF NOT EXISTS company_id BIGINT
    REFERENCES companies(id) ON DELETE CASCADE;
ALTER TABLE access_ip_allowlist DROP CONSTRAINT IF EXISTS access_ip_allowlist_scope_network_key;
CREATE UNIQUE INDEX IF NOT EXISTS access_ip_allowlist_global_scope_network_idx
    ON access_ip_allowlist (scope, network) WHERE company_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS access_ip_allowlist_company_scope_network_idx
    ON access_ip_allowlist (company_id, scope, network) WHERE company_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS registration_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS registration_attempts_lookup_idx
    ON registration_attempts (ip_address, attempted_at DESC);

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    email VARCHAR(254) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS company_id BIGINT NOT NULL DEFAULT 0;
ALTER TABLE login_attempts DROP CONSTRAINT IF EXISTS login_attempts_company_id_check;
ALTER TABLE login_attempts ADD CONSTRAINT login_attempts_company_id_check CHECK (company_id >= 0);

CREATE TABLE IF NOT EXISTS substitutions (
    "key" VARCHAR(120) NOT NULL,
    "value" TEXT NOT NULL,
    code VARCHAR(8) NOT NULL,
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY ("key", code)
);

ALTER TABLE substitutions ADD COLUMN IF NOT EXISTS company_id BIGINT NOT NULL DEFAULT 0;
ALTER TABLE substitutions DROP CONSTRAINT IF EXISTS substitutions_pkey;
ALTER TABLE substitutions ADD CONSTRAINT substitutions_pkey PRIMARY KEY (company_id, "key", code);
ALTER TABLE substitutions DROP CONSTRAINT IF EXISTS substitutions_company_id_check;
ALTER TABLE substitutions ADD CONSTRAINT substitutions_company_id_check CHECK (company_id >= 0);

INSERT INTO substitutions (company_id, "key", "value", code, updated_at)
SELECT company_id, "key", "value", upper(code), updated_at
FROM substitutions
WHERE code <> upper(code)
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = EXCLUDED.updated_at;
DELETE FROM substitutions WHERE code <> upper(code);

DROP INDEX IF EXISTS login_attempts_lookup_idx;
CREATE INDEX IF NOT EXISTS login_attempts_company_lookup_idx
    ON login_attempts (company_id, email, ip_address, attempted_at DESC);

CREATE TABLE IF NOT EXISTS kbo_lookup_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    enterprise_number VARCHAR(16) NOT NULL,
    request_type VARCHAR(40) NOT NULL DEFAULT 'read_enterprise',
    requested_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS kbo_lookup_attempts_requested_at_idx
    ON kbo_lookup_attempts (requested_at DESC);
ALTER TABLE kbo_lookup_attempts ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS kbo_lookup_attempts_platform_only ON kbo_lookup_attempts;
CREATE POLICY kbo_lookup_attempts_platform_only ON kbo_lookup_attempts
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

INSERT INTO roles (name, description, permissions, is_system)
SELECT seed.name, seed.description, seed.permissions::jsonb, seed.is_system
FROM (VALUES
    (
        'Beheerder',
        'Gebruikers en rollen beheren.',
        '["users.manage", "roles.manage", "translations.manage"]',
        TRUE
    ),
    ('Apotheker', 'Apotheker.', '[]', TRUE),
    ('Medewerker', 'Medewerker.', '[]', TRUE),
    ('Publiek', 'Publieke klant.', '[]', TRUE)
 ) AS seed(name, description, permissions, is_system)
WHERE NOT EXISTS (
    SELECT 1 FROM roles AS existing WHERE lower(existing.name) = lower(seed.name)
)
ON CONFLICT (name) DO NOTHING;

UPDATE roles
SET is_system = TRUE, updated_at = now()
WHERE lower(name) IN ('beheerder', 'apotheker', 'medewerker', 'publiek')
  AND is_system = FALSE;

UPDATE roles SET level = 10 WHERE lower(name) = 'beheerder' AND level = 200;
UPDATE roles SET level = 110 WHERE lower(name) = 'apotheker' AND level = 200;
UPDATE roles SET level = 120 WHERE lower(name) = 'medewerker' AND level = 200;
UPDATE roles
SET permissions = permissions || '["translations.manage"]'::jsonb
WHERE lower(name) = 'beheerder' AND NOT (permissions ? 'translations.manage');
UPDATE roles
SET permissions = permissions || '["companies.manage"]'::jsonb
WHERE lower(name) = 'beheerder' AND NOT (permissions ? 'companies.manage');
UPDATE company_roles
SET permissions = permissions || '["company.translations.manage"]'::jsonb
WHERE lower(name) = 'beheerder' AND NOT (permissions ? 'company.translations.manage');

INSERT INTO company_roles (company_id, name, description, level, permissions, is_system)
SELECT c.id, r.name, r.description, r.level,
       CASE WHEN lower(r.name) = 'beheerder'
                     THEN '["company.users.manage", "company.roles.manage", "company.settings.manage", "company.translations.manage"]'::jsonb
            ELSE '[]'::jsonb
       END,
       r.is_system
FROM companies AS c
CROSS JOIN roles AS r
ON CONFLICT (company_id, name) DO NOTHING;

UPDATE company_roles
SET is_system = TRUE, updated_at = now()
WHERE lower(name) IN ('beheerder', 'apotheker', 'medewerker', 'publiek')
  AND is_system = FALSE;

-- Give every existing tenant membership its own login identity row. Keep global
-- platform identities separate; tenant logins are scoped by (company_id, email).
CREATE TEMP TABLE grompil_legacy_tenant_users ON COMMIT DROP AS
SELECT DISTINCT cu.user_id
FROM company_users AS cu
JOIN users AS u ON u.id = cu.user_id
WHERE u.company_id IS NULL;

INSERT INTO users (
    email, display_name, name_first, name_last, street, house_number, box_number,
    postal_code, city, country_code, address_reference_id, acronym, password_hash, role_id,
    is_active, approval_status, approved_at, approved_by, last_login_at,
    created_at, updated_at, company_id
)
SELECT u.email, u.display_name, u.name_first, u.name_last, u.street, u.house_number, u.box_number,
       u.postal_code, u.city, u.country_code, u.address_reference_id, u.acronym, u.password_hash,
       (SELECT id FROM roles WHERE lower(name) = 'publiek' ORDER BY id LIMIT 1),
       u.is_active, u.approval_status, u.approved_at, u.approved_by, u.last_login_at,
       u.created_at, u.updated_at, cu.company_id
FROM company_users AS cu
JOIN users AS u ON u.id = cu.user_id
WHERE u.company_id IS NULL;

UPDATE company_users AS cu
SET user_id = tenant_user.id
FROM users AS original_user, users AS tenant_user
WHERE original_user.id = cu.user_id
  AND original_user.company_id IS NULL
  AND tenant_user.company_id = cu.company_id
  AND lower(tenant_user.email) = lower(original_user.email);

UPDATE users AS original_user
SET is_active = FALSE, updated_at = now()
FROM grompil_legacy_tenant_users AS legacy, roles AS r
WHERE original_user.id = legacy.user_id
  AND r.id = original_user.role_id
  AND r.level >= 100;

CREATE UNIQUE INDEX IF NOT EXISTS users_global_email_unique_idx
    ON users (lower(email)) WHERE company_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS users_company_email_unique_idx
    ON users (company_id, lower(email)) WHERE company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_customer_name_prefix_idx
    ON users (company_id, lower(display_name) text_pattern_ops) WHERE company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_customer_email_prefix_idx
    ON users (company_id, lower(email) text_pattern_ops) WHERE company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_customer_acronym_prefix_idx
    ON users (company_id, lower(acronym) text_pattern_ops)
    WHERE company_id IS NOT NULL AND acronym IS NOT NULL AND acronym <> '';
CREATE UNIQUE INDEX IF NOT EXISTS users_company_acronym_unique_idx
    ON users (company_id, lower(acronym))
    WHERE company_id IS NOT NULL AND acronym IS NOT NULL AND acronym <> '';
CREATE UNIQUE INDEX IF NOT EXISTS users_global_acronym_unique_idx
    ON users (lower(acronym))
    WHERE company_id IS NULL AND acronym IS NOT NULL AND acronym <> '';

ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_user_id_fkey;
ALTER TABLE company_users DROP CONSTRAINT IF EXISTS company_users_user_same_company_fk;
ALTER TABLE company_users ADD CONSTRAINT company_users_user_same_company_fk
    FOREIGN KEY (company_id, user_id) REFERENCES users (company_id, id) ON DELETE CASCADE;

-- Migrate the previous global role link once, then remove that legacy column.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'company_users'
          AND column_name = 'role_id'
    ) THEN
        EXECUTE $migration$
            UPDATE company_users AS cu
            SET company_role_id = cr.id
            FROM roles AS r, company_roles AS cr
            WHERE cu.company_role_id IS NULL
              AND cu.role_id = r.id
              AND cr.company_id = cu.company_id
              AND cr.name = r.name
        $migration$;
    END IF;
END
$$;

ALTER TABLE company_users ALTER COLUMN company_role_id SET NOT NULL;
ALTER TABLE company_users DROP COLUMN IF EXISTS role_id;
CREATE INDEX IF NOT EXISTS company_roles_company_level_idx
    ON company_roles (company_id, level);

ALTER TABLE companies ENABLE ROW LEVEL SECURITY;
ALTER TABLE company_domains ENABLE ROW LEVEL SECURITY;
ALTER TABLE company_roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE company_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE company_navigation_preferences ENABLE ROW LEVEL SECURITY;
ALTER TABLE platform_navigation_preferences ENABLE ROW LEVEL SECURITY;
ALTER TABLE platform_system_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE dd_varia ENABLE ROW LEVEL SECURITY;
ALTER TABLE access_ip_allowlist ENABLE ROW LEVEL SECURITY;
ALTER TABLE company_pages ENABLE ROW LEVEL SECURITY;
ALTER TABLE substitutions ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS substitutions_visible_to_scope ON substitutions;
CREATE POLICY substitutions_visible_to_scope ON substitutions
    FOR SELECT USING (
        company_id = 0
        OR company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS substitutions_write_to_scope ON substitutions;
CREATE POLICY substitutions_write_to_scope ON substitutions
    FOR ALL USING (
        (company_id = 0 AND current_setting('grompil.platform_operator', true) = 'true')
        OR company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        (company_id = 0 AND current_setting('grompil.platform_operator', true) = 'true')
        OR company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS companies_visible_to_public_members_or_platform ON companies;
CREATE POLICY companies_visible_to_public_members_or_platform ON companies
    FOR SELECT USING (
        (is_active AND is_public)
        OR id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR id IN (
            SELECT cu.company_id FROM company_users AS cu
            WHERE cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR (is_active AND current_setting('grompil.company_login_lookup', true) = 'true')
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS companies_platform_write ON companies;
CREATE POLICY companies_platform_write ON companies
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DROP POLICY IF EXISTS company_domains_verified_read ON company_domains;
CREATE POLICY company_domains_verified_read ON company_domains
    FOR SELECT USING (
        (is_active AND is_verified)
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_domains_platform_write ON company_domains;
CREATE POLICY company_domains_platform_write ON company_domains
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DROP POLICY IF EXISTS company_pages_public_read ON company_pages;
CREATE POLICY company_pages_public_read ON company_pages
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR company_id IN (SELECT id FROM companies WHERE is_active AND is_public)
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_pages_tenant_or_platform_write ON company_pages;
CREATE POLICY company_pages_tenant_or_platform_write ON company_pages
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_roles_visible_to_member_or_platform ON company_roles;
CREATE POLICY company_roles_visible_to_member_or_platform ON company_roles
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR company_id IN (
            SELECT cu.company_id FROM company_users AS cu
            WHERE cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_roles_member_or_platform_write ON company_roles;
CREATE POLICY company_roles_member_or_platform_write ON company_roles
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_users_self_read ON company_users;
CREATE POLICY company_users_self_read ON company_users
    FOR SELECT USING (
        user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
    );
DROP POLICY IF EXISTS company_users_tenant_or_platform ON company_users;
CREATE POLICY company_users_tenant_or_platform ON company_users
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_navigation_preferences_read ON company_navigation_preferences;
CREATE POLICY company_navigation_preferences_read ON company_navigation_preferences
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
                  AND cu.is_active AND cu.approval_status = 'approved'
            )
        )
    );

DROP POLICY IF EXISTS platform_navigation_preferences_read ON platform_navigation_preferences;
CREATE POLICY platform_navigation_preferences_read ON platform_navigation_preferences
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_navigation_preferences_admin_write ON platform_navigation_preferences;
CREATE POLICY platform_navigation_preferences_admin_write ON platform_navigation_preferences
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');
DROP POLICY IF EXISTS platform_system_settings_read ON platform_system_settings;
CREATE POLICY platform_system_settings_read ON platform_system_settings
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_system_settings_admin_write ON platform_system_settings;
CREATE POLICY platform_system_settings_admin_write ON platform_system_settings
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');
DROP POLICY IF EXISTS dd_varia_visible_active_or_platform ON dd_varia;
CREATE POLICY dd_varia_visible_active_or_platform ON dd_varia
    FOR SELECT USING (
        is_active OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS dd_varia_platform_write ON dd_varia;
CREATE POLICY dd_varia_platform_write ON dd_varia
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');
DROP POLICY IF EXISTS company_navigation_preferences_admin_write ON company_navigation_preferences;
CREATE POLICY company_navigation_preferences_admin_write ON company_navigation_preferences
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                JOIN company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM company_users AS cu
                JOIN company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = company_navigation_preferences.company_id
                  AND cu.user_id = NULLIF(current_setting('grompil.user_id', true), '')::BIGINT
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    );

DROP POLICY IF EXISTS access_ip_allowlist_tenant_or_platform ON access_ip_allowlist;
CREATE POLICY access_ip_allowlist_tenant_or_platform ON access_ip_allowlist
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR (company_id IS NULL AND current_setting('grompil.platform_operator', true) = 'true')
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR (company_id IS NULL AND current_setting('grompil.platform_operator', true) = 'true')
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

-- Password reset support.
ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_version BIGINT NOT NULL DEFAULT 0 CHECK (auth_version >= 0);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
---    token_hash CHAR(64) PRIMARY KEY CHECK (token_hash ~ '^[a-f0-9]{64}),
	token_hash CHAR(64) PRIMARY KEY CHECK (token_hash ~ '^[a-f0-9]{64}$'),
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    company_id BIGINT REFERENCES companies(id) ON DELETE CASCADE,
    password_hash_at_issue VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_reset_tokens_expiry_idx ON password_reset_tokens (expires_at);

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    identifier_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_reset_attempts_ip_idx ON password_reset_attempts (ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS password_reset_attempts_identifier_idx ON password_reset_attempts (company_id, identifier_hash, attempted_at);

COMMIT;
