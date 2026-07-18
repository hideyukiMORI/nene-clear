# A2 — `entities/` layer: mapping design (design gate)

Status: **APPROVED (hub, 2026-07-18) — execution GO.** Q1=(R) types-only,
Q2=co-locate BankAccount, Q3=boundary-lint deferred, Q4=single `entities/user`,
Q5=E0–E4 split, Q6=received. The §7 requests are kept below as the decision
record. See §4.1 for the mandated transitional-state note.

Author: Clear (clear-rina). Date: 2026-07-18.
Refs: work order `_work/handoff-clear-front-burst-2026-07-16-work-order.md` (Lane 2);
FSD spec `_work/reports/2026-07-14-frontend-standards/01-architecture.md` (binding);
exemplar = **payout**; #318 (FSD stop-gate plan); #317 (OpenAPI codegen); #307 finding 9.

This is stage ① of the two-stage gate: **mapping design → hub review → execution.**
It follows the same shape as the Tier2 design (`fsd-a2-tier2-shared-ui.md`, #369).

---

## 1. Scope

The `entities/` layer is the last non-trivial A2 layer. After Tier1
(`shared/lib` #359 / `shared/i18n` #360 / `shared/api` #362), Tier2
(`shared/ui` #370) and AppShell → `app/layout` (#371), the residents still to
place are the **domain model** and the **entity-scoped data access**:

| Current file | Role | LOC |
| --- | --- | --- |
| `src/types/index.ts` | 15 hand-written domain interfaces + 1 display union, snake_case, "mirror API JSON exactly" | 217 |
| `src/api/endpoints.ts` | ~45 promise-returning API functions + their request/response DTOs + `downloadCsv` | 475 |
| `src/hooks/useFiscalYearDefault.ts` | react-query hook reading `User.fiscal_year_end_month` from `/me` | 26 |

`components/keyboard/` stays **frozen** (fleet-tooling#89) and is out of scope.
Constraint (work order): **behavior-preserving — relocation + import-rewrite
only, no logic change; tests green; base always `main` (no stacking).**

---

## 2. The exemplar gap (why this layer is not a mechanical move)

Payout's `entities/<noun>/` slices are the fleet's only fully-compliant FSD
entities. Each slice is **fully built out**:

```
entities/<noun>/
  model.ts        # camelCase domain type + zod schema (z.infer = single type source) + branded ids
  api-types.ts    # snake_case wire DTOs (mirror openapi)
  mapper.ts       # DTO ↔ model, + mapper.test.ts (MUST)
  queries.ts      # react-query read hooks (call apiClient, then mapper)
  mutations.ts    # react-query write hooks (write-only entities)
  query-keys.ts   # key factory
  ids.ts          # branded id types
  index.ts        # public barrel (only importable face)
```

Pages import **react-query hooks** (`useCurrentUser`, `useLogin`) from
`@/entities/<noun>`.

**Clear is the mirror image of this.** Its types are a single snake_case
interface per noun with *no* model/mapper/camelCase split (the file header
literally says "All fields mirror the API JSON exactly. No renaming."), and its
pages call **raw promise functions** with `useQuery(...)` written inline. The
distance between clear-now and payout-shape is exactly the
**`hooks → model` transform**, which the work order assigns to the **A1 codemod,
applied *after* A2** — and A1 mandates *"手作業移設は禁止"* (no hand migration).

So the binding spec and hub's A2 constraint point in opposite directions:

| Binding spec (01-architecture.md) | Hub's A2 constraint |
| --- | --- |
| Entities are **generator-made**: `gen:entity <Noun>` MUST; hand-authoring MUST NOT (§2 l.151) | A2 is hand relocation (clear has **no** generator wired — see §3) |
| Each slice MUST have `mapper.ts` + `mapper.test.ts`; `model.ts` = zod `z.infer` (§2 l.193–198) | Behavior-preserving: adding a mapper + zod + camelCase model **is** logic |
| `queries.ts`/`mutations.ts` = react-query hooks (§4 Q1) | Relocating raw functions into those names is a lie about their shape |
| Free-named files ❌ — only the canonical set (§2 l.240–243) | Raw non-react-query API functions have **no canonical home** until A1 |

**A spec-canonical, behavior-preserving entities slice cannot be produced in one
shot.** That is the core finding of this design and the subject of §7.

---

## 3. Ground truth in clear (measured 2026-07-18)

- **No generator.** `frontend/package.json` has no `gen:entity`/`plop`; no
  `plopfile`/`generators/`. Every A2 move so far (`shared/*`) was **hand
  relocation**, green via `npm run check`
  (`codegen:check → type-check → lint → test:run → knip`).
- **No FSD boundary lint yet.** `frontend/eslint.config.js` has **no**
  `import-x/no-restricted-paths` zones (payout has them). So the segment-vocab
  and layer-direction rules are **not machine-enforced in clear today** — an
  intermediate structure that isn't yet payout-shaped will still pass
  `npm run check`. (Wiring those zones is a separate follow-on; see §7-Q3.)
- **Codegen is wired but not adopted.** `npm run codegen` already generates
  `src/shared/api/schema.gen.ts`; `src/types/index.ts` is **not** re-pointed to
  it — that re-point is #317 Phase 3 (see §5).
- **Blast radius:** `@/types` imported by **12** files (11 pages/tests + endpoints
  + client); `@/api/endpoints` imported by **15** files (13 pages + AppLayout +
  the hook). `ProblemDetails`/`ListEnvelope` are imported by
  `shared/api/client.ts` (transport types, not domain).

---

## 4. Proposed entity decomposition (types-only relocation — "Strategy R")

Recommended A2-entities = **relocate the domain *types* into entity slices**,
leave the API *functions* consolidated in `shared/api/` until the A1 codemod
distributes them. This keeps every PR behavior-preserving and green, and makes
"A1 after A2" coherent (A1 turns each entity's raw functions into
`queries.ts`/`mutations.ts` + `mapper.ts` + zod `model.ts`).

Entity nouns are **singular kebab-case** (§3 l.263). For A2 each slice holds
`model.ts` (the current interface, verbatim — no rename) + `index.ts` (barrel).

| Entity slice | Types moved from `src/types/index.ts` | Notes |
| --- | --- | --- |
| `entities/user` | `User` | also the auth/session subject; see session split Q4 |
| `entities/bank-import-batch` | `BankImportBatch` | |
| `entities/bank-transaction` | `BankTransaction` | |
| `entities/reconciliation` | `Reconciliation`, `ReconciliationAllocation` | allocation is same-slice (✓ not a sibling import) |
| `entities/client-credit` | `ClientCredit` | `status` already `open\|voided` (#340) |
| `entities/manual-receivable` | `ManualReceivable`, `ManualReceivableImportResult` | |
| `entities/dunning-notice` | `DunningNotice` | `DunningPause`/`DunningPreview`/`DunningStage` currently live in endpoints.ts (§6) |
| `entities/clear-settings` | `ClearSettings`, **`BankAccount`** | BankAccount co-located here on purpose — see §6 sibling-import |
| `entities/audit-event` | `AuditEvent`, `AuditAction` | `AuditAction` = frontend display union (§5, #317-C) |
| `entities/upstream-invoice` | `UpstreamInvoice`, **`UpstreamClient`** | read-only upstream read models |

> **Executed deviation (E3, hub-approved option A, 2026-07-18):** `UpstreamClient`
> was to be its own `entities/upstream-client` slice, but it has **no importer
> anywhere** (a dead export, pre-A2 too). A standalone slice would be a
> knip-flagged dead *file*, and deleting it would break the logic-diff-0
> pure-relocation contract. So both upstream read models are **co-located in
> `entities/upstream-invoice`** (the grouping the pre-A2 comment already used).
> Its future — delete vs. replace via #317/A1 — is tracked in a follow-up issue.
> Net entity slices: **10**, not 11.

**Stays in `shared/` (not entities):**

| Symbol | Home | Reason |
| --- | --- | --- |
| `ProblemDetails`, `ListEnvelope<T>` | `shared/api` | transport envelopes; `shared/api/client.ts` imports them; not a domain noun |
| `downloadCsv` | `shared/lib` | generic browser download helper; wraps `api.getBlob`, no domain knowledge |
| the ~45 API functions + request DTOs | `shared/api/endpoints.ts` (moved from `src/api/endpoints.ts`) | held here until A1 distributes into `entities/*/queries.ts`+`mutations.ts`; also finally empties `src/api/` |

---

## 4.1 Transitional state (Q1 condition — conformance note; do not audit as a violation)

Strategy R deliberately lands each `entities/<noun>/model.ts` in a shape that is
**not yet** the canonical form of 01-architecture.md §2. This is a **known,
approved transitional state**, not drift:

- `model.ts` holds the **snake_case interface verbatim** (no camelCase, no zod
  `z.infer`, no branded ids). The spec's canonical `model.ts` is a zod-derived
  camelCase domain type.
- The slice has **no `mapper.ts`/`mapper.test.ts`** (spec: MUST) and **no
  `queries.ts`/`mutations.ts`/`query-keys.ts`. Entity data access remains raw
  promise functions in `shared/api/endpoints.ts`; pages keep calling them with
  inline `useQuery`.

**Why this is safe / not a violation:**
- The exit is defined: the **A1 codemod (hooks→model), applied after A2**, turns
  each entity into the canonical shape (model+mapper+zod+queries+mutations). A2's
  job is only to create the FSD slice boundaries the codemod requires as input.
- These canonical-form rules are **not machine-enforced in clear today**
  (measured 2026-07-18: no `gen:entity`, no FSD `import-x/no-restricted-paths`
  zones, conformance not armed). So no CI gate is being bypassed — there is no
  gate yet. When the boundary lint / conformance is armed (§7-Q3 follow-on,
  under the front-test-hardening campaign), it must be armed **after** A1 brings
  the slices to canonical form, or with the transitional shape explicitly
  baselined.
- A future audit that finds "snake_case `model.ts`, no mapper" in clear should
  read **this note** and #318/A1 sequencing, not file a compliance defect.

**Exit criterion:** this section is removed when the A1 codemod has brought every
`entities/<noun>/` to the canonical `model.ts` (zod) + `mapper.ts` + `queries.ts`
form.

## 5. #317 boundary — "pure relocation" vs "types #317 will change"

Per hub's instruction, the doc marks the seam between A2 (pure move) and #317
(type-shape change). **A2 changes no type's shape**; it only moves files.
Every domain interface relocated in §4 is *also* a future #317 Phase 3 target
(the hand-written interface gets replaced by a re-export of the generated
`components['schemas'][...]`). Classified from #317's own buckets:

- **Pure relocation (A2 only), no #317 shape change:** the *act of moving* every
  interface. A2 leaves each `entities/<noun>/model.ts` as the verbatim
  hand-written interface. #317 Phase 3 later swaps the body for a generated-type
  re-export **in place** — the file path A2 creates is the stable seam.
- **Types #317 will change (NOT touched in A2):**
  - Bucket A missing fields — `User.fiscal_year_end_month`,
    `BankAccount.csv_*`/`csv_header_rows`, `ClientCredit.created_at`/
    `reconciliation_id`/`created_by`. A2 keeps them as-is; #317 Phase 1 reconciles
    the spec so codegen can emit them.
  - Bucket C representation — nullability (`| null` vs `field?`),
    `AuditEvent.action` (`AuditAction` union vs spec `string`),
    `ProblemDetails.errors` shape. A2 keeps the hand-written forms; #317 Phase 1/3
    decides the convention.
  - `ClientCredit.status` (Bucket B) is **already resolved** (#319/#340) — no
    action either way.
- **`AuditAction`** stays a **frontend-only display union** in
  `entities/audit-event/model.ts` (per #317-C: "keep as local/derived display
  union"). A2 relocates it unchanged; whether it becomes a registered spec enum
  is a #317 decision.

Net: **A2 and #317 do not overlap in edits.** A2 moves; #317 re-shapes. Doing A2
first gives #317 Phase 3 stable per-entity `model.ts` seams to re-point.

---

## 6. Special cases / hazards

1. **Sibling-entity import is forbidden (§1 l.59/70).** `ClearSettings` embeds
   `BankAccount[]`. If `bank-account` were its own slice,
   `entities/clear-settings/model.ts` importing it would be an
   entities→entities sibling import → **MUST NOT**. Resolution: **co-locate
   `BankAccount` inside `entities/clear-settings`** (it is only ever used within
   settings + CSV column mapping). No other cross-entity type embedding exists
   (`Reconciliation.allocations` is same-slice; all other cross-refs are plain
   `number` ids). Flagged for hub in §7-Q2.
2. **`useFiscalYearDefault` hook.** Spec puts hooks in `model/` (§1 l.99). It is
   session-flavored (reads `/me`). Proposal: relocate to
   `entities/session/model/use-fiscal-year-default.ts` (or `entities/user/...`
   if session is not split — Q4), re-pointing its `@/api/endpoints` import. It is
   a self-contained hook, so the move is behavior-preserving. Alternatively defer
   to A1 (it becomes trivial once `getCurrentUser` is a `session` query).
3. **Request/response DTOs in endpoints.ts.** `BankTransactionFilter`,
   `ReconciliationQuery`, `MatchSuggestion`, `AllocationInput`,
   `CreateManualReceivableInput`, `DunningPause`, `DunningPreview`,
   `DunningStage`, `ReceivableSource`, etc. live beside the functions. Under
   Strategy R they **stay in `shared/api/endpoints.ts`** for A2; A1 homes them to
   `entities/*/api-types.ts` when it distributes the functions. (Moving them now
   would be logic churn with no green resting place.)
4. **Import-order.** `endpoints.ts` imports the domain types. Sequence so a green
   `main` exists after every PR: create `entities/*/model.ts` + barrels first,
   then re-point `endpoints.ts` and pages in the same PR that introduces the slices
   they consume (never leave a dangling `@/types` path).
5. **`knip`/`codegen:check` gates.** `knip.json` `project` is `src/**/*.{ts,tsx}`
   (covers `entities/`), and ignores `schema.gen.ts` — no config change needed for
   relocation. `codegen:check` is unaffected (schema.gen untouched by A2).

---

## 7. Decision requests for hub (blocking — please rule before execution)

**Q1 — Depth of the A2 entities move (the core question).**
A spec-canonical entities slice (mapper + zod model + react-query queries) cannot
be produced behavior-preservingly, and clear has no generator. Which does hub
want?
  - **(R) Types-only relocation now** (§4): A2 creates `entities/*/model.ts` +
    barrels, moves `endpoints.ts` → `shared/api/endpoints.ts` intact, splits
    `downloadCsv`. Green, behavior-preserving, minimal. A1 codemod later builds
    mapper/model/queries/mutations. **← Clear's recommendation** — it is the only
    reading under which "A1 after A2" and "no hand migration" both hold.
  - **(T) Full payout-shape now**: hand-build model+mapper+queries+mutations per
    entity. Rejected by clear: violates behavior-preserving + "手作業移設は禁止"
    + no generator. Listed only for the record.
  - **(G) Wire `gen:entity` first, then generate + fill**: pulls the fleet
    generator into clear before A2 entities. Bigger blast radius; may be the
    "right" long-term path but is its own work item. Hub to decide if it
    precedes A2.

**Q2 — `BankAccount` placement.** Confirm co-locating `BankAccount` inside
`entities/clear-settings` (to avoid the sibling-entity import), vs. a standalone
`entities/bank-account` slice (which would need the shared-type escape hatch or a
zone exception). Clear recommends co-location.

**Q3 — FSD boundary lint (`import-x/no-restricted-paths` zones).** Clear has none
today. Wire the zones as part of A2-entities completion (matching payout), or
defer to a later hardening PR? Wiring now would retroactively gate the whole
tree and could surface pre-existing cross-layer imports — recommend a **separate
follow-on PR** after entities land.

**Q4 — `user` vs `session` split.** Payout separates `entities/session`
(login/me/credentials) from `entities/user` (admin user management). For A2
types-only, `User` is the only type and is shared by both concerns. Split into
two slices now (empty-ish `session`), or keep a single `entities/user` and let A1
introduce `session` when it moves `login`/`getCurrentUser`/invitation? Clear
leans single `entities/user` for A2 (fewer empty slices), session emerges in A1.

**Q5 — PR split.** Proposed, all base=`main`, each green (pending Q1=R):
  - **E0** — `endpoints.ts` → `shared/api/endpoints.ts`; `downloadCsv` →
    `shared/lib`; `ProblemDetails`/`ListEnvelope` → `shared/api`. Empties
    `src/api/`. (15 importers re-pointed.)
  - **E1** — reconciliation cluster: `bank-transaction`, `bank-import-batch`,
    `reconciliation`, `client-credit` (+ re-point their consumers).
  - **E2** — receivables + dunning: `manual-receivable`, `dunning-notice`.
  - **E3** — config + audit + upstream + user: `clear-settings` (incl.
    `BankAccount`), `audit-event`, `upstream-invoice`, `upstream-client`, `user`.
  - **E4** — delete emptied `src/types/index.ts`; move
    `useFiscalYearDefault` → `entities/<user|session>/model/`; remove `src/hooks/`.
  Confirm the grouping/granularity (or ask for one-slice-per-PR).

**Q6 — A1 hand-off note.** After A2 lands, the A1 codemod applies (hooks→model).
Recording here as a planned line so it is not forgotten (per hub): *"A2 完了後、
fleet の A1 codemod（hooks→model・手作業移設禁止）を entities/* + pages に適用。"*

---

## 8. Proof obligation (execution stage, per prior A2 PRs)

Each execution PR carries a **logic-diff-0 mechanical proof** (as #370/#371 did):
every `+/-` line in `git diff -M` is either an import/export path change, a file
rename, or a new `index.ts` barrel — nothing else. `npm run check` green
(incl. vitest count unchanged) + CI green (incl. e2e). No behavior change.
