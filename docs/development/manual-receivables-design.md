# Manual Receivables — Implementation Design

**Status: design (pre-implementation).** Engineering design for
[ADR 0014](../adr/0014-accept-manual-receivables.md) (accepted). It instantiates
the binding rules; on any conflict the rules win:
[`scope-contract.md`](../explanation/scope-contract.md) (X1 unchanged, X2
narrowed by ADR 0014), [`payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md),
[ADR 0010](../adr/0010-payment-system-of-record.md),
[`terminology.md`](../explanation/terminology.md).

See also: [`phase-1-reconciliation-design.md`](./phase-1-reconciliation-design.md)
(the existing reconciliation/allocation model this extends).

---

## 1. Goal & scope

Let an operator reconcile and dun receivables **without a NeNe Invoice upstream**,
by entering them directly (single form + CSV import), while keeping the existing
upstream path byte-for-byte unchanged. A `source` discriminator separates the two
and drives the system-of-record rule per receivable.

**In:** `manual_receivables` entity + CRUD + CSV import; source-aware
reconciliation (allocate to upstream **or** manual); source-aware dunning;
manual overpayment → client credit; `source` surfaced in lists/export; the
"not a tax original" UI framing.

**Out:** any invoice/PDF/tax issuance (X1 — still forbidden); editing an
upstream invoice in Clear; multi-currency (JPY only, as Phase 1); auto-matching.

Money is integer `*_cents` (BIGINT), JPY. Every tenant-scoped row carries
`organization_id`. Soft-delete only (no hard delete — X12/X13). Every state
change is audited via `AuditEvent` (entity/entity_id).

---

## 2. Architecture — one reconciliation/dunning core, two sources

The reconciliation and dunning code must stay source-agnostic. Introduce a thin
abstraction so the existing flows do not branch all over:

- **`ReceivableRef`** (value object) — `{ source: 'invoice_upstream' | 'manual', id: int }`.
  The canonical way any allocation / dunning notice points at "the thing being paid".
- **`ReceivableView`** (read model) — uniform shape the UI/matching consume:
  `{ source, id, document_number, client_name, total_cents, outstanding_cents,
  due_at, status, recipient_email? }`. `document_number` = upstream
  `invoice_number` or manual `reference_number`.
- **`ReceivableQuery`** — returns match candidates from **both** sources for a
  deposit (by amount / payer / open|partially_paid). It fans out to the upstream
  proxy **and** `manual_receivables` and merges. Degraded mode (Invoice down)
  drops only the upstream half; manual candidates still resolve.
- **`ReceivableLedger`** (writer) — two implementations dispatched by
  `allocation.source` at confirm/reverse:
  - `UpstreamReceivableLedger` — **existing** behaviour: create/void a payment in
    Invoice via API, idempotency key + `external_reference` (ADR 0010). Untouched.
  - `ManualReceivableLedger` — **new**: update `manual_receivables.outstanding_cents`
    / `status` locally. **No write-back**, no Invoice call.

This keeps `payment_reconciliations`, the propose→confirm→reverse use cases, and
the audit trail single-pathed; only the per-allocation ledger write differs.

---

## 3. Data model

### 3.1 New: `manual_receivables` (Clear-owned subledger row)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `organization_id` | BIGINT, indexed | tenant scope |
| `reference_number` | VARCHAR(64), NOT NULL | external document no. — **not** `invoice_number` (X1) |
| `client_name` | VARCHAR(255), NOT NULL | free-text payer (no upstream `client_id`) |
| `recipient_email` | VARCHAR(255), NULL | required to dun |
| `total_cents` | BIGINT, NOT NULL | basis for outstanding |
| `outstanding_cents` | BIGINT, NOT NULL | Clear-computed; maintained on confirm/reverse |
| `currency` | VARCHAR(3), NOT NULL, default `JPY` | |
| `issued_at` | DATE, NULL | optional |
| `due_at` | DATE, NULL | required for dunning/aging |
| `status` | VARCHAR(32), NOT NULL, default `open` | `open` / `partially_paid` / `paid` / `cancelled` |
| `created_by` | BIGINT | actor |
| `created_at`, `updated_at` | DATETIME | |
| `is_deleted`, `deleted_at` | BOOL / DATETIME | soft-delete (business entity) |

Indexes: `(organization_id)`, `(organization_id, status)`. **Soft-uniqueness**
of `(organization_id, reference_number)` among non-deleted rows is enforced in
the app layer (used as the CSV import dedupe key); not a hard DB unique
constraint (partial-unique support varies MySQL/SQLite).

`overdue` is **derived** (`due_at < today AND outstanding_cents > 0`), never
stored — same convention as upstream invoices.

### 3.2 Changed: `reconciliation_allocations` (polymorphic target)

Add, keeping the upstream columns untouched:

| Column | Change |
| --- | --- |
| `source` | **new** VARCHAR(32) NOT NULL, default `invoice_upstream` |
| `manual_receivable_id` | **new** BIGINT NULL, indexed |
| `invoice_id` | now nullable |

Invariant (app-enforced + checked in tests):

- `source = invoice_upstream` ⇒ `invoice_id` set, `manual_receivable_id` null,
  `payment_id` / `external_reference` populated by write-back.
- `source = manual` ⇒ `manual_receivable_id` set, `invoice_id` null,
  `payment_id` / `external_reference` **null** (no Invoice payment exists).

### 3.3 Changed: `dunning_notices`

| Column | Change |
| --- | --- |
| `source` | **new** VARCHAR(32) NOT NULL, default `invoice_upstream` |
| `manual_receivable_id` | **new** BIGINT NULL |
| `reference_number` | **new** VARCHAR(64) NULL — manual document-no. snapshot |
| `invoice_id`, `invoice_number` | now nullable (populated for upstream only) |

`recipient_email`, `outstanding_cents`, `due_at` snapshots already exist and are
reused for both sources.

### 3.4 Changed: `client_credits` (manual overpayment)

| Column | Change |
| --- | --- |
| `source` | **new** VARCHAR(32) NOT NULL, default `invoice_upstream` |
| `manual_receivable_id` | **new** BIGINT NULL — the over-paid manual receivable |
| `client_name` | **new** VARCHAR(255) NULL — payer snapshot when no `client_id` |
| `client_id` | now nullable (upstream only) |

Application of a `manual` credit targets a `manual_receivable` of the same payer;
the existing apply flow gains the same source dispatch.

---

## 4. Outstanding / status — the bounded subledger (manual only)

For `source = manual` Clear owns the math (it is the sole record — ADR 0014):

- **Confirm** allocation of `amount_cents` to a manual receivable:
  - allocate up to `outstanding_cents`; `outstanding_cents -= allocated`.
  - excess (`amount > outstanding`) → a **manual `client_credit`** for
    `client_name` — never a negative balance, never a silent write-off (X6).
  - status: `outstanding == 0` → `paid`; `0 < outstanding < total` →
    `partially_paid`; else `open`.
- **Reverse**: `outstanding_cents += reversed`; recompute status; audited.
- A short transfer-fee difference is handled exactly as the upstream path
  (operator records it with a reason code; nothing is silently absorbed).

Upstream receivables are unchanged: Invoice computes outstanding and status;
Clear only writes the payment back.

---

## 5. API surface (new / changed)

Capabilities reuse the existing reconciliation set — **no new role** (ADR 0006).

| Method & path | Capability | Purpose |
| --- | --- | --- |
| `GET /admin/manual-receivables` | `view_reconciliation` | list (filters: status, client_name, due range) |
| `POST /admin/manual-receivables` | `manage_reconciliation` | create (single entry) |
| `GET /admin/manual-receivables/{id}` | `view_reconciliation` | detail |
| `PUT /admin/manual-receivables/{id}` | `manage_reconciliation` | edit metadata (see §8 lock rule) |
| `POST /admin/manual-receivables/{id}/cancel` | `manage_reconciliation` | soft-cancel (no delete) |
| `POST /admin/manual-receivable-imports` | `manage_reconciliation` | CSV bulk import (parallels `bank-import-batches`) |
| `GET /admin/receivables` | `view_reconciliation` | **unified** read merging upstream + manual for matching |

`GET /admin/upstream/invoices` stays as the upstream-only proxy. The
reconciliation `propose` candidates switch to `ReceivableQuery` (union). Confirm
(`POST /admin/reconciliations`) accepts allocations carrying `source` +
(`invoice_id` | `manual_receivable_id`). Dunning (`POST /admin/dunning-notices`)
accepts a `ReceivableRef`.

Problem Details (register slug): `manual-receivable-not-found`,
`manual-receivable-cancelled` (dunning/allocating a cancelled one).

---

## 6. Frontend

- **Reconciliation / nav:** a "売掛 直接入力" (manual receivables) area — list +
  single-entry modal + **receivables CSV import** (mirrors the bank CSV import
  dropzone).
- **Mandatory framing copy** on every manual-entry surface (X1):
  *「これは消込・督促のための参照情報です。適格請求書の発行・原本（写しの保存）
  ではありません。」* (final wording pending 税理士 confirmation per ADR 0014).
- **`source` badge** (`Invoice` vs `手入力`) in reconciliation candidate lists,
  reconciliation history, dunning targets/history, and the manual-receivable
  list. Add a `source` filter where lists mix both.
- **CSV export** gains a `source` column so a reviewer sees provenance.
- Help page (`help-content.ts`): a short subsection under reconciliation noting
  receivables can come from Invoice **or** be entered directly, and what manual
  entry is / isn't.

---

## 7. Terminology to register (in the Issue 1 PR)

Per `terminology.md` rule 2 (register in the same PR as first code use):

- **§1 entity:** `ManualReceivable` / `manual_receivables` / `manual_receivable_id`.
- **Enum `source`** (on allocations, dunning notices, client credits, receivable
  reads): `invoice_upstream`, `manual`.
- **Fields:** `reference_number`, `client_name`; reuse existing spellings
  `total_cents`, `outstanding_cents`, `recipient_email`, `due_at`, `issued_at`,
  `currency`, `status`.
- **`manual_receivable` status enum:** `open`, `partially_paid`, `paid`, `cancelled`.
- **Problem Details slugs:** `manual-receivable-not-found`, `manual-receivable-cancelled`.
- **`glossary.md`:** add "receivable (upstream vs manual)".
- **`scope-contract.md` X2:** add the ADR 0014 narrowing note (manual = Clear-owned,
  no competing system).

---

## 8. Compliance guardrails (mapping)

| Rule | Effect here |
| --- | --- |
| **X1** (no issuance/tax/PDF) | Manual entry is a reference stub; `reference_number` ≠ invoice issuance; UI states "not a tax original". Unchanged. |
| **X2** (no own SSOR) | **Narrowed** to `invoice_upstream` (ADR 0014). `manual` is Clear-owned because nothing else holds it. |
| **X6 / X7** (no silent write-off / forced appropriation) | Manual confirm allocates only up to outstanding; excess → client credit; operator directs allocation. |
| **X11** (no silent auto-match) | Manual matching is human-confirmed like upstream. |
| **X12 / X13** (no hard delete / no mutate evidence) | Soft-delete + cancel only; bank lines still immutable. |
| **ADR 0010 degraded mode** | Applies to upstream only; manual reconcile/dun keep working when Invoice is down. |

---

## 9. Issue breakdown (sequenced, each independently shippable)

1. **Foundation** — `manual_receivables` migration + `ManualReceivable` entity +
   `PdoManualReceivableRepository` + DI (per-domain ServiceProvider) +
   soft-delete + audit wiring; **terminology + scope-contract X2 + glossary**
   updates in this PR. Tests: migration + repo. *No UI.*
2. **CRUD API** — create / get / list / edit / cancel; validation (required
   fields; `due_at`/`recipient_email` needed only to dun); OpenAPI; audit. Tests: HTTP.
3. **Receivables CSV import** — `POST /admin/manual-receivable-imports`; column
   mapping (reference_number, client_name, total, due_at, email); dedupe by
   `(organization_id, reference_number)`; batch + audit (reuse bank-import shape). Tests.
4. **Reconcile against manual** *(core)* — allocation polymorphism (§3.2);
   `ReceivableRef` / `ReceivableView` / `ReceivableQuery` union candidates;
   `ManualReceivableLedger` confirm/reverse; manual overpayment → client credit;
   `GET /admin/receivables`. Tests: unit (math/status/invariants) + HTTP +
   ensure upstream path regression-free.
5. **Dun manual** — dunning polymorphism (§3.3); eligibility by `due_at`;
   reference snapshot; send log; min-interval still applies. Tests.
6. **Frontend** — manual-entry form + CSV import UI + framing copy + `source`
   badge/filter across reconciliation/dunning/lists + export `source` column +
   help subsection. E2E (form, import, reconcile-manual, dun-manual, badges).
7. **(Optional) polish** — unified receivables list UX; degraded-mode copy;
   metrics on manual vs upstream.

Gate: 1→2→3 can land in parallel after 1; 4 depends on 1; 5 depends on 4; 6
depends on 4/5.

---

## 10. Test plan

- **Unit:** outstanding/status transitions; overpayment→credit; allocation
  source invariants (exactly one target id set); ReceivableQuery merge + degraded.
- **Repo:** `PdoManualReceivableRepository` (CRUD, soft-delete, tenant scope).
- **HTTP:** CRUD + import + reconcile-with-manual + dun-manual; capability gates.
- **Contract:** upstream reconcile path unchanged (existing 6 skipped contract
  tests stay green when env is set).
- **E2E (Playwright, API mocked):** manual form, CSV import, reconcile a manual
  receivable, dun it, `source` badges, export column.
- Gates: PHPUnit, PHPStan level 8, PHP-CS-Fixer; Vitest; E2E.

---

## 11. Open questions / decisions to confirm

1. **Edit lock:** once a manual receivable has any confirmed allocation, lock
   `total_cents` (allow only `recipient_email` / `due_at` edits). *Proposed: yes.*
2. **`reference_number` duplicates:** soft-unique per org; single-entry warns on
   dup, CSV import skips dup (dedupe). *Proposed: yes.*
3. **List UX:** separate "手入力売掛" list vs one unified receivables list with a
   `source` filter. *Proposed: separate list for entry/management; unified only
   in the reconciliation candidate picker.*
4. **UI framing copy:** exact "not a 適格請求書 / not the tax original" wording —
   confirm with 税理士 before Issue 6 ships (ADR 0014 advisory).
