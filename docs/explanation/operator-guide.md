# NeNe Clear — Operator Guide / System Overview

**Purpose:** This document describes how NeNe Clear stores, processes, and
retains electronic transaction data. It fulfills the documentation requirement
of the Act on Electronic Books and Records Preservation (電子帳簿保存法 §3.4)
for systems that capture and retain electronic transaction data (電子取引データ).

> This document is for **operators** (admin users running the system on behalf
> of their organization). For developer documentation see `docs/development/`.
> For compliance analysis see `docs/explanation/payment-reconciliation-dunning-compliance.md`.

---

## 1. What NeNe Clear does

NeNe Clear performs two functions:

1. **Payment reconciliation (入金消込):** Operators import bank deposit statements
   (CSV), match deposits to outstanding invoices, and confirm matches. Confirmed
   matches are posted to NeNe Invoice (the billing system) as payment records.
2. **Dunning (督促管理):** Operators send professional payment reminders to clients
   for overdue invoices. Every send is logged with the recipient, outstanding
   balance at send time, and actor.

NeNe Clear does **not** issue invoices, compute tax, or store invoice figures.
Those belong to NeNe Invoice (see `docs/integrations/sibling-products.md`).

---

## 2. Data stored in NeNe Clear

### 2.1 Bank import data (電子取引データ)

When a bank CSV is imported, NeNe Clear creates:

| Record | Table | Contents |
| --- | --- | --- |
| Import batch | `bank_import_batches` | File hash (SHA-256), source filename, row count, import timestamp, actor |
| Bank transaction | `bank_transactions` | Value date (入金日), amount (円), counterparty text, status, batch reference |

**Retention:** These records are **never deleted**. Reversal marks the batch as
`reversed` and voids transaction lines (status = `voided`) — no rows are removed.
The SHA-256 hash prevents re-importing the same file.

### 2.2 Reconciliation data (消込記録)

| Record | Table | Contents |
| --- | --- | --- |
| Reconciliation | `payment_reconciliations` | Bank transaction ID, status, confirmed/reversed timestamps, actor, reason code |
| Allocation | `reconciliation_allocations` | Invoice ID, amount allocated, upstream payment ID, external reference |

Reconciliations are **append-only**. Reversal creates a reversal record
(`status = reversed`) rather than deleting the original.

### 2.3 Client credit (前受金)

When a bank deposit exceeds the invoice outstanding, the excess is stored as a
`client_credit` record (remaining balance, status). Applying the credit to
a future invoice creates a new upstream payment.

### 2.4 Dunning history (督促履歴)

| Record | Table | Contents |
| --- | --- | --- |
| Dunning notice | `dunning_notices` | Invoice number, recipient email, outstanding at send (円), channel, sent timestamp, actor |

Dunning notices are **immutable** once created. No deletion.

> **A recorded "sent" is not "delivered."** Set up the sending domain (SPF / DKIM
> / DMARC) and a real SMTP relay so reminders reach the inbox — see
> [`dunning-email-deliverability.md`](./dunning-email-deliverability.md).

### 2.5 Audit trail (監査証跡)

Every mutating operation is recorded in `audit_events`:

| Event type | Trigger |
| --- | --- |
| `bank_import` | CSV imported |
| `bank_import_batch_reversed` | Import batch reversed |
| `reconciliation_confirmed` | Match confirmed |
| `reconciliation_reversed` | Match reversed |
| `client_credit_applied` | Credit applied to invoice |
| `dunning_sent` | Dunning notice sent |

Each record contains: organization, event type, actor user ID, timestamp, and a
JSON payload with before/after snapshots of the relevant data.

---

## 3. How to search records (電子取引データの検索方法)

The following search and filter operations are available via the API and (in Phase
3) the Admin UI:

### Bank transactions

`GET /admin/bank-transactions` supports:
- `status` — filter by `unmatched`, `matched`, `partially_matched`, `voided`
- `value_date_from` / `value_date_to` — date range filter on the bank value date
- `amount_min_cents` / `amount_max_cents` — amount range filter
- `counterparty` — substring match on remitter text
- `limit` / `offset` — pagination

### Reconciliations

`GET /admin/reconciliations` supports:
- `status` — `confirmed` or `reversed`
- `limit` / `offset`

### Dunning notices

`GET /admin/dunning-notices` — lists notices in reverse chronological order.
`GET /admin/dunning-notices/{id}` — detail by ID.

### Audit events

Audit events are stored in `audit_events` and are queryable by:
- `organization_id` (always scoped to caller's org)
- `event_type`
- `occurred_at` range (SQL-level; no REST endpoint in Phase 1/2)

---

## 4. Data immutability and retention

In compliance with 電子帳簿保存法 and financial record-keeping requirements:

- **No hard deletion.** All records (bank transactions, reconciliations, dunning
  notices, audit events) are retained indefinitely. Reversals and voids create
  new records, never overwrite.
- **File hash integrity.** Bank import CSV files are hashed (SHA-256) on import.
  Re-importing the same file is rejected with `duplicate-bank-import`.
- **Value dates are the bank value date**, not the NeNe Clear processing
  timestamp. This satisfies the requirement that the recorded date is the date
  funds were credited (入金日 / 取引日).
- **Retention period:** Operators must retain records for the legally required
  period (7 years for 法人税法 purposes; consult your 税理士 for your specific
  situation). NeNe Clear does not auto-purge records.
- **Backup:** Database backup is the operator's responsibility. NeNe Clear does
  not provide built-in backup; use database-level backup (e.g. `mysqldump` for
  MySQL, `pg_dump` for PostgreSQL).
- **Database adapter:** `DB_ADAPTER` selects the backend — `sqlite` (local/dev),
  `mysql`, or `pgsql`. All three share one Phinx migration set. The pgsql adapter
  needs the PHP `pdo_pgsql` extension and ignores `DB_CHARSET` (PostgreSQL derives
  the client encoding from `server_encoding`).

---

## 5. User roles and capabilities

| Role | Who | Capabilities |
| --- | --- | --- |
| `superadmin` | Platform administrator | Manage all organizations |
| `admin` | Organization administrator | Manage users, settings, all operations |
| `member` | Operator | Confirm/reverse matches, send dunning (if granted) |
| `viewer` | Read-only | View reconciliation lists and dunning history |

Specific capabilities:

| Capability | Operations |
| --- | --- |
| `view_reconciliation` | GET bank transactions, reconciliations, credits, dunning notices |
| `manage_reconciliation` | Import CSV, confirm/reverse matches, apply credits |
| `send_dunning` | Send dunning notices |
| `manage_users` | CRUD users within own organization |
| `manage_clear_settings` | Update Clear settings, bank accounts |
| `manage_organizations` | CRUD organizations (superadmin only) |

---

## 6. Integration with NeNe Invoice

NeNe Clear does not own invoice data. It reads invoices from NeNe Invoice via
HTTP API and posts payment records back on confirmed matches.

- Outstanding balances are always read from NeNe Invoice — NeNe Clear never
  recomputes them.
- If NeNe Invoice is unreachable, NeNe Clear enters **degraded mode**: bank CSV
  import continues, but match confirmation is blocked (`upstream-invoice-unavailable`).
- Invoice data is never stored permanently in NeNe Clear's database. Only
  foreign-key references (`invoice_id`, `client_id`) are stored.

---

## 7. Settings

`GET/PUT /admin/clear-settings` manages per-organization settings:

| Setting | Default | Description |
| --- | --- | --- |
| `dunning_min_interval_days` | 7 | Minimum days between dunning notices for the same invoice |
| Bank accounts | — | Registered bank accounts for CSV import |

---

## 8. Dunning scope and limits

Dunning notices sent by NeNe Clear are **operator-controlled payment reminders**
(自行催告). NeNe Clear does not perform:
- Third-party debt collection (弁護士法72条の「法律事務」に該当する行為)
- Legal enforcement
- Threats or coercive language

Operators must ensure their dunning templates comply with applicable law. The
default template is a professional reminder only.

---

## Related documents

- Compliance analysis: [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
- Invoice integration: [`../integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
- Scope contract: [`scope-contract.md`](./scope-contract.md)
- ADR index: [`../adr/`](../adr/)
