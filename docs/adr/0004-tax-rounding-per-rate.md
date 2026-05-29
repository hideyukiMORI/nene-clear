# ADR 0004: Consumption Tax Rounding Once Per Rate Per Document

## Status

accepted

## Context

NeNe Clear calculates consumption tax for quotes and invoices. The early
domain model draft (`docs/explanation/domain-model.md`) rounded tax **per line
item** and then summed the rounded line amounts into the document tax total.

Under the Japan qualified invoice system (適格請求書等保存方式), consumption tax
rounding is constrained: for **one qualified invoice**, the tax amount may be
rounded **once per tax rate** (税率ごとに1回). Rounding each line item and then
summing is **not** permitted, because it can round more than once per rate
within a single document and produce a tax total that does not match the
rate-grouped statutory figure.

The rounding direction itself (round half-up / floor / ceil) is the issuer's
choice, but it must be applied at the rate-group level, not per line, and stay
consistent across a document.

Alternatives considered:

1. **Round per line item, then sum** — rejected; rounds more than once per rate
   and is non-compliant with the qualified invoice rule.
2. **No rounding until the grand total** — rejected; the qualified invoice
   requires the consumption tax **per tax rate category** (税率ごとの消費税額)
   to be a displayed, rounded figure, so rounding must happen at the rate group.
3. **Round once per tax rate per document** (chosen) — compliant and matches the
   rate-category figures the PDF must render.

## Decision

Compute consumption tax by grouping line items by `tax_rate_bps`, summing the
taxable amount per rate, and rounding **once per rate** to integer minimum
currency units. Do not round individual line item tax amounts.

```
# Group line items by tax_rate_bps:
for each rate group:
    taxable_amount_cents[rate] = sum(quantity * unit_price_cents) in that group
    tax_cents[rate]            = round(taxable_amount_cents[rate] * rate / 10000)   # ROUND ONCE here

subtotal_cents = sum(taxable_amount_cents[rate] for all rates)
tax_cents      = sum(tax_cents[rate] for all rates)
total_cents    = subtotal_cents + tax_cents
```

- Rounding direction: **half-up** to integer minimum currency units, applied per
  rate group. A future ADR may make the direction configurable per issuer if a
  real operator requires floor/ceil; until then half-up is the single rule.
- This calculation is the **single source of truth** in the UseCase layer. The
  PDF and API responses both render these exact values; neither recalculates.
- `tax_rate_bps` is in basis points (1000 = 10%, 800 = 8% reduced). See
  `docs/explanation/glossary.md`.

## Consequences

**Benefits**

- Compliant with the qualified invoice "round once per tax rate per document"
  rule.
- The per-rate `tax_cents[rate]` figures are exactly the
  税率ごとの消費税額 the qualified invoice PDF must display.
- API and PDF totals are guaranteed equal — one calculation, no per-line drift.

**Costs**

- Line items no longer carry an independently rounded tax amount; any per-line
  tax shown in the UI is illustrative and must be labeled as such, not summed.
- Changing the rounding direction later is an issuer-visible behavior change and
  needs its own ADR.

**Follow-up**

- Implement in the `Quote` / `Invoice` tax calculation UseCase (Phase 1) with
  unit tests covering mixed 10% / 8% documents and half-up boundary cases.

## Related

- Domain model: `docs/explanation/domain-model.md`
- Requirements (qualified invoice fields): `docs/explanation/requirements.md`
- Backend standards (money and tax): `docs/development/backend-standards.md`
- Issue: `#9`
- Supersedes: none
- Superseded by: none
