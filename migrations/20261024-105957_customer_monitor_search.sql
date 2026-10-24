-- Prefix-search indexes for the company-scoped customer type-ahead field.
BEGIN;

CREATE INDEX IF NOT EXISTS users_customer_name_prefix_idx
    ON users (company_id, lower(display_name) text_pattern_ops) WHERE company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_customer_email_prefix_idx
    ON users (company_id, lower(email) text_pattern_ops) WHERE company_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS users_customer_acronym_prefix_idx
    ON users (company_id, lower(acronym) text_pattern_ops)
    WHERE company_id IS NOT NULL AND acronym IS NOT NULL AND acronym <> '';

COMMIT;
