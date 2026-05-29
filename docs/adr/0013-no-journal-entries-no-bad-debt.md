# ADR 0013: No Journal Entries and No Bad-Debt Determination — Subledger + Export

## Status

accepted

> Engineering's interpretation of accounting/tax boundaries — **not legal
> advice**. Confirm with a 税理士 / 公認会計士.

## Context

It is tempting for a reconciliation tool to drift toward "doing the accounting":
posting journal entries (仕訳), or deciding that an unpaid invoice is a bad debt
and writing it off. Both are out of bounds:

- **Journal entries / general ledger** are the domain of accounting software
  (freee, Money Forward, Yayoi) and double-entry bookkeeping. Duplicating them in
  Clear creates a second, conflicting books.
- **Bad-debt loss (貸倒損失)** is a **tax judgment** under 法人税基本通達
  9-6-1〜9-6-3 (legal extinguishment, factual uncollectibility, formal/one-year
  criteria). It depends on facts and judgment the software cannot evaluate, and
  getting it wrong has tax consequences.

We must state plainly that Clear does neither, so scope creep is rejected by
reference rather than re-litigated each time.

## Decision

1. **No journal entries.** Clear posts no 仕訳 and maintains no general ledger. It
   is a **reconciliation subledger** that records which bank line cleared which
   invoice payment, plus the evidence and audit trail.
2. **No bad-debt determination.** Clear MUST NOT classify a balance as 貸倒, post
   a write-off, or reduce a receivable as a tax event. An operator MAY **pause
   dunning / mark "collection paused"** for workflow purposes — **operational
   only**, with no accounting or tax effect, recorded in the audit trail.
3. **Export, don't post.** Clear provides **CSV export** of reconciliation and
   payment data (invoice number, issue date, `paid_at`, amount, tax breakdown
   from Invoice, match id) for the operator and their 税理士 to import into
   accounting software, where the actual journal entries and any 貸倒損失 decision
   are made.

## Consequences

**Benefits**

- Clear stays a subledger; the single general ledger lives in the operator's
  accounting software — no competing books.
- Tax-sensitive judgments (貸倒) remain with the professional who is accountable
  for them.

**Costs**

- Operators must run accounting software for journals and write-offs; Clear is
  not a one-stop bookkeeping replacement (by design — and consistent with the
  product vision).

**Follow-up**

- Define the CSV export columns with a 税理士 so they map cleanly to a
  journal-import workflow (compliance §9 gate, §5).

## Related

- Compliance: [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) §0, §5, §7
- Scope contract: [`../explanation/scope-contract.md`](../explanation/scope-contract.md) (X3, X4)
- Product vision (not full accounting): [`../explanation/product-vision.md`](../explanation/product-vision.md)
- Supersedes: none
- Superseded by: none
