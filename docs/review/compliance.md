# Accounting & Tax Compliance Self-Review

**Binding.** Use for **any** change touching quotes, invoices, payments, tax
calculation, document numbering, PDF rendering, or record retention. If unsure
whether a change has compliance impact, assume it does and run this list.

Source of truth: [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md).
Do not delete items to pass. Mark `N/A` only when genuinely not applicable.

## Checklist

- [ ] Change reviewed against `docs/explanation/accounting-compliance.md`; compliance impact stated in the PR.
- [ ] Qualified invoice required fields enforced before issuance (issuer name/address/`T`+13 registration number/date, per-rate taxable amount, per-rate consumption tax, total, buyer name).
- [ ] Reduced-rate (軽減税率 8%) items clearly marked.
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
