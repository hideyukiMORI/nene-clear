# Dependency vulnerability gate (frontend)

Every PR runs a dependency audit as a **merge gate**. This document says what the gate is,
how an exception is granted, and what is currently excepted.

- Config: [`frontend/audit-ci.jsonc`](../../frontend/audit-ci.jsonc) (the file itself carries
  the reasoning for each entry — keep the two in sync)
- Command: `npm run audit --prefix frontend`
- CI: the `Audit (fail on high/critical)` step of `Frontend CI`

## The gate

`audit-ci` fails the build on any **high** or **critical** advisory that is not explicitly
allowlisted. Moderate and below do not fail (they are still reported).

We use `audit-ci` rather than bare `npm audit --audit-level=high` for one reason: **`npm audit`
has no way to record a reasoned exception.** Without one, the only ways past a
not-yet-fixable advisory are to lower the severity threshold or drop the step — both of which
blind the gate to *everything*, not just the advisory in question.

## Rules for an exception

1. **Per advisory id, never per severity.** Allowlist `GHSA-…`; do not raise `--audit-level`
   and do not set `high: false`. A new advisory must still fail the build the day it lands.
2. **The reason must be measured, not assumed.** State why the vulnerable code path does not
   exist *in this codebase*, and how that was checked (a grep, a build artifact, a config).
   "We probably don't use that" is not a reason.
3. **Every entry has an expiry** and a named condition that removes it (an upgrade wave, an
   upstream fix). An expired entry is a task — re-argue it in a PR; do not extend it by reflex.
4. **Prefer the fix.** If a patched version exists in a range we can take, take it. An
   exception is only for "no fix exists that we can adopt".

Rule 4 is why this repo went from 8 advisories to 1 before writing any allowlist entry
(measured 2026-07-29): `react-router-dom` 7.16.0 → 7.18.1 and `vite` 8.0.14 → 8.1.5 (which
carries `postcss` 8.5.15 → 8.5.24), plus two `overrides` for transitive packages that have no
direct dependency line here — `js-yaml` `^4.3.0` and `brace-expansion` `^5.0.8`, both reached
through `openapi-typescript` → `@redocly/openapi-core` and through `eslint`. `npm run check`
exercises those overrides for real, because `codegen:check` runs `openapi-typescript`.

## Current exceptions

| Advisory | Package | Why it does not apply here | Expires |
| --- | --- | --- | --- |
| [GHSA-qwww-vcr4-c8h2](https://github.com/advisories/GHSA-qwww-vcr4-c8h2) | `react-router` (7.12.0–8.2.0) | The admin console is a **static SPA built by Vite**, served as files from `public_html/assets/`; the PHP front controller never renders a React route. Routing is `createBrowserRouter` with element-only routes — **no RSC mode, no server components, no server-side route `action`/`loader`, no `@react-router/dev` runtime**. The advisory's attack path (a server executing a route action before returning 400) has no counterpart in a client-only bundle. Measured 2026-07-29 in this tree: `grep -cE '\b(action\|loader):' frontend/src/app/router.tsx` = **0**, and `grep -rniE '@react-router/(dev\|node\|serve)\|react-router/server\|createStaticHandler\|rsc' frontend/src` returns **no match**. | **2026-08-31** |

There is **no fix available in the 7.x line**: `react-router-dom` ends at 7.18.1 (the version
this repo is on), and the fix lands in `react-router` v8 (≥ 8.2.1) — a different package and a
breaking upgrade. The exception is removed by the **react-router v8 migration wave** (bundled
with the NENE2 RR8 re-evaluation).

## Fleet note

The fleet reference implementation is **nene-contact** (contact #524 / #525, owner GO
2026-07-29); this repo follows it. Each sibling must **re-measure the claim in its own tree
before copying an allowlist entry** — copying an exception without re-measuring is exactly the
failure mode the rules above exist to prevent. The measurement above was re-run here, not
inherited.

## Related

- [`nene2-compliance.md`](./nene2-compliance.md) — the binding NENE2 conventions
- Pinning an exact version to dodge an advisory is a **time-limited** measure, not a fix: the
  pinned version can itself fall inside a later advisory. Prefer ranges, and revisit pins.
