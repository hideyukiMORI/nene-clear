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

- [ ] Invoice remains the payment/outstanding system of record; Clear writes back via the upstream contract (idempotency key + `external_reference`) — no local payment SSOT (ADR 0010).
- [ ] `paid_at` uses bank value date when matched from import (unless documented override).
- [ ] Partial payments update `partially_paid` / `paid` correctly; no over-allocation without overpayment flow.
- [ ] Transfer-fee mismatch handled per compliance doc §2.4 — no silent write-off.
- [ ] Overpayment creates `client_credit`; excess not discarded (Invoice rejects over-allocation).
- [ ] Multi-invoice allocation sums to bank transaction amount; appropriation is operator-directed, not silent auto (§2.9, 民法488–491).
- [ ] Match reversal creates audit record; no hard delete of payment/bank history.
- [ ] Human confirmation required before match is final (unless ADR exception).
- [ ] Bank import immutable; corrections via reversal batch with audit history (真実性の確保, ADR 0012); search by date/amount/counterparty available (§3.3).
- [ ] No journal entries posted; no 貸倒 determination — pause/mark is operational only (ADR 0013, §7).
- [ ] Aging is informational only; no prescription/time-bar (消滅時効) determination (§8).
- [ ] Dunning only for issued/partially_paid/overdue with outstanding > 0.
- [ ] Dunning is self-collection of the operator's own receivables; no third-party 取立代行, no agency/lawyer impersonation (弁護士法72条, §4.8, ADR 0011).
- [ ] Dunning send logged; minimum interval enforced; send reflects latest reconciliation state (no reminding a paid invoice).
- [ ] Default dunning templates: no threats/false claims, no auto statutory interest on balance (§4.4, §4.5).
- [ ] CSV export columns suitable for advisor handoff (document in PR if changed).
- [ ] Professional review gate (§9) satisfied for the milestone: 税理士/公認会計士 (reconciliation/retention/export), 弁護士 (dunning) — sign-off recorded.
