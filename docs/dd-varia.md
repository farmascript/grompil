# Platform lookup lists (`dd_varia`)

`dd_varia` stores platform-wide hierarchical values for dropdowns and other lookup controls. Only a platform administrator can add, change, or deactivate rows. Companies cannot maintain their own versions.

## Database setup

For an existing database, run `migrations/20261030-120000_dd_varia.sql`, then run the matching `schema_grant_local.sql`, `schema_grant_test.sql`, or `schema_grant_production.sql` as the database owner. For a newly created database, the table and row-level security policies are also included in `schema.sql`.

For an existing `dd_varia` table whose values have no `_root` row, run `migrations/20261104-211155_dd_varia_roots.sql` as the database owner. It creates one root for each existing list and attaches previously root-level values to it without deleting their labels or IDs.

After the root migration, run `migrations/20261104-213519_dd_varia_content_keys.sql`. It gives each list root a distinct token such as `@dd_varia_root_media@` and normalizes item tokens to underscore-separated names such as `@ddv_address_mevr@`. It moves existing translation values in `substitutions` to the new keys. Then use the normal translation import/export workflow for that database so the new root labels and item tokens are available in the language files.

## Lists and hierarchy

`list_key` identifies a separate collection, such as `media` or `company_number_status`. It is the namespace for one tree, not a node in the hierarchy. Each collection starts with a root row whose key is `_root` and whose `parent_id` is null. Every value in that collection is a child or descendant of the root and points to its parent through `parent_id`. Keys are unique within a list. `sort_order` controls the order among siblings.

Use the `dd-varia.php` platform management page to create and edit values. When adding an item to a list with a root, the form selects that root as the default parent. It collects the label in NL, FR, EN, and DE. The application creates an underscore-separated translation token based on `list_key` and `key` in `content`, saves the four translations in the platform-wide translation records, and publishes the language files. `info` is an optional internal note. `updated_at` records the last edit.

Removing a value deactivates it rather than deleting it, preserving references already stored by the application. A value with active child items cannot be deactivated until those children are deactivated.

## Application access

`includes/dd_varia.php` provides `dd_varia_get_options($listKey, $parentKey, $includeInactive)`. It returns localized child options ordered by `sort_order`, then stable key. Passing a null parent key returns root rows; passing a parent key such as `media` returns its children. Inactive rows are omitted by default, and only a platform administrator can request them.

Use each returned `key` as the stable application value and `label` for display. Do not save the translated label as the selected value.

## Company number statuses

The `company_number_status` list contains the platform-managed choices for enterprise-number and VAT-number status. `schema.sql` creates the root and the initial choices (`known`, `pending`, `not_applicable`, and `unknown`) when they are absent. For an existing database, run `migrations/20261104-183410_company_number_status.sql` after the `dd_varia` table migration. The `companies` table stores the selected stable key separately in `enterprise_number_status` and `vat_number_status`.

To add a choice, add a child under `_root` in the `company_number_status` list and provide its translated label in NL, FR, EN, and DE. No PHP change is needed for a new status choice. The key `known` has one special validation rule: the corresponding number field must contain a number. Other statuses can be saved with or without a number. Deactivating a status hides it from new selections; an existing company can retain its inactive value until an administrator chooses an active status.
