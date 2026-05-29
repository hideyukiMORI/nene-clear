# NeNe Clear

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)
[![Private](https://img.shields.io/badge/status-private-red)]()

**Clear billing from quote to cash — self-hosted for Japan SMB.**

**NeNe Clear** (*見積から入金まで、明快に。*) is a billing platform on [NENE2](https://github.com/hideyukiMORI/NENE2): quotes, **qualified invoice** (適格請求書) PDFs, payment tracking, reconciliation, and dunning — on shared hosting or Docker.

> **Repository:** private until Phase 3 launch-ready. Portfolio strategy: [publication-strategy `docs/products/nene-clear.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-clear.md).

## Goals

- **Japan invoice compliance** — registration number, tax rates, qualified invoice PDF
- **Self-hosted OSS** — MIT; Tier A shared hosting or Tier B Docker/VPS
- **Quote-to-cash** — estimate → invoice → collect → **clear**
- **AI-readable** — OpenAPI + MCP; human confirms, AI proposes
- **Sibling to NeNe ecosystem** — HTTP integration with Records / Concierge / Corpus

## Documentation

| Topic | Document |
| --- | --- |
| **Philosophy** | [`docs/explanation/philosophy.md`](./docs/explanation/philosophy.md) |
| **Product vision** | [`docs/explanation/product-vision.md`](./docs/explanation/product-vision.md) |
| **Expansion roadmap (1–5)** | [`docs/explanation/expansion-roadmap.md`](./docs/explanation/expansion-roadmap.md) |
| **Requirements** | [`docs/explanation/requirements.md`](./docs/explanation/requirements.md) |
| **Agents** | [`AGENTS.md`](./AGENTS.md) |
| **Roadmap** | [`docs/roadmap.md`](./docs/roadmap.md) |

## Status

**Phase 0** — governance and product design. Runtime scaffold follows Issues #4+.

## Ecosystem

```
NENE2 (framework)
  ├── NeNe Records   (CMS)
  ├── NeNe Corpus    (knowledge chat)
  ├── NeNe Concierge (scenario chat)
  └── NeNe Clear     (billing — this repo)
```

## License

MIT — see [LICENSE](./LICENSE).
