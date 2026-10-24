-- Platform-wide settings for account recovery and future operational parameters.
BEGIN;

CREATE TABLE IF NOT EXISTS platform_system_settings (
    "key" VARCHAR(80) PRIMARY KEY CHECK ("key" ~ '^[a-z][a-z0-9_]{0,79}$'),
    value_integer INTEGER NOT NULL CHECK (value_integer > 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
COMMENT ON TABLE platform_system_settings IS 'Non-secret platform runtime parameters; do not store credentials or private keys here.';

INSERT INTO platform_system_settings ("key", value_integer)
VALUES ('password_reset_max_requests', 3), ('password_reset_window_minutes', 15)
ON CONFLICT ("key") DO NOTHING;

ALTER TABLE platform_system_settings ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS platform_system_settings_read ON platform_system_settings;
CREATE POLICY platform_system_settings_read ON platform_system_settings
    FOR SELECT USING (true);
DROP POLICY IF EXISTS platform_system_settings_admin_write ON platform_system_settings;
CREATE POLICY platform_system_settings_admin_write ON platform_system_settings
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

GRANT SELECT, INSERT, UPDATE, DELETE ON platform_system_settings TO grompil_app;

COMMIT;
