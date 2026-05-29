# Frontend Standards

**Status:** Phase 2 — not implemented yet.

NeNe Clear admin UI will follow sibling product conventions:

- React + TypeScript + Vite
- Strict mode enabled
- API client maps **snake_case** JSON without renaming fields
- UI strings in locale catalogs — **ja (primary) + en (secondary) only**; no
  hardcoded strings, no other locales (ADR 0005)
- Outbound Japanese correspondence (dunning notices) and statutory labels from
  upstream invoices render in Japanese regardless of UI locale; en applies to UI
  chrome and operator guides
- Admin JWT never exposed to unauthenticated pages

When `frontend/` lands, expand this document with component layout, test strategy, and build output paths (`public_html/admin/`).

Until then, mark frontend checklist items as `N/A` in PRs.
