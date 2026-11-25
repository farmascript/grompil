-- User-facing multilingual release notes and per-user email subscriptions.
-- The selected function-key slot SHIFT_F12 is intentionally replaced.

BEGIN;

CREATE TABLE IF NOT EXISTS ft_release_notes (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    messages JSONB NOT NULL
        CHECK (jsonb_typeof(messages) = 'object'),
    wiki_page_id VARCHAR(12) NOT NULL
        CHECK (wiki_page_id ~ '^[0-9]{1,12}$'),
    created_by_user_code VARCHAR(6)
        REFERENCES bt_users(user_code) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ft_release_notes_has_message_ck CHECK (
        COALESCE(NULLIF(BTRIM(messages->>'NL'), ''),
                 NULLIF(BTRIM(messages->>'FR'), ''),
                 NULLIF(BTRIM(messages->>'EN'), ''),
                 NULLIF(BTRIM(messages->>'DE'), '')
        ) IS NOT NULL
    )
);

CREATE INDEX IF NOT EXISTS ft_release_notes_created_idx
    ON ft_release_notes (created_at DESC, id DESC);

ALTER TABLE ft_release_notes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS ft_release_notes_read_all ON ft_release_notes;
CREATE POLICY ft_release_notes_read_all ON ft_release_notes
    FOR SELECT USING (true);
DROP POLICY IF EXISTS ft_release_notes_platform_publish ON ft_release_notes;
CREATE POLICY ft_release_notes_platform_publish ON ft_release_notes
    FOR INSERT
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

CREATE TABLE IF NOT EXISTS dt_user_release_subscriptions (
    user_code VARCHAR(6) PRIMARY KEY
        REFERENCES bt_users(user_code) ON DELETE CASCADE,
    language_code VARCHAR(2) NOT NULL DEFAULT 'NL'
        CHECK (language_code IN ('NL', 'FR', 'EN', 'DE')),
    subscribed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE dt_user_release_subscriptions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS dt_user_release_subscriptions_self ON dt_user_release_subscriptions;
CREATE POLICY dt_user_release_subscriptions_self ON dt_user_release_subscriptions
    FOR SELECT USING (user_code = NULLIF(current_setting('grompil.user_code', true), ''));
DROP POLICY IF EXISTS dt_user_release_subscriptions_insert_self ON dt_user_release_subscriptions;
CREATE POLICY dt_user_release_subscriptions_insert_self ON dt_user_release_subscriptions
    FOR INSERT WITH CHECK (user_code = NULLIF(current_setting('grompil.user_code', true), ''));
DROP POLICY IF EXISTS dt_user_release_subscriptions_update_self ON dt_user_release_subscriptions;
CREATE POLICY dt_user_release_subscriptions_update_self ON dt_user_release_subscriptions
    FOR UPDATE
    USING (user_code = NULLIF(current_setting('grompil.user_code', true), ''))
    WITH CHECK (user_code = NULLIF(current_setting('grompil.user_code', true), ''));
DROP POLICY IF EXISTS dt_user_release_subscriptions_delete_self ON dt_user_release_subscriptions;
CREATE POLICY dt_user_release_subscriptions_delete_self ON dt_user_release_subscriptions
    FOR DELETE USING (user_code = NULLIF(current_setting('grompil.user_code', true), ''));

CREATE OR REPLACE FUNCTION fn_release_update_recipients()
RETURNS TABLE (email_address TEXT, language_code VARCHAR(2))
LANGUAGE SQL
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
SET row_security = off
AS $function$
    SELECT DISTINCT ON (lower(btrim(contact.contact_value)))
           btrim(contact.contact_value), subscription.language_code
    FROM public.dt_user_release_subscriptions AS subscription
    JOIN public.bt_users AS app_user
      ON app_user.user_code = subscription.user_code
     AND app_user.is_active
     AND app_user.approval_status = 'approved'
    JOIN public.dt_user_contacts AS contact
      ON contact.user_code = app_user.user_code
     AND contact.is_login_email
     AND contact.is_active
    WHERE btrim(contact.contact_value) <> ''
      AND current_setting('grompil.platform_operator', true) = 'true'
    ORDER BY lower(btrim(contact.contact_value)), subscription.language_code
$function$;

REVOKE ALL ON FUNCTION fn_release_update_recipients() FROM PUBLIC;

INSERT INTO ft_platform_dispensing_function_keys
    (key_code, action_key, action_config, labels, updated_at)
VALUES
    ('SHIFT_F12', 'open_page:release-notes.php', '{}'::JSONB,
     '{"NL":{"title":"Wijzigingen","tooltip":"Bekijk de wijzigingen en verbeteringen in Grompil"},"FR":{"title":"Nouveautés","tooltip":"Consulter les changements et améliorations de Grompil"},"EN":{"title":"What’s new","tooltip":"View changes and improvements in Grompil"},"DE":{"title":"Änderungen","tooltip":"Änderungen und Verbesserungen in Grompil anzeigen"}}'::JSONB,
     now())
ON CONFLICT (key_code) DO UPDATE
SET action_key = EXCLUDED.action_key,
    action_config = EXCLUDED.action_config,
    labels = EXCLUDED.labels,
    updated_by_user_code = NULL,
    updated_at = now();

DO $$
DECLARE
    application_role NAME;
BEGIN
    SELECT rolname INTO application_role
    FROM pg_roles
    WHERE rolname::text = current_database() || '_app'
       OR rolname = 'grompil_app'
    ORDER BY CASE WHEN rolname::text = current_database() || '_app' THEN 0 ELSE 1 END
    LIMIT 1;

    IF application_role IS NULL THEN
        RAISE EXCEPTION 'Application database role not found (expected %_app or grompil_app)', current_database();
    END IF;

    EXECUTE format('GRANT SELECT, INSERT ON ft_release_notes TO %I', application_role);
    EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON dt_user_release_subscriptions TO %I', application_role);
    EXECUTE format('GRANT USAGE, SELECT ON SEQUENCE ft_release_notes_id_seq TO %I', application_role);
    EXECUTE format('GRANT EXECUTE ON FUNCTION fn_release_update_recipients() TO %I', application_role);
    EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON ft_platform_dispensing_function_keys TO %I', application_role);
END;
$$;

COMMENT ON TABLE ft_release_notes IS
    'Platform-published, multilingual user-facing progress and change notes; each item links to a user-level DokuWiki page.';
COMMENT ON TABLE dt_user_release_subscriptions IS
    'Per-user email subscription for Grompil release notes; the language code is captured at subscription time.';
COMMENT ON FUNCTION fn_release_update_recipients() IS
    'Returns active login email addresses and language preferences of release-note subscribers for platform notifications.';

COMMIT;
