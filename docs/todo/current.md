# Current Work

Last updated: 2026-05-29

## Status

**Phase 0 — Governance & product docs:** domain split documented (ADR 0009, Issue #5).

**Runtime:** not started. Next: NENE2 scaffold + Invoice upstream contract (Issue #4+).

## Domain split (binding)

| Product | Repository | Domain |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` (public) | Quote, invoice, payment management — 見積・請求・入金管理 |
| **NeNe Clear** | `nene-clear` (this) | Payment reconciliation & dunning — 入金消込・督促管理 |

**Not upper compatible.** Not a migration path. See [`docs/adr/0009-separate-from-nene-invoice.md`](./adr/0009-separate-from-nene-invoice.md).

## Repository roles

| Repo | Role |
| --- | --- |
| **`nene-clear`** (this) | Reconciliation & dunning — canonical for this domain |
| **`nene-invoice`** | Billing documents — **upstream sibling**, active public product |
| **`publication-strategy`** | Portfolio strategy SSOT |

## Next steps

1. Issue #4 — NENE2 runtime scaffold + `GET /health`
2. Invoice upstream OpenAPI client (read invoices, write payments on match)
3. Phase 1 — bank import + reconciliation API
4. Phase 2 — admin UI + dunning

## Handoff

- Namespace: `NeneClear\`
- Problem Details: `https://nene-clear.dev/problems/`
- Private until launch
- Legacy invoice docs in this repo: bootstrap residue — authoritative invoice rules live in `nene-invoice`
