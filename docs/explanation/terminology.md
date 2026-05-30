# Terminology Registry — Single Source of Truth

**Status: binding.** This file is the **single source of truth** for the
canonical spelling and form of every NeNe Clear term and identifier:
entities, status values, JSON/DB field names, enums, Problem Details slugs, and
`operationId` stems.

> **Domain (ADR 0009):** NeNe Clear owns **payment reconciliation and dunning**
> (入金消込・督促管理) only. Quote, invoice, line item, and qualified-invoice
> identifiers belong to [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice).
> Invoice and client figures are **read from the Invoice upstream API** — Clear
> references them by id and may cache them read-only; it does **not** own their
> spelling here beyond the upstream read models in §6.

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

## 1. Domain entities (owned by Clear)

Tenant-scoped tables carry `organization_id`. Tables are **snake_case plural**;
domain folders are **PascalCase singular**.

| Concept | PHP class / domain folder | Table | Primary FK in JSON/DB |
| --- | --- | --- | --- |
| Tenant | `Organization` | `organizations` | `organization_id` |
| Operator account | `User` | `users` | `user_id` |
| Clear settings (singleton per org) | `ClearSettings` (entity); `Settings` (folder) | `clear_settings` | — (one per `organization_id`) |
| Registered company bank account + CSV profile | `BankAccount` | `bank_accounts` | `bank_account_id` |
| Bank CSV import batch | `BankImportBatch` | `bank_import_batches` | `bank_import_batch_id` |
| Imported bank deposit line | `BankTransaction` | `bank_transactions` | `bank_transaction_id` |
| Confirmed match link | `PaymentReconciliation` | `payment_reconciliations` | `payment_reconciliation_id` |
| Allocation row (one match → one invoice) | `ReconciliationAllocation` | `reconciliation_allocations` | `reconciliation_allocation_id` |
| Overpayment credit balance | `ClientCredit` | `client_credits` | `client_credit_id` |
| Dunning send record | `DunningNotice` | `dunning_notices` | `dunning_notice_id` |
| Audit event | `AuditEvent` | `audit_events` | `audit_event_id` |

**Not owned by Clear** (read from Invoice upstream, see §6): `invoice`, `client`,
`payment`. Clear stores only the upstream id plus reconciliation links; it never
owns quote, line item, tax, or qualified-invoice fields.

---

## 2. Status values (exact strings)

Stored and transmitted **exactly** as written (lowercase snake_case).

| Owner | Allowed values |
| --- | --- |
| `bank_transaction.status` | `unmatched`, `partially_matched`, `matched`, `voided` |
| `bank_import_batch.status` | `imported`, `reversed` |
| `payment_reconciliation.status` | `confirmed`, `reversed` |
| `client_credit.status` | `open`, `partially_applied`, `applied` |
| `dunning_notice.status` | `sent`, `failed` |
| `user.role` | `superadmin`, `admin`, `member`, `viewer` (ADR 0006) |
| `user.status` | `active`, `invited` |
| Capability (enum) | `manage_organizations`, `manage_users`, `manage_clear_settings`, `manage_reconciliation`, `view_reconciliation`, `send_dunning` (ADR 0006) |
| Org resolution mode | `single` (default), `path`, `subdomain`, `custom_domain` |
| **Upstream** `invoice.status` (read-only) | `issued`, `partially_paid`, `paid`; `overdue` is a computed flag — **owned by Invoice**, mirrored read-only for dunning eligibility |

Do not invent `cancelled`, `void`, `pending`, `unpaid`, etc. without registering
them here first.

---

## 3. Canonical field / column names (snake_case)

| Term | Canonical | Never |
| --- | --- | --- |
| Tenant foreign key | `organization_id` | `org_id`, `tenant_id`, `organizationId` |
| Organization slug | `slug` | `org_slug`, `code` |
| User role | `role` (values in §2) | `user_role`, `permission` |
| User credential | `password_hash` | `password`, `pass_hash` |
| Soft-delete flag / time | `is_deleted`, `deleted_at` | `deleted`, `is_del` |
| Bank transaction amount | `amount_cents` | `deposit_cents`, `value_cents` |
| Bank value date | `value_date` (date type) | `transaction_date`, `valued_at`, `paid_at` (on the bank line) |
| Counterparty text (remitter) | `counterparty_text` | `payer`, `remitter_name` |
| Import file hash | `file_hash` (SHA-256 hex) | `hash`, `checksum`, `sha256` |
| Import source filename | `source_filename` | `filename`, `file_name` |
| Import row count | `row_count` | `lines`, `count` |
| Per-line duplicate key | `line_key` | `row_key`, `dedupe_key` |
| Upstream token env-var name (not the secret) | `upstream_token_ref` | `upstream_token`, `bearer_token` |
| Bank account fields | `bank_name`, `bank_branch`, `account_type`, `account_number` | `branch`, `acct_no` |
| Allocation amount | `amount_cents` | `allocated_cents`, `alloc_cents` |
| Payment date written upstream | `paid_at` | `payment_date`, `paidAt` |
| Reversal reason | `reversal_reason` | `reason`, `reverse_note` |
| Reason code (fee absorption etc.) | `reason_code` | `reason`, `code` |
| Outstanding at send (dunning) | `outstanding_at_send_cents` | `outstanding_cents`, `balance_cents` |
| Dunning recipient | `recipient_email` | `email`, `to` |
| Dunning template version | `template_version` | `template`, `version` |
| Credit remaining balance | `remaining_cents` | `balance_cents`, `left_cents` |
| Upstream invoice id | `invoice_id` | `upstream_invoice_id`, `invoiceId` |
| Upstream client id | `client_id` | `upstream_client_id`, `clientId` |
| Upstream payment id | `payment_id` | `upstream_payment_id`, `paymentId` |
| Foreign keys (Clear-owned) | `bank_transaction_id`, `bank_import_batch_id`, `bank_account_id`, `payment_reconciliation_id`, `client_credit_id` | camelCase, abbreviations |
| Actor / timestamps | `imported_by`, `imported_at`, `confirmed_by`, `confirmed_at`, `reversed_at`, `sent_by`, `sent_at`, `created_by`, `created_at` | `issue_date`, `paidAt`, `actor_id` (use role-specific names above) |
| Audit event type | `event_type` | `type`, `action` |
| List envelope | `items`, `limit`, `offset` | `data`, `results`, `count` |

Rules: money columns end in `_cents` (integer); event timestamps end in `_at`;
pure calendar dates use `_date` (documented exception: `value_date`); booleans
use `is_` / `has_`; foreign keys are `{singular_entity}_id`. See
`naming-conventions.md` for the full pattern set.

---

## 4. Problem Details type slugs (kebab-case)

Base URL: `https://nene-clear.dev/problems/`. Slug is **kebab-case**.

| Slug | Use |
| --- | --- |
| `validation-failed` | Request body/field validation error |
| `unauthorized` | Missing or invalid bearer token (framework `BearerTokenMiddleware`) |
| `invalid-credentials` | Login failed — wrong email or password |
| `insufficient-capability` | Authenticated but lacks required capability |
| `organization-not-resolved` | Tenant could not be resolved for the request |
| `organization-not-found` | Organization id/slug not found |
| `organization-already-exists` | Create rejected — slug already taken |
| `user-not-found` | User id not found |
| `user-already-exists` | Create rejected — email already taken |
| `bank-account-not-found` | Bank account id not found / not in caller's org |
| `bank-import-batch-not-found` | Import batch id not found |
| `bank-transaction-not-found` | Bank transaction id not found |
| `reconciliation-not-found` | Reconciliation id not found |
| `client-credit-not-found` | Client credit id not found |
| `dunning-notice-not-found` | Dunning notice id not found |
| `invalid-state-transition` | Disallowed status change (e.g. reverse an already-reversed match) |
| `duplicate-bank-import` | Re-import of same file hash / duplicate line key |
| `allocation-exceeds-outstanding` | Match allocation > invoice outstanding without overpayment handling |
| `upstream-invoice-unavailable` | Invoice API unreachable; degraded (import-only) mode |
| `upstream-invoice-not-found` | Referenced invoice not found in Invoice upstream |
| `upstream-client-not-found` | Referenced client does not exist (or has been soft-deleted) in Invoice upstream |
| `invoice-not-eligible-for-dunning` | Invoice is already paid, voided, or has no outstanding balance |
| `dunning-too-frequent` | Dunning interval not elapsed; includes next-allowed datetime in detail |
| `credit-exceeds-remaining` | Credit application amount exceeds the remaining credit balance |

Add new slugs here before using them. Validation `errors[].field` uses
snake_case paths (e.g. `body.value_date`); `errors[].code` is snake_case (e.g.
`required`, `invalid_amount`).

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
| `getClearSettings`, `updateClearSettings`, `testUpstreamConnection` | Clear settings (admin) |
| `importBankCsv`, `listBankImportBatches`, `getBankImportBatchById`, `reverseBankImportBatch` | Bank import |
| `listBankTransactions`, `listUnmatchedTransactions`, `getBankTransactionById` | Bank transaction |
| `listUpstreamInvoices`, `getUpstreamInvoiceById` | Invoice upstream (read-only) |
| `proposeMatch`, `confirmMatch`, `reverseReconciliation`, `listReconciliations`, `getReconciliationById` | Reconciliation |
| `listClientCredits`, `applyClientCredit` | Client credit |
| `listDunningNotices`, `getDunningNoticeById`, `sendDunningNotice` | Dunning |

Extend this list (do not improvise) when adding operations. MCP tool names
mirror these `operationId` values exactly (`listUnmatchedTransactions`,
`proposeMatch`, `sendDunningNotice` are the Phase 4 MCP set — roadmap §Phase 4).

---

## 6. Upstream read models (Invoice API — not Clear SSOT)

Clear reads these from the Invoice upstream and may cache them read-only with a
TTL. Field spellings below are the **shape Clear consumes**; the Invoice product
owns their authoritative definition. Clear MUST NOT persist them as a source of
truth or expose write operations for them.

| Read model | Fields Clear consumes (read-only) |
| --- | --- |
| `invoice` (upstream) | `invoice_id`, `invoice_number`, `issued_at`, `due_at`, `total_cents`, `outstanding_cents`, `status`, `tax_breakdown` (opaque), `client_id` |
| `client` (upstream) | `client_id`, `contact_name`, `recipient_email` (match hints, dunning recipient) |
| `payment` (upstream) | `payment_id`, `invoice_id`, `amount_cents`, `paid_at` (created/updated by Clear via Invoice API after a confirmed match) |

`outstanding_cents` = `invoice.total_cents − sum(allocated payment amounts)`, as
reported by the Invoice upstream. Clear never recomputes invoice tax or totals.

---

## How to add or change a term

1. Add/rename the entry **here** in the same PR as the code.
2. Update `glossary.md` if it is a product concept; update `naming-conventions.md`
   if it introduces a new pattern.
3. Run the docs-policy and backend-api self-review checklists.
4. Confirm no spelling variant of the term remains anywhere (grep before commit).
