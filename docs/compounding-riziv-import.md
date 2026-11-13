# RIZIV magistral raw-material import

The RIZIV reference workbook is the source for reimbursable CNK entries used by
the compounding raw-material catalogue. The importer preserves every workbook
row used for reimbursement in `source_payload_json`, including reimbursement
basis, unit, density, signs, restrictions, officialization and supplier fields.
It creates one canonical raw material per CNK and keeps separate dated chapter
and reimbursement-code rows.

The current official workbook is downloaded from RIZIV's [magistral preparation
reference page](https://www.riziv.fgov.be/nl/thema-s/verzorging-kosten-en-terugbetaling/wat-het-ziekenfonds-terugbetaalt/geneesmiddelen/geneesmiddel-terugbetalen/magistrale-bereidingen).
The downloader uses RIZIV's stable workbook URL; the workbook's effective date
is read from the file itself. It includes chapters I, II, IV and V, which list
reimbursable substances/raw materials. Chapter III preparations and chapter VI
devices are left out of this raw-material catalogue.

## Database prerequisites

Run `scripts/schema_xt_compounding_raw_materials.sql` and the matching
`schema_grant_*.sql` file for the environment first. The application role is
intentionally read-only for these source tables. For an `--apply` import, use a
database role that has `SELECT`, `INSERT` and `UPDATE` on the three
`xt_compounding_*` tables. Configure its password in a private `.pgpass` file;
the role also needs `USAGE` on the identity sequences for those tables. The
script checks these rights before an `--apply` run. The example role name below
is a placeholder: it must be a real role provisioned by the database owner. The
web application's read-only role should stay read-only. Check-only needs only
temporary-table creation and does not read or change the permanent tables.

## Run on the host

The script creates `~/sam/compounding/download`, `results`, `tmp` and `logs`.
It keeps the downloaded workbook and extracted CSV by RIZIV effective date.
An already archived workbook is never silently overwritten; if RIZIV replaces a
workbook without changing its effective date, the script stops for review. The
CSV is a derived file: when its serialization changes, it is regenerated from
the verified archived workbook.

Set the database connection explicitly in your shell, just as for the SAM
workflow:

```bash
export PGHOST=localhost
export PGDATABASE=grompilc_test
export PGUSER=YOUR_IMPORT_ROLE
export PGPASSFILE="$HOME/.pgpass"
bash ~/grompil_test/scripts/xt_compounding_riziv_sync.sh
```

Replace `YOUR_IMPORT_ROLE` with the real database role configured in `.pgpass`.
The command above downloads, extracts and validates the latest workbook, loads
temporary staging data and rolls the transaction back. Review the counts and
the CSV in `~/sam/compounding/results/`. To publish the snapshot, run:

```bash
bash ~/grompil_test/scripts/xt_compounding_riziv_sync.sh --apply
```

`--source-xlsx /path/to/file.xlsx` can be used to inspect a workbook already
downloaded. It still defaults to check-only; `--apply` is required to write.
The importer closes prior RIZIV reimbursement periods when a newer effective
date arrives, inserts the new rows transactionally, and keeps prior dated rows
for history. It does not delete raw materials or overwrite CAS, EC, formula,
molecular-mass or pharmacopoeia data.

## Source interpretation

RIZIV calls the identifier in the workbook a CNK. Numeric values are stored as
seven-character strings, with leading zeroes retained. The raw chapter number
is kept in `list_number` (for example `54040`), and the normalized chapter
(`I`, `II`, `IV` or `V`) is kept separately. Reimbursement conditions and
technical values are retained in the original JSON payload because the
workbook encodes some of these fields as source codes rather than human-readable
Dutch and French text. Consult the current RIZIV legal source when interpreting
reimbursement eligibility.

The RIZIV workbook's layout document maps these numeric identifiers to readable
chapter and paragraph labels. For example, `54` means Chapter IV and `54040`
means Chapter IV, §4. The list number is therefore a legal-list reference, not
a description of the raw material itself. The current mapping is documented in
the RIZIV [reference-file layout](https://www.riziv.fgov.be/SiteCollectionDocuments/magistrale_bereidingen_detailregistratie_referentiebestand.pdf);
the chapter's current eligibility rules are in the [official reimbursement
list](https://www.riziv.fgov.be/nl/thema-s/verzorging-kosten-en-terugbetaling/wat-het-ziekenfonds-terugbetaalt/geneesmiddelen/geneesmiddel-terugbetalen/magistrale-bereidingen).
