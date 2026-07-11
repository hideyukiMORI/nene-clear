# Phase 1 — Reconciliation API Design

**Status: design (pre-implementation).** This is the engineering design engineers
build against for Phase 1. It instantiates the binding rules — it does not
override them. On any conflict, the rules win:
[`scope-contract.md`](../explanation/scope-contract.md),
[`payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md),
[`invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md),
[`terminology.md`](../explanation/terminology.md) (canonical identifiers).

See also: [`domain-model.md`](../explanation/domain-model.md) (entities, state
machines), [`backend-standards.md`](./backend-standards.md),
[`naming-conventions.md`](./naming-conventions.md).

---

## 1. Phase 1 scope

**In:** multi-tenant foundation + JWT + RBAC (ADR 0006); `clear_settings` and
registered bank accounts; bank CSV import; read-only Invoice upstream proxy;
reconciliation (propose → confirm → reverse) with allocation, overpayment →
client credit, transfer-fee handling; audit trail; CSV export.

**Out (later phases):** dunning send/templates/history (Phase 2); admin UI
(Phase 2); Tier A installer (Phase 3); MCP tools (Phase 4); non-CSV bank
ingestion (future ADR).

Money is **integer cents**, JPY only. Every tenant-scoped query carries
`organization_id`. No quote/invoice/tax/PDF logic (ADR 0009).

---

## 2. Data model

DDL-level intent (MySQL for Tier A; SQLite for tests). All money columns are
`BIGINT` cents; all tenant-scoped tables carry `organization_id BIGINT NOT NULL`
with an index. Soft-delete via `is_deleted` / `deleted_at` where applicable. No
hard delete of bank/match/credit history (compliance §2.7, §3).

### 2.1 `organizations`, `users`

Per [ADR 0006](../adr/0006-multi-tenancy-and-roles.md) / NeNe Records pattern.
`users.role ∈ {superadmin, admin, member, viewer}`; `users.status ∈ {active,
invited}`; `password_hash`. Org resolution mode `single` (default) / `path` /
`subdomain` / `custom_domain`.

### 2.2 `clear_settings` (one row per organization)

| Column | Type | Notes |
| --- | --- | --- |
| `organization_id` | BIGINT | PK/unique — one row per org |
| `upstream_base_url` | VARCHAR | Invoice API base URL |
| `upstream_token_ref` | VARCHAR | **name of the env var** holding the bearer token — the secret itself lives only in `.env` (never stored in DB) |
| `dunning_min_interval_days` | INT | default 7 (used in Phase 2) |
| `created_at` / `updated_at` | DATETIME | |

### 2.3 `bank_accounts` (registered company accounts + CSV profile)

One or more per organization. Drives import scope and the CSV parse profile.
Also the source of payment instructions for dunning (Phase 2).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | tenant |
| `bank_name`, `bank_branch` | VARCHAR | |
| `account_type` | VARCHAR | `ordinary` / `current` (普通 / 当座) |
| `account_number` | VARCHAR | |
| `csv_encoding` | VARCHAR | e.g. `shift_jis`, `utf8` (Japanese bank CSVs are often Shift_JIS) |
| `csv_date_format` | VARCHAR | e.g. `Y/m/d`, `Ymd` — maps to `value_date` |
| `csv_date_column` | VARCHAR/INT | column holding 取引日/入金日 |
| `csv_amount_column` | VARCHAR/INT | deposit (入金) amount column |
| `csv_counterparty_column` | VARCHAR/INT | 摘要/振込依頼人 → `counterparty_text` |
| `csv_header_rows` | INT | rows to skip |
| `csv_deposit_sign` | VARCHAR | how a credit is distinguished (sign, or separate 入金/出金 columns) |
| `is_deleted`, `deleted_at` | | soft delete |

> The CSV profile is intentionally **per bank account**, because a per-bank
> format exception to `value_date` sourcing must be documentable (compliance
> §2.2). One format at a time (Phase 1 / vision North Star).

### 2.4 `bank_import_batches`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | |
| `bank_account_id` | BIGINT | FK |
| `file_hash` | CHAR(64) | SHA-256; unique per org → duplicate detection |
| `source_filename` | VARCHAR | |
| `row_count` | INT | |
| `status` | VARCHAR | `imported` / `reversed` |
| `imported_by` | BIGINT | user id (actor) |
| `imported_at` | DATETIME | system clock, documented in operator guide |
| `reversed_at`, `reversal_reason` | | when status `reversed` |

Unique: `uniq_bank_import_batches_org_file_hash (organization_id, file_hash)`.

### 2.5 `bank_transactions`

Imported deposit line. **Immutable** after import (compliance §3.1; ADR 0012).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | |
| `bank_import_batch_id` | BIGINT | FK |
| `bank_account_id` | BIGINT | FK |
| `value_date` | DATE | bank value date (入金日/取引日) — **date type**, not `_at` |
| `amount_cents` | BIGINT | credit amount |
| `counterparty_text` | VARCHAR | remitter / 摘要 |
| `line_key` | VARCHAR | stable per-line key for duplicate detection within a file |
| `status` | VARCHAR | `unmatched` / `partially_matched` / `matched` / `voided` |
| `created_at` | DATETIME | |

Index: `idx_bank_transactions_status (organization_id, status)`,
`idx_bank_transactions_value_date`, `idx_bank_transactions_counterparty`
(search requirement, compliance §3.3). No update of `value_date`/`amount_cents`/
`counterparty_text` after insert.

### 2.6 `payment_reconciliations` + `reconciliation_allocations`

A confirmed match = one `payment_reconciliation` with ≥1 allocation rows.

`payment_reconciliations`:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | |
| `bank_transaction_id` | BIGINT | FK |
| `status` | VARCHAR | `confirmed` / `reversed` |
| `confirmed_by` | BIGINT | actor |
| `confirmed_at` | DATETIME | |
| `reversed_at`, `reversal_reason`, `reason_code` | | on reversal / fee absorption |

`reconciliation_allocations`:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | |
| `payment_reconciliation_id` | BIGINT | FK |
| `invoice_id` | BIGINT | **upstream** invoice id (not a local FK) |
| `amount_cents` | BIGINT | allocated to this invoice |
| `payment_id` | BIGINT | **upstream** payment id returned by Invoice |
| `external_reference` | VARCHAR | the value sent to Invoice (`clear:recon:{id}`) |

Σ(allocation `amount_cents`) for a confirmed reconciliation = the bank
transaction `amount_cents` (or a documented remainder per overpayment/fee rules).

### 2.7 `client_credits`

Overpayment balance (前受金/預り金 相当) — never discarded (compliance §2.5).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | |
| `client_id` | BIGINT | upstream client id |
| `amount_cents` | BIGINT | original excess |
| `remaining_cents` | BIGINT | unused balance |
| `status` | VARCHAR | `open` / `partially_applied` / `applied` |
| `source_bank_transaction_id` | BIGINT | provenance |
| `created_by`, `created_at` | | |

### 2.8 `audit_events`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT | |
| `event_type` | VARCHAR | `bank_import` / `bank_import_reversed` / `match_confirmed` / `match_reversed` / `client_credit_created` / `client_credit_applied` |
| `actor_user_id` | BIGINT | |
| `occurred_at` | DATETIME | |
| `payload_json` | JSON/TEXT | minimal fields per compliance §6 |

Append-only; never updated or deleted.

---

## 3. State transitions (enforcement)

Per [`domain-model.md`](../explanation/domain-model.md). Enforced in UseCases:

- `bank_transaction`: `unmatched → partially_matched → matched`; any → back on
  reversal; `voided` only via reversal import batch. Disallowed transitions
  return `invalid-state-transition`.
- `payment_reconciliation`: `confirmed → reversed` (reversing an already-reversed
  one is `invalid-state-transition`).
- `client_credit`: `open → voided`. Application progress is tracked by
  `remaining_cents`, not by a status value (#264).

---

## 4. API surface (Phase 1)

The concrete contract is [`../openapi/openapi.yaml`](../openapi/openapi.yaml)
(OpenAPI 3.1) — the table below is the index; the YAML is the source of truth and
what runtime contract tests read.

JSON, OpenAPI 3.1, RFC 9457 Problem Details (base `https://nene-clear.dev/problems/`),
snake_case bodies, `items`/`limit`/`offset` list envelope. Admin mutating routes
under `/admin/…`; `GET /health` unauthenticated. Auth: JWT bearer; capability
enforced by `CapabilityMiddleware`.

| operationId | Method · Path | Capability | Notes |
| --- | --- | --- | --- |
| `getHealth` | GET `/health` | — | unauthenticated |
| `login` | POST `/admin/auth/login` | — | → JWT |
| `getCurrentUser` | GET `/admin/auth/me` | (auth) | |
| `listOrganizations` … `deleteOrganization` | `/admin/organizations` | `manage_organizations` | superadmin |
| `listUsers` … `deleteUser` | `/admin/users` | `manage_users` | admin |
| `getClearSettings` | GET `/admin/clear-settings` | `manage_clear_settings` | |
| `updateClearSettings` | PUT `/admin/clear-settings` | `manage_clear_settings` | |
| `testUpstreamConnection` | POST `/admin/clear-settings/test-upstream` | `manage_clear_settings` | pings Invoice API |
| `importBankCsv` | POST `/admin/bank-import-batches` | `manage_reconciliation` | multipart CSV + `bank_account_id` |
| `listBankImportBatches` | GET `/admin/bank-import-batches` | `view_reconciliation` | |
| ~~`getBankImportBatchById`~~ | ~~GET `/admin/bank-import-batches/{id}`~~ | — | dropped (YAGNI; never implemented, removed from registry/OpenAPI) |
| `reverseBankImportBatch` | POST `/admin/bank-import-batches/{id}/reverse` | `manage_reconciliation` | voids lines, audited |
| `listBankTransactions` | GET `/admin/bank-transactions` | `view_reconciliation` | filter by status/date/amount/counterparty (search req.) |
| `listUnmatchedTransactions` | GET `/admin/bank-transactions/unmatched` | `view_reconciliation` | |
| `getBankTransactionById` | GET `/admin/bank-transactions/{id}` | `view_reconciliation` | |
| `listUpstreamInvoices` | GET `/admin/upstream/invoices` | `view_reconciliation` | read proxy to Invoice |
| ~~`getUpstreamInvoiceById`~~ | ~~GET `/admin/upstream/invoices/{id}`~~ | — | dropped (YAGNI; never implemented, removed from registry/OpenAPI) |
| `proposeMatch` | POST `/admin/reconciliations/propose` | `view_reconciliation` | returns suggestions; **no write** |
| `confirmMatch` | POST `/admin/reconciliations` | `manage_reconciliation` | human confirm → upstream write |
| `reverseReconciliation` | POST `/admin/reconciliations/{id}/reverse` | `manage_reconciliation` | |
| `listReconciliations` / `getReconciliationById` | `/admin/reconciliations` | `view_reconciliation` | |
| `listClientCredits` | GET `/admin/client-credits` | `view_reconciliation` | |
| `applyClientCredit` | POST `/admin/client-credits/{id}/apply` | `manage_reconciliation` | apply to an invoice (upstream write) |

Problem Details types used: `validation-failed`, `unauthorized`,
`invalid-credentials`, `insufficient-capability`, `organization-not-resolved`,
`*-not-found`, `invalid-state-transition`, `duplicate-bank-import`,
`allocation-exceeds-outstanding`, `upstream-invoice-unavailable`,
`upstream-invoice-not-found` (all registered in `terminology.md` §4).

---

## 5. UseCase specs (core reconciliation flows)

### 5.1 `importBankCsv`

1. Validate `bank_account_id` belongs to org; load its CSV profile.
2. Read file with `csv_encoding`; skip `csv_header_rows`; parse `value_date`
   (via `csv_date_format`), `amount_cents`, `counterparty_text`; keep credits only.
3. Compute `file_hash` (SHA-256). If `(organization_id, file_hash)` exists →
   `duplicate-bank-import` (warn/block). Detect duplicate `line_key` within file.
4. Insert `bank_import_batch` + immutable `bank_transactions` (status
   `unmatched`). Write `bank_import` audit event.
- **Errors:** `validation-failed` (bad profile/parse), `duplicate-bank-import`.

### 5.2 `proposeMatch` (read-only)

- Given a `bank_transaction_id` (or batch), fetch open/overdue invoices from
  upstream and rank candidates (exact amount, invoice number in
  `counterparty_text`, client name). Returns suggestions only. **No write, no
  state change** (compliance §2.8). AI may power ranking; output is advisory.

### 5.3 `confirmMatch` (human-confirmed → upstream write)

Input: `bank_transaction_id`, `allocations: [{invoice_id, amount_cents}]`,
optional `reason_code` (fee absorption).

1. Authorize `manage_reconciliation`; load bank transaction (must be
   `unmatched`/`partially_matched`).
2. Validate Σ(allocations) ≤ bank `amount_cents`; operator-directed appropriation
   (compliance §2.9) — no silent auto-split.
3. For each allocation, fetch upstream `outstanding_cents`:
   - if `amount_cents ≤ outstanding`: `createPayment` upstream (idempotent,
     `paid_at = value_date`, `external_reference`); store allocation with returned
     `payment_id`.
   - if `amount_cents > outstanding`: post outstanding as payment; record the
     remainder as `client_credit` (compliance §2.5). (Invoice also rejects
     over-allocation defensively → `allocation-exceeds-outstanding`.)
4. **Transfer-fee mismatch** (bank credit < invoice total): operator picks
   partial / fee absorption (`reason_code`, admin) / separate expense — never a
   silent write-off (compliance §2.4).
5. Recompute `bank_transaction.status` (`partially_matched`/`matched`). Write
   `payment_reconciliation` (`confirmed`) + allocations + `match_confirmed` audit.
- **Errors:** `invalid-state-transition`, `allocation-exceeds-outstanding`,
  `upstream-invoice-unavailable` (→ degraded mode, no finalize),
  `upstream-invoice-not-found`, `insufficient-capability`.

### 5.4 `reverseReconciliation`

1. Load `confirmed` reconciliation; require `reversal_reason`.
2. For each allocation: `voidPayment` upstream (idempotent). Invoice restores
   outstanding/status.
3. Set reconciliation `reversed`; recompute bank transaction status from
   remaining valid allocations; reverse any `client_credit` created by this match;
   write `match_reversed` audit. **No hard delete.**

### 5.5 `applyClientCredit`

- Apply `remaining_cents` (or a portion) of an `open`/`partially_applied` credit
  to a chosen invoice via upstream `createPayment` (idempotent,
  `external_reference = clear:credit:{id}`); decrement `remaining_cents`; set
  status; audit. **Explicit operator action only** — never automatic (compliance §2.5).

---

## 6. Upstream interaction & degraded mode

- All upstream calls go through `Upstream/Invoice/InvoiceUpstreamClientInterface`
  per the [contract](../integrations/invoice-upstream-contract.md). Writes carry
  an idempotency key + `external_reference`.
- If the upstream is unreachable: import and read-of-local still work; any flow
  that must write upstream (`confirmMatch`, `reverseReconciliation`,
  `applyClientCredit`) is **blocked** with `upstream-invoice-unavailable`. No
  reconciliation is finalized locally without the upstream write succeeding, so
  the two systems never diverge (ADR 0010).
- Until Invoice ships the API, build against a **fake client** + contract tests.

---

## 7. Deferred decisions / open questions

- Exact upstream OpenAPI field names: pin when Invoice publishes its spec; adjust
  the read models in `terminology.md` §6 if they differ.
- `proposeMatch` ranking algorithm (rules vs. AI weighting) — Phase 1 ships
  deterministic rules; AI ranking can follow without contract change.
- Whether `bank_account` CSV profiles need a separate `bank_csv_profiles` table
  if one account ever needs multiple formats — start folded into `bank_accounts`.
- CSV export endpoint shape for accounting handoff (ADR 0013) — design with the
  税理士 during the §9 review gate.

---

## Related

- Roadmap Phase 1: [`../roadmap.md`](../roadmap.md)
- Requirements: [`../explanation/requirements.md`](../explanation/requirements.md)
- Domain model: [`../explanation/domain-model.md`](../explanation/domain-model.md)
- Terminology (identifiers): [`../explanation/terminology.md`](../explanation/terminology.md)
- Invoice contract: [`../integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
