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
| **Philosophy** | [`docs/explanation/philosophy.md`](./docs/explanation/philosophy.md) |
| **Product vision** | [`docs/explanation/product-vision.md`](./docs/explanation/product-vision.md) |
| **Requirements** | [`docs/explanation/requirements.md`](./docs/explanation/requirements.md) |
| **Reconciliation & dunning (binding)** | [`docs/explanation/payment-reconciliation-dunning-compliance.md`](./docs/explanation/payment-reconciliation-dunning-compliance.md) |
| **Invoice upstream** | [`docs/integrations/sibling-products.md`](./docs/integrations/sibling-products.md) |
| **Agents** | [`AGENTS.md`](./AGENTS.md) |
| **Roadmap** | [`docs/roadmap.md`](./docs/roadmap.md) |

## Status

**Phase 0** — governance and product design. Runtime scaffold follows Issues #4+.

**Billing documents (見積・請求・入金):** [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice) — not this repo.

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
