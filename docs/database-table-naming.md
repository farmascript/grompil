# Database table naming

Table prefixes describe a table's data family. They do not encode its dependency level.

| Prefix | Meaning | Typical contents |
|---|---|---|
| `bt_` | Basic business entities | Companies, user identities, and future Grompil-owned product records |
| `xt_` | External source data | SAM catalog and classification imports |
| `ft_` | Shared facilities and platform reference/configuration data | Lookup lists, roles, shared settings, and translation overrides |
| `ct_` | Company-scoped records and configuration | Company memberships, roles, pages, domains, and preferences |
| `st_` | System operation and security records | Login/reset attempts, tokens, MFA credentials, and recovery codes |
| `rt_` | Pure relationship tables, if needed | Tables containing only keys that connect other records |

Use lowercase, unquoted PostgreSQL identifiers.

## Design levels

Levels are a design aid for choosing a table's structure and naming family. They are not stored in the database and have no runtime behavior.

- **Level 0:** foundational business or reference records used by other data.
- **Level 1:** records with their own content that can also reference other records.
- **Level 2:** pure relationships represented only by relational keys.
- **Outside the levels:** operational events and technical/security state, which belong to `st_`.

The prefix and level answer different questions. For example, `ct_company_users` is company-scoped (`ct_`) and Level 1 because membership includes approval and activation state. It is not Level 2 even though it connects users and companies.

## Current table mapping

| Current name | New name | Level |
|---|---|---:|
| `companies` | `bt_companies` | 0 |
| `users` | `bt_users` | 1 |
| `roles` | `ft_roles` | 0 |
| `company_roles` | `ct_company_roles` | 1 |
| `company_users` | `ct_company_users` | 1 |
| `company_domains` | `ct_company_domains` | 1 |
| `company_pages` | `ct_company_pages` | 1 |
| `company_navigation_preferences` | `ct_company_navigation_preferences` | 1 |
| `platform_navigation_preferences` | `ft_platform_navigation_preferences` | Outside |
| `platform_system_settings` | `ft_platform_system_settings` | Outside |
| `dd_varia` | `ft_dd_varia` | 1 |
| `substitutions` | `ft_substitutions` | Outside |
| `access_ip_allowlist` | `ft_access_ip_allowlist` | 1 |
| `registration_attempts` | `st_registration_attempts` | Outside |
| `login_attempts` | `st_login_attempts` | Outside |
| `kbo_lookup_attempts` | `st_kbo_lookup_attempts` | Outside |
| `password_reset_tokens` | `st_password_reset_tokens` | Outside |
| `password_reset_attempts` | `st_password_reset_attempts` | Outside |
| `platform_totp_credentials` | `st_platform_totp_credentials` | Outside |
| `platform_totp_recovery_codes` | `st_platform_totp_recovery_codes` | Outside |
| `sam_public_products` | `xt_sam_public_products` | 0 |
| `sam_atc_classifications` | `xt_sam_atc_classifications` | 0 |
| `pharmacies` | `xt_pharmacies` | 0 |

There is currently no pure Level 2 table. If one is introduced, use the `rt_` family. External SAM and AFMPS records remain `xt_`; a separately maintained Grompil product entity can use `bt_products` if and when that table is introduced.

## Applying the table rename

For an existing database, run `migrations/20261107-120000_table_prefixes.sql` once before deploying application code that uses the new names. The migration renames tables in place, preserving their rows, keys, and attached constraints. It can be run again safely after a successful rename.

For a new database, use the current `schema.sql`, `scripts/schema_sam_*.sql`, and `scripts/schema_xt_pharmacies.sql` files. Older migration files retain their original table names as historical migration records; do not replay already-applied historical migrations after the rename migration.
