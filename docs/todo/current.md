# Current Work

Last updated: 2026-05-29

## Status

**Phase 0 — Governance & product docs:** initialized (Issue #1).

**Runtime:** not started. Next: NENE2 scaffold (Issue #4+).

## Repository

| Repo | Role |
| --- | --- |
| **`nene-clear`** (this) | Canonical private product |
| **`nene-invoice`** | Public experiment — **do not use for strategy or new features** |
| **`publication-strategy`** | Portfolio strategy + expansion order SSOT |

## Post-MVP expansion (approved order)

See [`docs/explanation/expansion-roadmap.md`](./explanation/expansion-roadmap.md):

1. Payment reconciliation & dunning — 入金消込・督促
2. PO & delivery note — 発注・納品
3. Contract renewal — 契約期限・更新
4. Small subscription billing — 小規模サブスク
5. Minimal expense reimbursement — 経費最小版

## Next steps

1. Issue #4 — NENE2 runtime scaffold + `GET /health`
2. Phase 1 — core billing API (multi-tenant, clients, quotes, invoices, payments)
3. Phase 2 — admin UI + PDF
4. Phase 3 — Tier A installer → **then consider public**

## Handoff

- Namespace: `NeneClear\`
- Problem Details: `https://nene-clear.dev/problems/`
- Private until launch — no Packagist until public
