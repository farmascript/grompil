BEGIN;

ALTER TABLE public.dt_user_locations
    ADD COLUMN IF NOT EXISTS lambert72_x NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS lambert72_y NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS wgs84_latitude NUMERIC(10,7),
    ADD COLUMN IF NOT EXISTS wgs84_longitude NUMERIC(10,7);

ALTER TABLE public.dt_company_locations
    ADD COLUMN IF NOT EXISTS lambert72_x NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS lambert72_y NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS wgs84_latitude NUMERIC(10,7),
    ADD COLUMN IF NOT EXISTS wgs84_longitude NUMERIC(10,7);

ALTER TABLE public.dt_user_locations
    DROP CONSTRAINT IF EXISTS dt_user_locations_wgs84_latitude_range_check,
    DROP CONSTRAINT IF EXISTS dt_user_locations_wgs84_longitude_range_check,
    ADD CONSTRAINT dt_user_locations_wgs84_latitude_range_check
        CHECK (wgs84_latitude IS NULL OR wgs84_latitude BETWEEN -90 AND 90),
    ADD CONSTRAINT dt_user_locations_wgs84_longitude_range_check
        CHECK (wgs84_longitude IS NULL OR wgs84_longitude BETWEEN -180 AND 180);

ALTER TABLE public.dt_company_locations
    DROP CONSTRAINT IF EXISTS dt_company_locations_wgs84_latitude_range_check,
    DROP CONSTRAINT IF EXISTS dt_company_locations_wgs84_longitude_range_check,
    ADD CONSTRAINT dt_company_locations_wgs84_latitude_range_check
        CHECK (wgs84_latitude IS NULL OR wgs84_latitude BETWEEN -90 AND 90),
    ADD CONSTRAINT dt_company_locations_wgs84_longitude_range_check
        CHECK (wgs84_longitude IS NULL OR wgs84_longitude BETWEEN -180 AND 180);

COMMENT ON COLUMN public.dt_user_locations.lambert72_x IS 'BeSt Address Lambert 72 easting; null when not provided by source.';
COMMENT ON COLUMN public.dt_user_locations.lambert72_y IS 'BeSt Address Lambert 72 northing; null when not provided by source.';
COMMENT ON COLUMN public.dt_user_locations.wgs84_latitude IS 'BeSt Address WGS84 latitude used for map links; null when not provided by source.';
COMMENT ON COLUMN public.dt_user_locations.wgs84_longitude IS 'BeSt Address WGS84 longitude used for map links; null when not provided by source.';
COMMENT ON COLUMN public.dt_company_locations.lambert72_x IS 'BeSt Address Lambert 72 easting; null when not provided by source.';
COMMENT ON COLUMN public.dt_company_locations.lambert72_y IS 'BeSt Address Lambert 72 northing; null when not provided by source.';
COMMENT ON COLUMN public.dt_company_locations.wgs84_latitude IS 'BeSt Address WGS84 latitude used for map links; null when not provided by source.';
COMMENT ON COLUMN public.dt_company_locations.wgs84_longitude IS 'BeSt Address WGS84 longitude used for map links; null when not provided by source.';

COMMIT;
