# Contact and address records

`bt_users` and `bt_companies` hold the core identity of a person or company. Their variable contact channels and addresses live in Level 1 detail tables:

| Table | Owner | Contains |
|---|---|---|
| `dt_user_contacts` | `bt_users.user_code` | Email, telephone, mobile, website, and other user contact channels |
| `dt_company_contacts` | `bt_companies.id` | Company contact channels such as billing email and telephone |
| `dt_user_locations` | `bt_users.user_code` | Residential, postal, delivery, and other user addresses |
| `dt_company_locations` | `bt_companies.id` | Headquarters, branch, delivery, billing, and other company addresses |

Contact and address types are stable child keys in `ft_dd_varia`, under the roots `contact_types` and `address_types`. The keys are stored in the detail rows; their labels remain translatable. `purpose_key` and `label` distinguish multiple contacts of one type, while location rows can repeat the same address type. At most one active address per owner and type can be primary.

The user's login email is a contact row with `contact_type_key = 'email'`, `purpose_key = 'login'`, and `is_login_email = true`. A partial unique index permits only one active login-email row per user, and login email uniqueness remains scoped globally for platform accounts and by company for company accounts.

Company contact and address rows have an `is_public` flag. Public database reads are limited to rows explicitly marked public for an active, public company. A company's headquarters address is marked public because the company directory already showed its city; other contacts and locations default to private. Company administrators and platform operators can read and manage records in their authorized scope.

Address rows can also store coordinates returned by the official BeSt address lookup: `lambert72_x` and `lambert72_y` (Belgian Lambert 72), plus `wgs84_latitude` and `wgs84_longitude` (WGS84). The application uses WGS84 to show a Google Maps link for addresses with coordinates. Coordinates remain `NULL` when the source does not provide them, such as manually entered addresses. Existing databases need `scripts/20261111-150000_address_coordinates.sql`; saved addresses gain coordinates when selected again from the official suggestions and saved.

## Existing database migration

1. Run `schema.sql`. It creates the lookup types and four `dt_*` tables, and makes the legacy user email column nullable so the old application remains usable during rollout.
2. Run `scripts/20261107-160000_migrate_contact_locations.sql`. It copies existing login emails, profile addresses, company billing contacts, and headquarters addresses into the new tables. It is repeatable and leaves legacy columns in place.
3. Deploy the application code. Verify login, password recovery, registration, account edits, user administration, company management, customer lookup, and the public company directory.
4. After verification, run `scripts/20261107-160100_cleanup_legacy_contact_columns.sql` to drop the old inline contact and address columns.

Do not run the cleanup script before the new application version has been deployed and checked. The migration preserves existing values in the normalized tables before cleanup removes duplicate legacy storage.
