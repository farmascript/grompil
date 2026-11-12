-- One-time reset for company accounts only.
-- This removes encrypted TOTP secrets and recovery codes for users linked to a company.
-- Platform accounts (bt_users.company_id IS NULL) are preserved.
-- Run once on the local and test databases as the database owner, then do not rerun.

BEGIN;
SELECT set_config('grompil.platform_operator', 'true', true);

DELETE FROM st_platform_totp_recovery_codes AS recovery
USING bt_users AS users
WHERE users.user_code = recovery.user_code
  AND users.company_id IS NOT NULL;

DELETE FROM st_platform_totp_credentials AS credentials
USING bt_users AS users
WHERE users.user_code = credentials.user_code
  AND users.company_id IS NOT NULL;

COMMIT;
