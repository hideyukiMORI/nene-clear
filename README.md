# NeNe Clear

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)
[![Private](https://img.shields.io/badge/status-private-red)]()

**Payment reconciliation and dunning — self-hosted for Japan SMB.**

**NeNe Clear** is a **separate product** from [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice). It does **not** issue quotes or invoices. It **clears bank deposits against billed receivables** and **sends professional overdue reminders** — on [NENE2](https://github.com/hideyukiMORI/NENE2), shared hosting or Docker.

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
- **Self-hosted OSS** — MIT; Tier A shared hosting or Tier B Docker/VPS
- **AI-readable** — OpenAPI + MCP; human confirms, AI proposes
- **Upstream: NeNe Invoice** — invoice and payment truth via HTTP API, not shared DB

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
- Tests: 236 backend (PHPUnit, 6 skipped; PHPStan level 8), 27 frontend (Vitest),
  43 browser E2E (Playwright). CI runs all three on every push/PR.
- Login throttling; security assessment in
  [`docs/security/assessment-2026-05.md`](./docs/security/assessment-2026-05.md).

Next: Phase 3 (Tier A shared-hosting installer / release ZIP) and Phase 4
(MCP tools, accounting-software CSV export). See [`docs/roadmap.md`](./docs/roadmap.md).

**Billing documents (見積・請求・入金):** [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice) — not this repo.

## Quickstart (local development)

```bash
# 1. Infrastructure (MySQL 8.4 on :3383, Mailpit SMTP :1383 / web UI :8383)
docker compose up -d
cp .env.example .env            # adjust DB_*, NENE_CLEAR_JWT_SECRET as needed

# 2. Backend (PHP 8.4). NENE2 is a local path dependency (../NENE2).
composer install
composer migrations:migrate     # apply schema
php -S localhost:8384 -t public_html/    # or your preferred SAPI (NENE_CLEAR_PORT=8384)

# 3. Frontend admin UI (Node 22)
npm --prefix frontend install
npm --prefix frontend run dev   # Vite dev server on :5383, proxies /admin → backend

# Quality gates
composer check                  # PHPUnit + PHPStan level 8 + PHP-CS-Fixer
npm --prefix frontend run check # type-check + lint + Vitest
( cd tests/e2e && npm install && npx playwright test )   # browser E2E
```

`DB_ADAPTER=sqlite` (the `.env.example` default) needs no container; set
`DB_ADAPTER=mysql` to use the docker-compose database.

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
