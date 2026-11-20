# Pharmacy dispensing workbench

The pharmacy workbench is available at `dispense.php`. It requires an active company and the company permission `pharmacy.dispense`; the standard `Apotheker` role receives that permission. A company administrator can grant it to another role in the role editor.

## Current data and behavior

- Customer records are company-scoped in `bt_customers`. Each record gets a globally unique six-character `customer_code`; the same person entered for another company is a separate record. In pharmacy companies these are called patients; other company classes can use their own customer terminology.
- Each customer may have multiple addresses in `dt_customer_locations`, with address type, recognition label, display order, primary-address flag, and geocoding fields. The workbench shows the primary address and lets staff select, add, or edit the customer’s addresses.
- Database triggers record create and update events, actor codes, timestamps, and before/after snapshots in `st_customer_audit_log`. Customer codes and company ownership cannot be changed, and customer rows cannot be physically deleted. A dedicated authorized deactivation workflow can be added when needed.
- Companies have a platform-managed classification in `ft_dd_varia` (`company_classification`). New and existing companies default to `pharmacy`; the platform administrator can change a company to `business` in the company editor.
- Search and edits are scoped to the active company. PostgreSQL row-level security provides a second tenant boundary.
- The workbench has two explicit modes: patient-linked dispensing and walk-in sale without a patient. Product search in patient mode requires a valid active patient from the current company; walk-in mode does not attach products to any patient.
- The dispensing product-search endpoint checks the selected mode and, in patient mode, verifies the patient in the active company before searching the SAM catalogue. The separate `sam-products.php` endpoint remains available to its other authorized tools.
- Each browser tab keeps its own active dispensing session in `dt_dispensing_sessions`, with names in `dt_dispensing_parties` and product lines in `dt_dispensing_lines`. Confirming the receipt copies the details to history; F12 discards the current open session and its related rows after confirmation.
- The count at the upper right of the current dispensing panel shows the number of distinct product lines, not the sum of their quantities.
- Electronic prescriptions, live stock, safety checks, reimbursement, prices, and checkout are not connected. Their controls are deliberately disabled.

Page layout lives in `tmpl/core/dispensing*.html`; translations live in the four `src/lang/lang_*.php` files. `src/pharmacy/dispensing.php` handles access checks, database operations, and view data. Styling and browser behavior are in `assets/dispensing.css` and `assets/dispensing.js`.

## Existing databases

For an existing database, back it up, deploy the application files, then run `migrations/20261112-090000_patient_records.sql` if it has not already been run, followed by `migrations/20261112-160000_customer_company_classification.sql` as the database owner. Then run the matching environment grant file (`schema_grant_local.sql`, `schema_grant_test.sql`, or `schema_grant_production.sql`) so the application role has access under the renamed table names. Both migrations are transactional and preserve existing customer/address/audit data; all existing companies are initially classified as pharmacies. The full `schema.sql` is for initial database creation and defines the same current structure.

After the migration, open `dispense.php` while working in a company whose role has `pharmacy.dispense`. No translation-database import/export is needed; the UI strings are in the source language files. For the F12 discard action, also run `migrations/20261120_142834_dispensing_session_discard.sql` on each existing database as its database owner. It adds the action to the platform shortcut allow-list and assigns it to F12 when F12 is still unassigned.
