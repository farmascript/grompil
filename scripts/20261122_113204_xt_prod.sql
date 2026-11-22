-- Shared, company-independent product master, keyed by CNK.
-- Company-specific prices, stock, locations and assortment belong in separate
-- company-scoped tables and must not be added here.

BEGIN;

CREATE TABLE IF NOT EXISTS public.xt_prod (
    cnk             VARCHAR(7) PRIMARY KEY,
    description_nl  TEXT NOT NULL DEFAULT '',
    description_fr  TEXT NOT NULL DEFAULT '',
    source_code     VARCHAR(100) NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT xt_prod_cnk_nonempty_ck CHECK (btrim(cnk) <> ''),
    CONSTRAINT xt_prod_source_code_nonempty_ck CHECK (btrim(source_code) <> '')
);

COMMENT ON TABLE public.xt_prod IS
    'Shared product master keyed by CNK; descriptions and source are global, company data is stored separately.';
COMMENT ON COLUMN public.xt_prod.cnk IS
    'CNK stored as text so leading zeroes are preserved.';
COMMENT ON COLUMN public.xt_prod.source_code IS
    'Identifier of the source used for the current canonical product record, e.g. SAM or a supplier code.';
COMMENT ON COLUMN public.xt_prod.created_at IS
    'Timestamp when this canonical product row was first created.';
COMMENT ON COLUMN public.xt_prod.updated_at IS
    'Timestamp of the latest change; writers should set this to now() when updating the row.';

COMMIT;
