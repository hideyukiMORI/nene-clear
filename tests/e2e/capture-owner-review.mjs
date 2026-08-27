// Owner-review material for a kit-migration wave (#440).
//
// Renders the SAME thirteen screens from TWO builds — the wave's `before` commit
// and the candidate — at two viewports, and writes a two-column page the owner
// scrolls to answer one question per screen: does it work, and does it look put
// together (ruling 2026-08-23). This is not a comparison tool and it decides
// nothing: `docs/qa/owner-review/README.md` says what the columns mean and where
// the verdict goes.
//
// Why two local builds and not "production vs local" (nene-vault's shape):
// clear.ayane.co.jp answers every SPA path with 401 unless a real session is
// seated, and there is no demo seat to borrow. Two local builds also make the
// two columns carry IDENTICAL content — the API stand-in is shared — so a
// difference in the picture is a difference in the chrome and nothing else.
// The production build is recorded in `meta.json` instead, and the README says
// how far it is from the `before` column.
//
// Run: see docs/qa/owner-review/README.md.
import { chromium } from '@playwright/test'
import { readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve, join } from 'node:path'
import { execFileSync } from 'node:child_process'
import { TOKEN, TOKEN_KEY, mockApi } from './review-fixtures.mjs'

const HERE = dirname(fileURLToPath(import.meta.url))
const REPO = resolve(HERE, '../..')

const BEFORE_URL = process.env.CLEAR_OWNER_REVIEW_BEFORE_URL ?? 'http://localhost:4181'
const AFTER_URL = process.env.CLEAR_OWNER_REVIEW_AFTER_URL ?? 'http://localhost:4182'
const BEFORE_REPO = process.env.CLEAR_OWNER_REVIEW_BEFORE_REPO ?? REPO
const OUT = resolve(REPO, 'docs/qa/owner-review', process.env.CLEAR_OWNER_REVIEW_DIR ?? 'w1')

const SIDES = [
  { key: 'before', label: 'before（載せ替え前）', url: BEFORE_URL, repo: BEFORE_REPO },
  { key: 'after', label: 'after（キット載せ替え後）', url: AFTER_URL, repo: REPO },
]

const VIEWPORTS = [
  { key: 'pc', label: 'PC 1440×900', width: 1440, height: 900 },
  { key: 'sp', label: 'SP 375×812', width: 375, height: 812 },
]

// The thirteen screens of the wave's tracking issue, in the order the owner
// reads them there.
//
// The anchor is `.page` — the shell's content well — and NOT `.side-nav`: the
// rail is `display:none` below the mobile breakpoint, and Playwright waits for
// *visible*, so anchoring on the rail times out on every seated SP screen while
// the screen itself renders perfectly (measured 2026-08-27: 22/52 cells lost).
const SCREENS = [
  { key: 'login', label: 'ログイン', route: '/login', seated: false, anchor: '.login-card' },
  { key: 'accept-invite', label: '招待受諾', route: '/accept-invite?token=demo-invitation-token', seated: false, anchor: '.login-card, form, main' },
  { key: 'dashboard', label: 'ダッシュボード', route: '/admin' },
  { key: 'bank-import', label: '入金CSV取込', route: '/admin/bank-import' },
  { key: 'bank-transactions', label: '入金明細', route: '/admin/bank-transactions' },
  { key: 'reconciliation', label: '消込', route: '/admin/reconciliation' },
  { key: 'client-credits', label: '預り金', route: '/admin/client-credits' },
  { key: 'manual-receivables', label: '手動債権', route: '/admin/manual-receivables' },
  { key: 'dunning', label: '督促', route: '/admin/dunning' },
  { key: 'settings', label: '設定', route: '/admin/settings' },
  { key: 'users', label: 'ユーザー管理', route: '/admin/users' },
  { key: 'audit', label: '監査ログ', route: '/admin/audit-log' },
  { key: 'help', label: 'ヘルプ', route: '/admin/help' },
]

const git = (repo, ...args) => execFileSync('git', ['-C', repo, ...args], { encoding: 'utf8' }).trim()

/**
 * Files that differ from HEAD, as `git status --porcelain` lines.
 *
 * 🔴 A commit SHA alone does NOT identify what was photographed. Measured here
 * on 2026-08-27 (#440): the `after` column was captured from a tree carrying an
 * uncommitted fix, and `meta.json` recorded the bare HEAD — so the provenance
 * named a build that renders differently from the pictures the owner approved.
 * The bundle exists to tie a verdict to a build; a SHA that silently omits the
 * working tree unties it. Recorded, and surfaced on the page, rather than
 * refused: capturing a dirty tree is normal (a fix goes in with the wave), it
 * just may not be reported as if it were the commit.
 */
const dirty = (repo) => git(repo, 'status', '--porcelain').split('\n').filter(Boolean)

const pkgVersion = (repo, name) => {
  try {
    return JSON.parse(readFileSync(join(repo, 'frontend/node_modules', name, 'package.json'), 'utf8')).version
  } catch {
    return null
  }
}

/**
 * The `@source` guard, at run time (nene-vault #387, clear #440).
 *
 * `npm run source-probe` checks the build on disk; this checks the stylesheet
 * the browser actually loaded, because the bundle is captured from a preview
 * server that could be serving an older outDir than the one just built. Same
 * sentinel, same positive control: the class exists only in the kit's `dist`,
 * so its presence proves Tailwind scanned the kit.
 *
 * Only the `after` side is guarded — `before` predates the kit and is EXPECTED
 * not to carry it, so running the guard there would fail an honest build.
 */
async function assertKitStyled(page, sentinel) {
  const found = await page.evaluate((cls) => {
    for (const sheet of document.styleSheets) {
      try {
        for (const rule of sheet.cssRules) if (rule.cssText.includes(`.${cls}`)) return true
      } catch { /* cross-origin sheet (fonts) */ }
    }
    return false
  }, sentinel)
  if (!found) {
    throw new Error(
      `.${sentinel} is in no loaded stylesheet — the served build has no kit CSS.\n` +
        '  Rebuild with the @source line present, then restart the preview server.',
    )
  }
}

rmSync(OUT, { recursive: true, force: true })
mkdirSync(OUT, { recursive: true })

// The kit lives in `frontend/node_modules`, not this workspace's, so it is
// imported by path. Same single source of truth as `frontend/scripts/source-probe.mjs`:
// the sentinel is never spelled out here.
const kitDir = join(REPO, 'frontend/node_modules/@hideyukimori/nene2-ui')
const kitMain = JSON.parse(readFileSync(join(kitDir, 'package.json'), 'utf8')).main
const sentinel = (await import(pathToFileURL(resolve(kitDir, kitMain)).href)).SOURCE_PROBE_CLASS
const browser = await chromium.launch()
const notes = {} // `${side}-${screen}-${viewport}` -> reason it is not a picture
let guarded = false
let captured = 0

for (const side of SIDES) {
  for (const vp of VIEWPORTS) {
    const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } })
    for (const screen of SCREENS) {
      const cell = `${side.key}-${screen.key}-${vp.key}`
      const seated = screen.seated !== false
      const page = await context.newPage()
      if (seated) {
        await page.addInitScript(([k, t]) => sessionStorage.setItem(k, t), [TOKEN_KEY, TOKEN])
      }
      await mockApi(page)
      try {
        await page.goto(side.url + screen.route, { waitUntil: 'domcontentloaded', timeout: 30000 })
        await page.waitForSelector(screen.anchor ?? '.page', { timeout: 20000 })
        // A seated screen that fell back to the login form is a captured
        // *login page* wearing another screen's name (nene-deal §8-5).
        if (seated && (await page.locator('.login-card').count()) > 0) {
          throw new Error('fell back to the login screen — the session was not seated')
        }
        // `.page` exists while the shell is still showing its loading line, so
        // wait that line out before the picture is taken.
        await page
          .waitForFunction(() => document.querySelector('.page > p.muted') === null, { timeout: 10000 })
          .catch(() => {})
        await page.waitForLoadState('networkidle').catch(() => {})
        await page.waitForTimeout(700)
        if (side.key === 'after' && !guarded) {
          await assertKitStyled(page, sentinel)
          guarded = true
          console.log(`guard: .${sentinel} present in the served stylesheet`)
        }
        await page.screenshot({ path: `${OUT}/${cell}.png`, fullPage: true })
        captured += 1
        console.log(`✓ ${cell}`)
      } catch (e) {
        notes[cell] = e.message.split('\n')[0]
        console.log(`✗ ${cell}: ${notes[cell]}`)
      }
      await page.close()
    }
    await context.close()
  }
}

await browser.close()

if (captured === 0) {
  console.error('owner-review: nothing was captured on either side — are both preview servers up?')
  process.exit(1)
}
if (!guarded) {
  console.error('owner-review: the @source guard never ran (no `after` screen rendered).')
  process.exit(1)
}

// ── provenance ───────────────────────────────────────────────────────────────
const meta = {
  captured_at: process.env.CLEAR_OWNER_REVIEW_AT ?? null,
  wave: 'W1 — nene2-ui kit migration (#440)',
  before: {
    head: git(BEFORE_REPO, 'rev-parse', 'HEAD'),
    subject: git(BEFORE_REPO, 'log', '-1', '--format=%s'),
    uncommitted: dirty(BEFORE_REPO),
    'nene2-ui': pkgVersion(BEFORE_REPO, '@hideyukimori/nene2-ui'),
    url: BEFORE_URL,
  },
  after: {
    head: git(REPO, 'rev-parse', 'HEAD'),
    subject: git(REPO, 'log', '-1', '--format=%s'),
    uncommitted: dirty(REPO),
    'nene2-ui': pkgVersion(REPO, '@hideyukimori/nene2-ui'),
    url: AFTER_URL,
  },
  production: {
    host: 'clear.ayane.co.jp',
    note: process.env.CLEAR_OWNER_REVIEW_PROD_NOTE ?? null,
  },
  screens: SCREENS.map((s) => s.key),
  viewports: VIEWPORTS.map((v) => v.key),
  captured,
  expected: SIDES.length * VIEWPORTS.length * SCREENS.length,
  not_captured: notes,
}
writeFileSync(`${OUT}/meta.json`, JSON.stringify(meta, null, 2) + '\n')

// ── the page the owner reads ─────────────────────────────────────────────────
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
// Says so on the page, not only in meta.json: the reader has to know the column
// is the commit PLUS these files, or the verdict names the wrong build.
const uncommittedNote = (side) =>
  side.uncommitted?.length
    ? `<br><b class="warn">＋ 未コミットの変更 ${side.uncommitted.length} 件</b>` +
      `<br><span class="files">${side.uncommitted.map((l) => esc(l)).join('<br>')}</span>`
    : ''
const cellHtml = (side, screen, vp) => {
  const cell = `${side.key}-${screen.key}-${vp.key}`
  if (notes[cell]) return `<div class="miss"><b>not captured</b><span>${esc(notes[cell])}</span></div>`
  return `<a href="./${cell}.png" target="_blank"><img loading="lazy" src="./${cell}.png" alt="${esc(screen.label)} — ${esc(side.label)} — ${vp.label}"></a>`
}

const section = (screen) => `
  <section id="${screen.key}">
    <h2>${esc(screen.label)} <span class="route">${esc(screen.route)}</span></h2>
    ${VIEWPORTS.map((vp) => `
    <div class="vp">
      <h3>${vp.label}</h3>
      <div class="pair ${vp.key}">
        ${SIDES.map((side) => `<figure><figcaption>${esc(side.label)}</figcaption>${cellHtml(side, screen, vp)}</figure>`).join('\n        ')}
      </div>
    </div>`).join('')}
    <p class="verdict">判定: <b>GO</b> / <b>NG</b> — 追跡 issue #440 の表に記入</p>
  </section>`

const page = `<!doctype html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeNe Clear — W1 目視束</title>
<style>
 :root{ --ink:#1b2a3d; --muted:#5a6b80; --line:#dce3ec; --bg:#f4f6f9; }
 *{ box-sizing:border-box } body{ margin:0; background:var(--bg); color:var(--ink);
   font:14px/1.6 "Noto Sans JP",system-ui,sans-serif; padding:32px clamp(16px,3vw,48px); }
 h1{ font-size:22px; margin:0 0 4px } .lede{ color:var(--muted); margin:0 0 20px; max-width:74ch }
 table.meta{ border-collapse:collapse; margin:0 0 28px; background:#fff; border:1px solid var(--line) }
 table.meta th,table.meta td{ border:1px solid var(--line); padding:6px 12px; text-align:left; font-size:12.5px }
 table.meta th{ background:#eef2f7; font-weight:600; white-space:nowrap }
 code{ font-family:ui-monospace,monospace; font-size:12px }
 .warn{ color:#8c2c1e }
 .files{ font-family:ui-monospace,monospace; font-size:11px; color:var(--muted) }
 nav.toc{ display:flex; flex-wrap:wrap; gap:8px; margin:0 0 28px }
 nav.toc a{ background:#fff; border:1px solid var(--line); padding:5px 11px; border-radius:999px;
   text-decoration:none; color:inherit; font-size:12.5px }
 section{ background:#fff; border:1px solid var(--line); border-radius:10px; padding:20px 22px; margin:0 0 22px }
 section h2{ font-size:17px; margin:0 0 14px } .route{ color:var(--muted); font:12px ui-monospace,monospace; margin-left:8px }
 .vp h3{ font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin:16px 0 8px }
 .pair{ display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start }
 .pair.sp{ grid-template-columns:375px 375px; justify-content:start }
 figure{ margin:0 } figcaption{ font-size:12px; color:var(--muted); margin:0 0 6px }
 img{ width:100%; display:block; border:1px solid var(--line); border-radius:6px; background:#fff }
 .miss{ display:flex; flex-direction:column; gap:4px; padding:18px; border:1px dashed #c9432f;
   border-radius:6px; color:#8c2c1e; font-size:12.5px; background:#fdf3f1 }
 .verdict{ margin:18px 0 0; padding-top:12px; border-top:1px dashed var(--line); color:var(--muted); font-size:12.5px }
 @media (max-width:900px){ .pair,.pair.sp{ grid-template-columns:1fr } }
</style></head>
<body>
 <h1>NeNe Clear — W1 目視束（キット載せ替え）</h1>
 <p class="lede">同じ13画面を、載せ替え<b>前</b>のビルドと<b>後</b>のビルドで撮っています。
  中身のデータは両側とも同じ差し込みなので、<b>違いが出るのは部品の意匠だけ</b>です。
  見るのはボタン・カード・バッジ・表・余白といった「器」で、行の中身ではありません。
  判定は「同一かどうか」ではなく <b>動いていること／人が見て整っていること</b>（08-23 施主裁定）。</p>
 <table class="meta">
  <tr><th>before</th><td><code>${esc(meta.before.head.slice(0, 7))}</code> ${esc(meta.before.subject)}${uncommittedNote(meta.before)}<br>nene2-ui: ${meta.before['nene2-ui'] ?? '未導入'}</td></tr>
  <tr><th>after</th><td><code>${esc(meta.after.head.slice(0, 7))}</code> ${esc(meta.after.subject)}${uncommittedNote(meta.after)}<br>nene2-ui: ${meta.after['nene2-ui'] ?? '—'}</td></tr>
  <tr><th>本番</th><td>${esc(meta.production.note ?? 'clear.ayane.co.jp')}</td></tr>
  <tr><th>撮影</th><td>${esc(meta.captured_at ?? '—')} · ${meta.captured}/${meta.expected} 枚</td></tr>
 </table>
 <nav class="toc">${SCREENS.map((s) => `<a href="#${s.key}">${esc(s.label)}</a>`).join('')}</nav>
${SCREENS.map(section).join('\n')}
</body></html>
`
writeFileSync(`${OUT}/index.html`, page)

console.log(`\nowner-review: ${captured}/${meta.expected} → ${OUT}/index.html`)
