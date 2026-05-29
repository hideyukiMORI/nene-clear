# ADR 0001: Inherit NENE2 Governance and Workflow

## Status

accepted

## Context

NeNe Clear is a consumer application built on [NENE2](https://github.com/hideyukiMORI/NENE2). The maintainer wants the same strict engineering discipline as NENE2 and sibling NeNe products (Records, Corpus, Concierge) — Issue-driven workflow, Conventional Commits, self-review checklists, and AI-readable documentation — without copying the entire NENE2 documentation tree.

Alternatives considered:

1. **Link-only** — rejected; product-specific rules (reconciliation/dunning, compliance, sibling boundaries) would be unclear.
2. **Full copy** — rejected; upstream drift would be hard to track.
3. **Hybrid inheritance** (chosen): local source-of-truth for workflow and product rules; reference NENE2 upstream for framework runtime behavior.

## Decision

Adopt NENE2 governance patterns locally:

- Issue-driven development with `type/issue-number-summary` branches
- Conventional Commits (English type/scope and description/body per ADR 0008, Issue number in subject)
- Self-review checklists under `docs/review/`
- ADR policy under `docs/development/adr.md`
- AI agent entry via `AGENTS.md`, `CLAUDE.md`, and `.cursor/rules/`
- Inheritance map in `docs/inheritance-from-nene2.md`

Framework HTTP, middleware, validation, and MCP behavior remain defined by NENE2 unless this repository records an explicit deviation in a later ADR.

## Consequences

**Benefits**

- Contributors and AI agents have a single local entry point.
- Product rules (reconciliation/dunning model, upstream boundaries) stay separate from framework rules.
- NENE2 upstream improvements remain consumable via Composer.

**Costs**

- Two documentation layers must stay mentally in sync when NENE2 workflow policy changes materially.

**Follow-up**

- Scaffold runtime and CI (Phase 0+).
- Add reconciliation/dunning and compliance ADRs as domains stabilize.

## Related

- NENE2 workflow: https://github.com/hideyukiMORI/NENE2/blob/main/docs/workflow.md
- NeNe Records ADR 0001 (precedent): https://github.com/hideyukiMORI/nene-records/blob/main/docs/adr/0001-inherit-nene2-governance.md
- Inheritance map: `docs/inheritance-from-nene2.md`
