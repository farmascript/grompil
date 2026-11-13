BEGIN;

-- The available dispensing layouts are platform-managed lookup entries.
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
VALUES ('dispensing_templates', '_root', '@dd_varia_root_dispensing_templates@', NULL,
        'Root for available dispensing screen templates.', 0)
ON CONFLICT (list_key, "key") DO NOTHING;

WITH root AS (
    SELECT id FROM ft_dd_varia
    WHERE list_key = 'dispensing_templates' AND "key" = '_root'
)
INSERT INTO ft_dd_varia (list_key, "key", content, parent_id, info, sort_order)
SELECT 'dispensing_templates', seed.item_key, '@' || seed.translation_key || '@', root.id,
       seed.info, seed.sort_order
FROM root
CROSS JOIN (VALUES
    ('standard', 'dispensing_template_standard', 'Centered default dispensing layout.', 10),
    ('wide', 'dispensing_template_wide', 'Wide dispensing layout.', 20)
) AS seed(item_key, translation_key, info, sort_order)
ON CONFLICT (list_key, "key") DO NOTHING;

CREATE TABLE IF NOT EXISTS ct_company_preferences (
    company_id BIGINT PRIMARY KEY REFERENCES bt_companies(id) ON DELETE CASCADE,
    dispensing_template_list_key VARCHAR(80) NOT NULL DEFAULT 'dispensing_templates'
        CHECK (dispensing_template_list_key = 'dispensing_templates'),
    dispensing_template_key VARCHAR(80) NOT NULL DEFAULT 'standard',
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (dispensing_template_list_key, dispensing_template_key)
        REFERENCES ft_dd_varia(list_key, "key") ON DELETE RESTRICT
);

ALTER TABLE ct_company_preferences ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS company_preferences_read ON ct_company_preferences;
CREATE POLICY company_preferences_read ON ct_company_preferences
    FOR SELECT USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        OR current_setting('grompil.platform_operator', true) = 'true'
    );

DROP POLICY IF EXISTS company_preferences_admin_write ON ct_company_preferences;
CREATE POLICY company_preferences_admin_write ON ct_company_preferences
    FOR ALL USING (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM ct_company_users AS cu
                JOIN ct_company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = ct_company_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    )
    WITH CHECK (
        company_id = NULLIF(current_setting('grompil.company_id', true), '')::BIGINT
        AND (
            current_setting('grompil.platform_operator', true) = 'true'
            OR EXISTS (
                SELECT 1 FROM ct_company_users AS cu
                JOIN ct_company_roles AS cr
                  ON cr.company_id = cu.company_id AND cr.id = cu.company_role_id
                WHERE cu.company_id = ct_company_preferences.company_id
                  AND cu.user_code = NULLIF(current_setting('grompil.user_code', true), '')
                  AND cu.is_active AND cu.approval_status = 'approved'
                  AND cr.level > 0 AND cr.level < 100
                  AND cr.permissions ? 'company.settings.manage'
            )
        )
    );

COMMIT;
