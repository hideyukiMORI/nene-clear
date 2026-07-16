# ADR 0015: Language Policy — Repository-External Authoring May Be Japanese

## Status

accepted

## Context

[ADR 0008](0008-english-only-repository-documentation.md) made **all** repository
documentation English-only. Its Decision list included one item that is not
documentation in the sense ADR 0008's own rationale describes — **GitHub Issue
titles and bodies, PR titles and bodies, and commit message descriptions**.

ADR 0008's rationale is specifically about *published engineering documentation*:
it is read by "contributors, AI agents, and international reviewers — including
accounting advisors evaluating compliance design", and mixed-language **docs**
"created drift: statutory labels duplicated in kanji inside English sections".
That reasoning is strong for `docs/`, the READMEs, and the OpenAPI/Problem
Details prose that ships in the contract. It does not extend to Issues, PRs, and
commit messages, which are **maintainer-facing coordination**, not part of the
product or its compliance-review surface.

The evidence that the two cases differ is in this repository's own history
(measured 2026-07-16 at `767931b`):

- Of 212 commits on `main`, **20 contain Japanese**. The **earliest is the very
  first commit**, `#1` "docs: ガバナンスとプロダクト基盤の初期化" (2026-05-29) —
  authored *before* ADR 0008 (`#3`) existed — and Japanese commits continue
  through the most recent work.
- Meanwhile **`docs/` has stayed English** throughout. The English rule that
  *was* about published docs held; the part that reached into commit/Issue/PR
  text **never took effect in the ~14 months of the project** and was corrected
  by hand only sporadically.

So the split is not aspirational — it already describes reality. The maintainer
and the day-to-day contributors are Japanese speakers; forcing English onto
coordination text has a real cost (slower, less precise issue-writing) with no
reader who benefits, because that text has no international or advisor audience.

This ADR was requested by the owner (2026-07-16 ruling) after the discrepancy was
found while auditing repository practice, and mirrors the same split adopted
across sibling NeNe products.

## Decision

Language policy is **split by audience**.

**English only** (unchanged from ADR 0008 — these are the product / review
surface):

- `README.md`, `AGENTS.md`, `CLAUDE.md`
- Everything under `docs/` (explanation, ADR, review, development, integrations)
- `.cursor/rules/` summaries
- OpenAPI descriptions and Problem Details metadata

**Japanese permitted** (English also fine — these are maintainer coordination,
not published docs):

- GitHub **Issue** titles and bodies
- **Pull request** titles and bodies
- **Commit message** subjects and bodies

The exceptions ADR 0008 lists (qualified-invoice statutory text, admin-UI locale
catalogs, operator install guides, Japanese law names in parentheses) are
**unchanged** and continue to apply.

This **supersedes ADR 0008 in part**: only the fourth bullet of ADR 0008's
Decision (the one naming Issues, PRs, and commit messages). Every other clause of
ADR 0008 remains in force — repository documentation is still English-only.

## Consequences

**Benefits**

- The written rule now matches ~14 months of actual practice, so it is
  enforceable rather than routinely-and-silently broken.
- Maintainers write coordination text in their working language, precisely.
- The compliance-review surface (docs, OpenAPI, Problem Details) stays
  single-language English, preserving ADR 0008's actual benefit.

**Costs**

- Contributors and tools must know the boundary: English stops at the repository
  contents; Issues/PRs/commits may be either language. A reviewer scanning commit
  history sees mixed languages by design.
- Automated checks that assumed English commit/Issue text (if any are added
  later) must scope themselves to `docs/` and the READMEs, not to git metadata.
- Existing English Issues/PRs/commits are **not** rewritten (history is not
  edited); the mix is expected.

## Related

- [ADR 0008](0008-english-only-repository-documentation.md) — superseded in part
  (Issue/PR/commit language only); its documentation rules stand.
- [ADR 0005](0005-bilingual-ja-en-scope.md) — admin UI ja/en, source of the
  standing exceptions.
- `docs/inheritance-from-nene2.md` — language policy row.
- Owner ruling: 2026-07-16.
- Supersedes: ADR 0008, Decision bullet 4 (repository-external authoring) only.
