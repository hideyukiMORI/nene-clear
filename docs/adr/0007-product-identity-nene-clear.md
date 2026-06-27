# ADR 0007: Product Identity — NeNe Clear

## Status

accepted (amended by ADR 0009)

## Context

NeNe Clear is a **back-office application** in the NeNe portfolio. It is **not**
a rename or successor of `nene-invoice`. ADR 0009 defines the domain split:

- **`nene-invoice`** — quote, invoice, and payment management
- **`nene-clear`** — payment reconciliation and dunning only

Strategy lives in
[publication-strategy decision 0004](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/decisions/0004-nene-clear-product-strategy.md).

## Decision

- **Product name:** NeNe Clear
- **Tagline (EN):** Clear deposits. Collect with confidence.
- **Tagline (JA, marketing):** 入金を消込し、未収を見える化する。
- **Domain:** Payment reconciliation & dunning — **not** quote or invoice issuance
- **Repository:** `hideyukiMORI/nene-clear` (public)
- **PHP namespace:** `NeneClear\`
- **Problem Details base:** `https://nene-clear.dev/problems/`

*"Clear"* means **clearing** bank lines to receivables (消込) and **clarity**
of what is still owed — not "clear everything from quote to cash" in one app.

## Consequences

- All docs and code describe **reconciliation and dunning** — not billing documents.
- **`nene-invoice` is a sibling upstream**, not a deprecated experiment to migrate from.
- Clear is **not upper compatible** with Invoice; no feature superset claim.
- Legacy docs that describe Clear as quote-to-cash billing are **wrong** — superseded by ADR 0009.

## Related

- ADR 0009: Separate domain from nene-invoice
- Philosophy: `docs/explanation/philosophy.md`
- Issue: #1, amended #5
