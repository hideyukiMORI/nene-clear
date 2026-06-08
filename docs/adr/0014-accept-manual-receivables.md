# ADR 0014: Accept Manually-Entered Receivables (a scoped carve-out to ADR 0010 / Scope X2)

## Status

accepted

> Accepted by the product owner 2026-06-08. This ADR **amends a binding contract**
> (`scope-contract.md` row X2) by narrowing its scope (see Decision). The core
> decision is an internal-control one and cites no statute. One advisory item
> remains: the exact **UI wording** that frames a manual receivable as "not a
> 適格請求書 / not the tax original" should be confirmed with a 税理士 /
> 公認会計士 before the manual-entry UI ships — this affects copy, not the
> decision.

## Context

Today NeNe Clear can only reconcile invoices that come from the **NeNe Invoice
upstream API** (ADR 0009, ADR 0010, `invoice-upstream-contract.md`). Two
problems follow from that single precondition:

1. **Adoption ceiling (the core reason).** Many Japan SMBs issue invoices with
   another tool (freee / マネーフォワード / spreadsheet / paper) but still want
   only the *downstream* operations Clear is good at: bank reconciliation and
   dunning. Requiring NeNe Invoice excludes all of them and makes Clear a captive
   add-on rather than a standalone product — regardless of how mature the
   upstream is.
2. **The upstream is no longer a blocker, but it is also not a market.** The
   Invoice API is now implemented on the sibling side (nene-invoice PR #141);
   Clear's client and contract tests are ready, with only the live connection
   (env vars) pending. So "wait for the API" is no longer the issue. The issue is
   that even with the upstream fully live, an Invoice-required Clear still serves
   **only customers who also adopt NeNe Invoice** — which is the same ceiling as
   #1. The two products integrate when both are present; they should not be
   *coupled* such that Clear is dead weight without the sibling.

The obvious relief is to let an operator **enter a receivable directly** (or
import a batch of them) so Clear can reconcile and dun without an upstream. The
tension is `scope-contract.md` **X2** ("Clear must not hold invoices,
outstanding balances, or payments as its **own** source of truth") and ADR 0010
(Invoice is the payment system of record).

The key observation that resolves the tension: **X2's actual risk is two systems
holding the _same_ invoice and drifting** ("which number is correct?" at audit).
A receivable that exists **only** in Clear has no competing record, so it cannot
drift. The risk X2 guards against simply does not arise for Clear-only
receivables.

## Decision

**Clear accepts receivables from two sources, made explicit by a `source`
discriminator, and applies the system-of-record rule _per source_.**

| `source` | Where figures live (SSOR) | Payment record | Outstanding balance |
| --- | --- | --- | --- |
| `invoice_upstream` | **NeNe Invoice** (unchanged — ADR 0010 still binds) | Created/voided in Invoice via API (write-back) | Computed and reported by Invoice |
| `manual` | **NeNe Clear** (no competing system exists) | Stored in Clear (no write-back target) | Computed by Clear: `total_cents − Σ confirmed allocations` |

X2 and ADR 0010 are **not repealed**; their scope is **narrowed to
`invoice_upstream` receivables**, where a second system (Invoice) genuinely owns
the truth. For `manual` receivables Clear is the sole owner *by construction*,
so it may store the figures, the payment, and the computed outstanding.

### What stays forbidden (Scope X1 is unchanged)

A `manual` receivable is a **reconciliation reference (a receivable stub)**, not
an invoice. Clear MUST NOT, for manual entries:

- generate an invoice / 適格請求書 / PDF, or compute consumption tax (X1);
- present itself as the **issuer** of the document or as the **tax原本 /
  写しの保存** of record. The qualified-invoice copy and its 7-year retention
  remain on whatever tool actually issued the invoice — that responsibility does
  not move into Clear.

The UI and docs MUST state this plainly on every manual-entry surface (e.g.
"これは消込・督促用の参照情報です。適格請求書の原本ではありません").

### Minimum fields for a `manual` receivable

Matching alone needs number + amount + payer name, but the **full** Clear
feature set (dunning, aging) needs a due date and a recipient:

| Field | Required | Why |
| --- | --- | --- |
| `reference_number` | yes | The external document number (payer-facing); allocation/display key. Named *reference*, not *invoice*, on purpose (X1). |
| `client_name` | yes | Match + display (no upstream `client_id` to resolve) |
| `total_cents` | yes | Basis for `outstanding_cents` |
| `due_at` | required to dun / age | Without it, dunning (D7) and aging (D10) cannot function |
| `recipient_email` | required to dun | Dunning send target |
| `issued_at`, `currency` | optional | `currency` defaults to JPY |

`outstanding_cents` is **Clear-computed** for manual receivables and follows the
same no-silent-write-off rules as upstream (partial → still open; overpayment →
`client_credit`; never a negative balance).

### Reconciliation, dunning, export

- A confirmed match allocates a deposit to a receivable of **either** source.
  For `invoice_upstream` the payment is written back to Invoice (ADR 0010); for
  `manual` the payment is recorded locally and Clear updates its own
  `outstanding_cents` / status. No write-back is attempted for `manual`.
- Dunning, client credits, CSV export, and the audit trail work the same for
  both sources; lists and exports surface `source` so a reviewer can tell a
  Clear-owned receivable from an Invoice-owned one at a glance.
- Reversal/void rules are unchanged (no hard delete; audited).

## Consequences

**Benefits**

- Clear becomes a **standalone product** (bank reconciliation + dunning) for any
  SMB, decoupled from whether NeNe Invoice exists or has shipped its API. The
  addressable market is no longer gated on the sibling.
- The SSOR model stays auditable: upstream receivables remain one-to-one with
  Invoice; manual receivables are wholly within Clear with no second balance to
  drift against. `source` makes the provenance explicit and greppable.
- No change to the X1 boundary: Clear still issues nothing and computes no tax.

**Costs / follow-up (each its own Issue, gated on this ADR being accepted)**

- **Schema:** a Clear-owned `manual_receivables` table, and a way for
  `reconciliation_allocations` to target either an upstream `invoice_id` or a
  `manual_receivable_id` (proposed: carry `source` + a nullable
  `manual_receivable_id` beside the existing upstream `invoice_id`). Clear now
  owns an outstanding/status calculation for the manual class — a small,
  bounded subledger, not a billing ledger.
- **Ingress:** a single-entry form **and** a bulk **receivables CSV import**
  (the natural pair to the bank CSV import; better fit for SMB volume).
- **UI/docs:** the "not a tax original" framing above, plus a `source` badge in
  reconciliation/dunning/list/export views.
- **Terminology:** register the new identifiers below (in the implementation PR,
  per the same-PR-as-code rule).
- **Degraded-mode rule (ADR 0010):** still applies to `invoice_upstream` only;
  `manual` receivables are unaffected by Invoice being unavailable.

### Terminology to register (proposal — land in the implementation PR)

Per `terminology.md` rule 2 (register in the same PR as the code), these are
**not** yet added to the binding registry; this is the reserved spelling:

| Kind | Canonical | Notes |
| --- | --- | --- |
| Enum `source` (on a receivable target) | `invoice_upstream`, `manual` | Discriminates SSOR per this ADR |
| Domain entity (owned by Clear) | `ManualReceivable` / `manual_receivables` / `manual_receivable_id` | §1 of terminology |
| Field | `reference_number` | External document number; **not** `invoice_number` (X1) |
| Field | `client_name` | Free-text payer name (no upstream `client_id`) |
| Fields (reuse upstream spelling) | `total_cents`, `outstanding_cents`, `due_at`, `issued_at`, `recipient_email`, `currency`, `status` | Match §6 conventions |
| Problem Details slug (candidate) | `manual-receivable-not-found` | If a lookup endpoint is added |

`glossary.md` gains "receivable (manual vs upstream)"; `naming-conventions.md`
is unaffected (no new pattern — `source` is a standard enum, money is `_cents`,
dates are `_at`).

## Related

- Issue: `#161`
- PR: `#000`
- Amends (narrows scope of): [ADR 0010](./0010-payment-system-of-record.md);
  [`scope-contract.md`](../explanation/scope-contract.md) row **X2**
- Unaffected (still binding): [`scope-contract.md`](../explanation/scope-contract.md)
  row **X1**; [ADR 0009](./0009-separate-from-nene-invoice.md);
  [ADR 0013](./0013-no-journal-entries-no-bad-debt.md)
- Context: [`invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md);
  [`terminology.md`](../explanation/terminology.md) §1, §6
- Supersedes: none
- Superseded by: none
