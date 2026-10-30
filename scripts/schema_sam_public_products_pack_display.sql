-- Promote SAM's localized PackDisplayValue from the preserved AMPP JSON
-- into explicit searchable/displayable fields on existing catalog rows.
-- Run once as the owner of public.sam_public_products.

ALTER TABLE public.sam_public_products
    ADD COLUMN IF NOT EXISTS pack_display_nl TEXT,
    ADD COLUMN IF NOT EXISTS pack_display_fr TEXT;

UPDATE public.sam_public_products
SET pack_display_nl = ampp_data_json -> 'PackDisplayValue' ->> 'Nl',
    pack_display_fr = ampp_data_json -> 'PackDisplayValue' ->> 'Fr'
WHERE pack_display_nl IS NULL OR pack_display_fr IS NULL;

COMMENT ON COLUMN public.sam_public_products.pack_display_nl IS
    'SAM PackDisplayValue in Dutch, for example 5 x 1 ml.';

COMMENT ON COLUMN public.sam_public_products.pack_display_fr IS
    'SAM PackDisplayValue in French.';
