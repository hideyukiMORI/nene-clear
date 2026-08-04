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

Rule 4 is why the bumps came first (measured 2026-07-29): `react-router-dom` 7.16.0 → 7.18.1
and `vite` 8.0.14 → 8.1.5 (which carries `postcss` 8.5.15 → 8.5.24), plus `overrides` for
transitive packages that have no direct dependency line here — `js-yaml` `^4.3.0` and
`brace-expansion@5` `^5.0.8`, reached through `openapi-typescript` → `@redocly/openapi-core`
and through `eslint`.

**An override can break its consumer without failing any check.** A *flat*
`"brace-expansion": "^5.0.8"` was tried first here and silently broke
`@redocly/openapi-core`'s `minimatch@5.1.9`: brace-expansion 5 exports a named `expand`, while
minimatch 5 calls the module itself, so every brace pattern threw `TypeError: expand is not a
function`. `npm run check` stayed green — nothing in the suite walks that path. The override is
therefore **version-scoped** (`brace-expansion@5`), which patches the `minimatch@10` chain
under eslint while leaving the 2.x copy that minimatch 5 needs. When you add an override,
exercise the consumer directly rather than trusting a green suite:

```sh
# eslint chain (minimatch@10, named export)
node -e "const {minimatch}=require('minimatch'); console.log(minimatch('abd','a{b,c}d'))"
# codegen chain (minimatch@5, callable module export)
node -e "const m=require('./node_modules/@redocly/openapi-core/node_modules/minimatch'); console.log(m('abd','a{b,c}d'))"
```

Both must print `true`.

## Current exceptions

| Advisory | Package | Why it does not apply here | Expires |
| --- | --- | --- | --- |
| [GHSA-qwww-vcr4-c8h2](https://github.com/advisories/GHSA-qwww-vcr4-c8h2) | `react-router` (≥ 7.12.0, < 8.3.0) | The admin console is a **static SPA built by Vite**, served as files from `public_html/assets/`; the PHP front controller never renders a React route. Routing is `createBrowserRouter` with element-only routes — **no RSC mode, no server components, no server-side route `action`/`loader`, no `@react-router/dev` runtime**. The advisory's attack path (a server executing a route action before returning 400) has no counterpart in a client-only bundle. Re-measured 2026-08-04 in this tree: `grep -cE '\b(action\|loader):' frontend/src/app/router.tsx` = **0**, and `grep -rniE '@react-router/(dev\|node\|serve)\|react-router/server\|createStaticHandler\|rsc' frontend/src` returns **no match**. | **2026-08-31** |

This is the only exception. For `react-router` there is **no fix available in the 7.x line**:
the newest 7.x is `react-router-dom` 7.18.2 and it is still inside the vulnerable range (this
repo is on 7.18.1), while the fix lands in `react-router` v8 — a different package and a
breaking upgrade. Read from the GitHub Advisory API on 2026-08-04, the first patched version is
**8.3.0**; it was 8.2.1 when this entry was first written, which is why the range is restated
here rather than carried forward. That exception is removed by the **react-router v8 migration
wave** (bundled with the NENE2 RR8 re-evaluation).

### Removed exceptions

- **GHSA-mh99-v99m-4gvg** (`brace-expansion`) — removed 2026-08-04 (#407). The entry argued
  that the 2.x branch had no patched release, so `minimatch@5.1.9`'s `brace-expansion@2.1.3`
  had to be tolerated. Re-read from the GitHub Advisory API: mh99's 2.x fix is **2.1.3** — the
  version this tree was already on — so the entry had stopped matching anything and was
  silently dead. Its successor **GHSA-rgw5-rvv9-x895** widened the range (< 1.1.18 / < 2.1.4 /
  < 5.0.9) and *is* fully fixable here, so it was taken as a patch rather than allowlisted:
  a lockfile-only bump to `brace-expansion` **2.1.4 + 5.0.9**, with no `package.json` change
  (the `"brace-expansion@5": "^5.0.8"` caret already covered 5.0.9) and **no new allowlist
  entry**. `fast-uri` — the other advisory in the fleet wave (GHSA-7p8r-x3mc-p8w7) — is not a
  dependency of this tree (`npm ls fast-uri --all` is empty), so no action was needed for it.
  The two-chain check above was re-run after the bump and both still print `true`.

A dead exception is worse than a loud one: it reads as a live risk that somebody decided to
accept. When an advisory stops firing, delete the entry and say why here — do not leave it
parked "just in case".

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
