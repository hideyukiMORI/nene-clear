# Accounting & Tax Compliance Self-Review

**Binding.** Use for **any** change touching quotes, invoices, payments, tax
calculation, document numbering, PDF rendering, record retention, bank import,
payment reconciliation, or dunning. If unsure whether a change has compliance
impact, assume it does and run this list.

Source of truth:

- [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md)
- [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md)

Do not delete items to pass. Mark `N/A` only when genuinely not applicable.

## Checklist — invoices & tax

- [ ] Change reviewed against binding compliance docs; compliance impact stated in the PR.
- [ ] Qualified invoice required fields enforced before issuance (issuer name/address/`T`+13 registration number/date, per-rate taxable amount, per-rate consumption tax, total, buyer name).
- [ ] Reduced-rate (8%) items clearly marked.
- [ ] Consumption tax rounded **once per tax rate per document**, half-up — never per line (ADR 0004).
- [ ] Allowed tax rates only (10% / 8%); any rate change carries an ADR.
- [ ] Registration number treated as **syntax-only** validation; no UI/doc implies it proves existence/validity.
- [ ] Issued documents are immutable; corrections via credit note, not edit/delete.
- [ ] Document numbering sequential; no silent gap, reuse, or hard delete that hides a voided document.
- [ ] No hard delete of billing records (soft delete / void only).
- [ ] Issued copies retained and tamper-evident; no auto-purge before the statutory period (7y, up to 10y).
- [ ] All money is integer minimum currency units; no float/DECIMAL in DB, JSON, or tests.
- [ ] Monetary/tax figures computed once in the UseCase; PDF/API/stored copy do not recalculate independently.
- [ ] Audit trail recorded for issuance and payment (Phase 2+).
- [ ] Any deviation from the binding rules carries an ADR with tax/accounting professional sign-off.

## Checklist — reconciliation & dunning (Expansion #1+)

- [ ] `paid_at` uses bank value date when matched from import (unless documented override).
- [ ] Partial payments update `partially_paid` / `paid` correctly; no over-allocation without overpayment flow.
- [ ] Transfer-fee mismatch handled per compliance doc §2.4 — no silent write-off.
- [ ] Overpayment creates `client_credit`; excess not discarded.
- [ ] Multi-invoice allocation sums to bank transaction amount.
- [ ] Match reversal creates audit record; no hard delete of payment/bank history.
- [ ] Human confirmation required before match is final (unless ADR exception).
- [ ] Bank import batch stores file hash, actor, timestamp; imported lines immutable.
- [ ] Dunning only for issued/partially_paid/overdue with outstanding > 0.
- [ ] Dunning send logged; minimum interval enforced for scheduled sends.
- [ ] Default dunning templates: no threats, no auto statutory interest on balance.
- [ ] CSV export columns suitable for advisor handoff (document in PR if changed).
