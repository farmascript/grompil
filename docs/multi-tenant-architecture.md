<!--
/**
 *
 * @date_cre: Wed 2026-10-14 21:21:01
 * @date_mut: Wed 2026-10-21 09:38:39
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Documents tenant boundaries, data isolation, and SaaS architecture.
 *
 */
-->

# Multi-tenant architecture — Grompil

## Implemented foundation (October 2026)

The first multi-tenant foundation is now in the application. The database uses `companies` as tenants, `company_users` for tenant memberships, `company_roles` for company-specific permissions, and `company_domains` for verified host mappings. A composite foreign key prevents a membership from pointing at a role belonging to another company. Existing company memberships are mapped from their old global role names when the schema is applied.

After login, a tenant-scoped account can enter only its own company. Platform operators can choose any active company. `company_members.php` manages tenant membership and approval; `company_roles.php` manages company roles. Platform identities remain global. A user must have an approved active membership to enter the company workspace.

The public root (`index.php`) is the Grompil SaaS marketing site. A public tenant is reached at `/c=<slug>` or `index.php?c=<slug>`. Domain mappings in `company_domains` remain available for future verified custom domains; domain verification and domain administration still need an operational UI.

Row-level security is enabled for the tenant boundary tables. The application database user must not own those tables and must not have `BYPASSRLS`; set up the schema with an administrative database account and use the restricted `grompil_app` user only for the application. This foundation has not yet been reviewed against a deployed database or subjected to an isolation/security test. Do not expose it to production until that review is completed.

The existing installation's companies are used as tenants; no new company is inferred or created automatically. Confirm the intended company and its membership after applying the schema. Company-specific product offers, stock, patient files, carts, orders, branding, and audit history are future modules and must use the tenant key and composite foreign keys described below.

## Tenant boundary

Each company is a tenant. Every company-owned record must carry a non-null `company_id`: product offers, stock, patient files where applicable, customer memberships, carts, orders, staff memberships, domains, settings, and IP ranges. The shared product master is global reference data; tenant-specific availability and prices are not. Queries and writes must derive the tenant from a verified host-to-company mapping, then check the authenticated user's membership. A role level never grants access to another tenant.

The first deployment model is one PostgreSQL cluster and database with tenant-aware tables. PostgreSQL row-level security is an additional isolation layer, not a replacement for prepared queries and tenant checks. The web application's database role must not own protected tables and must not have `BYPASSRLS`. Tenant context is set only after resolving an active, verified domain and membership.

## Identity and roles

Tenant login identities in `users` are scoped to one company with `company_id`. A person registering at multiple companies receives a separate user row per company; email and optional `acronym` uniqueness are scoped to the company and compared without regard to case. Login accepts either identifier. Platform operators remain global identities with `company_id IS NULL` and global permissions. `company_role_id` on `company_users` is the sole and authoritative company-role link. The old global `role_id` column on `company_users` is removed by migration `20261018-212151_remove_legacy_company_users_role_id.sql`; `users.role_id` remains the global account role.

Role levels are tenant-configurable classifications. SS is exact equality; SL is an open interval. Fine-grained permissions remain the authority for actions such as managing staff, prices, orders, and IP rules. Registration has separate outcomes: customers are active immediately; staff applicants stay pending until a company administrator approves them and assigns a tenant-specific staff role. A pending applicant cannot select or grant themselves a role.

## Domains and public shops

An active hostname resolves to exactly one company tenant. Support both a Grompil subdomain and a verified company-owned domain. Unknown, duplicate, unverified, or suspended domains fail closed. Product catalogues, prices, branding, copy, inventory, carts, and orders are tenant-scoped. A person joining multiple companies receives separate login identities; memberships, addresses, consent, carts, and orders remain scoped to the relevant company.

System UI translations use the existing DB-to-PHP export workflow. General substitutions use `company_id = 0`; a company can override an existing key with a row scoped to its company ID. Runtime loading merges the company array over the general language array. Company override files are exported under `src/lang/companies/<company_id>/`. Company-specific page copy belongs in `company_pages`, keyed by company, page and language. Templates and fallback copy remain in source files.

General application templates live in `tmpl/core/`. Public company-site layouts and per-page bodies live in `tmpl/themes/<theme>/site/`; storefront templates live in the same theme folder as `store.html`. A company can override a layout or page under `tmpl/companies/<company_id>/` using the same relative path. Resolution checks the company override, then its selected theme, then `themes/default`. The theme resolver only accepts known page keys and template paths.

## Catalogue, patient files, and history

Keep a shared, versioned product master for supplier/manufacturer reference data (external identifiers, pack size, descriptions, product classification, and source/import version). Do not copy the entire master catalog once per company. Each company has a sparse `company_products`/offer table keyed by `(company_id, product_id)` for whether it carries the item, local sales status, price, stock, supplier mapping, and company-specific settings. Orders store an immutable price and product-description snapshot so later price or catalog imports cannot rewrite historical receipts.

Keep patient files separate from public webshop accounts. A patient may be known to more than one pharmacy, but their profile, medication/dispensing history, and staff access remain pharmacy-scoped. Do not create a shared cross-pharmacy clinical profile by default. Dispensing history is an append-only, pharmacy-scoped event history with strict staff permissions and access auditing; operational corrections should add a traceable correction event rather than silently rewriting the original event. The online shop's customer identity should not automatically reveal or link to a pharmacy's patient record.

At the scale described (tens of thousands of patient profiles per company and a shared catalog above one hundred thousand products), a pooled PostgreSQL database is still a reasonable starting architecture, subject to representative load tests and operational sizing. The total dispensing/order-event history and concurrency will likely dominate the catalog row count. Add tenant-leading indexes for common lookups and composite foreign keys so a row cannot reference another company's patient, offer, order, or membership. Consider time-based partitioning only for large history tables where measured query patterns benefit from partition pruning; partitioning is a physical optimization, not a security boundary. PostgreSQL supports declarative partitioning and partition pruning, but the benefit depends on queries matching the partition key. [PostgreSQL table partitioning](https://www.postgresql.org/docs/current/ddl-partitioning.html)

Design the tenant directory so a company can later move to a different database shard without changing its logical tenant identity. Start sharding only when measured capacity, maintenance, residency, or isolation requirements call for it; a shared product master can be replicated/cached separately from tenant-specific offers and histories.

## IP restrictions and audit

The allowlist belongs to a company and a scope (`admin` or `staff`); entries are PostgreSQL `inet` networks, editable only with a dedicated permission. Changes record actor, old/new network, timestamp, and reason. Validate the observed peer address, and trust proxy headers only from explicitly configured reverse proxies. A missing list denies internal access once enforcement is enabled. New networks should be verified before activation, and an authorized recovery path must exist so a mistaken change cannot permanently lock out a company.

## Commerce and medicines

Keep product type, prescription status, sale eligibility, fulfilment rules, and pharmacy registration status as explicit data, not a display-only category. Reject an order when the tenant is not active/eligible or an item is not allowed for online sale. FAGG guidance says online sale of prescription medicines is prohibited in Belgium even where the buyer has a prescription; the prescription does not turn a prohibited online sale into a permitted one. A public site may instead let the patient reserve a prescription medicine for personal pickup at the pharmacy. The reservation must not take online payment or offer courier delivery; the pharmacist handles and validates the prescription at the pharmacy. The exact treatment of an electronic prescription reference should be confirmed with the pharmacist's official dispensing workflow.

For online sale of allowed non-prescription medicines, the pharmacy must be eligible/notified as required. FAGG requires its EU internet-pharmacy logo on every page of a pharmacy selling medicines online, linked to that pharmacy's entry in the official register. Confirm each tenant's status before enabling those product sales.

## Rollout order

1. Review the schema migration and RLS behavior on a backup of the current database; confirm the restricted application role is not the table owner.
2. Confirm the existing companies, staff memberships, and tenant roles; create and verify domain mappings before using production hostnames.
3. Add generic company storefront configuration and product catalogue, followed by tenant-aware stock and patient modules.
4. Add product eligibility and pharmacy registration controls before checkout, then implement cart, payment, delivery/pickup, and order administration.

Treat all future company-owned records as tenant-scoped from their first migration. Do not rely on a frontend selector as a security boundary; every route and query must derive and verify tenant context.

