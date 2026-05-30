# Frontend Self-Review

**Status:** Active — the React/TypeScript admin UI lives in `frontend/`.

Use this checklist for any change under `frontend/`.

## Checklist

- [ ] TypeScript strict mode passes (`npm run type-check --prefix frontend`).
- [ ] API client uses snake_case fields without renaming (matches backend JSON / `terminology.md`).
- [ ] UI strings come from locale catalogs (`src/locales/`), not hardcoded — ja + en only (ADR 0005).
- [ ] Admin JWT kept in sessionStorage (not localStorage); never logged.
- [ ] Components hold no HTTP/business logic (fetching in `api/` + hooks).
- [ ] Money stays integer cents; formatted only at the view edge.
- [ ] Behaviour covered by Vitest unit tests and/or a Playwright E2E spec.
- [ ] `npm run check --prefix frontend` passes (type-check + lint + Vitest).
