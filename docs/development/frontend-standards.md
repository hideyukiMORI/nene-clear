# Frontend Standards

**Status:** Phase 2 — not implemented yet.

NeNe Clear admin UI will follow sibling product conventions:

- React + TypeScript + Vite
- Strict mode enabled
- API client maps **snake_case** JSON without renaming fields
- UI strings in locale catalogs — **ja (primary) + en (secondary) only**; no
  hardcoded strings, no other locales (ADR 0005)
- Statutory invoice content rendered in Japanese regardless of UI locale (legal
  document); en applies to UI chrome and operator guides
- No admin JWT in public document download pages

When `frontend/` lands, expand this document with component layout, test strategy, and build output paths (`public_html/admin/`).

Until then, mark frontend checklist items as `N/A` in PRs.
