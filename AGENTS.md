# Agent / AI Guide

Entry point for AI agents working on **NeNe Clear** (public repo `nene-clear`).

## Domain split (read first)

| Product | Repository | Domain |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` | Quote, invoice, payment management |
| **NeNe Clear** | `nene-clear` (this) | Payment reconciliation & dunning |

**Not upper compatible.** See [ADR 0009](docs/adr/0009-separate-from-nene-invoice.md).

## Read First

- **Domain boundary:** `docs/adr/0009-separate-from-nene-invoice.md`
- **Portfolio strategy:** [publication-strategy `docs/products/nene-clear.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-clear.md)
- **Philosophy:** `docs/explanation/philosophy.md`
- **Product vision:** `docs/explanation/product-vision.md`
- **Scope vs Invoice:** `docs/explanation/scope-boundary.md`
- **Requirements:** `docs/explanation/requirements.md`
- **Reconciliation & dunning (binding):** `docs/explanation/payment-reconciliation-dunning-compliance.md`
- **NENE2 coding conventions (binding):** `docs/development/nene2-compliance.md`
- **Invoice upstream:** `docs/integrations/sibling-products.md`
- **Current work:** `docs/todo/current.md`
- **Roadmap:** `docs/roadmap.md`

## Operating Rules

- Issue-driven; no direct commits to `main`
- Do **not** add quote/invoice/PDF features here — **`nene-invoice` owns billing documents**
- Do **not** describe Clear as replacing or superseding Invoice
- **Follow NENE2 conventions** — comply with `docs/development/nene2-compliance.md`; reuse framework objects (`JsonResponseFactory`, `Router`, `PaginationQuery`, `BearerTokenMiddleware`, `DatabaseQueryExecutorInterface`, …), don't reinvent them
- Namespace: `NeneClear\`; money: integer cents

## Framework

[NENE2](https://github.com/hideyukiMORI/NENE2) — wired via Composer as a local
path dependency (`../NENE2`, `hideyukimori/nene2: @dev`). Runtime is live: PSR-15
HTTP stack, DI via per-domain `ServiceProvider` + `ServiceResolver`, PHP 8.4.
