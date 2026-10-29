-- Adminer 6.1.2 PostgreSQL 18.4 dump

DROP SCHEMA IF EXISTS "public" CASCADE;
CREATE SCHEMA IF NOT EXISTS "public";
COMMENT ON SCHEMA "public" IS 'standard public schema';
SET search_path TO "public";

DROP TABLE IF EXISTS "pharmacies";

CREATE TABLE "pharmacies" (
    "Vergunningsnummer" character varying NOT NULL,
    "Naam" character varying,
    "Adres" character varying,
    "Postcode" character varying,
    "Gemeente" character varying,
    "Status" character varying,
    "Vergunninghouder" character varying,
    "Uitbater" character varying,
    "X" character varying,
    "Y" character varying,
    "BijkomendePercelen" character varying,
    CONSTRAINT "apbnr" PRIMARY KEY ("Vergunningsnummer")
);


-- 2026-10-29 17:00:38 UTC
