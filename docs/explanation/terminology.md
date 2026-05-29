# Terminology Registry — Single Source of Truth

**Status: binding.** This file is the **single source of truth** for the
canonical spelling and form of every NeNe Clear term and identifier:
entities, status values, JSON/DB field names, enums, Problem Details slugs, and
`operationId` stems.

If an identifier appears anywhere in the codebase, OpenAPI, database, tests, or
docs, its spelling **MUST** match this registry **exactly** — same characters,
same case, same separators. There is no "close enough."

## Authority and absolute rules

1. **Exact match is mandatory.** Any spelling variant or typo of a registered
   term is a defect and **blocks merge** — there are no acceptable synonyms or
   abbreviations outside this registry.
2. **Register before you use.** Introducing a new term/identifier, or renaming
   an existing one, **MUST** update this registry in the **same PR**. Code that
   uses an unregistered term does not merge.
3. **No renaming after release.** Shipped `operationId` values and public JSON
   field names are stable; deprecate, do not rename (see `naming-conventions.md`).
4. **Roles of the three docs — do not duplicate, cross-reference:**
   - **`terminology.md`** (this file) — the authoritative **spelling/form** of every identifier.
   - **`glossary.md`** — the **meaning** of product concepts. Its spellings MUST conform to this registry.
   - **`naming-conventions.md`** — the **patterns/rules** that generate names. This registry is the concrete instantiation of those rules.

See: [`glossary.md`](./glossary.md), [`../development/naming-conventions.md`](../development/naming-conventions.md).

---

## 0. Product identity (display names)

| Concept | Canonical | Notes |
| --- | --- | --- |
| Public product name | **NeNe Clear** | ADR 0007 |
| Repository slug | `nene-clear` | Private until public launch |
| PHP namespace | `NeneClear\` | ADR 0007 |
| Problem Details base | `https://nene-clear.dev/problems/` | Until branding domain Issue |

---

## 1. Domain entities

| Concept | PHP class / domain folder | Table | Primary FK in JSON/DB |
| --- | --- | --- | --- |
| Tenant | `Organization` | `organizations` | `organization_id` |
| Operator account | `User` | `users` | `user_id` |
| Issuer profile | `Company` (folder); `CompanySettings` (entity) | `company_settings` | — (one per `organization_id`) |
| Buyer | `Client` | `clients` | `client_id` |
| Estimate | `Quote` | `quotes` | `quote_id` |
| Bill | `Invoice` | `invoices` | `invoice_id` |
| Document row | `LineItem` | `line_items` | `line_item_id` |
| Receipt | `Payment` | `payments` | `payment_id` |
| Number generator | `DocumentSequence` | `document_sequences` | — |

Domain folders are **PascalCase singular**; tables are **snake_case plural**.

---

## 2. Status values (exact strings)

Stored and transmitted **exactly** as written (lowercase snake_case).

| Owner | Allowed values |
| --- | --- |
| `quote.status` | `draft`, `sent`, `accepted`, `rejected`, `expired` |
| `invoice.status` | `draft`, `issued`, `partially_paid`, `paid` |
| invoice computed | `overdue` (computed flag, not a stored status in Phase 1) |
| `payment.method` | `bank_transfer`, `cash`, `other` |
| `line_item.parent_type` | `quote`, `invoice` |
| `user.role` | `superadmin`, `admin`, `member`, `viewer` (ADR 0006) |
| `user.status` | `active`, `invited` |
| Capability (enum) | `manage_organizations`, `manage_users`, `manage_company_settings`, `manage_billing`, `view_billing` |
| Org resolution mode | `single` (default), `path`, `subdomain`, `custom_domain` |

Do not invent `cancelled`, `void`, `unpaid`, `pending`, etc. without registering them here first.

---

## 3. Canonical field / column names (snake_case)

| Term | Canonical | Never |
| --- | --- | --- |
| Tenant foreign key | `organization_id` | `org_id`, `tenant_id`, `organizationId` |
| Organization slug | `slug` | `org_slug`, `code` |
| User role | `role` (values in §2) | `user_role`, `permission` |
| User credential | `password_hash` | `password`, `pass_hash` |
| Invoice registration number | `registration_number` | `tax_registration_number`, `invoice_registration_number`, `t_number` |
| Qualified-invoice flag | `is_qualified_invoice` | `qualified`, `is_qualified` |
| Soft-delete flag / time | `is_deleted`, `deleted_at` | `deleted`, `is_del` |
| Subtotal (pre-tax) | `subtotal_cents` | `sub_total_cents`, `subtotal` |
| Tax amount | `tax_cents` | `tax`, `tax_amount` |
| Total | `total_cents` | `amount_cents` (on documents), `grand_total` |
| Unit price | `unit_price_cents` | `price_cents`, `unitprice_cents` |
| Payment amount | `amount_cents` | `paid_cents`, `payment_cents` |
| Tax rate | `tax_rate_bps` | `tax_rate`, `rate`, `tax_rate_percent` |
| Foreign keys | `client_id`, `quote_id`, `invoice_id` | `clientId`, `client` |
| Polymorphic parent | `parent_type`, `parent_id` | `parentType`, `owner_id` |
| Numbers | `quote_number`, `invoice_number` | `number`, `quote_no` |
| Timestamps | `issued_at`, `due_at`, `paid_at`, `valid_until`, `deleted_at` | `issue_date`, `due_date`, `paidAt` |
| Issuer fields | `legal_name`, `bank_name`, `bank_branch`, `account_type`, `account_number`, `logo_url` | `company_name`, `branch`, `acct_no` |
| Client fields | `contact_name`, `billing_address` | `contact`, `address` |
| List envelope | `items`, `limit`, `offset` | `data`, `results`, `count` |

Rules: money columns end in `_cents` (integer); timestamps end in `_at` (except
the documented `valid_until`); booleans use `is_` / `has_`; foreign keys are
`{singular_entity}_id`. See `naming-conventions.md` for the full pattern set.

---

## 4. Problem Details type slugs (kebab-case)

Base URL: `https://nene-clear.dev/problems/`. Slug is **kebab-case**.

| Slug | Use |
| --- | --- |
| `validation-failed` | Request body/field validation error |
| `invoice-not-found` | Invoice id/token not found |
| `quote-not-found` | Quote id not found |
| `client-not-found` | Client id not found |
| `organization-not-found` | Organization id/slug not found |
| `user-not-found` | User id not found |
| `invalid-credentials` | Login failed — wrong email or password |
| `unauthorized` | Missing or invalid bearer token (framework `BearerTokenMiddleware`) |
| `insufficient-capability` | Authenticated but lacks required capability |
| `organization-not-resolved` | Tenant could not be resolved for the request |
| `invalid-state-transition` | Disallowed status change |
| `qualified-invoice-incomplete` | Missing required qualified-invoice field |

Add new slugs here before using them. Validation `errors[].field` uses
snake_case paths (e.g. `body.registration_number`); `errors[].code` is
snake_case (e.g. `required`, `invalid_invoice_number`).

---

## 5. operationId stems (camelCase)

Shape `{verb}{Resource}` / `{verb}{Resource}ById`. Stable after release. Must
match between OpenAPI, route registration, and `docs/mcp/tools.json`.

| operationId | Resource |
| --- | --- |
| `getHealth` | System |
| `login`, `getCurrentUser` | Auth |
| `listOrganizations`, `getOrganizationById`, `createOrganization`, `deleteOrganization` | Organization (superadmin) |
| `listUsers`, `getUserById`, `createUser`, `updateUser`, `deleteUser` | User (admin) |
| `listClients`, `getClientById`, `createClient`, `updateClient`, `deleteClient` | Client |
| `listQuotes`, `getQuoteById`, `createQuote`, `convertQuoteToInvoice` | Quote |
| `listInvoices`, `getInvoiceById`, `createInvoice`, `issueInvoice` | Invoice |
| `listPayments`, `createPayment` | Payment |

Extend this list (do not improvise) when adding operations.

---

## How to add or change a term

1. Add/rename the entry **here** in the same PR as the code.
2. Update `glossary.md` if it is a product concept; update `naming-conventions.md`
   if it introduces a new pattern.
3. Run the docs-policy and backend-api self-review checklists.
4. Confirm no spelling variant of the term remains anywhere (grep before commit).
