# Dispensing templates

The dispensing workflow keeps its PHP logic in `src/pharmacy/dispensing.php`.
Its HTML is rendered from `tmpl/modules/dispensing/<variant>/`. The current
variants are `standard` and `wide`.

## Choose a company layout

Each company stores its selected layout in `ct_company_preferences`. A company
administrator with the `company.settings.manage` permission can change it on
the company settings page. Companies without a saved preference use `standard`.

The available choices are the active child entries in the `dispensing_templates`
list in `ft_dd_varia`. The stable key must match a folder under
`tmpl/modules/dispensing/`; entries without a matching folder are not offered.
The display label comes from the entry's translation token in `dd_varia`.

## Add a design variant

Create a folder such as `tmpl/modules/dispensing/<new-name>/`. Add a
`dispensing.html` file for the main screen. A variant can override any of the
smaller `dispensing_*.html` components; components it does not provide inherit
the matching file from `standard/`. Add a stylesheet under `assets/` and link it
from the variant's main HTML file.

Then add an active child to the `dispensing_templates` list in the platform
lists manager. Use the folder name as its stable key and a translation token as
its content. Templates receive translated text and data through `@token@`
placeholders. Values beginning with `_` are HTML fragments supplied by the
application. Preserve IDs, form fields, `data-*` attributes, and ARIA
relationships used by `assets/dispensing.js`; these form the interaction
contract between the design and the application logic.

Apply `migrations/20261113-090000_company_dispensing_template.sql` to databases
that already exist, then run the matching `schema_grant_*.sql` file so the
application role can access the new table. Fresh databases receive the table
and seed entries through `schema.sql`.
