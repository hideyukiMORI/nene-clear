# CLAUDE.md — NeNe Clear

Private canonical repo for **NeNe Clear**. Strategy SSOT:
[publication-strategy `docs/products/nene-clear.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-clear.md).

## One-liner

Quote → invoice → payment → **reconciliation**. Japan SMB · self-hosted · qualified invoice · MCP-ready.

## Hard rules

- Do **not** edit `nene-invoice` for Clear product work
- No direct commits to `main`
- No changes without a GitHub Issue
- Repository docs are **English only** (ADR 0008)

## Expansion order (fixed)

1. Payment reconciliation & dunning
2. Purchase order & delivery note
3. Contract renewal
4. Small-scale subscription billing
5. Minimal expense reimbursement

Details: `docs/explanation/expansion-roadmap.md`

## Compliance docs (binding)

| Topic | Path |
| --- | --- |
| Invoice & tax | `docs/explanation/accounting-compliance.md` |
| Reconciliation & dunning | `docs/explanation/payment-reconciliation-dunning-compliance.md` |

## Canonical paths

| Purpose | Path |
| --- | --- |
| Philosophy | `docs/explanation/philosophy.md` |
| TODO | `docs/todo/current.md` |
| Naming | `docs/development/naming-conventions.md` |
