# Company-specific patient profile

`dt_company_customer_profiles` stores optional settings for one patient at one company. A patient with no additional settings has no row; the application treats that as the default profile. Saving all values empty and all switches off removes the row.

The initial fields are:

- `fixed_caregiver_code`: a company-visible physician or dentist selected from `bt_caregivers`.
- `is_home_pharmacist`: whether this company is the patient's regular pharmacy.
- `print_bvac`: whether an administrative BVAC should be printed after closing a dispensing. This records the preference; receipt generation still needs to implement the print behavior.
- `invoice_group`: a temporary code or description, since invoice groups do not yet have a lookup table.
- `vat_number`: a billing VAT number.

The profile is company-scoped and protected by row-level security. National Register numbers stay in `dt_customer_identifiers`; email notification consent and delivery logging are outside this profile.

Apply `migrations/20261128_165621_company_customer_profiles.sql` to existing databases. Fresh schema installs create the table in `schema.sql`.
