# Magistral compounding: principles and workflow

**Status:** caregiver registry foundation implemented; magistral preparation workflow and operational tables remain future work.

This document records the design principles and implementation sequence for
magistral preparations in Grompil. Keep it current as decisions are made and
modules are implemented.

## Design principles

1. **Build a small usable core, then extend it.** Each phase should deliver a
   useful, reviewable module and leave the next phase possible without a
   redesign.
2. **Keep source data, shared reference data, company data and transactions
   separate.** Never turn an imported source snapshot into an editable
   operational record.
3. **Preserve provenance and versions.** A preparation must remain explainable
   after SAM or RIZIV publishes a newer version. Store the source/version used
   at the time and retain the actual preparation data.
4. **Make operational changes auditable.** Do not silently rewrite a completed
   preparation or dispense record. Corrections should be explicit, attributable
   and linked to the original.
5. **Make every module independently usable.** Modules communicate through
   stable identifiers and shared domain functions, not through page-specific
   HTML or duplicated SQL.
6. **Keep company-owned data tenant-scoped.** Company formulas, stock lots,
   preparation records and patient links must be isolated by `company_id` and
   protected by the application's existing access-control and RLS patterns.
7. **Separate calculations from presentation.** Unit conversions, scaling,
   reimbursement lookups and status transitions belong in reusable domain
   functions with explicit inputs and outputs.
8. **Do not make clinical or legal decisions implicitly.** Show source data,
   calculations, conditions and warnings for review by an authorized
   pharmacist. Keep the source and effective date visible.
9. **Keep patient history durable and retrievable.** A history must not become
   inaccessible merely because it is old, a patient is inactive, or a session
   has been closed. Support the practical case of retrieving records that are
   at least 40 years old, while applying the legally confirmed retention rule
   for each record category.

## Naming and module boundaries

New user-facing PHP entry points for this feature should start with `mb_`, for
example `mb_formula_catalog.php` and `mb_preparation.php`. Their visible names,
navigation labels and help text remain translatable and non-technical.

Keep the normal Grompil structure:

- the root `mb_*.php` file is a thin authenticated entry point and URL-manager
  registration;
- page/controller logic belongs under a domain folder in `src/`;
- reusable HTML-independent database and domain functions belong in `includes/`;
- templates and assets stay separate from those PHP layers.

The `mb_` prefix is for the new public module entry points. Do not add it to
shared function names, SQL table families or existing module URLs. Existing
lookup pages such as `compound-products.php` remain compatible; new
magistral-preparation screens can link to them or replace them deliberately in
a later, separately reviewed change.

## Data boundaries

Keep the existing source and reference tables in their current roles:

- `xt_sam_compounding_catalog` is the versioned SAM CMP source mirror. It
  contains both formula and ingredient records, multilingual names and source
  payloads. Imports replace or add source snapshots; users do not edit these
  rows through operational screens.
- `xt_compounding_raw_materials`,
  `xt_compounding_raw_material_sources` and
  `xt_compounding_reimbursement_codes` represent shared canonical material
  identity, source identifiers and dated RIZIV rules.
- `xt_chem` is an imported chemical source. Link its records to canonical
  materials while retaining its original identifier and data; do not make a
  second canonical master merely to copy the same chemicals.
- Company-specific supplier details, stock, locations, prices and received
  batches belong in company-scoped operational tables, not the shared source
  catalogues.

The current SAM CMP table does not expose a dedicated relational table for
formula components. Before implementing formula scaling, inspect the actual
SAM XML/payload and define a structured formula-to-component mapping with the
source product IDs, component order, quantity, unit and any required source
metadata. Do not infer component quantities from display names.

Keep these concepts distinct:

1. **Source formula:** the SAM or other source definition, including version.
2. **Company formula:** a locally created or adapted formula, with explicit
   owner, version and change history.
3. **Preparation instance:** a concrete, patient-specific job created from a
   prescription and a selected formula or entered composition.
4. **Preparation component:** planned and actual quantity for each ingredient
   used in that job.
5. **Stock batch:** a company's received raw-material lot, with supplier,
   source batch/lot number, received quantity, expiry and location.
6. **Dispense/history record:** the completed supply to the patient, linked to
   the preparation instance and its prescription number.

### Caregiver identity, contacts and addresses

The caregiver registry is a prerequisite for prescription and preparation
workflows. Use a Grompil-owned basic entity (`bt_caregivers`), not an `xt_`
source mirror. Give each caregiver an immutable generated `caregiver_code`;
external identifiers such as RIZIV numbers are attributes, never the primary
key. Store external identifiers separately because a caregiver can have more
than one identifier or competence code and these can change over time. Keep
the identifier type/issuer, source, validity and verification information.

Caregiver identity is shared, while contact and address details can be either
shared or company-specific. Use caregiver-owned detail tables following the
existing customer/user contact and address patterns:

- A row with `company_id IS NULL` is a shared/general contact or address,
  suitable for verified external sources such as an electronic prescription
  or an authorized external register.
- A row with a non-null `company_id` is private to that company. For example,
  one pharmacy may have received a caregiver's private mobile number that
  must not be exposed to other companies.
- Keep general and company-specific rows in the same caregiver contact table
  and the same caregiver address table, but enforce tenant access and
  uniqueness separately for the global (`company_id IS NULL`) and company
  scopes. Do not copy a company-private value into the shared row.
- Include provenance for imported/shared data (source and verification time)
  so manual edits and future source refreshes do not silently overwrite one
  another. Retain active/inactive state and the existing primary/sort-order
  behavior where applicable.
- In a company context, show applicable shared details together with that
  company's private details, clearly identifying their scope. Prefer the
  company's private value for that company when both scopes contain the same
  contact type; never use it as a fallback for another company.
- Model caregiver addresses analogously to customer addresses, including
  address type, label, street, house number, box, postal code, city, country
  and optional coordinates. A company may have a local address/label for a
  caregiver without changing the shared address.

The exact table and constraint design must be checked against the installed
PostgreSQL version and current RLS conventions before writing the migration.
The schema should not depend on nullable-column uniqueness behaving as if NULL
were an ordinary company ID: use explicit global and company-scoped unique
indexes/constraints where needed.

Proposed table names are not final until the relationships, uniqueness rules,
RLS policies and retention needs are reviewed. In particular, verify the SAM
formula-component mapping before creating its production schema.

## Historiek, bewaring en raadpleegbaarheid

Patient-linked prescriptions, preparations and completed dispensing records
must remain findable and readable over the long term. The application should
support the practical requirement that a pharmacist can retrieve and inspect
records at least 40 years old, as can be necessary for historical prescription
and dispensing data. Treat this as an archive and application-design
requirement; do not assume that every source, audit or operational record has
the same statutory retention period.

Design implications:

- Never delete a completed preparation or dispensing history as a side effect
  of closing a session, deactivating a patient, merging patient records or
  importing a newer source version. Corrections must preserve the original
  record and its traceability.
- Keep stable patient, prescription, preparation and dispense identifiers,
  together with the date, company, actor and relevant source/version snapshot.
  A patient merge must retain links from old identifiers to the surviving
  patient record.
- Make old records searchable and paginated through the normal authorized
  history screens. If cold/archive storage is introduced, retrieval should be
  transparent to the user and the record must be restored before it is shown
  as available.
- Include long-range history in backup, restore and migration planning. Verify
  that restored records retain their links, readable values and access audit
  information.
- Define retention and eventual disposal separately for each category (for
  example patient file, reimbursement evidence, source snapshots, security
  audit trail and temporary import data). Use the legally applicable trigger
  date for that category; where the patient-file rule applies, this may be the
  last patient contact rather than the row creation date.
- Keep access restricted to authorized users with the relevant patient-care
  relationship and record access in the audit trail. Long retention does not
  mean unrestricted access or keeping unrelated data indefinitely.

Before production retention or purge jobs are introduced, confirm the current
Belgian requirements with the pharmacy's responsible professional and map
each Grompil record type to the applicable rule. Article 35 of the Belgian
Quality of Healthcare Practice Act sets a 30-to-50-year period for the patient
file, counted from the last patient contact, for healthcare practitioners to
whom that provision applies. Its scope must be checked for each pharmacy data
category; reimbursement evidence and technical import data may follow
different rules. No automatic purge should be implemented from a single
platform-wide age threshold.

## User workflow

### 1. Formula and ingredient lookup

Provide a fast search by name, synonym, source code and CNK where applicable.
Show multilingual short and full names, formula/ingredient type, source,
version, validity and the source identifier. A result should open details
without modifying source data.

### 2. Prescription and preparation draft

Start from the active patient and prescription context. Select the prescriber
from the company's caregiver list by name or scanned identifier. Capture the
caregiver identity and relevant identifier as they were at prescription time,
the prescription/recept number, formula or free composition, dosage form,
prescribed amount and directions. Allow a draft to be saved before weighing;
show incomplete or missing required information without losing the draft.

### 3. Calculation and material selection

Scale the source formula to the requested amount and show each planned
ingredient quantity with its unit and calculation basis. Let the pharmacist
choose the company's actual stock batch. Display batch identity and expiry
before recording use. Keep the planned amount separate from the actual amount
weighed and require a reason when they differ beyond a configured tolerance.

### 4. Preparation and review

Use a clear, persisted status flow, initially proposed as:

`draft → prescription checked → ready to prepare → preparing → prepared →
checked → ready to dispense → dispensed`

Provide explicit cancellation and correction paths. Record who performed or
checked each step, when it happened, the procedure/reference used, actual
quantities and final amount. Do not calculate beyond-use dates or assign
preparation rules unless an approved, versioned source and company policy are
defined for that calculation.

### 5. Dispense and history

The completed preparation should appear as a line in the existing dispensing
session, linked to its preparation record. Confirming the receipt should move
the relevant information into the existing history workflow without deleting
the traceability chain. Make it possible to navigate from patient history to
preparation, component lots, formula version and source data.

## Screen layout

Prefer a preparation workbench divided into clear stages or panels rather than
one very long form:

1. Patient, prescriber and prescription.
2. Formula/composition and requested quantity.
3. Calculated ingredients and stock-batch selection.
4. Actual weighing, preparation notes and checks.
5. Dispense/receipt hand-off and historical record.

Keep the active patient and preparation identifier visible while moving through
the stages. Save draft progress server-side so multiple tabs/devices cannot
silently overwrite one another; use explicit version checks when editing the
same preparation concurrently.

## Implementation sequence

0. **Caregiver registry foundation (implemented):** shared caregiver identity,
   company-specific caregivers, professional identifiers, contacts and
   addresses are stored separately. `mb_caregivers.php` supports search and
   maintenance of local records and company-private details. Shared identity
   and general details are read-only for company managers; platform managers
   can maintain shared master records without selecting a company. Its lookup
   endpoint can be reused by the later prescription/dispensing workflow. No
   public bulk RIZIV import is assumed; imported shared records need a verified
   source and provenance.

1. **Source analysis:** compare `xt_chem`, SAM CMP ingredients/formulas and
   RIZIV source rows. Document identifiers, overlap and source precedence.
2. **Read-only formula explorer:** search and inspect current formula and
   ingredient source records. Add structured formula components only after the
   underlying SAM data relationship is verified.
3. **Company material stock:** manage suppliers, received lots, expiry,
   locations, quantities and company-specific prices; record movements rather
   than directly editing a stock total.
4. **Preparation draft:** create and persist patient/prescription-bound
   preparation jobs with formula version and planned composition.
5. **Execution and checks:** actual weighing, batch consumption, procedure,
   staff attribution and review/correction history.
6. **Dispense integration:** link a finished preparation to the existing
   dispensing session, receipt and patient history.
7. **Cost and reimbursement:** calculate from dated, imported rules and show
   the source date and eligibility conditions. Keep clinical preparation and
   reimbursement calculations separately reviewable.

Each phase should define its data contract, permission matrix, status model,
translations, template/UI behavior, migration and rollback approach before
implementation. Do not begin the next phase until the current data flow has
been exercised with representative source and company records.

## Official references to verify during implementation

- [RIZIV: reimbursement of magistral preparations](https://www.riziv.fgov.be/nl/thema-s/verzorging-kosten-en-terugbetaling/wat-het-ziekenfonds-terugbetaalt/geneesmiddelen/geneesmiddel-terugbetalen/magistrale-bereidingen)
- [FAGG: Royal Decree of 21 January 2009 concerning pharmacists](https://www.fagg-afmps.be/sites/default/files/downloads/AR-KB-2009-01-21-pharmaciens.pdf)
- [Belgian Quality of Healthcare Practice Act of 22 April 2019, Article 35](https://etaamb.openjustice.be/nl/wet-van-22-april-2019_n2019041141.html)
- [Ordomedic: patient-file retention and GDPR context](https://ordomedic.be/nl/faq/bewaring-van-pati%C3%ABntendossiers)

These references help identify data and workflow requirements; review the
current applicable rules before implementing automated decisions or claims.
