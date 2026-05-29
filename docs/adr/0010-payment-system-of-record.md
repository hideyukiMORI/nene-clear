# ADR 0010: NeNe Invoice is the Payment System of Record; Clear Writes Back

## Status

accepted

## Context

NeNe Clear matches bank deposits to issued invoices. NeNe Invoice already owns
invoices, payments, and the outstanding balance, and computes
`partially_paid` / `paid` from its payment records (it is the billing ledger).

If Clear also stored payments and outstanding balances as its **own** source of
truth and merely "synced" status to Invoice, the two systems could drift. An
accountant or tax accountant (税理士 / 公認会計士) reviewing the books would then
face two competing balances and ask "which number is correct?" — exactly the
failure we must avoid.

We need to fix, unambiguously, where each fact lives, and how Clear records a
confirmed match without becoming a second ledger.

## Decision

**NeNe Invoice is the single system of record for invoice figures, payment
records, and outstanding balances.** NeNe Clear is a **reconciliation subledger
and evidence custodian** that writes payments back to Invoice via HTTP.

System-of-record split (帳簿 ↔ 証憑):

| Fact | Owner |
| --- | --- |
| Invoice figures, tax breakdown, outstanding balance | **Invoice** |
| Payment record against an invoice | **Invoice** (created/voided by Clear via API) |
| Imported bank deposit line (証憑 / 電子取引データ) | **Clear** |
| Reconciliation link (deposit ↔ payment) | **Clear** |
| Overpayment credit (client_credit) | **Clear** |
| Dunning history, audit trail | **Clear** |

Write-back rules (full detail in
[`invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)):

- On a **human-confirmed** match, Clear calls Invoice to **create a payment**
  with `paid_at` = bank value date, an **idempotency key**, and an
  **`external_reference`** (Clear's reconciliation id).
- On **reversal**, Clear calls Invoice to **void** that payment (void-with-audit,
  never hard delete). Invoice recomputes outstanding/status.
- **Over-allocation is rejected** by Invoice; the excess becomes a `client_credit`
  in Clear, never a payment beyond outstanding.
- Clear **never** computes invoice tax, totals, or status, and never stores them
  as truth. If Invoice is unavailable, Clear enters **degraded mode** (import
  works; match confirmation is blocked) so the two never silently diverge.

## Consequences

**Benefits**

- One balance, one truth. 帳簿 (Invoice) and 証憑 (Clear) reconcile one-to-one.
- Idempotency + `external_reference` make the two systems mutually auditable.
- Clear stays small: evidence + links + dunning, not a ledger.

**Costs**

- Clear depends on an Invoice API that **does not exist yet** — it must be built
  per the contract doc, tracked as an Issue in `nene-invoice`.
- Match confirmation requires a successful upstream write (no offline finalize).

**Follow-up**

- Hand off [`invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
  to the Invoice team; open the implementation Issue in `nene-invoice`.
- Clear builds against a fake upstream + contract tests until the API lands.

## Related

- ADR 0009: Separate domain from nene-invoice
- Invoice upstream contract: [`../integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
- Scope contract: [`../explanation/scope-contract.md`](../explanation/scope-contract.md) (X2)
- Compliance: [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) §0, §2
- Supersedes: none
- Superseded by: none
