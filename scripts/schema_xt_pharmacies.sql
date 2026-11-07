-- AFMPS pharmacy registry data imported from an external source.
-- Defines the table structure only; it does not import or delete records.
BEGIN;

CREATE TABLE IF NOT EXISTS public.xt_pharmacies (
    "Vergunningsnummer" VARCHAR NOT NULL,
    "Naam" VARCHAR,
    "Adres" VARCHAR,
    "Postcode" VARCHAR,
    "Gemeente" VARCHAR,
    "Status" VARCHAR,
    "Vergunninghouder" VARCHAR,
    "Uitbater" VARCHAR,
    "X" VARCHAR,
    "Y" VARCHAR,
    "BijkomendePercelen" VARCHAR,
    CONSTRAINT xt_pharmacies_pkey PRIMARY KEY ("Vergunningsnummer")
);

COMMENT ON TABLE public.xt_pharmacies IS
    'AFMPS pharmacy registry records imported from an external source.';

COMMIT;
