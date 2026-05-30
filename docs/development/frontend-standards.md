# Frontend Standards

**Status:** Implemented (Phase 2). **Binding** for `frontend/`.

NeNe Clear's admin UI follows **NENE2's frontend conventions**
([`nene2-compliance.md`](./nene2-compliance.md) §14, NENE2
`frontend-integration.md` / `view-rendering.md`). This document is the Clear-side
detail; on any conflict, NENE2's frontend policy wins.

> The SPA lives in `frontend/` (React 19 + TypeScript + Vite). Tests: Vitest
> unit (`frontend/src/**/*.test.tsx`) + Playwright E2E (`tests/e2e/`). CI runs
> `npm run check` + build + E2E on every push/PR.

## 1. Stack & tooling

- **React + TypeScript + Vite**, TypeScript **strict mode**.
- **npm** as package manager; active **Node.js LTS**.
- `frontend/package.json` declares `engines` (node/npm) and `packageManager`;
  commit `frontend/package-lock.json`; **never commit `node_modules/`**.
- Lint/format: ESLint (TS + React) + Prettier.
- Commands: `npm run dev|build|check --prefix frontend`, where
  `check = type-check + lint + format`. Use `npm ci` in CI.

## 2. Layout & build output

```text
frontend/
  package.json            # engines, packageManager, scripts
  src/
    api/        # typed fetch wrapper + endpoint functions (snake_case mapping)
    hooks/      # useX() data hooks (call api/, hold fetch state)
    components/ # presentational + container components (no fetch, no business logic)
    pages/      # route-level screens composed from components + hooks
    locales/    # ja (primary) + en (secondary) catalogs
    types/      # TS models mirroring API JSON (snake_case fields)
```

- **Source stays in `frontend/`.** Build output is generated to
  `public_html/assets/` (treated as generated; not the source of truth).
- Never place frontend source under `public_html/`. `public_html/index.php`
  remains the PHP front controller; asset serving never bypasses the API runtime.

## 3. Data flow (one direction, enforced)

```text
api/  typed fetch wrapper
        - injects JWT bearer
        - maps snake_case JSON ↔ typed TS models (NO field renaming)
        - parses RFC 9457 Problem Details into typed errors
   → hooks/  useX() — call api functions, own loading/error/data state
      → components/ — render from hook output; raise user events upward
```

Rules (MUST):

- **Components contain no HTTP and no business logic.** Fetching and rules live in
  `api/` and `hooks/`. Components receive data/handlers via props or hooks.
- **Do not rename API fields in transit.** JSON is snake_case
  ([`terminology.md`](../explanation/terminology.md)); TS models keep the same
  field names. No camelCase remapping layer.
- Errors are typed from Problem Details (`type`/`title`/`status`/`errors[]`); the
  UI shows safe messages and field-level `errors[].field` / `code`.
- Money is integer cents end-to-end; format for display only at the view edge,
  never mutate the cents value.

## 4. Connection & API base

- Dev: Vite **proxies `/api/*`** to the Docker backend.
- Configurable base via **`VITE_NENE_CLEAR_API_BASE_URL`** for non-default hosts.
- A **generated API client** is considered only after the OpenAPI contract is
  stable; until then use the small typed fetch wrapper in `frontend/src/api/`.

## 5. Auth in the client

- JWT bearer attached by the api layer (from secure in-memory/session storage as
  decided at implementation). **Admin JWT is never exposed to unauthenticated
  pages** and never logged.
- On `401`, the client clears auth state and routes to login; on `403`
  (`insufficient-capability`) it shows a capability error, not a retry loop.

## 6. Localization (ADR 0005)

- UI strings in locale catalogs — **ja (primary) + en (secondary) only**; no
  hardcoded strings, no other locales.
- Outbound Japanese correspondence (dunning notices) and statutory labels from
  upstream invoices render in **Japanese regardless of UI locale**; English
  applies to operator UI chrome, navigation, and guides.

## 7. Testing

- Component tests focus on behavior (render from props/hook state, event
  callbacks), not snapshots of large markup.
- api/hooks tested against a faked transport; assert snake_case mapping and
  Problem Details parsing.

When `frontend/` lands, expand with concrete component patterns, routing, and the
exact build path under `public_html/assets/`.
