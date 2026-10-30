-- Current SAM public-pharmacy catalog table.
-- One row per current DMPP with a public (P) CNK code. A CNK can be linked
-- to more than one AMP/AMPP registration, so the source identity is composite.
-- This file defines the structure only; it does not import data.

CREATE TABLE IF NOT EXISTS public.sam_public_products (
    sam_export_version              VARCHAR(20) NOT NULL,
    sam_id                           VARCHAR(40) NOT NULL,
    validity_date                    DATE NOT NULL,
    amp_code                         VARCHAR(30),
    ampp_code                        VARCHAR(30),
    cnk                              VARCHAR(7) NOT NULL,
    delivery_environment             CHAR(1) NOT NULL CHECK (delivery_environment = 'P'),
    cnk_code_type                    VARCHAR(10) NOT NULL CHECK (cnk_code_type = 'CNK'),
    amp_status                       VARCHAR(30),
    ampp_status                      VARCHAR(30),
    official_name                    TEXT,
    name_nl                          TEXT,
    name_fr                          TEXT,
    prescription_name_nl             TEXT,
    prescription_name_fr             TEXT,
    medicine_type                    VARCHAR(50),
    delivery_modus_codes_json        JSONB NOT NULL DEFAULT '[]'::JSONB,
    delivery_modus_specification_codes_json JSONB NOT NULL DEFAULT '[]'::JSONB,
    amp_data_json                    JSONB,
    ampp_data_json                   JSONB,
    dmpp_data_json                   JSONB,
    CONSTRAINT sam_public_products_pkey PRIMARY KEY (amp_code, ampp_code, cnk),
    CONSTRAINT sam_public_products_delivery_codes_are_arrays_ck CHECK (
        JSONB_TYPEOF(delivery_modus_codes_json) = 'array'
        AND JSONB_TYPEOF(delivery_modus_specification_codes_json) = 'array'
    )
);

CREATE INDEX IF NOT EXISTS sam_public_products_amp_code_idx
    ON public.sam_public_products (amp_code);

CREATE INDEX IF NOT EXISTS sam_public_products_status_idx
    ON public.sam_public_products (amp_status, ampp_status);

COMMENT ON TABLE public.sam_public_products IS
    'Current SAM public-pharmacy catalog snapshot; one row per current public DMPP/CNK registration.';

COMMENT ON COLUMN public.sam_public_products.cnk IS
    'Public DMPP CNK code; a CNK may occur on multiple AMP/AMPP registrations.';

COMMENT ON COLUMN public.sam_public_products.amp_data_json IS
    'Current AMP Data slice from the source export, preserved for fields not yet mapped.';

COMMENT ON COLUMN public.sam_public_products.ampp_data_json IS
    'Current AMPP Data slice from the source export, preserved for fields not yet mapped.';

COMMENT ON COLUMN public.sam_public_products.dmpp_data_json IS
    'Current public DMPP Data slice, including its applicable price and reimbursement fields.';
