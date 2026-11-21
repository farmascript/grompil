BEGIN;

ALTER TABLE bt_customers
    ADD COLUMN IF NOT EXISTS date_of_death DATE;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'bt_customers_death_after_birth_check'
          AND conrelid = 'bt_customers'::regclass
    ) THEN
        ALTER TABLE bt_customers
            ADD CONSTRAINT bt_customers_death_after_birth_check
            CHECK (date_of_death IS NULL OR date_of_birth IS NULL OR date_of_death >= date_of_birth);
    END IF;
END;
$$;

COMMIT;
