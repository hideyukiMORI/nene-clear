# Accounting & Tax Compliance — Binding Rules

> **Scope (ADR 0009):** NeNe Clear owns **reconciliation and dunning** only.
> **Quote, invoice, and qualified invoice compliance belong in
> [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice).** Sections 1–8
> below are **bootstrap residue** retained for reference until moved to Invoice;
> **§9–10 and [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
> are authoritative for Clear.**

**Status: binding (non-negotiable)** where applicable to Clear's domain.

These are not guidelines. They are **MUST** requirements. Where a rule here
conflicts with UX, performance, implementation convenience, or any other
concern, **compliance wins** — every time, without exception.

See also: [`requirements.md`](./requirements.md), [`domain-model.md`](./domain-model.md),
[ADR 0004](../adr/0004-tax-rounding-per-rate.md), self-review checklist
[`../review/compliance.md`](../review/compliance.md).

---

## 0. Governing principle

1. **Compliance is non-negotiable.** Correct adherence to the law takes
   precedence over every other product goal.
2. **No silent deviation.** Any departure from the rules in this document — even
   temporary — requires an **ADR** and **explicit review sign-off by a tax/
   accounting professional (licensed tax accountant / 税理士)** recorded in that ADR.
   Code may not merge a deviation without it.
3. **Engineering is not the legal authority.** This document is engineering's
   binding interpretation of the rules. When a requirement is unclear, **stop
   and consult a licensed tax accountant (税理士)** — do not guess. Record the
   resolved interpretation here.
4. **Single source of truth for figures.** Every monetary and tax figure is
   computed once in the UseCase layer. The PDF, API, and stored copy render the
   exact same values; no layer recalculates independently.

---

## 1. Statutory basis

NeNe Clear targets the following Japanese rules. This list states *what we
comply with*; it is not legal advice.

| Area | Rule set |
| --- | --- |
| Consumption tax | Consumption Tax Act (standard 10% / reduced 8%) |
| Qualified invoice | Qualified Invoice System (適格請求書等保存方式) |
| Stored records | Act on Electronic Books and Records Preservation |

When any of these change (rate changes, new statutory fields, retention rule
changes), treat it as a compliance defect until the product is updated, and open
a P0 Issue.

---

## 2. Qualified invoice — mandatory content

When `is_qualified_invoice = true`, the system **MUST** enforce and render **all**
fields required under the Qualified Invoice System. Missing any required field
**MUST** block issuance. PDF rendering uses **Japanese statutory labels** on
the document; this section describes the data rules in English.

### Issuer (supplier)
- Legal name or trade name
- Address
- **Registration number**, format `T` + 13 digits — see §4
- Transaction date and issue date

### Buyer (recipient)
- Name
- Address when applicable

### Transaction details
- Description per line; reduced-rate (8%) items **MUST** be clearly marked
- **Taxable amount per tax rate** (aggregated per rate category)
- **Consumption tax amount per tax rate**
- Total billed amount

These figures are statutory. They are not cosmetic PDF fields — they are
validated in the UseCase before any document is issued or rendered.

---

## 3. Consumption tax calculation

- Allowed rates: **10% (1000 bps)** and **8% reduced (800 bps)**. Adding or
  changing a rate is a compliance change → requires an ADR.
- **Rounding: once per tax rate per document**, never per line item.
  Direction: half-up. This is binding — see
  [ADR 0004](../adr/0004-tax-rounding-per-rate.md). Per-line rounding is
  **prohibited** because it can round more than once per rate within one
  document.
- The per-rate consumption tax figure produced here is exactly the amount the
  qualified invoice must display per rate category.

---

## 4. Registration number

- Format check: `^T[0-9]{13}$`. This is **syntax only** — it does **not** prove
  the number exists or is registered, and the system does **not** perform a
  check-digit or registry lookup. UI and docs **MUST NOT** present a format pass
  as proof of validity.
- An invoice **MUST NOT** be markable as qualified while the issuer registration
  number is empty.

---

## 5. Document integrity, numbering, and immutability

- **Issued documents are immutable.** Once an invoice is `issued`, its line
  items, tax figures, totals, dates, and number **MUST NOT** be edited or
  deleted. Corrections are made by issuing a **credit note / corrected qualified
  invoice** (Phase 4+), never by mutating or removing the original.
- **Sequential numbering with no compliance-breaking gaps.** Quote and invoice
  numbers are assigned in sequence per organization and year. The system **MUST
  NOT** silently reuse, back-fill, or delete numbers in a way that hides a
  voided document. A voided document is recorded as voided, not erased.
- **No hard delete of billing records.** Quotes, invoices, payments, and issued
  PDFs use soft delete / void semantics; financial history is preserved.

---

## 6. Retention of issued copies

- The system **MUST** retain a copy of every **issued** qualified invoice (and
  the figures used to produce it).
- Retained copies are **tamper-evident**: a stored issued document **MUST NOT**
  be silently mutated. Reissuing produces a new versioned record, not an
  in-place overwrite.
- Statutory retention is **7 years** (and may extend to **10 years** in certain
  loss-carryforward situations). The product **MUST NOT** auto-purge issued
  billing records before the statutory period. Operators are warned before any
  destructive retention action.

---

## 7. Money representation

- All amounts are stored and transmitted as **integer minimum currency units**
  (`*_cents`; for JPY, ¥1 = 1 unit). **Float and DECIMAL for money are
  prohibited** in DB, API JSON, and tests.
- Phase 1–3 currency is **JPY only**.

---

## 8. Audit trail

- Invoice **issuance** and **payment recording** are auditable events
  (who / when / what), from Phase 2.
- Audit records follow the same no-silent-mutation rule as §6.

---

## 9. Payment reconciliation and dunning (core product)

Bank import, payment matching, and dunning are **NeNe Clear's core domain** —
not a post-MVP expansion. All rules in
[`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
apply with the same force as sections 1–8 above.

Summary (non-exhaustive — the linked document is authoritative):

- **`paid_at` MUST reflect bank value date** when reconciled from import.
- **Partial payments, overpayments, and transfer-fee mismatches** MUST follow
  documented allocation rules; no silent write-offs.
- **Imported bank lines and match history** MUST be immutable, retained, and
  searchable per the Act on Electronic Books and Records Preservation.
- **Dunning** is operator-controlled professional reminder only; default
  templates MUST NOT threaten legal action or auto-compute statutory interest
  claims on outstanding balance.
- **Human confirmation** MUST finalize reconciliation unless a future ADR
  defines a narrow auto-match exception with professional sign-off.

---

## 10. How this rule applies to every change

Any change that touches quotes, invoices, payments, tax calculation, document
numbering, PDF rendering, retention, bank import, reconciliation, or dunning
**MUST**:

1. Be reviewed against this document and [`../review/compliance.md`](../review/compliance.md).
2. State compliance impact in the PR.
3. If it deviates from any rule here, carry an ADR with professional sign-off
   (§0.2). No exceptions.

If you are unsure whether a change has compliance impact, **assume it does** and
run the checklist.
