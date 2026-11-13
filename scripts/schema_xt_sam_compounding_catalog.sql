-- Structural mirror of the SAM v2 CMP export.
-- It preserves formula and ingredient identities and their multilingual synonyms.
-- It is kept separate from the canonical raw-material and RIZIV tables.

BEGIN;

CREATE TABLE IF NOT EXISTS public.xt_sam_compounding_catalog (
    sam_export_version      VARCHAR(20) NOT NULL,
    sam_id                  VARCHAR(40) NOT NULL,
    entity_type             VARCHAR(20) NOT NULL
        CHECK (entity_type IN ('FORMULA', 'INGREDIENT')),
    product_id              TEXT NOT NULL,
    code                    TEXT,
    code_type               VARCHAR(30),
    data_version            INTEGER NOT NULL CHECK (data_version > 0),
    valid_from              DATE,
    valid_to                DATE,
    preferred_name_nl       TEXT NOT NULL DEFAULT '',
    preferred_name_fr       TEXT NOT NULL DEFAULT '',
    preferred_name_la       TEXT NOT NULL DEFAULT '',
    synonyms_json           JSONB NOT NULL DEFAULT '[]'::JSONB,
    source_payload_json     JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT xt_sam_compounding_catalog_pkey
        PRIMARY KEY (entity_type, product_id, data_version),
    CONSTRAINT xt_sam_compounding_catalog_validity_ck
        CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from),
    CONSTRAINT xt_sam_compounding_catalog_synonyms_array_ck
        CHECK (JSONB_TYPEOF(synonyms_json) = 'array'),
    CONSTRAINT xt_sam_compounding_catalog_payload_object_ck
        CHECK (JSONB_TYPEOF(source_payload_json) = 'object')
);

CREATE INDEX IF NOT EXISTS xt_sam_compounding_catalog_code_idx
    ON public.xt_sam_compounding_catalog (code_type, code);
CREATE INDEX IF NOT EXISTS xt_sam_compounding_catalog_name_nl_idx
    ON public.xt_sam_compounding_catalog (lower(preferred_name_nl));
CREATE INDEX IF NOT EXISTS xt_sam_compounding_catalog_name_fr_idx
    ON public.xt_sam_compounding_catalog (lower(preferred_name_fr));

COMMENT ON TABLE public.xt_sam_compounding_catalog IS
    'Versioned SAM CMP source catalogue of compounding formulas and ingredients; not the canonical material master or reimbursement rules.';
COMMENT ON COLUMN public.xt_sam_compounding_catalog.product_id IS
    'Opaque ProductId exactly as supplied by SAM; do not treat it as a human-readable code.';
COMMENT ON COLUMN public.xt_sam_compounding_catalog.synonyms_json IS
    'All names from the SAM Data slice, preserving language and source rank.';
COMMENT ON COLUMN public.xt_sam_compounding_catalog.source_payload_json IS
    'Source attributes and Data slice payload retained for later field mapping.';

COMMIT;
