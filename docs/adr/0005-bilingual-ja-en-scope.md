# ADR 0005: Bind Product Localization to Japanese and English Only

## Status

accepted

## Context

NeNe Clear is built around the Japan invoice system (適格請求書, consumption
tax rates, per-rate rounding — see ADR 0004). The domain rules are
Japan-specific and cannot be localized away.

The operator base is shifting: more non-Japanese people now run businesses
**inside Japan**. They operate under Japanese accounting and tax rules but are
more comfortable driving the admin UI in English. So an English operator UI adds
real value.

Going further to arbitrary multilingual support (e.g. zh, ko, fr, es) does not.
A non-Japanese-accounting locale serves no real operator, because every operator
— regardless of native language — is subject to the same Japanese statutory
rules. Each added UI locale increases translation surface, review burden, and
the risk of mistranslating tax/legal terms, without serving the actual audience.

Alternatives considered:

1. **Japanese only** — rejected; excludes the growing non-Japanese operator
   segment running businesses in Japan.
2. **Full i18n / community-driven multilingual** — rejected; large maintenance
   and translation-accuracy surface for locales that match no real operator
   profile, since the domain is locked to Japanese rules.
3. **Japanese + English only** (chosen) — covers the actual operator base at a
   bounded, maintainable translation surface.

## Decision

NeNe Clear localizes to **Japanese (primary) and English (secondary) only**.

- **In scope:** admin UI strings, operator-facing guides, and operator-visible
  labels in **ja and en**. Japanese is the default; English is a first-class
  alternate.
- **Out of scope:** any additional UI locale. Pull requests adding other locales
  are declined unless a future ADR supersedes this decision. This is a
  deliberate product non-goal, not a missing feature.
- **Statutory document content stays Japanese.** The qualified invoice
  (適格請求書) PDF renders its legally required fields in Japanese because it is
  a legal document under Japanese law. English applies to the operator's working
  UI chrome, navigation, and guides — not to the statutory invoice content.
- **Development docs are unaffected.** Source-of-truth docs, OpenAPI text, and
  API error metadata remain **English** per the existing language policy
  (`docs/inheritance-from-nene2.md`); Issues, PRs, commits, and `.cursor/rules/`
  continue to allow Japanese. This ADR governs **product localization**, not the
  repository's documentation language.

## Consequences

**Benefits**

- Serves the real operator base — Japanese SMB plus non-Japanese operators doing
  business in Japan — without over-investing.
- Bounded, maintainable translation surface; lower risk of mistranslated tax or
  legal terminology.
- Clear contributor expectation: locale additions beyond ja/en are out of scope.

**Costs**

- Explicitly turns away community multilingual contributions.
- Frontend must carry a real ja/en locale catalog from Phase 2, not a
  Japanese-only string set.

**Follow-up**

- Phase 2 admin UI ships ja + en locale catalogs (`docs/development/frontend-standards.md`).
- If a concrete operator need for another locale ever appears, supersede this
  ADR rather than quietly adding locales.

## Related

- Product vision: `docs/explanation/product-vision.md`
- Requirements: `docs/explanation/requirements.md`
- Frontend standards: `docs/development/frontend-standards.md`
- Documentation language policy: `docs/inheritance-from-nene2.md`
- Issue: `#11`
- Supersedes: none
- Superseded by: none
