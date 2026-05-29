# CLAUDE.md — NeNe Clear

Private repo for **NeNe Clear** — **入金消込・督促管理 only**.

**Not** `nene-invoice`. Invoice = 見積・請求・入金管理. **Separate domain. Not upper compatible.**

Strategy: [publication-strategy `docs/products/nene-clear.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-clear.md).

## Hard rules

- Do **not** implement quotes, invoices, or PDF here → `nene-invoice`
- Do **not** describe Clear as Invoice successor or superset
- Do **not** edit `nene-invoice` unless explicitly asked for Invoice work
- No direct commits to `main`; Issue required
- Repository docs: English only (ADR 0008)

## Canonical paths

| Purpose | Path |
| --- | --- |
| Domain split | `docs/adr/0009-separate-from-nene-invoice.md` |
| Scope boundary | `docs/explanation/scope-boundary.md` |
| Compliance | `docs/explanation/payment-reconciliation-dunning-compliance.md` |
| Invoice upstream | `docs/integrations/sibling-products.md` |
| TODO | `docs/todo/current.md` |
