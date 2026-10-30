# Platform lookup lists (`dd_varia`)

`dd_varia` stores platform-wide hierarchical values for dropdowns and other lookup controls. Only a platform administrator can add, change, or deactivate rows. Companies cannot maintain their own versions.

## Database setup

For an existing database, run `migrations/20261030-120000_dd_varia.sql`, then run the matching `schema_grant_local.sql`, `schema_grant_test.sql`, or `schema_grant_production.sql` as the database owner. For a newly created database, the table and row-level security policies are also included in `schema.sql`.

## Lists and hierarchy

`list_key` identifies a separate collection, such as `varia`. The stable `key` identifies one item within that collection. A list normally starts with a root row whose key is `_root` and whose `parent_id` is null. Child rows refer to their parent row through `parent_id`; keys are unique within a list. `sort_order` controls the order among siblings.

Use the `dd-varia.php` platform management page to create and edit values. It collects the label in NL, FR, EN, and DE. The application creates a translation token such as `@ddv_123@` in `content`, saves the four translations in the platform-wide translation records, and publishes the language files. `info` is an optional internal note. `updated_at` records the last edit.

Removing a value deactivates it rather than deleting it, preserving references already stored by the application. A value with active child items cannot be deactivated until those children are deactivated.

## Application access

`includes/dd_varia.php` provides `dd_varia_get_options($listKey, $parentKey, $includeInactive)`. It returns localized child options ordered by `sort_order`, then stable key. Passing a null parent key returns root rows; passing a parent key such as `media` returns its children. Inactive rows are omitted by default, and only a platform administrator can request them.

Use each returned `key` as the stable application value and `label` for display. Do not save the translated label as the selected value.
