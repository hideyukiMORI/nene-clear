# ADR 0004: Consumption Tax Rounding Once Per Rate Per Document

## Status

superseded by [ADR 0009](./0009-separate-from-nene-invoice.md) — **relocated to
`nene-invoice`**. Not applicable to NeNe Clear.

## Context

This ADR was written while NeNe Clear's bootstrap docs still described quote and
invoice issuance. It specified that consumption tax for a qualified invoice must
be rounded **once per tax rate per document** (税率ごとに1回), never per line
item.

[ADR 0009](./0009-separate-from-nene-invoice.md) split the domains: **quote,
invoice, qualified-invoice content, and consumption-tax calculation belong to
[`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice)**, not Clear.
NeNe Clear performs **payment reconciliation and dunning only**; it never
computes invoice tax or totals — it reads `total_cents` / `outstanding_cents`
from the Invoice upstream and allocates known bank amounts against them.

## Decision

- The per-rate tax-rounding rule is **out of scope for NeNe Clear** and is no
  longer binding in this repository.
- The decision itself remains valid **for `nene-invoice`**, which owns tax
  calculation. Maintain and evolve it there.
- No tax-rounding logic may be implemented in NeNe Clear.

## Consequences

- Clear's domain model, compliance, and code carry **no consumption-tax
  calculation** (see [`../explanation/domain-model.md`](../explanation/domain-model.md)
  "Allocation logic" and [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md) §0.4).
- This ADR number is retained as a historical record; the live rule lives in the
  Invoice repository.

## Related

- ADR 0009: Separate domain from nene-invoice
- Accounting & records compliance (Clear scope): [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md)
- Issue: `#9`
- Superseded by: ADR 0009
