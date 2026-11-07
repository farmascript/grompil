-- Run only after the normalized-contact application version has been deployed
-- and checked. The migration script has already copied these values into dt_*.
BEGIN;

DROP INDEX IF EXISTS users_global_email_unique_idx;
DROP INDEX IF EXISTS users_company_email_unique_idx;
DROP INDEX IF EXISTS users_customer_email_prefix_idx;
ALTER TABLE bt_users DROP CONSTRAINT IF EXISTS users_email_key;
ALTER TABLE bt_users DROP COLUMN IF EXISTS email;
ALTER TABLE bt_users DROP COLUMN IF EXISTS street;
ALTER TABLE bt_users DROP COLUMN IF EXISTS house_number;
ALTER TABLE bt_users DROP COLUMN IF EXISTS box_number;
ALTER TABLE bt_users DROP COLUMN IF EXISTS postal_code;
ALTER TABLE bt_users DROP COLUMN IF EXISTS city;
ALTER TABLE bt_users DROP COLUMN IF EXISTS country_code;
ALTER TABLE bt_users DROP COLUMN IF EXISTS address_reference_id;

ALTER TABLE bt_companies DROP COLUMN IF EXISTS billing_email;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS phone;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS street;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS house_number;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS box_number;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS postal_code;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS city;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS country_code;
ALTER TABLE bt_companies DROP COLUMN IF EXISTS address_reference_id;

COMMIT;
