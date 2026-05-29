# Current Work

Last updated: 2026-05-29

## Status

**Phase 0 — Governance & product docs:** Issue #1 merged; Issue #3 English docs + reconciliation compliance.

**Runtime:** not started. Next: NENE2 scaffold (Issue #4+).

## Repository

| Repo | Role |
| --- | --- |
| **`nene-clear`** (this) | Canonical private product |
| **`nene-invoice`** | Public experiment — **do not use for strategy or new features** |
| **`publication-strategy`** | Portfolio strategy + expansion order SSOT |

## Post-MVP expansion (approved order)

See [`docs/explanation/expansion-roadmap.md`](./explanation/expansion-roadmap.md):

1. Payment reconciliation & dunning
2. Purchase order & delivery note
3. Contract term & renewal
4. Small-scale subscription billing
5. Minimal expense reimbursement

**Expansion #1 compliance SSOT:** [`payment-reconciliation-dunning-compliance.md`](./explanation/payment-reconciliation-dunning-compliance.md)

## Next steps

1. Issue #4 — NENE2 runtime scaffold + `GET /health`
2. Phase 1 — core billing API (multi-tenant, clients, quotes, invoices, payments)
3. Phase 2 — admin UI + PDF
4. Phase 3 — Tier A installer → **then consider public**
5. Expansion #1 — after Phase 1–2 core; advisor sign-off before E1-d production

## Handoff

- Namespace: `NeneClear\`
- Problem Details: `https://nene-clear.dev/problems/`
- Repository docs: English only (ADR 0008)
- Private until launch — no Packagist until public
