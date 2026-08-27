// Capture every admin page (and key UI states) of the running app as standalone
// HTML files for design review. Renders the built app served by `vite preview`
// on http://localhost:4174 (same target as playwright.config.ts), mocks the API,
// and writes each rendered #root with the app's compiled CSS so each file is
// independently viewable. Output: /tmp/nene-clear-screens/
//
// Prerequisite: the preview server must already be running, e.g.
//   (cd ../../frontend && npm run build && npm run preview -- --port 4174 --strictPort)
// then from tests/e2e/:  npm run screens   (or: node capture-screens.mjs)
import { chromium } from '@playwright/test'
import { readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import { TOKEN, TOKEN_KEY, mockApi } from './review-fixtures.mjs'

const HERE = dirname(fileURLToPath(import.meta.url))
const REPO = resolve(HERE, '../..')
const BASE = 'http://localhost:4174'
const OUT = '/tmp/nene-clear-screens'

rmSync(OUT, { recursive: true, force: true })
mkdirSync(OUT, { recursive: true })

// ── icon sprite (from index.html) so <use href="#i-..."> resolves standalone ──
const indexHtml = readFileSync(resolve(REPO, 'frontend/index.html'), 'utf8')
const sprite = indexHtml.match(/<svg width="0" height="0"[\s\S]*?<\/svg>/)?.[0] ?? ''

function htmlDoc(title, rootHtml) {
  return `<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeNe Clear — ${title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./app.css">
</head>
<body data-theme="a">
${sprite}
${rootHtml}
</body>
</html>
`
}

// ── scenarios ────────────────────────────────────────────────────────────────
const SCENARIOS = [
  { file: 'page-login', title: 'Login', route: '/login', token: false, group: 'Pages' },
  { file: 'page-dashboard', title: 'Dashboard / ダッシュボード', route: '/admin', group: 'Pages' },
  { file: 'page-bank-import', title: 'Bank import / 入金CSV取込', route: '/admin/bank-import', group: 'Pages' },
  { file: 'page-bank-transactions', title: 'Bank transactions / 入金明細', route: '/admin/bank-transactions', group: 'Pages' },
  { file: 'page-reconciliation', title: 'Reconciliation / 消込', route: '/admin/reconciliation', group: 'Pages' },
  { file: 'page-client-credits', title: 'Client credits / 預り金', route: '/admin/client-credits', group: 'Pages' },
  { file: 'page-dunning', title: 'Dunning / 督促', route: '/admin/dunning', group: 'Pages' },
  { file: 'page-settings', title: 'Settings / 設定', route: '/admin/settings', group: 'Pages' },
  { file: 'page-users', title: 'Users / ユーザー管理', route: '/admin/users', group: 'Pages' },
  { file: 'page-audit-log', title: 'Audit log / 監査ログ', route: '/admin/audit-log', group: 'Pages' },
  // states / parts
  { file: 'state-bank-transactions-empty', title: 'Bank transactions — empty state', route: '/admin/bank-transactions', empty: true, group: 'States' },
  { file: 'state-users-empty', title: 'Users — empty state', route: '/admin/users', empty: true, group: 'States' },
  { file: 'state-audit-empty', title: 'Audit log — empty state', route: '/admin/audit-log', empty: true, group: 'States' },
  { file: 'state-users-invite-modal', title: 'Users — invite modal', route: '/admin/users', group: 'States', action: async (page) => { await page.getByRole('button', { name: /招待|invite/i }).first().click() } },
  { file: 'state-dunning-send-modal', title: 'Dunning — send modal', route: '/admin/dunning', group: 'States', action: async (page) => { await page.getByRole('button', { name: /送信|send/i }).first().click() } },
  { file: 'state-reconciliation-confirm-modal', title: 'Reconciliation — match modal', route: '/admin/reconciliation', group: 'States', action: async (page) => { await page.getByRole('button', { name: '消込を確定' }).first().click(); await page.waitForSelector('[role="dialog"]', { timeout: 5000 }) } },
]

const browser = await chromium.launch()
let appCssWritten = false
const done = []

for (const s of SCENARIOS) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } })
  if (s.token !== false) {
    await context.addInitScript(([k, t]) => sessionStorage.setItem(k, t), [TOKEN_KEY, TOKEN])
  }
  const page = await context.newPage()
  await mockApi(page, { empty: s.empty })

  try {
    await page.goto(BASE + s.route, { waitUntil: 'domcontentloaded', timeout: 20000 })
    // Wait for the app to actually mount before snapshotting (avoids blank #root).
    const anchor = s.route === '/login' ? '.login-card' : '.side-nav'
    await page.waitForSelector(anchor, { timeout: 15000 })
    await page.waitForLoadState('networkidle').catch(() => {})
    await page.waitForTimeout(900)
    if (s.action) {
      try { await s.action(page); await page.waitForTimeout(400) } catch (e) { console.log(`  (action skipped for ${s.file}: ${e.message.split('\n')[0]})`) }
    }

    if (!appCssWritten) {
      const css = await page.evaluate(() => {
        let out = ''
        for (const sheet of document.styleSheets) {
          try { for (const rule of sheet.cssRules) out += rule.cssText + '\n' } catch (e) { /* cross-origin */ }
        }
        return out
      })
      writeFileSync(`${OUT}/app.css`, css)
      appCssWritten = true
    }

    const root = await page.evaluate(() => document.getElementById('root')?.outerHTML ?? '<div id="root"></div>')
    writeFileSync(`${OUT}/${s.file}.html`, htmlDoc(s.title, root))
    done.push(s)
    console.log(`✓ ${s.file}`)
  } catch (e) {
    console.log(`✗ ${s.file}: ${e.message.split('\n')[0]}`)
  }
  await context.close()
}

// ── index gallery ─────────────────────────────────────────────────────────────
const groups = [...new Set(done.map((s) => s.group))]
const cards = (g) => done.filter((s) => s.group === g).map((s) =>
  `      <a class="card" href="./${s.file}.html"><span class="t">${s.title}</span><span class="f">${s.file}.html</span></a>`).join('\n')
const index = `<!doctype html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeNe Clear — Screens</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+JP:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body{ margin:0; font-family:"Noto Sans JP",Inter,system-ui,sans-serif; background:#f4f6f9; color:#1b2a3d; padding:40px clamp(20px,4vw,64px); }
  h1{ font-size:24px; margin:0 0 6px; } .lede{ color:#5a6b80; margin:0 0 28px; font-size:14px; }
  h2{ font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:#7186a0; margin:30px 0 12px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px; }
  .card{ display:flex; flex-direction:column; gap:4px; padding:16px 18px; background:#fff; border:1px solid #e2e8f0;
    border-radius:8px; text-decoration:none; color:inherit; transition:.12s; }
  .card:hover{ border-color:#3d6ba3; box-shadow:0 4px 14px rgba(20,40,70,.08); transform:translateY(-1px); }
  .card .t{ font-weight:600; font-size:14px; } .card .f{ font-family:ui-monospace,monospace; font-size:11.5px; color:#8094ab; }
</style></head>
<body>
  <h1>NeNe Clear — UI screens</h1>
  <p class="lede">Standalone snapshots of the live admin UI (real rendered DOM + the app's compiled <code>app.css</code>). Each file opens independently for design review.</p>
${groups.map((g) => `  <h2>${g}</h2>\n  <div class="grid">\n${cards(g)}\n  </div>`).join('\n')}
</body></html>
`
writeFileSync(`${OUT}/index.html`, index)

await browser.close()
console.log(`\nDone: ${done.length}/${SCENARIOS.length} screens → ${OUT}`)
