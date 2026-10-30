-- Enable indexed fuzzy search on Dutch and French SAM prescription names.
-- Run once after schema_sam_public_products.sql, as the database/table owner.

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX IF NOT EXISTS sam_public_products_prescription_name_nl_trgm_idx
    ON public.sam_public_products USING GIN (lower(prescription_name_nl) gin_trgm_ops);

CREATE INDEX IF NOT EXISTS sam_public_products_prescription_name_fr_trgm_idx
    ON public.sam_public_products USING GIN (lower(prescription_name_fr) gin_trgm_ops);

CREATE INDEX IF NOT EXISTS sam_public_products_pack_display_nl_trgm_idx
    ON public.sam_public_products USING GIN (lower(pack_display_nl) gin_trgm_ops);

CREATE INDEX IF NOT EXISTS sam_public_products_pack_display_fr_trgm_idx
    ON public.sam_public_products USING GIN (lower(pack_display_fr) gin_trgm_ops);
