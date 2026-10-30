-- SAM ATC hierarchy descriptions from the REF export.
-- Create once; populate with sam_atc_classifications_export.php and CSV import.

CREATE TABLE IF NOT EXISTS public.sam_atc_classifications (
    atc_code VARCHAR(7) PRIMARY KEY,
    description TEXT NOT NULL
);

COMMENT ON TABLE public.sam_atc_classifications IS
    'SAM reference descriptions for ATC classification codes.';
