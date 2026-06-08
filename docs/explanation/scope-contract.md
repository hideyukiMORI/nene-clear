# Scope Contract — GOAL / DO / DON'T (binding)

**Status: binding (non-negotiable).** This is the charter for what NeNe Clear
**is**, what it **does**, and what it **must never do**. Every Issue, ADR, and PR
is measured against it. When a request conflicts with this contract, the contract
wins; changing the contract requires an ADR (and, where a row cites law,
professional sign-off).

> This is engineering's interpretation of accounting, record-keeping, and
> collection-law boundaries — **not legal advice**. Rows that touch tax or law
> name the statute so a 税理士 / 公認会計士 / 弁護士 can verify them. Confirm
> before relying on any of it.

Read first: [ADR 0009](../adr/0009-separate-from-nene-invoice.md) (domain split),
[`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
(binding detail), [`invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
(the Invoice boundary).

---

## GOAL

> **NeNe Clear lets a Japan SMB clear bank deposits against issued invoices and
> send professional overdue reminders — with an audit trail and evidence
> retention that an accountant or tax accountant can review without finding a
> single silent deviation.**

Concretely, the goal is reached when an operator can:

1. Connect to NeNe Invoice (upstream) and import a bank CSV.
2. See unmatched deposits beside open/overdue invoices.
3. **Propose → confirm (by a human)** a match; Clear writes the payment back to
   Invoice and stores the bank evidence + reconciliation link + audit entry.
4. Handle partial payment, overpayment (→ client credit), and transfer-fee
   mismatch **without any silent write-off**.
5. Send a logged dunning notice for an overdue invoice, within legal tone and
   frequency limits.
6. Hand a tax advisor a CSV and a searchable evidence trail that maps every
   cleared invoice to the exact bank line and date.

A reviewing professional is the real acceptance test: **帳簿 (Invoice) ↔ 証憑
(Clear) line up one-to-one, and nothing was changed in a way that isn't logged.**

---

## DO — Clear owns these

| # | Clear does | Grounded in |
| --- | --- | --- |
| D1 | Import bank deposit lines from CSV and preserve them as **immutable evidence** (証憑) | 電子帳簿保存法 (電子取引データ) |
| D2 | Keep imported data **searchable** by transaction date / amount / counterparty | 電子帳簿保存法 可視性の確保 |
| D3 | Propose matches (rules / AI) and require **human confirmation** before finalizing | Internal control; [`philosophy.md`](./philosophy.md) |
| D4 | Write the resulting **payment back to Invoice** (idempotent), keeping Invoice the system of record | ADR 0010 |
| D5 | Record **partial payment**, **overpayment → client credit (前受金/預り金 相当)**, and **transfer-fee** handling with audit, no silent write-off | 会計実務 |
| D6 | Let the operator **direct how a deposit is appropriated** across multiple invoices (指定充当) | 民法 488–491（弁済の充当） |
| D7 | Send **operator-controlled** overdue reminders (督促) for the operator's **own** receivables, with logged history and a minimum interval | 弁護士法72条 self-collection boundary; ADR 0011 |
| D8 | Maintain an **immutable audit trail** (import, match, reverse, dunning, credit) and **7–10 year retention** | 法人税法/所得税法 帳簿保存; 電子帳簿保存法 |
| D9 | **Export CSV** of reconciliation/payment data for accounting software | Subledger handoff; ADR 0013 |
| D10 | Surface **aging / overdue** information for operator awareness | Operational; informational only (see X5) |

---

## DON'T — Clear must never do these

| # | Clear must NOT | Why (risk) | Belongs to |
| --- | --- | --- | --- |
| X1 | Issue quotes / invoices / qualified-invoice PDFs, or compute consumption tax | Two issuers of the same document = two truths; tax errors | **NeNe Invoice** (ADR 0009) |
| X2 | Hold **upstream (Invoice-sourced)** invoices, balances, or payments as its **own** source of truth | Competing balances → "which number is right?" at audit | **NeNe Invoice** (ADR 0010); narrowed by **ADR 0014** |
| X3 | Post journal entries (仕訳) or replace double-entry bookkeeping | Clear is a reconciliation subledger, not a ledger | Accounting software (ADR 0013) |
| X4 | **Determine bad debt (貸倒損失)** or write off a receivable as a tax event | 貸倒 is a tax judgment (法人税基本通達 9-6-1〜9-6-3) | Operator + 税理士 |
| X5 | Make **legal determinations** about prescription/time-bar (消滅時効, 売掛金 5年 — 民法166) | Misstating a legal status creates liability | Operator + 弁護士 |
| X6 | **Silently write off** a fee difference, overpayment, or shortfall | Hides money movement from the books | — (operator chooses, logged) |
| X7 | **Auto-appropriate** a payment across debts without operator choice | Contradicts 指定充当; can misstate which invoice is paid | Operator (民法 488–491) |
| X8 | **Auto-compute statutory/late interest (遅延損害金) and add it to the balance**, or present it as a legal demand by default | Wrong rate/basis = inaccurate claim; coercive tone risk | Optional, off by default + advisor (ADR 0011) |
| X9 | Act as a **third-party debt collector** or present itself/its messages as a collection agency or lawyer | 非弁行為 (弁護士法72条) / unlicensed servicer (サービサー法) | Licensed 弁護士 / 債権回収会社 only |
| X10 | Send dunning that is **threatening, coercive, or false** ("we will sue immediately") | Improper collection conduct; misrepresentation | — (prohibited) |
| X11 | Finalize a match **without human confirmation** (no silent auto-match in MVP) | Removes the control an auditor expects | — (ADR-gated exception only) |
| X12 | **Hard-delete** bank lines, matches, payments, or dunning records | Destroys evidence / audit trail | — (reversal/void only) |
| X13 | Mutate **imported bank data** in place | Breaks 真実性の確保 | — (reversal import batch only) |
| X14 | Share a database with Invoice or any sibling | Couples schemas, bypasses the contract | — (HTTP only, ADR 0002/0009) |
| X15 | Issue receipts (領収書) | 印紙税 and document issuance are not Clear's domain | Invoice / operator |

> **X2 carve-out ([ADR 0014](../adr/0014-accept-manual-receivables.md)):**
> *Manually-entered* receivables (`source = manual`) exist **only** in Clear — no
> NeNe Invoice record holds them — so there is no competing balance to drift
> against, and Clear **is** their system of record (it stores the figures and
> computes `outstanding_cents` / `status`). This narrows X2 to upstream
> receivables; it does **not** touch **X1** — a manual receivable is a
> reconciliation reference, and Clear still issues no invoice/PDF and computes no
> tax.

---

## Boundaries that are easy to get wrong (clarifications)

- **督促 (reminder) ≠ 取立 (collection).** Clear reminds the operator's own
  customers about the operator's own invoices. That is lawful self-collection.
  The moment a feature would collect **others'** debts for a fee, or dress the
  message as a collection agency, it crosses 弁護士法72条 / サービサー法 — and is
  out of scope (X9, X10).
- **Clear records dates and amounts; it does not judge tax.** `paid_at` (bank
  value date) is captured accurately for the advisor, but Clear takes no position
  on revenue recognition, consumption-tax basis, or 貸倒 (X4).
- **Overpayment is a liability, not revenue.** Excess over outstanding becomes a
  **client credit** in Clear and is **not** posted to the invoice (the Invoice
  API rejects over-allocation — see the contract). Applying it later is an
  explicit operator action.
- **"Mark uncollectible" is operational, not accounting.** An operator may pause
  dunning for an invoice (logged), but that has **no** accounting or tax effect
  and is **not** a 貸倒 determination.

---

## Definition of done (rules layer)

The rule set is "done enough to start coding" when:

- [ ] This contract, the compliance doc, and the Invoice upstream contract agree
      with each other (no conflicting statements).
- [ ] Every DON'T has either a statute/ADR citation or an explicit owner.
- [ ] A 税理士 / 公認会計士 has reviewed the reconciliation + retention + export
      rules; a 弁護士 (or the same advisor) has reviewed the dunning boundary.
- [ ] `terminology.md` registers every entity the rules reference.

---

## Related

- Domain split: [ADR 0009](../adr/0009-separate-from-nene-invoice.md)
- Binding detail: [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
- Invoice boundary: [`invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
- Payment SSOT: [ADR 0010](../adr/0010-payment-system-of-record.md)
- Dunning boundary: [ADR 0011](../adr/0011-dunning-self-collection-only.md)
- Electronic records: [ADR 0012](../adr/0012-electronic-records-bank-data.md)
- No journals / no bad-debt: [ADR 0013](../adr/0013-no-journal-entries-no-bad-debt.md)

Last updated: 2026-05-30
