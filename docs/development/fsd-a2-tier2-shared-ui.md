# NeNe Clear — A2 Tier2 execution design: `shared/ui`

**Status: APPROVED by hub (2026-07-18) — design gate passed; ready for
execution.** Rulings folded in below (§3, §5). It is the execution plan for the
`shared/ui` slice of A2, the last piece of the "shared foundation" PR (PR-split
step 1 in [`fsd-migration-plan.md`](./fsd-migration-plan.md) §7).

Step-0 SHA pin: `497b172` (`origin/main` after the #366 CI-rename merge).
Sources: the approved [`fsd-migration-plan.md`](./fsd-migration-plan.md) (§1
rules, §2 mapping, cited `01:NNN`), the standards doc `01` canonical tree, and
read-verified structure of the FSD siblings **invoice** and **vault**.

## 0. Scope — what Tier2 does and does not touch

Tier1 already shipped the non-UI shared segments: `shared/lib` (#359),
`shared/i18n` (#360), `shared/api` (#361/#362). Tier2 completes the shared
foundation with the **UI** segment.

**In scope (this design):**

- `src/components/ui/*` (15 components, one aggregate barrel of 21 exports) →
  `src/shared/ui/<component>/` — **explode the aggregate barrel** (plan §1,
  01:126; clear's barrel is the standard's named negative example, 01:238).
- `src/contexts/ThemeContext.tsx` → `src/shared/ui/theme/theme-context.tsx`
  (plan §2: theme home is `shared/ui/theme`, 01:208).
- Re-point all importers (14 files) and `main.tsx`'s `ThemeProvider` import.

**Out of scope (adjacent — separate PRs, hub-confirmed §5-3):**

- `src/components/keyboard/*` (11 files) → `shared/lib/keyboard/` — `shared/lib`,
  not `shared/ui`. **Separate small PR** so Tier2 stays a pure UI slice.
- `src/components/AppShell.tsx` → `app/layout/AppLayout.tsx` — **app layer**
  (layout composition). Belongs to PR-split step 4 (pages + app).
- `src/styles/design.css` — regenerated in W3 (Lane 3), **not touched here**
  (plan §2, line 98).

## 1. Current state (measured at `497b172`)

| Fact | Value | Evidence |
| --- | --- | --- |
| UI components | 15 `.tsx` under `src/components/ui/` | `ls *.tsx` (excl. tests) |
| Aggregate barrel | `components/ui/index.ts` = 21 `export` lines | the standard's negative example (01:126, 01:238) |
| Co-located test | 1 (`InfoDot.test.tsx`) | `ls *.test.tsx` |
| Barrel importers | **14 files** (13 pages + `AppShell.tsx`) | `grep "from '@/components/ui'"` |
| Component-internal sibling imports | 6 (`Icon` ×5, `Badge` ×1) | `grep "from './"` — break on move, rewritten (§4) |
| Theme | `src/contexts/ThemeContext.tsx`; `ThemeProvider` used once in `main.tsx` | `grep` |
| Import style | path alias `@/*` → `./src/*` (`tsconfig.app.json`); `isolatedModules` | — |
| Direct `fetch` in components | 0 | already compliant |

## 2. Target layout

Per the `01` canonical tree — **dir is kebab-case, the component `.tsx` inside is
PascalCase**, each dir has exactly one `index.ts` of explicit named re-exports;
**no aggregate `shared/ui/index.ts`** (01:126, 01:238; hub ruling §5-4):

```
src/shared/ui/
  badge/         ├ index.ts  ├ Badge.tsx          (Badge, BadgeVariant)
  button/        ├ index.ts  ├ Button.tsx         (Button, ButtonVariant)
  card/          ├ index.ts  ├ Card.tsx           (Card, CardHead, CardBody, CardFoot)  ← compound family
  data-table/    ├ index.ts  ├ DataTable.tsx      (DataTable, TableStateRow, SortableTh, Pager, nextSort, SortState, SortDir)  ← B1, §3b
  date-picker/   ├ index.ts  ├ DatePicker.tsx
  filter-bar/    ├ index.ts  ├ FilterBar.tsx      (FilterBar, FilterField)
  icon/          ├ index.ts  ├ Icon.tsx
  info-dot/      ├ index.ts  ├ InfoDot.tsx  ├ InfoDot.test.tsx   (InfoDot, GlossaryEntry)
  kpi/           ├ index.ts  ├ Kpi.tsx           (Kpi, KpiGrid)
  logo-mark/     ├ index.ts  ├ LogoMark.tsx
  modal/         ├ index.ts  ├ Modal.tsx
  notice/        ├ index.ts  ├ Notice.tsx        (Notice, NoticeVariant)
  page-head/     ├ index.ts  ├ PageHead.tsx
  status-badge/  ├ index.ts  ├ StatusBadge.tsx   (StatusBadge, StatusMeta)
  tabs/          ├ index.ts  ├ Tabs.tsx
  theme/         ├ theme-context.tsx              (ThemeProvider, useTheme, ThemeContext)
```

Importers switch from the single barrel import to per-component paths, e.g.
`import { Button } from '@/shared/ui/button'`.

## 3. Component inventory & structural rulings

All 15 map cleanly to a dir. Four are **compound** (multi-symbol) files.

| Component file | Exports | Target dir | Handling |
| --- | --- | --- | --- |
| Icon, LogoMark, Badge, Button, Notice, PageHead, Modal, DatePicker, StatusBadge, Tabs, InfoDot | 1 component (+ its own type) each | own dir | trivial |
| **Card** | Card, CardHead, CardBody, CardFoot | `card/` | keep together — a compound component is one component |
| **FilterBar** | FilterBar, FilterField | `filter-bar/` | keep together — FilterField is FilterBar's field slot |
| **Kpi** | Kpi, KpiGrid | `kpi/` | keep together — grid is Kpi's layout container |
| **DataTable** | DataTable, TableStateRow, SortableTh, Pager, nextSort, + types SortState/SortDir | `data-table/` | **B1** (hub §5-2) |

**(a) Standard vs. invoice — RULED (hub §5-1): follow the standard.**
The FSD sibling **invoice** does *not* follow 01:126 on `shared/ui`: it uses flat
`shared/ui/components/*.tsx` + `shared/ui/primitives/` + a top-level **aggregate**
`shared/ui/index.ts`. Following invoice would reproduce exactly the aggregate
barrel clear is being told to explode. clear therefore follows the **standard**
(1-dir-per-component, no aggregate barrel), per precedence standard > invoice
(type-gen) > vault (naming). invoice's `shared/ui` non-compliance is a **new
finding hub registered in the fleet ledger** (candidate for simultaneous
remediation with invoice's W3 barrel work) — **out of clear's scope**.

**(b) DataTable's non-component exports — RULED (hub §5-2): B1.**
Keep the whole table system in `shared/ui/data-table/` for this PR — `DataTable`,
`TableStateRow`, `SortableTh`, `Pager` as the table family, with `nextSort` +
`SortState`/`SortDir` co-located. Grounds: `shared/lib` is for *context-free pure
functions*; `nextSort`/`SortState` are DataTable-context incidentals. The B2
split (`Pager` own dir; sort logic → `shared/lib`) is deferred to the
promotion trigger in `fsd-migration-plan.md` §5 — **the PR where a second
consuming slice appears** — not preempted. (vault's `SortableTh` also lives under
`shared/ui`, same instinct.)

## 4. Implementation mechanics (behavior-preserving)

The change is **move + import-rewrite only — zero logic diff**. Steps:

1. `git mv src/components/ui/<C>.tsx src/shared/ui/<kebab>/<C>.tsx` for each of
   the 15 (git detects renames); `InfoDot.test.tsx` moves with `info-dot/`.
2. Fix the **6 component-internal sibling imports** broken by the move (the only
   content change inside moved files, and it is an import path):
   `Notice`, `DataTable`, `InfoDot`, `DatePicker`, `Modal`: `from './Icon'` →
   `from '@/shared/ui/icon'`; `StatusBadge`: `from './Badge'` →
   `from '@/shared/ui/badge'`.
3. Author one `index.ts` per dir — named re-exports sliced verbatim from the old
   barrel (value symbols + `export type` for the type symbols).
4. `git mv src/contexts/ThemeContext.tsx src/shared/ui/theme/theme-context.tsx`
   (no internal edits — it imports only `react`).
5. Re-point the **14 importers** + `main.tsx`: split each
   `import { … } from '@/components/ui'` (and separate `import type { … }`) into
   per-dir imports (`@/shared/ui/<kebab>`), value and type lines kept separate to
   mirror current style; deterministic alphabetical dir order.
6. Delete `src/components/ui/index.ts`; remove the now-empty `src/components/ui/`
   and `src/contexts/` (verify empty first).
7. `knip` prunes anything the barrel exported but no page imports (recorded in
   the PR if any).

## 5. Rulings (hub, 2026-07-18) — gate resolved

1. **Follow the standard**, not invoice's `shared/ui` (§3a). Approved.
2. **B1** for DataTable (§3b). Approved.
3. **Scope** = `shared/ui` + `theme` only; `keyboard/` (→`shared/lib`) and
   `AppShell` (→`app/layout`) are separate PRs. Approved.
4. **Dir naming = kebab-case**, component `.tsx` PascalCase (corrected from the
   draft's PascalCase-dir; matches 01 §2 canonical tree
   `shared/ui/button/ ├ index.ts ├ Button.tsx` and §3 naming table).

## 6. PR & execution

- **One behavior-preserving PR** ("A2 Tier2: shared/ui foundation"): move 15
  components + theme, explode the barrel, re-point 14 importers + `main.tsx`.
- **Self-merge gate (hub, deal #141 style):** allowed once the PR carries a
  **mechanical proof of logic-diff-0** — rename detection (`git diff -M`) plus
  demonstration that the only non-rename content changes are barrel deletion,
  new per-dir `index.ts`, and import-path lines — **and** `check` is green.
- Branches from `main` (stacked PRs don't run CI, issues #54; plan §7).

## 7. Verification

- `npm run check` green (type-check, lint `--max-warnings 0`, `codegen:check`,
  vitest, knip).
- `composer check` unaffected (frontend-only).
- **No logic diff** — the mechanical proof above is the review gate.
- **Demo happy-path smoke** — clear is 1 of 4 public demos; before/after visual
  smoke on pages rendering these components (dashboard, reconciliation, dunning
  at minimum), since every page imports from this barrel.

## 8. Risks

- **Barrel explosion touches every page.** 14 importer files change; a missed
  re-point fails `tsc`/build (caught by `npm run check`), not silently.
- **Deliberate divergence from invoice** (§3a) — clear's `shared/ui` will differ
  from the sibling most people diff against; documented as standard-compliance,
  not drift; hub holds the fleet-ledger item for invoice.
- **DataTable taxonomy deferred** (B1) — a later B2 move if a second consumer
  appears, not a rework.
- **No generators** (plan §9) — hand-authored `index.ts` to the standard shape;
  regenerate + diff when `gen:` ships.
