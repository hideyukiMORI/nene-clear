# ADR 0008: English-Only Repository Documentation

## Status

accepted

## Context

NeNe Clear serves Japan-registered businesses subject to Japanese tax and
invoice law. Operators may use a Japanese or English **admin UI** (ADR 0005),
but **engineering documentation** is read by contributors, AI agents, and
international reviewers — including accounting advisors evaluating compliance
design.

Mixed Japanese/English repository docs created drift: statutory labels duplicated
in kanji inside English sections, maintainer-only Japanese blocks, and
Japanese commit/cursor rules inconsistent with product docs aimed at
professional review.

## Decision

All **repository documentation** is **English only**:

- `README.md`, `AGENTS.md`, `CLAUDE.md`
- Everything under `docs/` (explanation, ADR, review, development, integrations)
- `.cursor/rules/` summaries
- OpenAPI descriptions and Problem Details metadata (when added)

> **Superseded in part by [ADR 0015](0015-language-policy-repository-external-authoring.md):**
> GitHub Issue titles/bodies, PR titles/bodies, and commit message descriptions
> were originally required to be English here. ADR 0015 (2026-07-16) permits
> Japanese for that repository-external coordination text. Everything else in
> this ADR stands — repository documentation remains English-only.

**Exceptions (not repository docs):**

- **Qualified invoice PDF** statutory field text — Japanese as required by law
  (ADR 0005)
- **Admin UI locale catalogs** — Japanese (primary) and English (secondary)
  (ADR 0005)
- **Operator-facing install guides** shipped with the product — ja + en per ADR 0005
- **Japanese law names** may appear once in parentheses where needed for advisor
  traceability (e.g. "Act on Electronic Books and Records Preservation
  (電子帳簿保存法)")

## Consequences

**Benefits**

- Single language for compliance review by 税理士 / 公認会計士 reading design docs
- AI agents and international contributors have one canonical prose language
- Aligns with NENE2 public API / OpenAPI English policy

**Costs**

- Japanese-speaking maintainers write commits and Issues in English
- Supersedes Japanese commit body rule in prior `commit-conventions.md`

## Related

- ADR 0005: Admin UI ja/en only
- `docs/inheritance-from-nene2.md` — language policy row updated
- Issue: `#3`
- Supersedes: informal "Japanese allowed in commits/rules" wording
- Superseded by: [ADR 0015](0015-language-policy-repository-external-authoring.md) — in part (Issue/PR/commit language only; the documentation rules stand)
