-- Add platform-managed hierarchical dropdown data.
BEGIN;

CREATE TABLE IF NOT EXISTS dd_varia (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    list_key VARCHAR(80) NOT NULL
        CHECK (list_key ~ '^[a-z][a-z0-9_-]{0,79}$'),
    "key" VARCHAR(80) NOT NULL
        CHECK ("key" ~ '^(_root|[a-z][a-z0-9_-]{0,79})$'),
    content VARCHAR(130) NOT NULL
        CHECK (content ~ '^@[A-Za-z0-9_.-]{1,120}@$'),
    parent_id BIGINT,
    info TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (list_key, id),
    UNIQUE (list_key, "key"),
    CONSTRAINT dd_varia_parent_same_list_fk
        FOREIGN KEY (list_key, parent_id) REFERENCES dd_varia (list_key, id) ON DELETE RESTRICT,
    CONSTRAINT dd_varia_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id)
);
COMMENT ON TABLE dd_varia IS 'Platform-managed hierarchical lookup values. content stores an @translation_key@ token.';
CREATE INDEX IF NOT EXISTS dd_varia_lookup_idx
    ON dd_varia (list_key, parent_id, is_active, sort_order, id);

ALTER TABLE dd_varia ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS dd_varia_visible_active_or_platform ON dd_varia;
CREATE POLICY dd_varia_visible_active_or_platform ON dd_varia
    FOR SELECT USING (
        is_active OR current_setting('grompil.platform_operator', true) = 'true'
    );
DROP POLICY IF EXISTS dd_varia_platform_write ON dd_varia;
CREATE POLICY dd_varia_platform_write ON dd_varia
    FOR ALL USING (current_setting('grompil.platform_operator', true) = 'true')
    WITH CHECK (current_setting('grompil.platform_operator', true) = 'true');

COMMIT;
