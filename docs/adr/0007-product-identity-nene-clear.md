# ADR 0007: Product Identity — NeNe Clear

## Status

accepted

## Context

NeNe Clear is the fourth **application-layer** product in the NeNe portfolio
(beside Records, Corpus, Concierge). The public working repo `nene-invoice` was
used for early experiments; **this repository (`nene-clear`) is the canonical
product** (private until launch). Strategy lives in
[publication-strategy decision 0004](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/decisions/0004-nene-clear-product-strategy.md).

## Decision

- **Product name:** NeNe Clear
- **Tagline (EN):** Clear billing from quote to cash.
- **Tagline (JA):** 見積から入金まで、明快に。
- **Repository:** `hideyukiMORI/nene-clear` (private until public launch)
- **PHP namespace:** `NeneClear\`
- **Problem Details base:** `https://nene-clear.dev/problems/`

*"Clear"* reads as payment **clearing** (消込), **clarity** of status, and
removing Excel chaos.

## Consequences

- All docs and code use **NeNe Clear** in prose and `NeneClear\` in PHP.
- `nene-invoice` is not updated for product strategy; do not merge strategy docs there.

## Related

- Philosophy: `docs/explanation/philosophy.md`
- Issue: #1
