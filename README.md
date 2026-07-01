# NeNe Clear

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)
[![Public](https://img.shields.io/badge/status-public-brightgreen)]()

**Payment reconciliation and dunning — self-hosted for Japan SMB.**

**NeNe Clear** is a **separate product** from [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice). It does **not** issue quotes or invoices. It **clears bank deposits against billed receivables** and **sends professional overdue reminders** — on [NENE2](https://github.com/hideyukiMORI/NENE2), shared hosting or Docker. Receivables come from **NeNe Invoice (optional upstream)** or are **entered / CSV-imported directly** ([manual receivables, ADR 0014](./docs/adr/0014-accept-manual-receivables.md)), so Clear runs **standalone** — no NeNe Invoice required.

> **Not upper compatible with `nene-invoice`.** Different domain, different repo, different database. See [ADR 0009](./docs/adr/0009-separate-from-nene-invoice.md).

## Domain split (binding)

| Product | Repository | What it does |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` | Quote, invoice, payment management — 見積・請求・入金管理 |
| **NeNe Clear** | `nene-clear` (this) | Payment reconciliation & dunning — 入金消込・督促管理 |

Operators who need both install **two sibling apps** connected via HTTP.

## Goals

- **Bank reconciliation** — CSV import, human-confirmed match, audit trail
- **Dunning** — operator-controlled overdue reminders with send history
- **Compliance** — binding rules for reconciliation and dunning ([compliance doc](./docs/explanation/payment-reconciliation-dunning-compliance.md))
- **Self-hosted OSS** — MIT; recommended on **VPS + Docker** (Tier B). Shared hosting is **not recommended** for receivables/bank/PII data (no root, throttled SMTP, limited DKIM & backups); a managed / install-service option is planned
- **AI-readable** — OpenAPI + MCP; human confirms, AI proposes
- **Optional upstream: NeNe Invoice** — when connected, invoice/payment truth via HTTP API (not a shared DB); without it, Clear reconciles and duns directly-entered / CSV-imported receivables ([ADR 0014](./docs/adr/0014-accept-manual-receivables.md))

## Documentation

| Topic | Document |
| --- | --- |
| **Domain boundary (read first)** | [`docs/adr/0009-separate-from-nene-invoice.md`](./docs/adr/0009-separate-from-nene-invoice.md) |
| **Scope contract (GOAL / DO / DON'T)** | [`docs/explanation/scope-contract.md`](./docs/explanation/scope-contract.md) |
| **Invoice upstream contract** | [`docs/integrations/invoice-upstream-contract.md`](./docs/integrations/invoice-upstream-contract.md) |
| **Philosophy** | [`docs/explanation/philosophy.md`](./docs/explanation/philosophy.md) |
| **Product vision** | [`docs/explanation/product-vision.md`](./docs/explanation/product-vision.md) |
| **Requirements** | [`docs/explanation/requirements.md`](./docs/explanation/requirements.md) |
| **Reconciliation & dunning (binding)** | [`docs/explanation/payment-reconciliation-dunning-compliance.md`](./docs/explanation/payment-reconciliation-dunning-compliance.md) |
| **Invoice upstream** | [`docs/integrations/sibling-products.md`](./docs/integrations/sibling-products.md) |
| **Agents** | [`AGENTS.md`](./AGENTS.md) |
| **Roadmap** | [`docs/roadmap.md`](./docs/roadmap.md) |

## Status

**Phase 1 (Reconciliation API) and Phase 2 (Admin UI + Dunning) complete.**

- Multi-tenant JWT/RBAC, bank CSV import, reconciliation (propose / confirm /
  reverse), client credit, dunning, immutable audit trail — all live.
- React + TypeScript admin UI (`frontend/`), Japanese + English.
- Invoice upstream HTTP client + contract tests (activate by setting
  `NENE_INVOICE_API_BASE_URL` / `NENE_INVOICE_BEARER_TOKEN`).
- Tests: 336 backend (PHPUnit, 6 skipped; PHPStan level 8), 46 frontend (Vitest),
  43 browser E2E (Playwright). CI runs all three on every push/PR.
- Login throttling, optional **TOTP two-factor auth** (per-user enrolment +
  recovery codes; API-complete, enrolment UI in progress), and
  **encryption-at-rest** for the bank account number (libsodium, opt-in key);
  security assessment in
  [`docs/security/assessment-2026-05.md`](./docs/security/assessment-2026-05.md).

Next: Phase 3 (distribution — managed / install-service on VPS + Docker) and
Phase 4 (MCP tools, accounting-software CSV export). See [`docs/roadmap.md`](./docs/roadmap.md).

**Billing documents (見積・請求・入金):** [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice) — not this repo.

## Quickstart (local development)

```bash
# 1. Infrastructure (MySQL 8.4 on :3383, Mailpit SMTP :1383 / web UI :8383)
docker compose up -d
cp .env.example .env            # adjust DB_*, NENE_CLEAR_JWT_SECRET as needed

# 2. Backend (PHP 8.4 with ext-curl — required by the Invoice upstream client).
composer install
composer migrations:migrate     # apply schema

# First run only: create the first organization + admin so you can log in
# (prompts for a password; or pass --password / ADMIN_PASSWORD). See --help.
php tools/create-admin.php --org-name "My Company" --email admin@example.com

php -S localhost:8384 -t public_html/    # or your preferred SAPI (NENE_CLEAR_PORT=8384)

# 3. Frontend admin UI (Node 22)
npm --prefix frontend install
npm --prefix frontend run dev   # Vite dev server on :5383, proxies /admin → backend

# Quality gates
composer check                  # PHPUnit + PHPStan level 8 + PHP-CS-Fixer
npm --prefix frontend run check # type-check + lint + Vitest
( cd tests/e2e && npm install && npx playwright test )   # browser E2E
```

`DB_ADAPTER=sqlite` (the `.env.example` default) needs no container. For a
server-grade database, set `DB_ADAPTER=mysql` (`docker compose up -d mysql`,
`DB_PORT=3383`) or `DB_ADAPTER=pgsql` (`docker compose up -d postgres`,
`DB_PORT=5483`); `DB_CHARSET` is ignored for pgsql. All three share one schema —
run `composer migrations:migrate` after switching. The PHP `pdo_pgsql` extension
is required for the pgsql adapter.

## Invoice integration (optional)

Reconciliation and dunning against **NeNe Invoice** receivables are activated by
two env vars; without them Clear runs standalone (manual receivables only,
ADR 0014). `ext-curl` is required for the HTTP client.

```bash
# In .env (or the environment):
NENE_INVOICE_API_BASE_URL=https://invoice.example.com
NENE_INVOICE_BEARER_TOKEN=<service token>
```

Obtain the service token from the Invoice side (`nene-invoice` →
`tools/issue-service-token.php`). With both set, the contract suite runs against
the live Invoice API:

```bash
NENE_INVOICE_API_BASE_URL=… NENE_INVOICE_BEARER_TOKEN=… vendor/bin/phpunit --filter InvoiceUpstreamContractTest
```

See [`docs/integrations/invoice-upstream-contract.md`](./docs/integrations/invoice-upstream-contract.md).

## Ecosystem

```
NENE2 (framework)
  ├── NeNe Records    (CMS)
  ├── NeNe Corpus     (knowledge chat)
  ├── NeNe Concierge  (scenario chat)
  ├── NeNe Invoice    (quote · invoice · payment)     ← billing documents
  └── NeNe Clear      (reconciliation · dunning)    ← this repo
```

## License

MIT — see [LICENSE](./LICENSE).
