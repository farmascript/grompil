/**
 *
 * @date_cre: Wed 2026-10-14 19:27:16
 * @date_mut: Sat 2026-11-07 14:57:26
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

CREATE TABLE IF NOT EXISTS ft_roles (
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

CREATE TABLE IF NOT EXISTS bt_users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- Nullable during schema migration so legacy membership copies can be backfilled.
    user_code VARCHAR(6),
    display_name VARCHAR(255) NOT NULL,
    name_first VARCHAR(120) NOT NULL DEFAULT '',
    name_last VARCHAR(120) NOT NULL DEFAULT '',
    acronym VARCHAR(80),
    password_hash VARCHAR(255) NOT NULL,
    role_id BIGINT NOT NULL REFERENCES ft_roles(id) ON DELETE RESTRICT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    approval_status VARCHAR(12) NOT NULL DEFAULT 'approved'
        CHECK (approval_status IN ('pending', 'approved', 'rejected')),
    approved_at TIMESTAMPTZ,
    approved_by_user_code VARCHAR(6),
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT users_user_code_format_check CHECK (
        user_code ~ '^[A-Z0-9]{6}$'
        AND length(regexp_replace(user_code, '[A-Z]', '', 'g')) = 3
        AND length(regexp_replace(user_code, '[0-9]', '', 'g')) = 3
    )
);

CREATE TABLE IF NOT EXISTS bt_companies (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE
        CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
    name VARCHAR(160) NOT NULL,
    legal_name VARCHAR(255) NOT NULL DEFAULT '',
    enterprise_number VARCHAR(32) NOT NULL DEFAULT '',
    vat_number VARCHAR(32) NOT NULL DEFAULT '',
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
        WHERE table_schema = 'public' AND table_name = 'bt_companies' AND column_name = 'pharmacy_permit_number'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_companies' AND column_name = 'company_permit_number'
    ) THEN
        ALTER TABLE bt_companies RENAME COLUMN pharmacy_permit_number TO company_permit_number;
    ELSIF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_companies' AND column_name = 'pharmacy_permit_number'
    ) THEN
        UPDATE bt_companies
        SET company_permit_number = pharmacy_permit_number
        WHERE company_permit_number = '' AND pharmacy_permit_number <> '';
        ALTER TABLE bt_companies DROP COLUMN pharmacy_permit_number;
    END IF;
END;
$$;
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS company_permit_number VARCHAR(32) NOT NULL DEFAULT '';
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS enterprise_number_status VARCHAR(80) NOT NULL DEFAULT 'unknown';
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS vat_number_status VARCHAR(80) NOT NULL DEFAULT 'unknown';
UPDATE bt_companies SET enterprise_number_status = 'known'
WHERE enterprise_number <> '' AND enterprise_number_status = 'unknown';
UPDATE bt_companies SET vat_number_status = 'known'
WHERE vat_number <> '' AND vat_number_status = 'unknown';
DROP INDEX IF EXISTS companies_pharmacy_permit_number_unique_idx;
CREATE UNIQUE INDEX IF NOT EXISTS companies_company_permit_number_unique_idx
    ON bt_companies (company_permit_number)
    WHERE company_permit_number <> '';

ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS is_public BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS legal_name VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS store_theme VARCHAR(64) NOT NULL DEFAULT 'default';
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS store_logo_file VARCHAR(128) NOT NULL DEFAULT '';
ALTER TABLE bt_companies ADD COLUMN IF NOT EXISTS store_hero_image_file VARCHAR(128) NOT NULL DEFAULT '';

ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS company_id BIGINT
    REFERENCES bt_companies(id) ON DELETE RESTRICT;
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS user_code VARCHAR(6);
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE bt_users DROP CONSTRAINT IF EXISTS users_email_key;
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'bt_users' AND column_name = 'email'
    ) THEN
        ALTER TABLE bt_users ALTER COLUMN email DROP NOT NULL;
    END IF;
END;
$$;
CREATE UNIQUE INDEX IF NOT EXISTS users_user_code_uidx ON bt_users (user_code);
ALTER TABLE bt_users DROP CONSTRAINT IF EXISTS users_approved_by_user_code_fkey;
ALTER TABLE bt_users ADD CONSTRAINT users_approved_by_user_code_fkey
    FOREIGN KEY (approved_by_user_code) REFERENCES bt_users(user_code) ON DELETE SET NULL;
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'bt_users'::regclass AND conname = 'users_user_code_format_check'
    ) THEN
        ALTER TABLE bt_users ADD CONSTRAINT users_user_code_format_check CHECK (
            user_code ~ '^[A-Z0-9]{6}$'
            AND length(regexp_replace(user_code, '[A-Z]', '', 'g')) = 3
            AND length(regexp_replace(user_code, '[0-9]', '', 'g')) = 3
        ) NOT VALID;
    END IF;
END;
$$;
CREATE UNIQUE INDEX IF NOT EXISTS users_company_user_code_uidx
    ON bt_users (company_id, user_code);

CREATE TABLE IF NOT EXISTS ct_company_domains (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE CASCADE,
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
    ON ct_company_domains (company_id, is_active, is_verified);

CREATE UNIQUE INDEX IF NOT EXISTS company_domains_one_primary_idx
    ON ct_company_domains (company_id) WHERE is_primary AND is_active AND is_verified;

CREATE TABLE IF NOT EXISTS ct_company_pages (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE CASCADE,
    page_key VARCHAR(32) NOT NULL CHECK (page_key IN ('home', 'services', 'about_us', 'contact')),
    language_code VARCHAR(2) NOT NULL CHECK (language_code IN ('NL', 'FR', 'EN', 'DE')),
    title VARCHAR(200) NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    is_published BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (company_id, page_key, language_code)
);
CREATE INDEX IF NOT EXISTS company_pages_public_lookup_idx
    ON ct_company_pages (company_id, page_key, language_code) WHERE is_published;

CREATE TABLE IF NOT EXISTS ct_company_roles (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE CASCADE,
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

CREATE TABLE IF NOT EXISTS ct_company_users (
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE RESTRICT,
    user_code VARCHAR(6) NOT NULL,
    company_role_id BIGINT NOT NULL REFERENCES ct_company_roles(id) ON DELETE RESTRICT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    approval_status VARCHAR(12) NOT NULL DEFAULT 'approved'
        CHECK (approval_status IN ('pending', 'approved', 'rejected')),
    approved_at TIMESTAMPTZ,
    approved_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (company_id, user_code)
);
CREATE INDEX IF NOT EXISTS company_users_user_lookup_idx
    ON ct_company_users (user_code, company_id);

ALTER TABLE ct_company_users ADD COLUMN IF NOT EXISTS company_role_id BIGINT
    REFERENCES ct_company_roles(id) ON DELETE RESTRICT;

ALTER TABLE ct_company_users DROP CONSTRAINT IF EXISTS company_users_company_role_same_company_fk;
ALTER TABLE ct_company_users ADD CONSTRAINT company_users_company_role_same_company_fk
    FOREIGN KEY (company_id, company_role_id)
    REFERENCES ct_company_roles (company_id, id) ON DELETE RESTRICT;

ALTER TABLE ft_roles ADD COLUMN IF NOT EXISTS level INTEGER NOT NULL DEFAULT 200;
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS approval_status VARCHAR(12) NOT NULL DEFAULT 'approved';
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS approved_at TIMESTAMPTZ;
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS approved_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL;
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS name_first VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS name_last VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS acronym VARCHAR(80);
ALTER TABLE bt_users ALTER COLUMN display_name TYPE VARCHAR(255);

CREATE TABLE IF NOT EXISTS ct_company_navigation_preferences (
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE CASCADE,
    application VARCHAR(80) NOT NULL
        CHECK (application ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
    configuration JSONB NOT NULL DEFAULT '{}'::jsonb
        CHECK (jsonb_typeof(configuration) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (company_id, application)
);

CREATE TABLE IF NOT EXISTS ft_platform_navigation_preferences (
    application VARCHAR(80) PRIMARY KEY
        CHECK (application ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
    configuration JSONB NOT NULL DEFAULT '{"groups": [], "assignments": {}}'::jsonb
        CHECK (jsonb_typeof(configuration) = 'object'),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS ft_platform_system_settings (
    "key" VARCHAR(80) PRIMARY KEY CHECK ("key" ~ '^[a-z][a-z0-9_]{0,79}$'),
    value_integer INTEGER NOT NULL CHECK (value_integer > 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE ft_platform_system_settings IS 'Non-secret platform runtime parameters; do not store credentials or private keys here.';
INSERT INTO ft_platform_system_settings ("key", value_integer)
VALUES ('password_reset_max_requests', 3), ('password_reset_window_minutes', 15)
ON CONFLICT ("key") DO NOTHING;

CREATE TABLE IF NOT EXISTS ft_dd_varia (
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
        FOREIGN KEY (list_key, parent_id) REFERENCES ft_dd_varia (list_key, id) ON DELETE RESTRICT,
    CONSTRAINT dd_varia_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id)
);
COMMENT ON TABLE ft_dd_varia IS 'Platform-managed hierarchical lookup values. content stores an @translation_key@ token.';
CREATE INDEX IF NOT EXISTS dd_varia_lookup_idx
    ON ft_dd_varia (list_key, parent_id, is_active, sort_order, id);

INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT DISTINCT item.list_key, '_root', '@dd_varia_root_' || item.list_key || '@', NULL::BIGINT,
       'Root entry for this lookup list.', 0
FROM ft_dd_varia AS item
ON CONFLICT (list_key, "key") DO NOTHING;

UPDATE ft_dd_varia AS item
SET parent_id = root.id, updated_at = now()
FROM ft_dd_varia AS root
WHERE item.list_key = root.list_key
  AND root."key" = '_root'
  AND root.parent_id IS NULL
  AND item.id <> root.id
  AND item.parent_id IS NULL;

INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES (
    'company_number_status', '_root', '@dd_varia_root_company_number_status@', NULL,
    'Shared status choices for enterprise and VAT numbers.', 0
)
ON CONFLICT (list_key, "key") DO NOTHING;

WITH root AS (
    SELECT id FROM ft_dd_varia WHERE list_key = 'company_number_status' AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT 'company_number_status', item.status_key, '@' || item.translation_key || '@', root.id,
       'Shared status choice for company identification numbers.', item.sort_order
FROM root
CROSS JOIN (VALUES
    ('known', 'company_number_status_known', 10),
    ('pending', 'company_number_status_pending', 20),
    ('not_applicable', 'company_number_status_not_applicable', 30),
    ('unknown', 'company_number_status_unknown', 40)
) AS item(status_key, translation_key, sort_order)
ON CONFLICT (list_key, "key") DO NOTHING;

INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES
    ('contact_types', '_root', '@dd_varia_root_contact_types@', NULL, 'Root for contact channels.', 0),
    ('address_types', '_root', '@dd_varia_root_address_types@', NULL, 'Root for address purposes.', 0)
ON CONFLICT (list_key, "key") DO NOTHING;
WITH root AS (
    SELECT list_key, id FROM ft_dd_varia
    WHERE list_key IN ('contact_types', 'address_types') AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT seed.list_key, seed.item_key, '@' || seed.translation_key || '@', root.id,
       seed.info, seed.sort_order
FROM (VALUES
    ('contact_types', 'email', 'contact_type_email', 'Email address', 10),
    ('contact_types', 'phone', 'contact_type_phone', 'Telephone number', 20),
    ('contact_types', 'mobile', 'contact_type_mobile', 'Mobile telephone number', 30),
    ('contact_types', 'fax', 'contact_type_fax', 'Fax number', 40),
    ('contact_types', 'website', 'contact_type_website', 'Website URL', 50),
    ('contact_types', 'facebook', 'contact_type_facebook', 'Facebook profile URL', 60),
    ('contact_types', 'linkedin', 'contact_type_linkedin', 'LinkedIn profile URL', 70),
    ('contact_types', 'whatsapp', 'contact_type_whatsapp', 'WhatsApp contact', 80),
    ('contact_types', 'other', 'contact_type_other', 'Other contact channel', 90),
    ('address_types', 'residential', 'address_type_residential', 'Residential address', 10),
    ('address_types', 'postal', 'address_type_postal', 'Postal address', 20),
    ('address_types', 'headquarters', 'address_type_headquarters', 'Head office address', 30),
    ('address_types', 'branch', 'address_type_branch', 'Branch address', 40),
    ('address_types', 'delivery', 'address_type_delivery', 'Delivery address', 50),
    ('address_types', 'billing', 'address_type_billing', 'Billing address', 60),
    ('address_types', 'other', 'address_type_other', 'Other address', 90)
) AS seed(list_key, item_key, translation_key, info, sort_order)
JOIN root ON root.list_key = seed.list_key
ON CONFLICT (list_key, "key") DO NOTHING;

CREATE TABLE IF NOT EXISTS dt_user_contacts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE CASCADE,
    company_id BIGINT,
    contact_type_list_key VARCHAR(80) NOT NULL DEFAULT 'contact_types'
        CHECK (contact_type_list_key = 'contact_types'),
    contact_type_key VARCHAR(80) NOT NULL,
    purpose_key VARCHAR(80) NOT NULL DEFAULT '',
    contact_value VARCHAR(1024) NOT NULL,
    label VARCHAR(160) NOT NULL DEFAULT '',
    is_login_email BOOLEAN NOT NULL DEFAULT FALSE,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (company_id, user_code) REFERENCES bt_users(company_id, user_code)
        ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
    FOREIGN KEY (contact_type_list_key, contact_type_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT,
    CHECK (NOT is_login_email OR contact_type_key = 'email'),
    UNIQUE (user_code, contact_type_key, purpose_key)
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_user_contacts_one_login_email_per_user_idx
    ON dt_user_contacts(user_code) WHERE is_login_email;
CREATE UNIQUE INDEX IF NOT EXISTS dt_user_contacts_global_login_email_idx
    ON dt_user_contacts(lower(contact_value)) WHERE is_login_email AND company_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS dt_user_contacts_company_login_email_idx
    ON dt_user_contacts(company_id, lower(contact_value)) WHERE is_login_email AND company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS dt_user_contacts_user_lookup_idx
    ON dt_user_contacts(user_code, is_active, sort_order, id);

CREATE TABLE IF NOT EXISTS dt_company_contacts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE CASCADE,
    contact_type_list_key VARCHAR(80) NOT NULL DEFAULT 'contact_types'
        CHECK (contact_type_list_key = 'contact_types'),
    contact_type_key VARCHAR(80) NOT NULL,
    purpose_key VARCHAR(80) NOT NULL DEFAULT '',
    contact_value VARCHAR(1024) NOT NULL,
    label VARCHAR(160) NOT NULL DEFAULT '',
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (contact_type_list_key, contact_type_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT,
    UNIQUE (company_id, contact_type_key, purpose_key)
);
CREATE INDEX IF NOT EXISTS dt_company_contacts_lookup_idx
    ON dt_company_contacts(company_id, is_active, sort_order, id);

CREATE TABLE IF NOT EXISTS dt_user_locations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE CASCADE,
    company_id BIGINT,
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
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (company_id, user_code) REFERENCES bt_users(company_id, user_code)
        ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
    FOREIGN KEY (address_type_list_key, address_type_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_user_locations_one_primary_per_type_idx
    ON dt_user_locations(user_code, address_type_key) WHERE is_primary AND is_active;
CREATE INDEX IF NOT EXISTS dt_user_locations_lookup_idx
    ON dt_user_locations(user_code, is_active, sort_order, id);

CREATE TABLE IF NOT EXISTS dt_company_locations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES bt_companies(id) ON DELETE CASCADE,
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
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (address_type_list_key, address_type_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT
);
CREATE UNIQUE INDEX IF NOT EXISTS dt_company_locations_one_primary_per_type_idx
    ON dt_company_locations(company_id, address_type_key) WHERE is_primary AND is_active;
CREATE INDEX IF NOT EXISTS dt_company_locations_lookup_idx
    ON dt_company_locations(company_id, is_active, sort_order, id);

UPDATE ft_roles SET level = 10 WHERE lower(name) = 'beheerder' AND level = 200;
UPDATE ft_roles SET level = 110 WHERE lower(name) = 'apotheker' AND level = 200;
UPDATE ft_roles SET level = 120 WHERE lower(name) = 'medewerker' AND level = 200;
ALTER TABLE bt_users DROP CONSTRAINT IF EXISTS users_approval_status_check;
ALTER TABLE bt_users ADD CONSTRAINT users_approval_status_check
    CHECK (approval_status IN ('pending', 'approved', 'rejected'));

CREATE TABLE IF NOT EXISTS ft_access_ip_allowlist (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT REFERENCES bt_companies(id) ON DELETE CASCADE,
    scope VARCHAR(12) NOT NULL CHECK (scope IN ('admin', 'staff')),
    network INET NOT NULL,
    description VARCHAR(160) NOT NULL DEFAULT '',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (scope, network)
);

ALTER TABLE ft_access_ip_allowlist ADD COLUMN IF NOT EXISTS company_id BIGINT
    REFERENCES bt_companies(id) ON DELETE CASCADE;
ALTER TABLE ft_access_ip_allowlist DROP CONSTRAINT IF EXISTS access_ip_allowlist_scope_network_key;
CREATE UNIQUE INDEX IF NOT EXISTS access_ip_allowlist_global_scope_network_idx
    ON ft_access_ip_allowlist (scope, network) WHERE company_id IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS access_ip_allowlist_company_scope_network_idx
    ON ft_access_ip_allowlist (company_id, scope, network) WHERE company_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS st_registration_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS registration_attempts_lookup_idx
    ON st_registration_attempts (ip_address, attempted_at DESC);

CREATE TABLE IF NOT EXISTS st_login_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    email VARCHAR(254) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE st_login_attempts ADD COLUMN IF NOT EXISTS company_id BIGINT NOT NULL DEFAULT 0;
ALTER TABLE st_login_attempts DROP CONSTRAINT IF EXISTS login_attempts_company_id_check;
ALTER TABLE st_login_attempts ADD CONSTRAINT login_attempts_company_id_check CHECK (company_id >= 0);

CREATE TABLE IF NOT EXISTS ft_substitutions (
    "key" VARCHAR(120) NOT NULL,
    "value" TEXT NOT NULL,
    code VARCHAR(8) NOT NULL,
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY ("key", code)
);

ALTER TABLE ft_substitutions ADD COLUMN IF NOT EXISTS company_id BIGINT NOT NULL DEFAULT 0;
ALTER TABLE ft_substitutions DROP CONSTRAINT IF EXISTS substitutions_pkey;
ALTER TABLE ft_substitutions ADD CONSTRAINT substitutions_pkey PRIMARY KEY (company_id, "key", code);
ALTER TABLE ft_substitutions DROP CONSTRAINT IF EXISTS substitutions_company_id_check;
ALTER TABLE ft_substitutions ADD CONSTRAINT substitutions_company_id_check CHECK (company_id >= 0);

INSERT INTO ft_substitutions (company_id, "key", "value", code)
VALUES
    (0, 'dd_varia_root_contact_types', 'Contactkanalen', 'NL'),
    (0, 'dd_varia_root_contact_types', 'Canaux de contact', 'FR'),
    (0, 'dd_varia_root_contact_types', 'Contact channels', 'EN'),
    (0, 'dd_varia_root_contact_types', 'Kontaktkanäle', 'DE'),
    (0, 'dd_varia_root_address_types', 'Adressoorten', 'NL'),
    (0, 'dd_varia_root_address_types', 'Types d’adresse', 'FR'),
    (0, 'dd_varia_root_address_types', 'Address types', 'EN'),
    (0, 'dd_varia_root_address_types', 'Adressarten', 'DE'),
    (0, 'contact_type_email', 'E-mail', 'NL'),
    (0, 'contact_type_email', 'E-mail', 'FR'),
    (0, 'contact_type_email', 'Email', 'EN'),
    (0, 'contact_type_email', 'E-Mail', 'DE'),
    (0, 'contact_type_phone', 'Telefoon', 'NL'),
    (0, 'contact_type_phone', 'Téléphone', 'FR'),
    (0, 'contact_type_phone', 'Telephone', 'EN'),
    (0, 'contact_type_phone', 'Telefon', 'DE'),
    (0, 'contact_type_mobile', 'Gsm', 'NL'),
    (0, 'contact_type_mobile', 'Mobile', 'FR'),
    (0, 'contact_type_mobile', 'Mobile', 'EN'),
    (0, 'contact_type_mobile', 'Mobiltelefon', 'DE'),
    (0, 'contact_type_fax', 'Fax', 'NL'),
    (0, 'contact_type_fax', 'Fax', 'FR'),
    (0, 'contact_type_fax', 'Fax', 'EN'),
    (0, 'contact_type_fax', 'Fax', 'DE'),
    (0, 'contact_type_website', 'Website', 'NL'),
    (0, 'contact_type_website', 'Site web', 'FR'),
    (0, 'contact_type_website', 'Website', 'EN'),
    (0, 'contact_type_website', 'Webseite', 'DE'),
    (0, 'contact_type_facebook', 'Facebook', 'NL'),
    (0, 'contact_type_facebook', 'Facebook', 'FR'),
    (0, 'contact_type_facebook', 'Facebook', 'EN'),
    (0, 'contact_type_facebook', 'Facebook', 'DE'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'NL'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'FR'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'EN'),
    (0, 'contact_type_linkedin', 'LinkedIn', 'DE'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'NL'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'FR'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'EN'),
    (0, 'contact_type_whatsapp', 'WhatsApp', 'DE'),
    (0, 'contact_type_other', 'Overig contact', 'NL'),
    (0, 'contact_type_other', 'Autre contact', 'FR'),
    (0, 'contact_type_other', 'Other contact', 'EN'),
    (0, 'contact_type_other', 'Sonstiger Kontakt', 'DE'),
    (0, 'address_type_residential', 'Woonadres', 'NL'),
    (0, 'address_type_residential', 'Adresse résidentielle', 'FR'),
    (0, 'address_type_residential', 'Residential address', 'EN'),
    (0, 'address_type_residential', 'Wohnadresse', 'DE'),
    (0, 'address_type_postal', 'Postadres', 'NL'),
    (0, 'address_type_postal', 'Adresse postale', 'FR'),
    (0, 'address_type_postal', 'Postal address', 'EN'),
    (0, 'address_type_postal', 'Postanschrift', 'DE'),
    (0, 'address_type_headquarters', 'Hoofdzetel', 'NL'),
    (0, 'address_type_headquarters', 'Siège social', 'FR'),
    (0, 'address_type_headquarters', 'Head office', 'EN'),
    (0, 'address_type_headquarters', 'Hauptsitz', 'DE'),
    (0, 'address_type_branch', 'Vestiging', 'NL'),
    (0, 'address_type_branch', 'Établissement', 'FR'),
    (0, 'address_type_branch', 'Branch', 'EN'),
    (0, 'address_type_branch', 'Niederlassung', 'DE'),
    (0, 'address_type_delivery', 'Afleveradres', 'NL'),
    (0, 'address_type_delivery', 'Adresse de livraison', 'FR'),
    (0, 'address_type_delivery', 'Delivery address', 'EN'),
    (0, 'address_type_delivery', 'Lieferadresse', 'DE'),
    (0, 'address_type_billing', 'Facturatieadres', 'NL'),
    (0, 'address_type_billing', 'Adresse de facturation', 'FR'),
    (0, 'address_type_billing', 'Billing address', 'EN'),
    (0, 'address_type_billing', 'Rechnungsadresse', 'DE'),
    (0, 'address_type_other', 'Ander adres', 'NL'),
    (0, 'address_type_other', 'Autre adresse', 'FR'),
    (0, 'address_type_other', 'Other address', 'EN'),
    (0, 'address_type_other', 'Sonstige Adresse', 'DE')
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = now();

INSERT INTO ft_substitutions (company_id, "key", "value", code, updated_at)
SELECT company_id, "key", "value", upper(code), updated_at
FROM ft_substitutions
WHERE code <> upper(code)
ON CONFLICT (company_id, "key", code) DO UPDATE
SET "value" = EXCLUDED."value", updated_at = EXCLUDED.updated_at;
DELETE FROM ft_substitutions WHERE code <> upper(code);

DROP INDEX IF EXISTS login_attempts_lookup_idx;
CREATE INDEX IF NOT EXISTS login_attempts_company_lookup_idx
    ON st_login_attempts (company_id, email, ip_address, attempted_at DESC);

CREATE TABLE IF NOT EXISTS st_kbo_lookup_attempts (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    enterprise_number VARCHAR(16) NOT NULL,
    request_type VARCHAR(40) NOT NULL DEFAULT 'read_enterprise',
    requested_by_user_code VARCHAR(6) REFERENCES bt_users(user_code) ON DELETE SET NULL,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS kbo_lookup_attempts_requested_at_idx
    ON st_kbo_lookup_attempts (requested_at DESC);
ALTER TABLE st_kbo_lookup_attempts ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS kbo_lookup_attempts_platform_only ON st_kbo_lookup_attempts;
CREATE POLICY kbo_lookup_attempts_platform_only ON st_kbo_lookup_attempts
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

INSERT INTO ft_roles (name, description, permissions, is_system)
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
    SELECT 1 FROM ft_roles AS existing WHERE lower(existing.name) = lower(seed.name)
)
ON CONFLICT (name) DO NOTHING;

UPDATE ft_roles
SET is_system = TRUE, updated_at = now()
WHERE lower(name) IN ('beheerder', 'apotheker', 'medewerker', 'publiek')
  AND is_system = FALSE;

UPDATE ft_roles SET level = 10 WHERE lower(name) = 'beheerder' AND level = 200;
UPDATE ft_roles SET level = 110 WHERE lower(name) = 'apotheker' AND level = 200;
UPDATE ft_roles SET level = 120 WHERE lower(name) = 'medewerker' AND level = 200;
UPDATE ft_roles
SET permissions = permissions || '["translations.manage"]'::jsonb
WHERE lower(name) = 'beheerder' AND NOT (permissions ? 'translations.manage');
UPDATE ft_roles
SET permissions = permissions || '["companies.manage"]'::jsonb
WHERE lower(name) = 'beheerder' AND NOT (permissions ? 'companies.manage');
UPDATE ct_company_roles
SET permissions = permissions || '["company.translations.manage"]'::jsonb
WHERE lower(name) = 'beheerder' AND NOT (permissions ? 'company.translations.manage');

INSERT INTO ct_company_roles (company_id, name, description, level, permissions, is_system)
SELECT c.id, r.name, r.description, r.level,
       CASE WHEN lower(r.name) = 'beheerder'
                     THEN '["company.users.manage", "company.roles.manage", "company.settings.manage", "company.translations.manage"]'::jsonb
            ELSE '[]'::jsonb
       END,
       r.is_system
FROM bt_companies AS c
CROSS JOIN ft_roles AS r
ON CONFLICT (company_id, name) DO NOTHING;

UPDATE ct_company_roles
SET is_system = TRUE, updated_at = now()
WHERE lower(name) IN ('beheerder', 'apotheker', 'medewerker', 'publiek')
  AND is_system = FALSE;

-- The one-time conversion of legacy global company memberships is handled by
-- its historical migration before this user_code-based schema is applied.

CREATE INDEX IF NOT EXISTS users_customer_name_prefix_idx
    ON bt_users (company_id, lower(display_name) text_pattern_ops) WHERE company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_customer_acronym_prefix_idx
    ON bt_users (company_id, lower(acronym) text_pattern_ops)
    WHERE company_id IS NOT NULL AND acronym IS NOT NULL AND acronym <> '';
CREATE UNIQUE INDEX IF NOT EXISTS users_company_acronym_unique_idx
    ON bt_users (company_id, lower(acronym))
    WHERE company_id IS NOT NULL AND acronym IS NOT NULL AND acronym <> '';
CREATE UNIQUE INDEX IF NOT EXISTS users_global_acronym_unique_idx
    ON bt_users (lower(acronym))
    WHERE company_id IS NULL AND acronym IS NOT NULL AND acronym <> '';

ALTER TABLE ct_company_users DROP CONSTRAINT IF EXISTS company_users_user_same_company_fk;
ALTER TABLE ct_company_users ADD CONSTRAINT company_users_user_same_company_fk
    FOREIGN KEY (company_id, user_code) REFERENCES bt_users (company_id, user_code) ON DELETE CASCADE;

-- Migrate the previous global role link once, then remove that legacy column.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'ct_company_users'
          AND column_name = 'role_id'
    ) THEN
        EXECUTE $migration$
            UPDATE ct_company_users AS cu
            SET company_role_id = cr.id
            FROM ft_roles AS r, company_roles AS cr
            WHERE cu.company_role_id IS NULL
              AND cu.role_id = r.id
              AND cr.company_id = cu.company_id
              AND cr.name = r.name
        $migration$;
    END IF;
END
$$;

ALTER TABLE ct_company_users ALTER COLUMN company_role_id SET NOT NULL;
ALTER TABLE ct_company_users DROP COLUMN IF EXISTS role_id;
CREATE INDEX IF NOT EXISTS company_roles_company_level_idx
    ON ct_company_roles (company_id, level);

ALTER TABLE bt_companies ENABLE ROW LEVEL SECURITY;
ALTER TABLE ct_company_domains ENABLE ROW LEVEL SECURITY;
ALTER TABLE ct_company_roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE ct_company_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE ct_company_navigation_preferences ENABLE ROW LEVEL SECURITY;
ALTER TABLE ft_platform_navigation_preferences ENABLE ROW LEVEL SECURITY;
ALTER TABLE ft_platform_system_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE ft_dd_varia ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_company_contacts ENABLE ROW LEVEL SECURITY;
ALTER TABLE dt_company_locations ENABLE ROW LEVEL SECURITY;
ALTER TABLE ft_access_ip_allowlist ENABLE ROW LEVEL SECURITY;
ALTER TABLE ct_company_pages ENABLE ROW LEVEL SECURITY;
ALTER TABLE ft_substitutions ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS substitutions_visible_to_scope ON ft_substitutions;
CREATE POLICY substitutions_visible_to_scope ON ft_substitutions
    FOR SELECT USING (
        company_id = 0
        OR company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS substitutions_write_to_scope ON ft_substitutions;
CREATE POLICY substitutions_write_to_scope ON ft_substitutions
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

DROP POLICY IF EXISTS companies_visible_to_public_members_or_platform ON bt_companies;
CREATE POLICY companies_visible_to_public_members_or_platform ON bt_companies
    FOR SELECT USING (
        (is_active AND is_public)
        OR id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR id IN (
            SELECT cu.company_id FROM ct_company_users AS cu
            WHERE cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR (is_active AND current_setting('grompil.company_login_lookup', true) = 'true')
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS companies_platform_write ON bt_companies;
CREATE POLICY companies_platform_write ON bt_companies
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DROP POLICY IF EXISTS company_domains_verified_read ON ct_company_domains;
CREATE POLICY company_domains_verified_read ON ct_company_domains
    FOR SELECT USING (
        (is_active AND is_verified)
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_domains_platform_write ON ct_company_domains;
CREATE POLICY company_domains_platform_write ON ct_company_domains
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DROP POLICY IF EXISTS company_pages_public_read ON ct_company_pages;
CREATE POLICY company_pages_public_read ON ct_company_pages
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR company_id IN (SELECT id FROM bt_companies WHERE is_active AND is_public)
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_pages_tenant_or_platform_write ON ct_company_pages;
CREATE POLICY company_pages_tenant_or_platform_write ON ct_company_pages
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_roles_visible_to_member_or_platform ON ct_company_roles;
CREATE POLICY company_roles_visible_to_member_or_platform ON ct_company_roles
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR company_id IN (
            SELECT cu.company_id FROM ct_company_users AS cu
            WHERE cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
              AND cu.is_active AND cu.approval_status = 'approved'
        )
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_roles_member_or_platform_write ON ct_company_roles;
CREATE POLICY company_roles_member_or_platform_write ON ct_company_roles
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_users_self_read ON ct_company_users;
CREATE POLICY company_users_self_read ON ct_company_users
    FOR SELECT USING (
        user_code = NULLIF(current_setting('grompil.user_code', true), '')
    );
DROP POLICY IF EXISTS company_users_tenant_or_platform ON ct_company_users;
CREATE POLICY company_users_tenant_or_platform ON ct_company_users
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_navigation_preferences_read ON ct_company_navigation_preferences;
CREATE POLICY company_navigation_preferences_read ON ct_company_navigation_preferences
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM ct_company_users AS cu
                WHERE cu.company_id = ct_company_navigation_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
            )
        )
    );

DROP POLICY IF EXISTS platform_navigation_preferences_read ON ft_platform_navigation_preferences;
CREATE POLICY platform_navigation_preferences_read ON ft_platform_navigation_preferences
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_navigation_preferences_admin_write ON ft_platform_navigation_preferences;
CREATE POLICY platform_navigation_preferences_admin_write ON ft_platform_navigation_preferences
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');
DROP POLICY IF EXISTS platform_system_settings_read ON ft_platform_system_settings;
CREATE POLICY platform_system_settings_read ON ft_platform_system_settings
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_system_settings_admin_write ON ft_platform_system_settings;
CREATE POLICY platform_system_settings_admin_write ON ft_platform_system_settings
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');
DROP POLICY IF EXISTS dd_varia_visible_active_or_platform ON ft_dd_varia;
CREATE POLICY dd_varia_visible_active_or_platform ON ft_dd_varia
    FOR SELECT USING (
        is_active OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS dd_varia_platform_write ON ft_dd_varia;
CREATE POLICY dd_varia_platform_write ON ft_dd_varia
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DROP POLICY IF EXISTS dt_company_contacts_scope ON dt_company_contacts;
CREATE POLICY dt_company_contacts_scope ON dt_company_contacts
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR (is_public AND company_id IN (SELECT id FROM bt_companies WHERE is_active AND is_public))
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS dt_company_locations_scope ON dt_company_locations;
CREATE POLICY dt_company_locations_scope ON dt_company_locations
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR (is_public AND company_id IN (SELECT id FROM bt_companies WHERE is_active AND is_public))
        OR current_setting('grompil.platform_operator', true) = 'true'
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS company_navigation_preferences_admin_write ON ct_company_navigation_preferences;
CREATE POLICY company_navigation_preferences_admin_write ON ct_company_navigation_preferences
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM ct_company_users AS cu
                JOIN ct_company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = ct_company_navigation_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
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
                SELECT 1 FROM ct_company_users AS cu
                JOIN ct_company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = ct_company_navigation_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    );

DROP POLICY IF EXISTS access_ip_allowlist_tenant_or_platform ON ft_access_ip_allowlist;
CREATE POLICY access_ip_allowlist_tenant_or_platform ON ft_access_ip_allowlist
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

-- Platform MFA credentials and one-time recovery codes.
CREATE TABLE IF NOT EXISTS st_platform_totp_credentials (
    user_code VARCHAR(6) PRIMARY KEY REFERENCES bt_users(user_code) ON DELETE CASCADE,
    secret_ciphertext TEXT NOT NULL,
    enabled_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (secret_ciphertext <> '')
);

CREATE TABLE IF NOT EXISTS st_platform_totp_recovery_codes (
    user_code VARCHAR(6) NOT NULL REFERENCES bt_users(user_code) ON DELETE CASCADE,
    code_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    used_at TIMESTAMPTZ,
    PRIMARY KEY (user_code, code_hash)
);

ALTER TABLE st_platform_totp_credentials ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_totp_credentials_platform_only ON st_platform_totp_credentials;
CREATE POLICY platform_totp_credentials_platform_only ON st_platform_totp_credentials
    FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

ALTER TABLE st_platform_totp_recovery_codes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_totp_recovery_codes_platform_only ON st_platform_totp_recovery_codes;
CREATE POLICY platform_totp_recovery_codes_platform_only ON st_platform_totp_recovery_codes
    FOR ALL
    USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

-- Password reset support.
ALTER TABLE bt_users ADD COLUMN IF NOT EXISTS auth_version BIGINT NOT NULL DEFAULT 0 CHECK (auth_version >= 0);

CREATE TABLE IF NOT EXISTS st_password_reset_tokens (
---    token_hash CHAR(64) PRIMARY KEY CHECK (token_hash ~ '^[a-f0-9]{64}),
	token_hash CHAR(64) PRIMARY KEY CHECK (token_hash ~ '^[a-f0-9]{64}$'),
    user_code VARCHAR(6) NOT NULL UNIQUE REFERENCES bt_users(user_code) ON DELETE CASCADE,
    company_id BIGINT REFERENCES bt_companies(id) ON DELETE CASCADE,
    password_hash_at_issue VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_reset_tokens_expiry_idx ON st_password_reset_tokens (expires_at);

CREATE TABLE IF NOT EXISTS st_password_reset_attempts (
    company_id BIGINT NOT NULL DEFAULT 0 CHECK (company_id >= 0),
    identifier_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_reset_attempts_ip_idx ON st_password_reset_attempts (ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS password_reset_attempts_identifier_idx ON st_password_reset_attempts (company_id, identifier_hash, attempted_at);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM bt_users WHERE user_code IS NULL) THEN
        ALTER TABLE bt_users ALTER COLUMN user_code SET NOT NULL;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM bt_users
        WHERE user_code IS NOT NULL
          AND (user_code !~ '^[A-Z0-9]{6}$'
               OR length(regexp_replace(user_code, '[A-Z]', '', 'g')) <> 3
               OR length(regexp_replace(user_code, '[0-9]', '', 'g')) <> 3)
    ) THEN
        ALTER TABLE bt_users VALIDATE CONSTRAINT users_user_code_format_check;
    END IF;
END;
$$;

-- Once every existing account has its final user_code, prevent changing the
-- relational identity or physically deleting the account. Deactivate instead.
CREATE OR REPLACE FUNCTION grompil_protect_user_identity()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'User accounts cannot be deleted; set is_active = false instead.'
            USING ERRCODE = '55000';
    END IF;
    IF NEW.user_code IS DISTINCT FROM OLD.user_code THEN
        RAISE EXCEPTION 'users.user_code is immutable after account creation.'
            USING ERRCODE = '55000';
    END IF;
    RETURN NEW;
END;
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM bt_users
        WHERE user_code IS NULL
           OR user_code !~ '^[A-Z0-9]{6}$'
           OR length(regexp_replace(user_code, '[A-Z]', '', 'g')) <> 3
           OR length(regexp_replace(user_code, '[0-9]', '', 'g')) <> 3
    ) THEN
        EXECUTE 'DROP TRIGGER IF EXISTS users_user_code_immutable ON bt_users';
        EXECUTE 'CREATE TRIGGER users_user_code_immutable BEFORE UPDATE OF user_code ON bt_users '
             || 'FOR EACH ROW EXECUTE FUNCTION grompil_protect_user_identity()';
        EXECUTE 'DROP TRIGGER IF EXISTS users_delete_forbidden ON bt_users';
        EXECUTE 'CREATE TRIGGER users_delete_forbidden BEFORE DELETE ON bt_users '
             || 'FOR EACH ROW EXECUTE FUNCTION grompil_protect_user_identity()';
    END IF;
END;
$$;

COMMIT;
