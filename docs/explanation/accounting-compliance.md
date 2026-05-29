# Accounting & Records Compliance — Binding Rules

> **Scope (ADR 0009):** NeNe Clear owns **payment reconciliation and dunning**.
> Quote, invoice, qualified-invoice content, consumption-tax calculation, and
> per-rate tax rounding belong to
> [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice) — they are
> **not** implemented or governed here. The previous §1–8 of this file (qualified
> invoice fields, tax calculation, document numbering, PDF retention) were
> bootstrap residue and have been removed; that compliance now lives in the
> Invoice repository.
>
> **Authoritative for Clear:**
> [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md).
> This file holds only the **cross-cutting principles** (money representation,
> electronic-records retention, audit trail) that the reconciliation/dunning
> rules build on.

**Status: binding (non-negotiable)** within Clear's domain.

These are not guidelines. They are **MUST** requirements. Where a rule here
conflicts with UX, performance, implementation convenience, or any other
concern, **compliance wins** — every time, without exception.

See also: [`requirements.md`](./requirements.md), [`domain-model.md`](./domain-model.md),
self-review checklist [`../review/compliance.md`](../review/compliance.md).

---

## 0. Governing principle

1. **Compliance is non-negotiable.** Correct adherence to the law takes
   precedence over every other product goal.
2. **No silent deviation.** Any departure from the rules in this document or in
   [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
   — even temporary — requires an **ADR** and **explicit review sign-off by a
   licensed tax accountant / 税理士** recorded in that ADR. Code may not merge a
   deviation without it.
3. **Engineering is not the legal authority.** This document is engineering's
   binding interpretation of the rules. When a requirement is unclear, **stop
   and consult a licensed tax accountant (税理士)** — do not guess. Record the
   resolved interpretation here.
4. **Invoice figures are upstream truth.** Clear does **not** compute invoice
   totals or consumption tax. It reads `total_cents` / `outstanding_cents` from
   the Invoice upstream and computes only **allocations** of known bank amounts.
   Allocation math is done once in the UseCase layer; API responses and stored
   rows render the same values without recomputation.

---

## 1. Statutory basis (Clear scope)

NeNe Clear targets the following Japanese rules **as they apply to bank
evidence, reconciliation records, and payment reminders**. This list states
*what we design for*; it is not legal advice.

| Area | Rule set | Clear role |
| --- | --- | --- |
| Bookkeeping & evidence | Corporation Tax Act bookkeeping duty; Civil Code performance of obligations | Preserve tamper-evident bank, match, and dunning history |
| Electronic records | Act on Electronic Books and Records Preservation (電子帳簿保存法) | Retain imported bank data and reconciliation audit trail with searchability |
| Personal data | Act on the Protection of Personal Information (個人情報保護法) | Use client contact data for dunning only as the operator instructs; log sends |
| Late payment | Civil Code; Interest Rate Act (法定利率) | Reminders state facts; no automated legal threats or coercive language |

Consumption-tax filing, qualified-invoice content, and revenue recognition are
**out of scope** (operator + tax advisor + `nene-invoice`). When any in-scope
rule changes, treat it as a compliance defect until updated, and open a P0 Issue.

---

## 2. Money representation

- All amounts are stored and transmitted as **integer minimum currency units**
  (`*_cents`; for JPY, ¥1 = 1 unit). **Float and DECIMAL for money are
  prohibited** in DB, API JSON, and tests.
- Phase 1–3 currency is **JPY only**.
- Clear never alters upstream invoice figures; it records bank amounts,
  allocations, client-credit balances, and the outstanding-at-send snapshot.

---

## 3. Retention of bank and reconciliation records

- Imported bank lines, reconciliation links, client-credit history, and dunning
  send records are retained for a **minimum of 7 years**, up to **10 years**
  where applicable (e.g. loss-carryforward). The product **MUST NOT** auto-purge
  these before the statutory period; operators are warned before any destructive
  retention action.
- Retained records are **tamper-evident**: a stored `bank_transaction`,
  `payment_reconciliation`, or `dunning_notice` **MUST NOT** be silently mutated.
  Corrections use reversal records / reversal import batches, never in-place edits
  (see reconciliation compliance §2.7, §3.1).
- **Searchability:** list/filter by date, amount, match status, client, and
  invoice number.

---

## 4. Audit trail

Every state-changing action records an immutable `audit_event` (who / when /
what). Minimum fields per event are defined in
[`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md) §6:

| Event | Audited |
| --- | --- |
| Bank import batch | batch id, file hash, row count, actor, timestamp |
| Match confirmed | bank transaction id(s), payment id(s), invoice id(s), amounts, actor, timestamp |
| Match reversed | reversal id, prior match id, reason, actor, timestamp |
| Dunning sent | invoice id, outstanding at send, recipient, template version, actor, timestamp |
| Client credit created | client id, amount, source transaction, actor, timestamp |

Audit records follow the same no-silent-mutation rule as §3.

---

## 5. Reconciliation & dunning (core domain)

Bank import, payment matching, client credit, and dunning are NeNe Clear's
**core domain**. The binding rules — payment date sourcing, partial/overpayment/
transfer-fee handling, import integrity, dunning eligibility and template
boundaries, human confirmation — live in
[`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
and apply with the same force as the principles above.

---

## 6. How this rule applies to every change

Any change that touches bank import, reconciliation, payment allocation, client
credit, dunning, retention, or audit **MUST**:

1. Be reviewed against this document and [`../review/compliance.md`](../review/compliance.md).
2. State compliance impact in the PR.
3. If it deviates from any rule here or in the reconciliation/dunning compliance
   doc, carry an ADR with professional sign-off (§0.2). No exceptions.

If you are unsure whether a change has compliance impact, **assume it does** and
run the checklist.
