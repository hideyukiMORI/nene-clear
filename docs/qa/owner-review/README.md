# Owner-review material — the shape (#440)

**What this is.** The material the owner looks at before a UI-kit migration wave is
deployed. Fleet ruling 2026-08-23: the design-preservation constraint is lifted, and in
exchange **every ship's migration passes the owner's visual check before it goes to
production — once per wave, not once per PR.** The acceptance criterion, in the owner's
words: *"アプリケーションが正常に動くこと、人間が見てインターフェースが整っていること"* —
it works, and it looks put together. Not "it is identical", not "it conforms".

**What it is not.** Not a comparison tool and not a gate that decides anything. It measures
nothing and reports no differences; it shows pictures, and a person decides.

## Run

Two builds are served side by side, so the run needs two preview servers. No PHP backend
and no database: every API call is fulfilled in the browser from `review-fixtures.mjs`.

```bash
# 1. the `after` side — the candidate (this working tree)
(cd frontend && npm run build -- --base=/ --outDir=dist-e2e \
   && npx vite preview --outDir dist-e2e --port 4182 --strictPort)

# 2. the `before` side — the wave's baseline commit, in its own worktree
git worktree add /tmp/clear-before <before-commit>
(cd /tmp/clear-before/frontend && npm ci && npm run build -- --base=/ --outDir=dist-e2e \
   && npx vite preview --outDir dist-e2e --port 4181 --strictPort)

# 3. capture
cd tests/e2e
CLEAR_OWNER_REVIEW_BEFORE_REPO=/tmp/clear-before \
CLEAR_OWNER_REVIEW_AT="$(date)" \
npm run owner-review
```

| variable | default | is |
| --- | --- | --- |
| `CLEAR_OWNER_REVIEW_BEFORE_URL` | `http://localhost:4181` | preview server for the `before` column |
| `CLEAR_OWNER_REVIEW_AFTER_URL` | `http://localhost:4182` | preview server for the `after` column |
| `CLEAR_OWNER_REVIEW_BEFORE_REPO` | this repo | worktree the `before` provenance is read from |
| `CLEAR_OWNER_REVIEW_DIR` | `w1` | output directory name under `docs/qa/owner-review/` |
| `CLEAR_OWNER_REVIEW_AT` | *(none)* | capture time; pass the raw `date` output, do not reformat it |
| `CLEAR_OWNER_REVIEW_PROD_NOTE` | *(none)* | one line saying what production was serving at run time |

Output: `docs/qa/owner-review/<dir>/index.html` + 52 PNGs + `meta.json`
(13 screens × 2 viewports × 2 sides). **Gitignored** — the bundle is per-wave and
disposable; the generator and this file are what persist.

## Read

| column | is |
| --- | --- |
| **before** | the build at the wave's baseline commit, named in `meta.json` (`before.head`) |
| **after** | the candidate, named in `meta.json` (`after.head`) **plus `after.uncommitted`** |

🔴 **A commit SHA alone does not identify what was photographed.** Read `uncommitted`
beside every `head`: it lists the files that differed from that commit when the picture was
taken, and the page repeats it above the frames. Capturing a dirty tree is normal — a fix
lands with the wave — but a verdict recorded against the bare SHA names a build that renders
differently from what the owner approved. Measured here on 2026-08-27 (#440): W1's `after`
column was captured from `08f3bc9` carrying an uncommitted `nowrap` fix, and the first
`meta.json` recorded only `08f3bc9`. Tie the verdict to the commit the reviewed tree
actually became.

Both columns render the **same fixture data**, so anything that differs in the picture is a
difference in the chrome — buttons, cards, badges, tables, spacing — and never in the rows.
Look at the container, not at the content.

**Neither column is production.** Unlike nene-vault's bundle, this one does not photograph
the live site: `clear.ayane.co.jp` answers every SPA path with `401` unless a real session
is seated, and there is no demo seat to borrow. What production was serving at run time is
recorded in `meta.json` (`production.note`) instead — establish how far it is from the
`before` column before reading a row as "this is what changes for users".

A cell reading **not captured** carries the reason. It is reported, never dropped: a screen
that could not be reached is a finding, not a blank.

## Record

The owner's verdict is **GO / NG per screen, on the wave's tracking issue** (for W1: #440),
with the `meta.json` values (`before.head`, `after.head`, kit version) quoted so the verdict
is tied to what was looked at. An NG is fixed by slot values or `className` and the **same
bundle is regenerated** — not by reverting the migration, and not by adding a second bundle
next to the first. An NG that the kit has to absorb becomes an issue on the kit
(DoD step 3), not a special case on the screen.

## Guards

- **Unstyled build** (the `@source` regression: nene-vault #387, clear #440). The kit's
  sentinel class must be present in the stylesheet the browser actually loaded, checked on
  the first `after` screen before anything else is trusted. `npm run source-probe` checks the
  build on disk; this checks the build that is being photographed, which can be an older
  `outDir` than the one just built. Only the `after` side is guarded — `before` predates the
  kit and is expected not to carry it.
- **Session seating** (nene-deal §8-5). A seated screen that fell back to the login form is
  recorded as *not captured*, never saved as a picture. Without this the bundle silently
  becomes thirteen photographs of the login screen.
- **Nothing captured** fails the run. Partial capture is reported in the table.

## For another wave

Point `CLEAR_OWNER_REVIEW_DIR` at a new name and `CLEAR_OWNER_REVIEW_BEFORE_REPO` at the new
baseline. `SCREENS` in `tests/e2e/capture-owner-review.mjs` is the list from the tracking
issue, in the order the owner reads it there; keep the two viewports and the two columns.
