# Invoice Upstream Contract — what NeNe Clear needs from NeNe Invoice

**Status: accepted (binding) — agreed by both repos 2026-05-30.**

This document has two audiences:

1. **NeNe Clear** — it is the binding spec of the upstream dependency Clear
   builds against. Clear's `Upstream/Invoice/` client and contract tests target
   exactly this contract.
2. **The NeNe Invoice team** — it is a **hand-off / request document**. The
   endpoints and invariants below **do not exist in `nene-invoice` yet**. This
   file states *what Invoice should implement and guarantee* so that Clear can
   reconcile bank deposits while keeping Invoice as the single source of truth
   for billing figures.

> **Boundary (ADR 0009):** NeNe Invoice owns 見積・請求・**入金管理** (quote,
> invoice, payment). NeNe Clear owns 入金消込・督促管理 (bank reconciliation,
> dunning). They integrate over **HTTP only**, never a shared database
> ([`sibling-products.md`](./sibling-products.md)).
>
> This is engineering's interpretation of accounting/record-keeping needs, **not
> legal advice**. Where a rule below touches tax or law, confirm with a licensed
> 税理士 / 公認会計士 (and 弁護士 for dunning) before relying on it.

---

## 1. The core decision: who is the system of record

To satisfy an accountant or tax accountant (税理士 / 公認会計士) reviewing the
two systems, there must be **exactly one** source of truth for each fact. The
classic mental model is **帳簿 (books) ↔ 証憑 (evidence)**, and the split is:

| Fact | System of record | Notes |
| --- | --- | --- |
| Invoice figures (税抜 / 税額 / 税込), tax breakdown | **Invoice** | Issued figures immutable; Clear never recomputes |
| Outstanding balance (売掛金残高) of an invoice | **Invoice** | `outstanding = total − Σ valid payments`, computed by Invoice |
| Payment record (入金) against an invoice | **Invoice** | Created/voided by Clear **via API**; Invoice stores and owns it |
| Issued qualified-invoice copy (写しの保存) | **Invoice** | 7-year retention on the Invoice side |
| Imported bank deposit line (証憑 / 電子取引データ) | **Clear** | 電子帳簿保存法 evidence; retained and searchable in Clear |
| Reconciliation link (which deposit cleared which invoice) | **Clear** | Ties Clear's bank line ↔ Invoice's payment |
| Overpayment credit (前受金 / 預り金 相当) | **Clear** | `client_credit`; **not** posted to Invoice as an over-payment |
| Dunning send history (督促履歴) | **Clear** | Operator-controlled reminders |
| Audit of match / reverse / dunning | **Clear** | Bank-side audit trail |

**Why this split (recommended):** if Clear also kept payments as its own source
of truth, there would be **two competing balances**. Any drift between them is
exactly what a reviewer flags as "which number is correct?". Keeping the payment
record in Invoice, with Clear holding the bank evidence and the link, means the
books (Invoice) and the evidence (Clear) line up **one-to-one** — the way an
auditor expects.

**What this requires of Invoice:** Invoice must accept payments created by an
external, authenticated service (Clear) and must recompute outstanding/status
itself. The rest of this document specifies that.

---

## 2. Read API — Invoice → Clear

Clear reads invoices, balances, and (optionally) clients to drive the matching
UI and dunning eligibility. All read-only.

### 2.1 List invoices

- **operationId:** `listInvoices` (service scope)
- **Method/path:** `GET /api/invoices`
- **Query:** `status` (one or many of `issued`, `partially_paid`, `paid`),
  `overdue` (bool, computed), `client_id`, `due_before` / `due_after`,
  `outstanding_gt` (e.g. `0`), `limit`, `offset`.
- **Response item (read model Clear consumes):**

```json
{
  "invoice_id": 123,
  "invoice_number": "INV-2026-001",
  "client_id": 45,
  "issued_at": "2026-04-01",
  "due_at": "2026-04-30",
  "total_cents": 110000,
  "outstanding_cents": 110000,
  "status": "issued",
  "currency": "JPY"
}
```

`tax_breakdown` may be included but is **opaque** to Clear (Clear never sums or
recomputes it). Pagination uses the NENE2 `items` / `limit` / `offset` envelope.

### 2.2 Get invoice detail (with payment history)

- **operationId:** `getInvoiceById`
- **Method/path:** `GET /api/invoices/{id}`
- Adds the authoritative `outstanding_cents` and the payment history:

```json
{
  "invoice_id": 123,
  "...": "...fields as above...",
  "payments": [
    { "payment_id": 900, "amount_cents": 50000, "paid_at": "2026-04-20",
      "method": "bank_transfer", "external_reference": "clear:recon:777" }
  ]
}
```

### 2.3 List clients (optional, for match hints / dunning recipient)

- **operationId:** `listClients`
- **Method/path:** `GET /api/clients`
- Minimal read model: `client_id`, `contact_name`, `recipient_email`.
- If Invoice cannot expose client contact, Clear falls back to operator-entered
  recipients; this endpoint is **nice-to-have**, not required for MVP matching.

---

## 3. Write API — Clear → Invoice (the important part)

This is where Invoice stays the system of record. Clear **proposes** a match, a
human **confirms** it, and only then does Clear call these endpoints.

### 3.1 Create a payment (on confirmed match)

- **operationId:** `createPayment`
- **Method/path:** `POST /api/invoices/{id}/payments`
- **Request:**

```json
{
  "amount_cents": 50000,
  "paid_at": "2026-04-20",
  "method": "bank_transfer",
  "external_reference": "clear:recon:777",
  "idempotency_key": "clear:recon:777:v1"
}
```

**Invoice MUST:**

1. **Use `paid_at` as given** — it is the **bank value date (入金日 / 取引日)**
   supplied by Clear from the bank statement, **not** the time the row was
   created. (消費税法 / 法人税法: the date funds were received matters; the
   posting timestamp does not.)
2. **Be idempotent on `idempotency_key`.** A retried call with the same key
   returns the same payment, never a duplicate. This prevents double-posting if
   the network fails mid-call.
3. **Store `external_reference`** (Clear's reconciliation id) so the payment can
   be traced back to the bank line, and so §3.2 can void by reference.
4. **Recompute `outstanding_cents` and `status`** (`partially_paid` / `paid`)
   from the full set of valid payments. Clear never sends a status.
5. **Reject over-allocation.** If `amount_cents > outstanding_cents`, return
   `422` with a Problem Details of type `payment-exceeds-outstanding` and include
   the current `outstanding_cents`. Clear then splits: post a payment for the
   outstanding amount and record the remainder locally as `client_credit`
   (前受金 / 預り金 相当). **Rationale:** an invoice should never show negative
   outstanding; the excess is a credit owed to the client, not extra revenue on
   that invoice.

### 3.2 Void / reverse a payment (on match reversal)

- **operationId:** `voidPayment`
- **Method/path:** `POST /api/invoices/{id}/payments/{paymentId}/void`
  (or `DELETE` by `external_reference` — either is acceptable, both idempotent)
- **Request:** `{ "reason": "operator reversal", "idempotency_key": "..." }`

**Invoice MUST:**

1. **Not hard-delete.** Reversal is a **void with audit** (who / when / reason),
   preserving financial history — mirroring Clear's reversal record. (No silent
   mutation of payment history.)
2. **Recompute** outstanding/status from the remaining valid payments.
3. Be **idempotent**; voiding an already-voided payment is a no-op success.

### 3.3 Manual payments still belong to Invoice

Invoice keeps its existing **manual** payment entry (e.g. cash, or operators not
using Clear). Bank-sourced payments arrive via §3.1 and are distinguished by a
non-null `external_reference`. The two coexist; Clear only ever touches payments
it created (matched by `external_reference`).

---

## 4. Invariants Invoice must guarantee

A reviewer (税理士 / 公認会計士) should be able to rely on all of these:

- **Issued invoice figures immutable** — line items, tax, totals, number, dates
  do not change after issue (already Invoice policy; restated for the contract).
- **`outstanding_cents = total_cents − Σ(valid payments)`**, computed and owned
  by Invoice. Authoritative everywhere.
- **`paid_at` is the bank value date**, supplied by Clear, never overwritten with
  a posting timestamp.
- **No hard delete** of payments; void-with-audit only.
- **Idempotency + `external_reference` round-trip** on every write, so Clear and
  Invoice can always be reconciled with each other.
- **Integer minimum currency units (`*_cents`), JPY** for Phase 1–3. No float /
  DECIMAL for money.
- **Audit on the Invoice side** for payment create/void: actor = the Clear
  service principal, timestamp, `external_reference`, amount.
- **Tenant scoping matches.** A Clear service token is scoped to one (or more)
  organizations; Invoice must reject cross-tenant access. `organization_id`
  semantics must agree between the two products (ADR 0006 on both sides).

---

## 5. Auth, versioning, and contract tests

- **Service-to-service auth:** a bearer **service token** issued by Invoice for
  Clear, scoped to `read invoices` + `write payments` for the operator's
  organization(s). Stored only in Clear's `.env`
  (`NENE_INVOICE_API_BASE_URL`, `NENE_INVOICE_BEARER_TOKEN`).
- **Errors:** RFC 9457 Problem Details, including at least
  `payment-exceeds-outstanding`, `invoice-not-found`, `payment-not-found`,
  `unauthorized`, `insufficient-scope`.
- **OpenAPI:** Invoice publishes the OpenAPI for these operations; Clear writes
  **contract tests** against it (`docs/explanation/requirements.md` §3).
- **Stability:** `operationId` values and JSON field names are stable once
  shipped — deprecate, never rename (both repos follow this).

---

## 6. Degraded mode (Invoice API unavailable)

If Invoice is unreachable, Clear enters **degraded mode**:

- Bank CSV import still works (evidence capture is local to Clear).
- Match **confirmation is blocked** (it cannot write the payment upstream) and
  surfaces `upstream-invoice-unavailable` to the operator.
- No reconciliation is finalized without the upstream write succeeding, so the
  two systems never silently diverge.

---

## 7. Explicitly NOT requested of Invoice

To keep the boundary clean, Invoice should **not** build any of these (they are
Clear's domain — ADR 0009):

- Bank CSV / statement import or parsing
- Deposit-to-invoice matching or match suggestions
- Dunning (督促) sending, templates, or send history
- Client credit (前受金) balances from overpayment
- Bad-debt (貸倒) determination — that is a tax judgment, made by neither product
  automatically (see [`payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md))

Conversely, Clear will **not** issue documents, compute tax, or store invoice
figures as truth.

---

## 8. Hand-off checklist for the NeNe Invoice team

When you pick this up in `nene-invoice`, the minimum to unblock Clear Phase 1:

- [ ] `GET /api/invoices` with status / overdue / outstanding filters (§2.1)
- [ ] `GET /api/invoices/{id}` with `outstanding_cents` + payment history (§2.2)
- [ ] `POST /api/invoices/{id}/payments` — idempotent, `paid_at` honored,
      `external_reference` stored, over-allocation rejected (§3.1)
- [ ] `POST /api/invoices/{id}/payments/{paymentId}/void` — void-with-audit,
      idempotent (§3.2)
- [ ] Service-token auth scoped per organization (§5)
- [ ] OpenAPI published for the above; field/operationId stability committed (§5)
- [ ] (Optional) `GET /api/clients` minimal contact read model (§2.3)
- [ ] Add NeNe Clear to `docs/integrations/sibling-products.md` as the
      downstream reconciliation/dunning consumer

Open an Issue in `nene-invoice` referencing this document. Until these land,
Clear runs against a **fake upstream** in contract tests and cannot finalize
real matches.

---

## Related

- Domain split: [`../adr/0009-separate-from-nene-invoice.md`](../adr/0009-separate-from-nene-invoice.md)
- Sibling integration policy: [`./sibling-products.md`](./sibling-products.md)
- Requirements (upstream): [`../explanation/requirements.md`](../explanation/requirements.md)
- Reconciliation & dunning compliance: [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md)
