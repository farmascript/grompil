-- Allow platform operators to edit and delete published release notes.

BEGIN;

DROP POLICY IF EXISTS ft_release_notes_platform_edit ON ft_release_notes;
CREATE POLICY ft_release_notes_platform_edit ON ft_release_notes
    FOR UPDATE
    USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

DROP POLICY IF EXISTS ft_release_notes_platform_delete ON ft_release_notes;
CREATE POLICY ft_release_notes_platform_delete ON ft_release_notes
    FOR DELETE
    USING (current_setting('grompil.platform_operator', true) = 'true');

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

    EXECUTE format('GRANT UPDATE (messages, wiki_page_id) ON ft_release_notes TO %I', application_role);
    EXECUTE format('GRANT DELETE ON ft_release_notes TO %I', application_role);
END;
$$;

COMMIT;
