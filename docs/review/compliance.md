# Reconciliation, Dunning & Records Compliance Self-Review

**Binding.** Use for **any** change touching bank import, payment
reconciliation, allocation, client credit, dunning, record retention, or audit.
If unsure whether a change has compliance impact, assume it does and run this
list.

> **Scope (ADR 0009):** quote/invoice/qualified-invoice/tax compliance belongs
> to [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice). Invoices are
> read via the upstream API only; Clear never issues documents or computes tax.

Source of truth:

- [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) (authoritative)
- [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md) (cross-cutting principles)

Do not delete items to pass. Mark `N/A` only when genuinely not applicable.

## Checklist — money, records & audit

- [ ] Change reviewed against binding compliance docs; compliance impact stated in the PR.
- [ ] No quote/invoice/line-item/PDF/tax logic added to Clear (ADR 0009); invoice figures read from upstream, never recomputed.
- [ ] All money is integer minimum currency units (`*_cents`); no float/DECIMAL in DB, JSON, or tests.
- [ ] Allocation math computed once in the UseCase; API/stored rows do not recompute independently.
- [ ] Imported bank lines immutable; corrections via reversal import batch, not in-place edit.
- [ ] Import batch stores `file_hash` (SHA-256), `source_filename`, actor, timestamp; duplicate hash/line key warns or blocks.
- [ ] Bank/match/credit/dunning records retained, tamper-evident, searchable; no auto-purge before the statutory period (7y, up to 10y).
- [ ] Audit event recorded for import, match confirm, match reverse, dunning send, and client-credit creation.
- [ ] Any deviation from the binding rules carries an ADR with tax/accounting professional (税理士 / 公認会計士) sign-off.

## Checklist — reconciliation & dunning

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
