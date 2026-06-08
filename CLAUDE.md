# CLAUDE.md — NeNe Clear

Private repo for **NeNe Clear** — **入金消込・督促管理 only**.

**Not** `nene-invoice`. Invoice = 見積・請求・入金管理. **Separate domain. Not upper compatible.**

Strategy: [publication-strategy `docs/products/nene-clear.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-clear.md).

## Hard rules

- Do **not** implement quotes, invoices, or PDF here → `nene-invoice`
- Do **not** describe Clear as Invoice successor or superset
- Do **not** edit `nene-invoice` unless explicitly asked for Invoice work
- **Follow NENE2 conventions** — code MUST comply with `docs/development/nene2-compliance.md` (binding); reuse framework objects, don't reinvent them
- **Terminology registry is binding** — every identifier (Problem Details slug, field name, status value, operationId) MUST be registered in `docs/explanation/terminology.md` **before first use**. Check the registry before introducing any new term. Spelling MUST match exactly — same characters, same case, same separators. No synonyms, no abbreviations, no typos.
- No direct commits to `main`; Issue required
- Closing **multiple** issues from one PR: repeat the keyword for each — `Closes #a, Closes #b, Closes #c`. A single `Closes #a, #b, #c` only closes the first; the rest stay open
- Repository docs: English only (ADR 0008)

## Local development ports

All ports are fixed for this project to avoid collisions with sibling apps.

| Service | Host port | Notes |
| --- | --- | --- |
| MySQL | **3383** | `docker-compose.yml` |
| Mailpit SMTP | **1383** | `docker-compose.yml`; set `SMTP_HOST=127.0.0.1 SMTP_PORT=1383` |
| Mailpit Web UI | **8383** | `http://localhost:8383` |
| PHP backend | **8384** | `NENE_CLEAR_PORT=8384` in `.env` |
| Vite dev server | **5383** | `NENE_CLEAR_FRONTEND_PORT=5383` in `.env` |

Do **not** change these back to defaults (8080, 1025, 8025, 3306).

## Canonical paths

| Purpose | Path |
| --- | --- |
| Domain split | `docs/adr/0009-separate-from-nene-invoice.md` |
| NENE2 conventions (binding) | `docs/development/nene2-compliance.md` |
| Scope contract (GOAL/DO/DON'T) | `docs/explanation/scope-contract.md` |
| Scope boundary | `docs/explanation/scope-boundary.md` |
| Compliance | `docs/explanation/payment-reconciliation-dunning-compliance.md` |
| Invoice upstream contract | `docs/integrations/invoice-upstream-contract.md` |
| Invoice upstream (overview) | `docs/integrations/sibling-products.md` |
| Terminology registry (binding) | `docs/explanation/terminology.md` |
| Operator guide / system overview | `docs/explanation/operator-guide.md` |
| TODO | `docs/todo/current.md` |
