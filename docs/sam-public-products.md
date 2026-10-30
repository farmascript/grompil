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

Numeric input is an exact CNK lookup. Other input uses PostgreSQL `pg_trgm`
similarity against both Dutch and French prescription names. Searches shorter
than three characters return no fuzzy matches. The result limit defaults to 20
and is capped at 50. A CNK may map to more than one SAM registration, so a CNK
lookup can return multiple rows.

Before fuzzy search can run, execute `scripts/schema_sam_public_products_search.sql`
as a database/table owner. It enables `pg_trgm` and creates GIN trigram indexes
for both prescription-name columns. Fresh database creation through
`scripts/schema_sam_public_products.sql` includes the same extension and indexes.

Callers must apply authentication and company access checks before showing
results. The helper returns SAM snapshot data; it does not decide whether a
product can legally or commercially be sold.
