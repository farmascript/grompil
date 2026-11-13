# Dispensing templates

The dispensing workflow keeps its PHP logic in `src/pharmacy/dispensing.php`.
Its HTML is rendered from `tmpl/modules/dispensing/<variant>/`. The current
variants are `standard` and `wide`.

## Select a variant

Set the deployment-specific option in `settings_local.php`:

```php
'dispensing_template' => 'wide',
```

Use `standard` to keep the existing centered layout. The `wide` variant uses a
fluid page wrapper and fills the available screen width. If the option is
missing or invalid, the application uses `standard`.

## Add a design variant

Create a folder such as `tmpl/modules/dispensing/<new-name>/`. Add a
`dispensing.html` file for the main screen. A variant can override any of the
smaller `dispensing_*.html` components; components it does not provide inherit
the matching file from `standard/`. Add a stylesheet under `assets/` and link it
from the variant's main HTML file.

Templates receive translated text and data through `@token@` placeholders.
Values beginning with `_` are HTML fragments supplied by the application.
Preserve IDs, form fields, `data-*` attributes, and ARIA relationships used by
`assets/dispensing.js`; these form the interaction contract between the design
and the application logic. A designer can change the markup and styling while
keeping that contract intact.

The selection currently applies to the deployment as a whole. Company-specific
selection can be added later without changing the template files themselves.
