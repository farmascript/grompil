-- Make the DokuWiki page reference optional for release notes.
-- When omitted, the release note's own table ID is shown instead.

BEGIN;

ALTER TABLE ft_release_notes
    ALTER COLUMN wiki_page_id DROP NOT NULL;

ALTER TABLE ft_release_notes
    DROP CONSTRAINT IF EXISTS ft_release_notes_wiki_page_id_check,
    DROP CONSTRAINT IF EXISTS ft_release_notes_wiki_page_id_ck;

ALTER TABLE ft_release_notes
    ADD CONSTRAINT ft_release_notes_wiki_page_id_ck
    CHECK (wiki_page_id IS NULL OR wiki_page_id ~ '^[0-9]{1,12}$');

COMMIT;
