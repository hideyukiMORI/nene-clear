# Scope Boundary — NeNe Clear vs NeNe Invoice

**Binding.** This document replaces the legacy "Post-MVP expansion roadmap"
that incorrectly listed reconciliation as Expansion #1 after quote/invoice
features inside Clear.

## Two products, two domains

| | **NeNe Invoice** | **NeNe Clear** |
| --- | --- | --- |
| **Repository** | [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice) | `nene-clear` (this) |
| **Domain (JA)** | 見積・請求・入金管理 | 入金消込・督促管理 |
| **Domain (EN)** | Quote, invoice, payment management | Payment reconciliation & dunning |
| **Relationship** | Upstream billing SSOT | Downstream operations — **not upper compatible** |

## What happened to "Expansion #1–5"?

Legacy Clear documentation copied a billing roadmap from early `nene-invoice`
bootstrap and labeled reconciliation as "Expansion #1." That implied one
product growing from invoice → reconciliation. **That model is rejected** (ADR 0009).

| Legacy item | Owner |
| --- | --- |
| Payment reconciliation & dunning | **NeNe Clear** (core MVP — not an "expansion") |
| Purchase order & delivery note | **NeNe Invoice** (future — not Clear) |
| Contract term & renewal | **NeNe Invoice** (future — not Clear) |
| Small-scale subscription billing | **NeNe Invoice** (future — not Clear) |
| Minimal expense reimbursement | **NeNe Invoice** or separate product — **not Clear** |

Portfolio strategy for Invoice-side expansions: track in
[`publication-strategy`](https://github.com/hideyukiMORI/publication-strategy)
when defined — not in this repo.

## NeNe Clear roadmap only

Clear MVP = Phase 1–3 in [`roadmap.md`](../roadmap.md):

1. Bank import + Invoice upstream integration
2. Human-confirmed matching + audit
3. Dunning + send log
4. Tier A installer (available; not recommended for bank/PII — at your own risk)

Post-MVP Clear improvements (same domain only): additional bank CSV formats,
stronger match suggestions, optional postal dunning log — each via Issue + ADR
if compliance impact.

## Related

- [ADR 0009](../adr/0009-separate-from-nene-invoice.md)
- [`scope-contract.md`](./scope-contract.md) — the binding GOAL / DO / DON'T charter
- [`product-vision.md`](./product-vision.md)
- [`requirements.md`](./requirements.md)

Last updated: 2026-05-29
