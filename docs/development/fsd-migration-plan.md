# NeNe Clear — FSD migration mapping design (A2)

**Status: design approved by hub (2026-07-17); ready for execution review.**
Step-0 SHA pin: `fb6e556` (= current origin/main; re-measured after fetch). This
is the design gate required by the front-burst work order before any file moves;
the type-generation approach (§5) is ruled (Option A). It maps clear's current
non-FSD frontend onto the fleet FSD 5-layer standard and sequences the migration.
**No files are moved by this document** — it is the plan A2 execution follows.

Sources: `_work/reports/2026-07-14-frontend-standards/01-architecture.md` (layer
rules, cited `01:NNN`), `02-data-flow.md` (`02:NNN`), the front-burst work order,
and read-verified structure of the two references — **payout** (structure
exemplar) and **invoice** (already-FSD sibling).

## 0. Why clear needs A2 (structural rebuild), not A1

clear is one of the 3 non-FSD repos. Its `src/` has the FSD names `app/` and
`pages/` but **9 of its 11 top-level directories are off-layer** and must be
relocated: `api/`, `components/`, `contexts/`, `hooks/`, `locales/`, `styles/`,
`test/`, `types/`, `utils/`. The A1 hooks→model codemod presupposes an existing
slice structure, so it cannot land until A2 creates one. **A2 is the body of
clear's refactor; A1 runs after.**

Measured current-state violations (at `fb6e556`):

| Violation | Evidence | FSD rule |
| --- | --- | --- |
| 9 off-layer top-level dirs | see above | `src/` holds only the 5 layers + `main.tsx` (01:23) |
| Aggregate UI barrel | `components/ui/index.ts` = 21 exports over 16 components | 1 component = 1 dir = 1 index; aggregate barrel MUST NOT (01:126, named as clear's negative example 01:238) |
| `hooks/` directories | `src/hooks/`, `components/keyboard/…` | hooks live in `model/`; `hooks/` segment MUST NOT (01:99) |
| Central types file | `types/index.ts` | types live per-entity in `model.ts`/`api-types.ts` (02) |
| 15 default exports | `grep export default` | named export only (01:130) |
| Direct `fetch` | **0** | already compliant — all traffic via `api/client.ts` |

## 1. Target FSD standard (the rules clear must satisfy)

- **5 layers only**: `app / pages / features / entities / shared` (01:21-33).
  `widgets/`, `processes/`, root `components/` are **MUST NOT** (unknown-layer
  error, 01:23,42). Cross-feature composition is pushed up to `pages`. (Note: the
  work order's "widgets" wording was an error, corrected by hub; layout
  composition goes in `app/` or `pages/`.)
- **Only `main.tsx` may sit directly under `src/`** (01:23).
- **Dependency direction** (01:54-60): a layer imports only strictly-lower
  layers; **sibling-slice imports MUST NOT** (features→features, entities→
  entities, pages→pages). `pages` imports only `features` + `shared` (+ router);
  `features` import `entities` + `shared`; `entities` import `shared` only.
- **Segment vocabulary inside a slice = `ui / model / api / lib / config`**
  (01:97-99). `hooks/`, `components/`, `utils/`, `types/` MUST NOT. Hooks go in
  `model/`. `shared` adds `i18n` as a 6th shared-only segment (01:103).
- **Entity slice = flat named-segment files** (01:188-201, 243): `api-types.ts`
  (DTO, internal), `model.ts` (domain type/branded id — payout's separate
  `ids.ts` is non-standard 02:306, fold into `model.ts`), `mapper.ts` (internal),
  `query-keys.ts`, `queries.ts`, `mutations.ts` (if writes), `index.ts` (public
  barrel), co-located `*.test.ts`.
- **Feature slice = segment folders** `ui/` + `model/` (form zod schema + the
  container hook). **Container hook returns a discriminated union**; the View
  consumes it via exhaustive `switch` with no `default` (01:351,381). (payout
  puts the hook in `hooks/` — that is its known violation; clear uses `model/`.)
- **Page slice** = one thin `<Name>Page.tsx` that delegates to a feature's
  exported view/hook (01:177-180).
- **TY-2 (MUST, 02:261)**: `features / pages / shared/ui` MUST NOT import DTO
  types (`api-types.ts` / `schema.gen.ts`). **Only `model.ts` UI types cross the
  entity boundary.**
- **Barrels**: every slice exposes exactly one `index.ts` of explicit named
  re-exports; `api-types.ts` / `mapper.ts` are withheld. `shared/ui` = 1
  component = 1 dir = 1 index, **no aggregate barrel** (01:126).
- **Naming**: entity = singular kebab; feature = verb-noun kebab; page = route
  kebab; components PascalCase, `<Name>Page`/`<Name>View`; container hooks
  `use-<kebab>.ts` (01:266 — payout's `-form` container name violates this, so
  **vault, not payout, is the naming reference**; precedence standard > invoice
  > vault); named export only (01:259-279, 01:130).
- **Generators**: the standard says entity/feature/page are generator-authored
  (`gen:entity` etc., 01:150). **These generators do not exist yet in the fleet**
  (searched fleet-tooling + payout; 0 hits; the standard self-declares W0b/W1
  unwired 01:93-95). So the migration hand-authors to the exemplar shape until
  generators ship; recorded as a risk in §8.

## 2. Current → FSD mapping (directory level)

| Current | → FSD target | Notes |
| --- | --- | --- |
| `src/main.tsx` | `src/main.tsx` | the one allowed root file |
| `src/app/App.tsx`, `router.tsx` | `src/app/` (providers.tsx, router.tsx) | app layer already; formalize providers |
| `src/components/AppShell.tsx` | `src/app/layout/AppLayout.tsx` | layout composition = app layer |
| `src/components/ui/*` (16) | `src/shared/ui/<Component>/index.ts` | **explode the aggregate barrel**: 1 dir per component |
| `src/components/keyboard/*` (11) | `src/shared/lib/keyboard/` | context-free keyboard control → shared/lib |
| `src/api/client.ts` | `src/shared/api/client.ts` | sole transport adapter (01:216) |
| `src/api/endpoints.ts` | **dissolved** into each entity's `queries.ts`/`mutations.ts` | 40 functions → per-entity api (see §3) |
| `src/api/schema.gen.ts` | `src/shared/api/schema.gen.ts` | **Ruled A (§5)**: entities import it |
| `src/contexts/I18nContext.tsx` | `src/shared/i18n/i18n-context.tsx` | |
| `src/contexts/ThemeContext.tsx` | `src/shared/ui/theme/theme-context.tsx` | theme home is shared/ui/theme (01:208) |
| `src/hooks/useTranslation.ts` | `src/shared/i18n/use-translation.ts` | |
| `src/hooks/useFiscalYearDefault.ts` | feature/entity `model/` | consumes clear-settings; place with its consumer |
| `src/locales/{en,ja,index}.ts` | `src/shared/i18n/messages/` | |
| `src/utils/format.ts` (`yen`, `formatDate`) | `src/shared/lib/format.ts` | **special case §6**: standard prefers `@hideyukimori/nene2-i18n/format`; confirm whether to adopt or keep local |
| `src/utils/fiscal.ts` | `src/shared/lib/fiscal.ts` | context-free → shared/lib |
| `src/types/index.ts` | **dissolved** → entity `model.ts` + `shared/api` | **Ruled A (§5)**: DTOs from schema.gen; UI types in model.ts |
| `src/styles/design.css` | `src/shared/ui/theme/` | regenerated in W3 (Lane 3), not here |
| `src/test/{render,setup}` | `tests/` support | test harness, not a slice |
| `src/pages/*` (13) | `src/pages/<route>/` slices | thin; logic moves to features (§4) |

## 3. Entities (≈11)

Derived from the 40 `endpoints.ts` functions and the response schemas. Each is a
flat slice (`api-types` / `model` / `mapper` / `query-keys` / `queries` /
`mutations?` / `index`).

| Entity (singular kebab) | Reads | Writes → become features (§4) |
| --- | --- | --- |
| `user` | listUsers, getCurrentUser | create-user, delete-user |
| `invitation` | getInvitation | accept-invitation |
| `bank-import-batch` | listBankImportBatches | import-bank-csv, reverse-bank-import |
| `bank-transaction` | listBankTransactions, listUnmatchedTransactions | — |
| `reconciliation` | listReconciliations, getReconciliation | propose-match, confirm-match, reverse-reconciliation |
| `client-credit` | listClientCredits | apply-client-credit |
| `manual-receivable` | listManualReceivables | create/cancel/import-manual-receivable |
| `upstream-invoice` | listUpstreamInvoices | — (read-only, Invoice-owned) |
| `dunning-notice` | listDunningNotices | send/preview/test-send-dunning |
| `dunning-pause` | listDunningPauses | pause/resume-dunning |
| `audit-event` | listAuditEvents | — (read-only) |
| `clear-settings` | getClearSettings | update-clear-settings, test-upstream-connection |

CSV export path helpers (`*ExportPath`, `downloadCsv`) → the owning entity's
`api`/`lib`, or `shared/lib` for the generic `downloadCsv`.

## 4. Features (≈19) and Pages (13)

**Features** (verb-noun, `ui/` + `model/`): authenticate, accept-invitation,
import-bank-csv, reverse-bank-import, propose-match, confirm-match,
reverse-reconciliation, apply-client-credit, create-manual-receivable,
cancel-manual-receivable, import-manual-receivables, send-dunning,
preview-dunning, test-send-dunning, pause-dunning, resume-dunning,
update-clear-settings, create-user, delete-user.

**Pages** (thin, one per route): login, accept-invite, dashboard, bank-import,
bank-transactions, reconciliation, client-credits, manual-receivables, dunning,
audit, settings, users, help. Each becomes `pages/<route>/ui/<Name>Page.tsx`
delegating to a feature view + `model/use-<route>-page.ts` container hook.

## 5. Type-surface work-stream (#317 Phase 3) — RULED: Option A

**hub ruling (2026-07-17): Option A — schema.gen import.** Grounds: 02:139 is a
MUST (depcruise-enforced); **invoice is the type-generation reference**
(`entities/line-item/api-types.ts` → `import type { components } from
'@/shared/api/schema.gen'`); clear's #317 Phase 2 infrastructure (schema.gen +
regen gate + spec-parity, #355) is a live asset and is kept; hand-authored DTOs
are the #264 drift hazard and A prevents it mechanically. The fleet separately
confirmed **payout is not the reference for implementation detail** — its feature
hooks sit in `hooks/` and its container is named `-form` (violating
`use-<kebab>`, 01:266), a double non-compliance. **Precedence for implementation
detail: standard doc > invoice (type-gen) > vault (naming).** "payout as model"
covers layer/segment **structure** only. deal/vault will also build entities
under A; "A = fleet type standard" is being written into 05/02 via the standards
patch lane.

**Work-stream (confirmed):** each entity's `api-types.ts` imports
`components['schemas'][X]` from `shared/api/schema.gen.ts` → `mapper.ts` →
`model.ts` (UI type). `types/index.ts` is dissolved. `AuditAction` becomes the
`audit-event` entity's `model.ts` UI union (frontend-only display type).
`ProblemDetails` / `ListEnvelope` move to `shared/api`. The Phase 2
knip-ignore on `schema.gen.ts` is removed once entities import it (it stops being
an unused file).

Consuming-surface measurement (for PR sizing): User 4 files, BankTransaction 3,
Reconciliation 6, ClientCredit 2, ManualReceivable 3, DunningNotice 1,
ClearSettings 2, BankAccount 1, AuditEvent 2, UpstreamInvoice 2.

## 6. Special cases

- **`UpstreamClient`** (`types/index.ts:209`) has **no OpenAPI schema** (spec
  never defines it) and **0 consumers** — drop it (phantom), or keep a local
  `shared/api` type if a consumer appears. Confirm in review.
- **`AuditAction`** — a 22-member display union driving an exhaustive Record; it
  is frontend-only (backend sends `action: string`). Keep it in
  `entities/audit-event/model.ts`, not in the spec.
- **Formatter placement** — the standard says formatters may NOT live in
  `shared/lib` (canonical is `@hideyukimori/nene2-i18n/format`, 01:229-231).
  clear's `yen`/`formatDate` need either adoption of that package or a sanctioned
  local exception. **Flagged for hub — not decided here.**
- **Frontend phantoms to drop during re-point**: `created_by` on ClientCredit,
  `imported_by` on BankImportBatch (declared in `types/index.ts`, never emitted).
- **Frontend null-safety bugs to fix during re-point**: `ReconciliationAllocation.invoice_id`
  typed non-null but PHP is `?int` (audit §3.3 bug ③).

## 7. PR split (per-layer, behavior-preserving, tests green each step)

Stacked PRs don't run CI (issues #54) — **every PR branches from main**.

1. **shared foundation** — `shared/api` (client, errors, schema.gen), `shared/ui`
   (explode barrel, 16 components), `shared/lib`, `shared/i18n`, `shared/config`.
   No page touched yet; old imports re-pointed.
2. **entities** — one PR per 2-3 entities (11 total → ~4 PRs), each with
   api-types/model/mapper/queries/mutations + tests. **[type shape per §5 ruling]**
3. **features** — grouped by domain (reconciliation, dunning, manual-receivable,
   settings, users) → ~5 PRs.
4. **pages + app** — thin pages delegating to features; `app/` providers/router/
   layout. Removes the last off-layer dirs.
5. **cleanup** — delete `types/index.ts`, `utils/`, `contexts/`, `hooks/`,
   `components/`; knip ignore for schema.gen removed only if B, kept if A until
   entities import it.

Each PR: logic unchanged (move + import rewrite only), `npm run check` +
`composer check` green, demo smoke unaffected.

## 8. 🔴 Escape hatch (#317 Phase 3 must not be captured indefinitely)

The owner asked for Phase 3 ("re-point the types") *now*; hub folded it into A2.
Guard: **if execution has not started within 2–3 days of this design passing hub
review**, Phase 3 (the type re-point, §5) is **split back out as a standalone PR**
against the current structure (re-point `types/index.ts` to `schema.gen` aliases
without the full FSD move), so the owner's "now" ships. The rest of A2 continues
on its own timeline. Trigger = calendar (review-pass date + 3 days) OR a blocking
dependency (e.g. type-gen ruling unresolved).

## 9. Risks

- **No generators (01:150 unmet)** — hand-authoring to exemplar shape; when
  gen:entity ships, regenerate and diff. Recorded so it isn't mistaken for
  compliance.
- **payout exemplar has known violations** (feature `hooks/`, `ids.ts`) — clear
  follows the *standard* (model/, folded ids), not payout's deviations.
- **Broad type surface** (§5 counts) moves across every page — behavior
  preservation verified by tests + demo smoke (clear is 1 of 4 public demos;
  before/after visual smoke required per work order Lane 3, but A2 must not
  regress it either).
- **A1 after A2** — hooks→model codemod applies once slices exist.

## 10. Verification

Per PR: `npm run check` (type-check, lint, codegen:check, test, knip) +
`composer check` green; no logic diff (review gate); demo happy-path smoke.
Final: dependency-cruiser / lint layer clean (when the fleet gate ships, Lane 1).
