# SAM public product lookup

The read-only lookup helper is in `includes/sam_products.php`:

```php
$matches = grompil_sam_product_search('Haldol 5 mg/ml');
$matches = grompil_sam_product_search('0046128');
```

After deployment, the authenticated JSON test endpoint is available at
`/sam-products.php?q=0046128` or `/sam-products.php?q=Haldol%205%20mg%2Fml`.
It returns a compact JSON summary without the full source JSON payloads.

Platform operators can also use the localized browser test page at
`sam-products-test.php`. It is registered in the URL-manager catalog as
**Test SAM products / SAM-producten testen** and is hidden from other users.
The page shows suggestions while typing. Compact searches such as `hald50`
match name fragment `hald` plus numeric component `50`; an alphabetic run such
as `dafcod` is also tried as `daf` + `cod`. Numeric components are searched in
the prescription name and package display. The localized SAM `PackDisplayValue`
(for example `5 x 1 ml`) is exposed as `pack_display_nl` / `pack_display_fr`,
shown in results, and included in search.

The suggestion line displays the CNK, prescription name, package display,
status letters, and readable ATC hierarchy. Matching text is highlighted. A
new query or status-filter change hides the previous result panel so that it
cannot be confused with the new suggestions.

The test page filters on AMP and AMPP status together. All three checkboxes
(`AUTHORIZED`, `REVOKED`, `SUSPENDED`) are selected by default; a row is returned
when both its AMP and AMPP statuses match selected values.

The ATC column and suggestion line show the readable ATC hierarchy through the
pharmacological subgroup. For example, `N05AD01` displays
`Nervous System · Psycholeptics · Antipsychotics · Butyrophenone Derivatives`;
the final substance-level label (`Haloperidol`) is omitted. These descriptions
are English-only in the supplied SAM REF export.

To populate the reference descriptions, create `sam_atc_classifications` with
`scripts/schema_sam_atc_classifications.sql`, then export the REF file:

```powershell
php scripts/sam_atc_classifications_export.php "C:\path\to\REF-export.xml"
```

Import the generated CSV into `public.sam_atc_classifications` with columns
`atc_code, description` (for example using Adminer or psql `\\copy`). Then
run the matching `schema_grant_local.sql`, `schema_grant_test.sql`, or
`schema_grant_production.sql` so the application role can read the table.
This is a small, separate reference import; it does not require re-importing
the SAM product CSV.

The per-installation maximum number of product results is configured in
`settings_local.php` with `'sam_product_search_limit' => 20`. The default is
20, and values above 100 are capped at 100. The result page and JSON endpoint
also report when matching rows remain beyond the displayed limit. The cap
limits the response and the number of result rows rendered in the browser; it
is a practical payload and interface limit, not a PostgreSQL restriction. The
total match count is still calculated for the “shown of total” message.

Numeric input is an exact CNK lookup. Other input uses PostgreSQL `pg_trgm`
similarity when the extension is installed, and otherwise falls back to
substring/token matching. Compact fragment searches such as `dafcod` work in
either mode; typo similarity requires `pg_trgm`. Searches shorter than three
characters return no fuzzy matches. A CNK may map to more than one SAM
registration, so a CNK lookup can return multiple rows.

For an existing catalog, run `scripts/schema_sam_public_products_pack_display.sql`
as the table owner. It creates the localized package columns and fills them
from the preserved `ampp_data_json`. Run
`scripts/schema_sam_public_products_search.sql` only on a server where
`pg_trgm` is installed and available. Fresh database creation through
`scripts/schema_sam_public_products.sql` works without that optional extension.

Callers must apply authentication and company access checks before showing
results. The helper returns SAM snapshot data; it does not decide whether a
product can legally or commercially be sold.
